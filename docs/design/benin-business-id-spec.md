# Spécification d'implémentation : `egbavou/benin-business-id`

**Statut** :
- règles, données et messages validés par l'utilisateur le 21/10/2026 ;
- **nom du package définitif** : `egbavou/benin-business-id`. Le slug `benin-business-id`, sans préfixe `egbavou-`, sert pour la config et les traductions ;
- **noms d'API définitifs** : validés par l'utilisateur après comparaison des propositions de Codex et de la session parente. Ils sont identiques à ceux de ce plan ;
- Codex ne sera lancé qu'avec le feu vert explicite de l'utilisateur.

**Déroulé convenu** :
1. Codex implémente cette spécification via `codex exec`.
2. Claude lance les tests, PHPStan et Pint, puis montre le diff à l'utilisateur.
3. Aucun commit sans son accord.

**Sources des décisions** :
- `docs/research/benin-ifu-rccm.md`, §6 (décisions D1 à D26, validées le 21/10/2026) ;
- les conventions communes aux trois packages, transmises par la session parente.

Chaque élément découle d'une décision écrite de l'utilisateur.

> **Langue.** Ce document est en français. Tout ce que Codex écrit dans le dépôt est en **anglais** : code, identifiants, commentaires, messages d'exception, tests et noms de fichiers. Seule exception : `lang/fr/`.

---

## 1. Périmètre

**Le package fait :**
- la validation hors ligne du format de l'IFU et du RCCM béninois ;
- la vérification d'existence d'un RCCM dans l'annuaire public de l'APIEx. Elle est désactivée par défaut ;
- un vérificateur d'IFU sans source, qui renvoie toujours `Unavailable` tant qu'aucun accès DGI autorisé n'existe ;
- l'intégration Laravel : règles objets, service provider, configuration publiable, messages en anglais et en français.

**Le package ne fait jamais :**
- d'appel réseau pendant une validation de format ;
- d'interrogation de `ifu.impots.bj`, ni d'aucune page protégée par CAPTCHA ;
- de calcul de clé de contrôle IFU, faute d'algorithme publié ;
- de renvoi de données personnelles d'un entrepreneur individuel (nom, prénom, téléphone, e-mail), même si l'annuaire les fournit.

---

## 2. Outillage et dépendances

