<?php

namespace Database\Seeders;

use App\Models\Budget;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['6111', 'Salaries to permanent staff'],
            ['6113', 'Wages of contract staff'],
            ['6114', 'Wages of casual staff'],
            ['6121', 'Allowances to permanent staff'],
            ['6131', 'Government contributions to permanent staff Pensions'],
            ['6211', 'Uniforms, clothing, bedding'],
            ['6212', 'Office supplies'],
            ['6213', 'Printing'],
            ['6214', 'Medical supplies'],
            ['6217', 'Fuel and lubricants'],
            ['6218', 'Other material and supplies'],
            ['6219', 'Miscellaneous equipment'],
            ['6221', 'Agricultural, forestry and marine inputs'],
            ['6231', 'Per diem'],
            ['6232', 'Transport fees'],
            ['6233', 'Official entertainment'],
            ['6241', 'Maintenance and repair of vehicles and other transport'],
            ['6243', 'Maintenance and repair of plant, machinery and equipment'],
            ['6244', 'Maintenance and repair of buildings, furnishings and fixtures'],
            ['6245', 'Maintenance and repair of infrastructure'],
            ['6251', 'Contracted professional services'],
            ['6252', 'Rent'],
            ['6253', 'Advertising'],
            ['6254', 'Insurance'],
            ['6255', 'Freight'],
            ['6256', 'Fees and charges'],
            ['6257', 'Electricity charges'],
            ['6258', 'Telecommunication charges'],
            ['6259', 'Water and other utilities'],
            ['6271', 'Local training'],
            ['6311', 'Purchase of vehicles and other vehicular transport'],
            ['6313', 'Purchase of plant, machinery and equipment'],
            ['6314', 'Purchase of buildings, furnishings and fixtures'],
            ['6412', 'Grants, contributions and subsidies to institutions and enterprises'],
            ['6416', 'Compensation to individuals and institutions'],
            ['6417', 'Grants and gratuities to individuals'],
            ['6419', 'Miscellaneous'],
            ['6434', 'Payments of interest and bank charges on domestic public debt'],
        ];

        $defaultBiCode = '04/21/000/152/01/01';
        $defaultFiscalYear = '2018';

        foreach ($items as [$code, $account]) {
            Budget::updateOrCreate(
                [
                    'bi_code' => $defaultBiCode,
                    'fiscal_year' => $defaultFiscalYear,
                    'budget_code' => $code,
                ],
                [
                    'account_name' => $account,
                    'source_of_finance' => '1900',
                    'bank_account_code' => '29 - Mana Qopheesaa',
                    'budget_type' => '1 - Recurrent',
                    'allocated_amount' => 0,
                    'used_amount' => 0,
                    'remaining_amount' => 0,
                    'status' => Budget::STATUS_ACTIVE,
                ]
            );
        }
    }
}
