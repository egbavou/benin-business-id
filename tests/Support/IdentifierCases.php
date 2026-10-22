<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Tests\Support;

use Egbavou\BeninBusinessId\FailureReason;
use Egbavou\BeninBusinessId\TaxpayerType;

final class IdentifierCases
{
    /** @return list<array{string, string, TaxpayerType, int}> */
    public static function validIfu(): array
    {
        return [
            ['3202444139959', '3202444139959', TaxpayerType::Company, 2024],
            ['3.201.200.247.219', '3201200247219', TaxpayerType::Company, 2012],
            ['32009008191 13', '3200900819113', TaxpayerType::Company, 2009],
            ["\u{00A0}32024\u{202F}44139959\t\n", '3202444139959', TaxpayerType::Company, 2024],
            ['4201710116446', '4201710116446', TaxpayerType::PublicEntity, 2017],
            ['5201000673108', '5201000673108', TaxpayerType::InternationalOrganization, 2010],
            ['6201001214905', '6201001214905', TaxpayerType::Ngo, 2010],
            // Synthetic individual identifiers; an accidental real-world assignment cannot be ruled out.
            ['1201500000000', '1201500000000', TaxpayerType::Individual, 2015],
            ['0201500000000', '0201500000000', TaxpayerType::Individual, 2015],
            ['2201500000000', '2201500000000', TaxpayerType::Individual, 2015],
        ];
    }

    /** @return list<array{string, FailureReason}> */
    public static function invalidIfu(): array
    {
        return [
            ['', FailureReason::InvalidLength],
            ['   ', FailureReason::InvalidLength],
            ['320244413995', FailureReason::InvalidLength],
            ['32024441399590', FailureReason::InvalidLength],
            ['3202444139A59', FailureReason::InvalidCharacters],
            ['IFU : 3202444139959', FailureReason::InvalidCharacters],
            ['BJ-IFU-3200901353510', FailureReason::InvalidCharacters],
            ['7202400000000', FailureReason::UnknownTaxpayerType],
            ['9999900000001', FailureReason::UnknownTaxpayerType],
            ['5200081145006', FailureReason::InvalidYear],
            ['2977421186134', FailureReason::InvalidYear],
            ['3'.((int) gmdate('Y') + 1).'00000000', FailureReason::InvalidYear],
            ['3200500000000', FailureReason::InvalidYear],
            ['3202444139959:', FailureReason::InvalidCharacters],
            ["3202444139959\xFF", FailureReason::InvalidCharacters],
            ['３２０２４４４１３９９５９', FailureReason::InvalidCharacters],
            ['A', FailureReason::InvalidCharacters],
            ['9999', FailureReason::InvalidLength],
        ];
    }

    /** @return list<array{string, string, int}> */
    public static function validRccm(): array
    {
        return [
            ['RB/COT/18 B 22272', 'RB/COT/18 B 22272', 2018],
            ['RB/COT/07-B-1432', 'RB/COT/07 B 1432', 2007],
            ['RB/COT/07/B 1351', 'RB/COT/07 B 1351', 2007],
            ['RB KDI 22 B 137', 'RB/KDI/22 B 137', 2022],
            ['RB/Cot/13B9933', 'RB/COT/13 B 9933', 2013],
            ['RBCOT24B38169', 'RB/COT/24 B 38169', 2024],
            ['RB/COT/2005 B 0040', 'RB/COT/2005 B 0040', 2005],
            ['RB/LKS/2008-B-180', 'RB/LKS/2008 B 180', 2008],
            ['RB/PN/11 B 303', 'RB/PN/11 B 303', 2011],
            ['RB/ABY/94 A 1215', 'RB/ABY/94 A 1215', 1994],
            ['RB/PNO/23 C 28', 'RB/PNO/23 C 28', 2023],
            ['RB/COT/23 D 232', 'RB/COT/23 D 232', 2023],
            ['RB/COT/20 E 2841', 'RB/COT/20 E 2841', 2020],
            ['RB/ABC/26 B 11379', 'RB/ABC/26 B 11379', 2026],
            ["\u{2003}RB/PKO/18\u{00A0}A\u{202F}00001", 'RB/PKO/18 A 00001', 2018],
        ];
    }

    /** @return list<array{string, FailureReason}> */
    public static function invalidRccm(): array
    {
        return [
            ['RCCM : RB/COT/24 B 39072', FailureReason::InvalidCharacters],
            ['RB/COT/07 B 2058 (Ancien n°: 02 B 1545)', FailureReason::InvalidCharacters],
            ['RCCM RB/COT/25 B 41160', FailureReason::InvalidStructure],
            ['RB/PKO 18 B 277 du 13/06/2018', FailureReason::InvalidStructure],
            ['BJ/COT/18 B 22272', FailureReason::InvalidCountry],
            ['RB/CO/18 B 22272', FailureReason::UnknownRegistry],
            ['RB/OUI/22 B 12', FailureReason::UnknownRegistry],
            ['RB/ODH/23 B 12', FailureReason::UnknownRegistry],
            ['RB/COT/018 B 22272', FailureReason::InvalidStructure],
            ['RB/COT/18 22272', FailureReason::InvalidStructure],
            ['RB/COT/18 B', FailureReason::InvalidStructure],
            ['RB/COT/18 B 1234567', FailureReason::InvalidLength],
            ['RB/COT/24 M 12', FailureReason::UnknownCategory],
            ['RB/COT/2021 B 12', FailureReason::InvalidYear],
            ['RB/COT/85 B 12', FailureReason::InvalidYear],
            ['RB COTONOU 2005 M 2323', FailureReason::InvalidStructure],
            ['CM-YDE-01-2015-B23-00010', FailureReason::InvalidStructure],
            ['3200700019113', FailureReason::InvalidStructure],
            ['24905-B', FailureReason::InvalidStructure],
            ['', FailureReason::InvalidLength],
            [' .- ', FailureReason::InvalidLength],
            ["RB/COT/18 B 12\xFF", FailureReason::InvalidCharacters],
            ['BJ/ZZZ/1985 M 1234567', FailureReason::InvalidLength],
            ['BJ/ZZZ/1985 M 12', FailureReason::InvalidCountry],
            ['RB/ZZZ/1985 M 12', FailureReason::UnknownRegistry],
            ['RB/ODH/2021 M 12', FailureReason::UnknownCategory],
            ['RB/ODH/2021 B 12', FailureReason::InvalidYear],
        ];
    }
}