- **Package** : `egbavou/benin-business-id`, licence MIT.
- **Namespace** : `Egbavou\BeninBusinessId\`, en PSR-4 sur `src/`.
- **PHP** : `^8.3`. La CI couvre 8.3, 8.4 et 8.5.
- **`require`** :
  - `php` ;
  - `psr/http-client ^1.0` ;
  - `psr/http-factory ^1.0`.
- **`require-dev`** :
  - `pestphp/pest` ;
  - `phpstan/phpstan` ;
  - `laravel/pint` ;
  - `guzzlehttp/guzzle ^7.8` ;
  - `illuminate/support`, `illuminate/validation`, `illuminate/translation` ;
  - `orchestra/testbench` (versions compatibles avec Laravel 12 et 13).
- **`suggest`** :
  - `illuminate/support` : intégration Laravel ;
  - `guzzlehttp/guzzle` : client PSR-18 branché automatiquement sous Laravel.
- **`extra.laravel.providers`** : `Egbavou\BeninBusinessId\Laravel\BeninBusinessIdServiceProvider`.
- **Le cœur fonctionne sans Laravel** : rien dans `src/` hors de `src/Laravel/` ne référence `Illuminate\`.
- **Qualité** :
  - Pest ;
  - PHPStan au niveau `max` sur `src/` et `tests/` ;
  - Pint avec le préréglage par défaut (`laravel`).
- **CI GitHub Actions** (`.github/workflows/ci.yml`) :
  - matrice PHP 8.3, 8.4, 8.5 × {sans Laravel, Laravel 12, Laravel 13} ;
  - pour la variante « sans Laravel », supprimer les dépendances `illuminate/*` et `orchestra/testbench` avant l'installation, puis exécuter les tests du cœur seulement (groupe Pest `laravel` exclu) ;
  - PHPStan et Pint tournent dans un job dédié.

## 3. Arborescence

```
composer.json
config/benin-business-id.php
lang/en/validation.php
lang/fr/validation.php
src/Ifu.php
src/Rccm.php
src/TaxpayerType.php
src/FailureReason.php
src/InvalidIdentifierException.php
src/CheckStatus.php
src/IfuChecker.php
src/IfuCheckResult.php
src/RccmChecker.php
src/RccmCheckResult.php
src/RccmRegister.php
src/Internal/ApiexDirectory.php      (@internal)
src/Laravel/BeninBusinessIdServiceProvider.php
src/Laravel/Rules/IfuRule.php
src/Laravel/Rules/RccmRule.php
src/Laravel/Rules/IfuExistsRule.php
src/Laravel/Rules/RccmExistsRule.php
tests/...
phpstan.neon
pint.json (optionnel)
.github/workflows/ci.yml
README.md                            (en anglais)
LICENSE
```

---

## 4. Énumérations et exception

### 4.1 `FailureReason`

Enum adossé à des chaînes en snake_case, qui servent aussi de clés de traduction.

| Cas | Valeur | Produit par |
|---|---|---|
| `InvalidCharacters` | `invalid_characters` | IFU, RCCM |
| `InvalidLength` | `invalid_length` | IFU, RCCM |
| `InvalidChecksum` | `invalid_checksum` | jamais, pour le Bénin. Le cas existe par convention commune. |
| `UnknownTaxpayerType` | `unknown_taxpayer_type` | IFU |
| `InvalidYear` | `invalid_year` | IFU, RCCM |
| `InvalidCountry` | `invalid_country` | RCCM |
| `UnknownRegistry` | `unknown_registry` | RCCM |
| `UnknownCategory` | `unknown_category` | RCCM |
| `InvalidStructure` | `invalid_structure` | RCCM |

### 4.2 `InvalidIdentifierException`

- `final`, étend `\InvalidArgumentException`.
- Expose `public readonly FailureReason $reason`.
- Message en anglais, par exemple `Invalid RCCM: unknown_registry.`.

### 4.3 `TaxpayerType`

Enum pur, avec une fabrique `fromDigit(int $digit): self`.

| Cas | Premier chiffre de l'IFU |
|---|---|
| `Individual` | 0, 1, 2 |
| `Company` | 3 |
| `PublicEntity` | 4 |
| `InternationalOrganization` | 5 |
| `Ngo` | 6 |

### 4.4 `CheckStatus`

Cas `Found`, `NotFound`, `InvalidFormat` et `Unavailable`, propres à ce package, adossés aux chaînes `found`, `not_found`, `invalid_format` et `unavailable`.

---

## 5. `Ifu`, objet valeur

`final readonly class Ifu implements \Stringable`

**Propriétés publiques :**
- `string $value` : les 13 chiffres ;
- `TaxpayerType $taxpayerType` ;
- `int $registrationYear`.

**Méthodes :**
- statiques : `isValid(string $input): bool`, `tryFrom(string $input): ?self`, `from(string $input): self`. Cette dernière lève `InvalidIdentifierException` ;
- `format(): string` renvoie `$value` : aucun groupement officiel n'existe ;
- `__toString(): string` renvoie `$value`.

Le constructeur est privé ; seules les méthodes statiques créent l'objet.

**Algorithme, dans cet ordre :**
1. **Nettoyage.** Retirer tous les espaces, y compris l'espace insécable U+00A0, l'espace fine U+202F et toute la classe Unicode `\s` / `\p{Zs}`, ainsi que les points `.` et les tirets `-`. Aucun autre retrait : `IFU`, `N°`, `:` et `BJ-IFU-` ne sont pas retirés.
2. Si la chaîne contient autre chose que `0-9` : `InvalidCharacters`.
3. Si la longueur est différente de 13 : `InvalidLength`, y compris pour une chaîne vide.
4. Si le premier chiffre n'est pas entre 0 et 6 : `UnknownTaxpayerType`.
5. Si `année = (int) substr(2..5)` n'est pas comprise entre 2006 et l'année en cours : `InvalidYear`. L'année en cours vient de l'horloge système.
6. Aucun contrôle des chiffres 6 à 13 : ni séquence, ni ordre, ni clé.

**Origine** : décisions D1, D2, D11, D12, D13 et D23 ; §1.6 de la recherche.

---

## 6. `Rccm`, objet valeur

`final readonly class Rccm implements \Stringable`

**Propriétés publiques :**
- `string $value`, par exemple `RB/COT/18 B 22272` ;
- `string $registryCode` ;
- `int $year`, sur 4 chiffres ;
- `string $category`, de `A` à `E` ;
- `string $sequenceNumber`.

**Méthodes :**
- statiques : `isValid`, `tryFrom` et `from`, comme pour `Ifu` ;
- `format(): string` renvoie `$value` ;
- `__toString(): string` renvoie `$value`.

**Listes fermées :**
- greffes actifs, valables pour toutes les années : `COT`, `ABC`, `PNO`, `PKO`, `ABY`, `LKS`, `NAT`, `DGO`, `KDI`, `APL`, `SVL` ;
- greffes anciens, valables seulement si l'année est au plus 2020 : `PN`, `PK`, `ODH`, `POB`, `ALD` ;
- catégories : `A`, `B`, `C`, `D`, `E`.

**Algorithme, dans cet ordre :**
1. **Nettoyage.** Retirer les espaces (même définition que pour l'IFU), les points et les tirets, puis passer en majuscules. Les `/` sont conservés.
2. Si la chaîne contient autre chose que `A-Z`, `0-9` et `/` : `InvalidCharacters`.
3. Si la chaîne est vide : `InvalidLength`.
4. **Structure.** Tester `^([A-Z]{2})/?([A-Z]{2,3})/?(\d{2}|\d{4})/?([A-Z])/?(\d+)$`.
   - Pas de correspondance : `InvalidStructure`. C'est le cas d'une année sur 3 chiffres, d'une lettre manquante, d'un numéro manquant, d'un préfixe `RCCM`, d'une ville en toutes lettres ou du format OHADA 2019.
   - Numéro d'ordre (groupe 5) de plus de 6 chiffres : `InvalidLength`.
5. Si le pays (groupe 1) n'est pas `RB` : `InvalidCountry`.
6. Si le greffe (groupe 2) n'appartient à aucune des deux listes : `UnknownRegistry`.
7. Si la catégorie (groupe 4) n'est pas dans `A`–`E` : `UnknownCategory`.
8. **Année** (groupe 3) :
   - sur 2 chiffres AA : 19AA si AA est supérieur aux deux derniers chiffres de l'année en cours, 20AA sinon ;
   - sur 4 chiffres : doit être comprise entre 1990 et 2020, sinon `InvalidYear` ;
   - dans tous les cas, l'année complète doit être comprise entre 1990 et l'année en cours, sinon `InvalidYear`.
9. Si le greffe est ancien et l'année complète dépasse 2020 : `UnknownRegistry`.
10. **Forme normalisée.** `RB/{greffe}/{année telle que saisie} {catégorie} {numéro tel que saisi}`.
    - L'année garde sa forme : `RB/COT/2005 B 0040` reste tel quel.
    - Les zéros du numéro d'ordre sont conservés.

**Origine** : décisions D3 à D6, D10, D12, D13 et D24 ; §2.7 et §2.8 de la recherche.

---

## 7. Résultats de vérification

Classes `final readonly`. Elles sont créées seulement par les vérificateurs.

### 7.1 `IfuCheckResult`

| Propriété | Type |
|---|---|
| `status` | `CheckStatus` |
| `checkedAt` | `\DateTimeImmutable`, en UTC |
| `input` | `string`, la chaîne reçue, ou `->value` si un objet `Ifu` a été passé |
| `ifu` | `?Ifu`, `null` si `InvalidFormat` |

### 7.2 `RccmCheckResult`

Le socle (`status`, `checkedAt`, `input`, `rccm: ?Rccm`) est le même que pour l'IFU. S'y ajoutent les champs suivants, tous nullables, et renseignés **seulement quand le statut est `Found`** :

| Propriété | Type | Registre des sociétés | Registre des établissements |
|---|---|---|---|
| `name` | `?string` | `Dénomination` | toujours `null` |
| `tradeName` | `?string` | `Nom commercial` | `Nom commercial` |
| `creationDate` | `?\DateTimeImmutable` | `Date création` | `Date de création` |
| `municipality` | `?string` | `Commune`, champ catalogue : prendre `value` | `Commune` (chaîne) |
| `district` | `?string` | `Quartier`, catalogue ou chaîne | `Quartier` |
| `phone` | `?string` | `Téléphone` | toujours `null`, la donnée est personnelle |
| `email` | `?string` | `Email` | toujours `null`, la donnée est personnelle |
| `mainActivity` | `?string` | toujours `null` | `Activité principale` |
| `formerNumber` | `?string` | ancien numéro tiré de `Numéro RC` (§8.5) | idem |
| `register` | `?RccmRegister` | `Companies` | `Establishments` |

**Règles de lecture :**
- `RccmRegister` est un enum adossé à des chaînes, avec deux cas : `Companies` (`companies`) et `Establishments` (`establishments`).
- Les champs `Prénom` et `Nom` du registre des établissements ne sont **jamais** lus.
- Clé absente, chaîne vide ou valeur `null` : `null`.
- Un champ catalogue prend la forme `{"key": "…", "value": "…"}`, ou d'une chaîne.
- Les dates sont au format `Y-m-d`. Tout autre format donne `null`.

**Origine** : D14, D19 et D25.

### 7.3 `UnavailableReason` (D28, ajouté après la revue du 21/10/2026)

`RccmCheckResult` porte en plus `public ?UnavailableReason $unavailableReason`. Il vaut `null` sauf quand le statut est `Unavailable`.

Enum adossé à des chaînes :

| Cas | Valeur | Quand |
|---|---|---|
| `NoSource` | `no_source` | IFU : aucune source autorisée n'existe (D31) |
| `Disabled` | `disabled` | interrupteur coupé |
| `Transport` | `transport` | échec avant toute réponse : `ClientExceptionInterface`, URL refusée par la fabrique de requêtes, toute autre erreur levée avant la réception d'une réponse |
| `HttpStatus` | `http_status` | réponse reçue avec un code différent de 200 |
| `InvalidJson` | `invalid_json` | corps qui n'est pas du JSON |
| `Schema` | `schema` | JSON inattendu (`count`, `results`, `Numéro RC`), `count` supérieur au nombre de lignes reçues sans correspondance trouvée, ou toute erreur levée après le décodage d'une réponse 200 |

`IfuCheckResult` porte lui aussi `public ?UnavailableReason $unavailableReason` (D31, décision du 21/10/2026, harmonisée avec `vat-number`). Il vaut `null` sauf quand le statut est `Unavailable`. `IfuChecker` le renseigne avec `UnavailableReason::NoSource` pour tout IFU valide. Le cas `NoSource` n'est jamais produit par `RccmChecker`.

Pas d'équivalent de `registryErrorCode` (présent dans `vat-number`) : l'annuaire APIEx ne renvoie pas de codes d'erreur métier, et ses erreurs HTTP relèvent de `HttpStatus`.

---

## 8. `RccmChecker`

`final class RccmChecker`

**Constructeur.** La vérification est coupée par défaut, même hors Laravel. Il faut écrire `new RccmChecker($client, $factory, enabled: true)` pour l'activer.
```php
public function __construct(
    ClientInterface $httpClient,               // PSR-18
    RequestFactoryInterface $requestFactory,   // PSR-17
    bool $enabled = false,
    string $companiesUrl = 'https://gdb.guichet.investbenin.bj/data-view/pm/latest/5',
    string $establishmentsUrl = 'https://gdb.monentreprise.bj/data-view/registre%20pp/latest/310',
)
```

**Méthodes :**
- `check(string|Rccm $rccm): RccmCheckResult` ;
- `isEnabled(): bool`, utilisée par la règle Laravel pour le message dédié de D22.

### 8.1 Déroulé

Il suit le parcours du §3.5 de la recherche.
1. Si l'entrée est une chaîne, appeler `Rccm::tryFrom`. Si le résultat est `null` : renvoyer `InvalidFormat`, **sans aucun appel réseau**.
2. Si `!$enabled` : renvoyer `Unavailable`, sans appel réseau.
3. **Ordre des registres** :
   - catégorie `A` : établissements, puis sociétés ;
   - autres catégories : sociétés, puis établissements.
4. **Pour chaque registre** :
   - requête A, égalité exacte (§8.3). Si un enregistrement correspond (§8.4), renvoyer `Found` ;
   - sinon, requête B, « contient » suivi d'un délimiteur (§8.3). Si un enregistrement correspond, renvoyer `Found`.
5. Sans correspondance dans les deux registres : `NotFound`.
6. À tout moment, tout échec technique donne `Unavailable` (§8.6).

`check()` ne lève **jamais** d'exception, à l'exception de `\TypeError` venant de PHP lui-même.

### 8.2 Requête HTTP

```
GET {url}?page=1&page_size=50&filters={json}
User-Agent: egbavou/benin-business-id
Accept: application/json
```

Construction de `filters` :
- tableau JSON d'objets `{"gate": "&&" | "||", "type": "string", "field_id": <id>, "logic": "==" | "icontains", "value": "<chaîne>"}` ;
- le premier objet porte `"&&"`, les suivants `"||"`, ce qui donne un « OU » entre eux ;
- `field_id` vaut `307` pour les sociétés et `141` pour les établissements. Ces valeurs sont des constantes dans `Internal\ApiexDirectory` (D20).

### 8.3 Formes du numéro cherchées

À partir de l'objet `Rccm` :
- **formes de l'année** : l'année telle que saisie. Si l'année complète est au plus 2020, ajouter aussi l'autre forme (2 → 4 chiffres, ou 4 → 2 chiffres) ;
- **formes de base du numéro d'ordre** : tel que saisi, plus la version sans zéros en tête si elle diffère (`0040` → `40`) ;
- **formes complétées du numéro d'ordre** (requête A seulement) : si le numéro saisi n'a pas de zéro en tête et compte moins de 5 chiffres, ajouter ses versions complétées par des zéros à 3, 4 et 5 chiffres (`40` → `040`, `0040`, `00040`). Le registre stocke en effet des numéros comme `B 0040`, `A 090` ou `A 00016` ;
- **pour chaque combinaison** : `RB/{code}/{année} {cat} {num}` et `RB/{code}/{année}-{cat}-{num}`.

**Requête A** : un filtre `==` par forme, en utilisant les formes de base et complétées. Cela fait 16 filtres au plus.

**Requête B** : par forme de base, deux filtres `icontains`, `"{forme} "` et `"{forme}("`. Ils trouvent les valeurs suivies d'une mention, par exemple `RB/COT/07 B 2058 (Ancien n°: 02 B 1545)`. Cela fait 16 filtres au plus.

Vérifié le 21/10/2026 sur l'annuaire réel :
- une requête de 16 filtres (URL de 2 767 caractères) est acceptée ;
- la saisie `RB/COT/2005 B 40` retrouve `RB/COT/2005 B 0040` par la requête A.

**Limite connue** : une valeur stockée avec un zéro de tête **et** une mention entre parenthèses n'est retrouvée que si la saisie porte elle aussi le zéro. Elle sera documentée.

### 8.4 Correspondance

Pour chaque enregistrement reçu :
1. Lire `Numéro RC`.
2. Couper au premier `(` ; la partie gauche est le numéro.
3. Appliquer `Rccm::tryFrom` à cette partie gauche.
4. Il y a correspondance si la clé (`registryCode`, `year`, `category`, numéro sans zéros en tête) est égale à celle du numéro cherché. Un numéro réduit à des zéros se ramène à `0`.

Une valeur que `Rccm::tryFrom` refuse ne correspond jamais.

### 8.5 `formerNumber`

- Lu dans la partie entre parenthèses de `Numéro RC`.
- Motif insensible à la casse : `\(\s*ancien\s*n\s*[°o]?\s*(?:rccm)?\s*:?\s*(.+?)\s*\)`.
- Valeur : le groupe 1, débarrassé des espaces en bordure.
- Exemples réels :
  - `(Ancien n°: 02 B 1545)` donne `02 B 1545` ;
  - `(ANCIEN N° : 21.130 B)` donne `21.130 B` ;
  - `(Ancien no: RB/PK/11 B 059)` donne `RB/PK/11 B 059` ;
  - `(Ancien n°RCCM RB/PNO/05 B 146)` donne `RB/PNO/05 B 146`.

### 8.6 `Unavailable`

Le vérificateur renvoie `Unavailable` dans tous ces cas :
- `Psr\Http\Client\ClientExceptionInterface` levée ;
- code HTTP différent de 200 ;
- corps qui n'est pas du JSON ;
- JSON sans `count` entier ni `results` en liste ;
- un élément de `results` qui n'est pas un objet ou qui n'a pas de clé `Numéro RC`. Cela signale un changement de schéma.

Le délai d'attente relève du client HTTP (§10).

---

## 9. `IfuChecker`

`final class IfuChecker`, sans dépendance.
- `check(string|Ifu $ifu): IfuCheckResult` :
  - si l'entrée est invalide : `InvalidFormat` ;
  - sinon : `Unavailable`, avec `ifu` renseigné ;
  - jamais d'appel réseau.
- Un docblock en anglais indique qu'aucune source autorisée n'existe pour l'instant (D15).

---

## 10. Laravel

### 10.1 `BeninBusinessIdServiceProvider`

- `mergeConfigFrom` sur `config/benin-business-id.php`.
- Publication de la config (tag `benin-business-id-config`) et des traductions (tag `benin-business-id-lang`).
- `loadTranslationsFrom(…/lang, 'benin-business-id')`.
- Singleton `RccmChecker`, construit avec :
  - `new \GuzzleHttp\Client(['timeout' => config('…rccm.apiex.timeout')])` ;
  - `new \GuzzleHttp\Psr7\HttpFactory()` ;
  - `enabled`, `companies_url` et `establishments_url` tirés de la config.
- Singleton `IfuChecker`.

### 10.2 `config/benin-business-id.php` (D20)

```php
return [
    'rccm' => [
        'apiex' => [
            'enabled' => env('BENIN_RCCM_APIEX_ENABLED', false),
            'timeout' => env('BENIN_RCCM_APIEX_TIMEOUT', 10),
            'companies_url' => env('BENIN_RCCM_APIEX_COMPANIES_URL', 'https://gdb.guichet.investbenin.bj/data-view/pm/latest/5'),
            'establishments_url' => env('BENIN_RCCM_APIEX_ESTABLISHMENTS_URL', 'https://gdb.monentreprise.bj/data-view/registre%20pp/latest/310'),
        ],
    ],
];
```

### 10.3 Règles, toutes dans `Egbavou\BeninBusinessId\Laravel\Rules\`

Elles implémentent `Illuminate\Contracts\Validation\ValidationRule`.
- **Valeur non-chaîne** : échec avec la clé `<id>.invalid_characters`.
- **`IfuRule`, `RccmRule`** : format seul, jamais de réseau. En cas d'échec : `$fail('benin-business-id::validation.<id>.<reason>')->translate()`, où `<reason>` est la valeur de `FailureReason`.
- **`IfuExistsRule`, `RccmExistsRule`** :
  - elles résolvent leur vérificateur depuis le conteneur ;
  - `failWhenUnavailable(): static` rend la règle stricte ;
  - `InvalidFormat` : échec avec le message de la raison de format ;
  - `NotFound` : échec, clé `<id>.not_found` ;
  - `Unavailable` : succès par défaut. En mode strict, échec avec la clé `<id>.unavailable`. Exception pour `RccmExistsRule` : si `RccmChecker::isEnabled()` est faux, la clé est `rccm.verification_disabled` (D22) ;
  - `Found` : succès.
- **`IfuExistsRule`** est livrée dès maintenant (D21). Aujourd'hui, elle passe toujours, et échoue toujours en mode strict.

### 10.4 Traductions

Fichiers `lang/en/validation.php` et `lang/fr/validation.php`, avec les clés `ifu.*` et `rccm.*`. Les textes français ci-dessous sont validés. L'anglais en est la traduction directe.

| Clé | Français |
|---|---|
| `ifu.invalid_characters` | Le champ :attribute ne doit contenir que des chiffres. |
| `ifu.invalid_length` | Le champ :attribute doit contenir 13 chiffres. |
| `ifu.invalid_checksum` | Le champ :attribute n'est pas un IFU valide. |
| `ifu.unknown_taxpayer_type` | Le champ :attribute doit commencer par un chiffre de 0 à 6. |
| `ifu.invalid_year` | Le champ :attribute contient une année d'immatriculation invalide. |
| `ifu.not_found` | L'IFU indiqué dans :attribute est introuvable. |
| `ifu.unavailable` | L'IFU indiqué dans :attribute ne peut pas être vérifié pour le moment. |
| `rccm.invalid_characters` | Le champ :attribute contient des caractères non autorisés. |
| `rccm.invalid_length` | Le champ :attribute n'a pas la longueur d'un numéro RCCM. |
| `rccm.invalid_checksum` | Le champ :attribute n'est pas un numéro RCCM valide. |
| `rccm.invalid_structure` | Le champ :attribute doit avoir la forme RB/COT/18 B 22272. |
| `rccm.invalid_country` | Le champ :attribute doit commencer par RB. |
| `rccm.unknown_registry` | Le champ :attribute indique un greffe inconnu. |
| `rccm.unknown_category` | Le champ :attribute doit porter une lettre de catégorie de A à E. |
| `rccm.invalid_year` | Le champ :attribute contient une année invalide. |
| `rccm.not_found` | Le numéro RCCM indiqué dans :attribute est absent de l'annuaire de l'APIEx. |
| `rccm.unavailable` | Le numéro RCCM indiqué dans :attribute ne peut pas être vérifié pour le moment. |
| `rccm.verification_disabled` | La vérification en ligne des numéros RCCM est désactivée. |

---

## 11. Tests (Pest)

**Règles générales :**
- Aucun test n'appelle le vrai annuaire. Un faux client PSR-18 renvoie des réponses JSON préparées, inventées et sans donnée personnelle réelle.
- Un groupe Pest `live`, exclu par défaut et de la CI, s'active à la main par une variable d'environnement (`BENIN_BUSINESS_ID_LIVE=1`). Il interroge le vrai annuaire avec deux numéros de sociétés publics, `RB/COT/18 B 22272` (Coris Bank International Bénin) et `RB/COT/07 B 2058` (Société Générale Bénin).
- Les bornes d'année sont calculées à partir de l'année en cours, jamais codées en dur.
- Les IFU de personnes physiques sont **synthétiques**, par exemple `1201500000000`, jamais tirés de listes réelles.

### 11.1 IFU

**Valides** (sources : §4.1 de la recherche) :

| Entrée | `value` | `taxpayerType` | Année |
|---|---|---|---|
| `3202444139959` | `3202444139959` | `Company` | 2024 |
| `3.201.200.247.219` | `3201200247219` | `Company` | 2012 |
| `32009008191 13` | `3200900819113` | `Company` | 2009 |
| `3202444139959` avec des espaces insécables | `3202444139959` | `Company` | 2024 |
| `4201710116446` | `4201710116446` | `PublicEntity` | 2017 |
| `5201000673108` | `5201000673108` | `InternationalOrganization` | 2010 |
| `6201001214905` | `6201001214905` | `Ngo` | 2010 |
| `1201500000000` (synthétique) | `1201500000000` | `Individual` | 2015 |

**Invalides :**

| Entrée | Raison |
|---|---|
| `''` | `InvalidLength` |
| `320244413995` | `InvalidLength` |
| `32024441399590` | `InvalidLength` |
| `3202444139A59` | `InvalidCharacters` |
| `IFU : 3202444139959` | `InvalidCharacters` |
| `BJ-IFU-3200901353510` | `InvalidCharacters` |
| `7202400000000` | `UnknownTaxpayerType` |
| `9999900000001` | `UnknownTaxpayerType` |
| `5200081145006` (année 2000) | `InvalidYear` |
| `2977421186134` | `InvalidYear` |
| IFU de type 3 dont l'année vaut l'année en cours + 1 | `InvalidYear` |

### 11.2 RCCM

**Valides** (sources : §4.3 et §2.7 de la recherche) :

| Entrée | `value` | Année |
|---|---|---|
| `RB/COT/18 B 22272` | `RB/COT/18 B 22272` | 2018 |
| `RB/COT/07-B-1432` | `RB/COT/07 B 1432` | 2007 |
| `RB/COT/07/B 1351` | `RB/COT/07 B 1351` | 2007 |
| `RB KDI 22 B 137` | `RB/KDI/22 B 137` | 2022 |
| `RB/Cot/13B9933` | `RB/COT/13 B 9933` | 2013 |
| `RBCOT24B38169` | `RB/COT/24 B 38169` | 2024 |
| `RB/COT/2005 B 0040` | `RB/COT/2005 B 0040` | 2005 |
| `RB/LKS/2008-B-180` | `RB/LKS/2008 B 180` | 2008 |
| `RB/PN/11 B 303` | `RB/PN/11 B 303` | 2011 |
| `RB/ABY/94 A 1215` | `RB/ABY/94 A 1215` | 1994 |
| `RB/PNO/23 C 28` | `RB/PNO/23 C 28` | 2023 |
| `RB/COT/23 D 232` | `RB/COT/23 D 232` | 2023 |
| `RB/COT/20 E 2841` | `RB/COT/20 E 2841` | 2020 |
| `RB/ABC/26 B 11379` | `RB/ABC/26 B 11379` | 2026 |

**Invalides :**

| Entrée | Raison |
|---|---|
| `RCCM : RB/COT/24 B 39072` | `InvalidCharacters` (le `:`) |
| `RB/COT/07 B 2058 (Ancien n°: 02 B 1545)` | `InvalidCharacters` |
| `RCCM RB/COT/25 B 41160` | `InvalidStructure` |
| `RB/PKO 18 B 277 du 13/06/2018` | `InvalidStructure` |
| `BJ/COT/18 B 22272` | `InvalidCountry` |
| `RB/CO/18 B 22272` | `UnknownRegistry` |
| `RB/OUI/22 B 12` | `UnknownRegistry` |
| `RB/ODH/23 B 12` | `UnknownRegistry` (ancien code après 2020) |
| `RB/COT/018 B 22272` | `InvalidStructure` |
| `RB/COT/18 22272` | `InvalidStructure` |
| `RB/COT/18 B` | `InvalidStructure` |
| `RB/COT/18 B 1234567` | `InvalidLength` |
| `RB/COT/24 M 12` | `UnknownCategory` |
| `RB/COT/2021 B 12` | `InvalidYear` |
| `RB/COT/85 B 12` | `InvalidYear` (1985) |
| `RB COTONOU 2005 M 2323` | `InvalidStructure` |
| `CM-YDE-01-2015-B23-00010` | `InvalidStructure` |
| `3200700019113` | `InvalidStructure` |
| `24905-B` | `InvalidStructure` |
| `''` | `InvalidLength` |

### 11.3 Vérificateurs

Tous ces tests utilisent le faux client PSR-18.

**Déclenchement des appels :**
- `InvalidFormat` : aucun appel au client.
- Interrupteur coupé : `Unavailable`, aucun appel au client.

**Trouvé ou non :**
- Trouvé dès la requête A dans le registre des sociétés. Tous les champs sont mappés, `register` vaut `Companies`, `Commune` est lue au format catalogue.
- Valeur stockée `RB/COT/07 B 2058 (Ancien n°: 02 B 1545)` : trouvée par la requête B, `formerNumber` vaut `02 B 1545`.
- Réponse « contient » ramenant `RB/COT/18 B 22270` à `22279` pour une recherche de `RB/COT/18 B 2227` : seul le numéro exact correspond.
- Saisie `RB/ABC/12 B 180`, valeur stockée `RB/ABC/2012 B 180` : trouvée.
- Saisie `RB/COT/2005 B 40`, valeur stockée `RB/COT/2005 B 0040` : trouvée.
- Catégorie A : le registre des établissements est interrogé en premier. Les champs `Prénom`, `Nom`, `Téléphone` et `Email` de la réponse ne se retrouvent dans aucune propriété : `phone` et `email` valent `null`.
- Absent des deux registres : `NotFound`, après 4 requêtes.

**`Unavailable` :**
- exception PSR-18 ;
- statut 500 ;
- JSON invalide ;
- `results` absent ;
- élément sans `Numéro RC`.

**Forme des requêtes :**
- l'URL contient `page_size=50` et un `filters` qui se décode en la liste attendue ;
- l'en-tête `User-Agent` est présent.

**`IfuChecker` :**
- entrée invalide : `InvalidFormat` ;
- entrée valide : `Unavailable`, avec `ifu` renseigné.

### 11.4 Laravel (groupe Pest `laravel`, Testbench)

- **Règles de format** : un message par raison, en anglais et en français.
- **Règles d'existence** :
  - `Unavailable` passe par défaut, et échoue avec `failWhenUnavailable()` ;
  - `NotFound` échoue ;
  - interrupteur coupé et mode strict : message `rccm.verification_disabled`.
- **Service provider** :
  - la config est fusionnée et publiable ;
  - le `RccmChecker` résolu reprend bien la config (interrupteur, URL).

---

## 12. Fin de mission pour Codex

Codex rend la main quand :
- `vendor/bin/pest` passe ;
- `vendor/bin/phpstan analyse` passe au niveau `max` ;
- `vendor/bin/pint --test` passe.

Codex ne fait aucun `git commit` et aucun `git push`. La création du dépôt git (`git init`) se fait seulement avec l'accord explicite de l'utilisateur.
