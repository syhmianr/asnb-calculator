<?php

namespace Database\Seeders;

use App\Models\AsnbFund;
use App\Models\AsnbRate;
use Illuminate\Database\Seeder;

class AsnbSeeder extends Seeder
{
    public function run(): void
    {
        $funds = [
            ['code' => 'ASB', 'name' => 'ASB', 'description' => 'Amanah Saham Bumiputera'],
            ['code' => 'ASB2', 'name' => 'ASB 2', 'description' => 'Amanah Saham Bumiputera 2'],
            ['code' => 'ASB3', 'name' => 'ASB 3 Didik', 'description' => 'Amanah Saham Bumiputera 3 - Didik'],
            ['code' => 'ASM', 'name' => 'ASM', 'description' => 'Amanah Saham Malaysia'],
            ['code' => 'ASM2', 'name' => 'ASM 2 Wawasan', 'description' => 'Amanah Saham Malaysia 2 - Wawasan'],
            ['code' => 'ASM3', 'name' => 'ASM 3', 'description' => 'Amanah Saham Malaysia 3'],
        ];

        foreach ($funds as $i => $fund) {
            AsnbFund::updateOrCreate(['code' => $fund['code']], $fund + ['sort_order' => $i, 'is_active' => true]);
        }

        // Declared ASB distributions (sen per unit = % on RM1 units). Special bonuses
        // are folded into bonus_rate. Source: PNB announcements, FY2019–FY2025.
        $asbRates = [
            2025 => [5.20, 0.55, null],
            2024 => [5.50, 0.25, null],
            2023 => [4.25, 1.00, null],
            2022 => [3.35, 1.75, 'Includes 0.50 sen special bonus'],
            2021 => [4.25, 0.75, null],
            2020 => [3.50, 1.50, 'Includes 0.75 sen special bonus'],
            2019 => [5.00, 0.50, null],
        ];

        $asb = AsnbFund::where('code', 'ASB')->first();

        foreach ($asbRates as $year => [$dividend, $bonus, $notes]) {
            AsnbRate::updateOrCreate(
                ['asnb_fund_id' => $asb->id, 'financial_year' => $year],
                [
                    'dividend_rate' => $dividend,
                    'bonus_rate' => $bonus,
                    'calculation_method' => AsnbRate::METHOD_MINIMUM,
                    'notes' => $notes,
                    'is_active' => true,
                ],
            );
        }
    }
}
