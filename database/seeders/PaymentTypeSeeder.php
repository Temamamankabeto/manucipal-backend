<?php

namespace Database\Seeders;

use App\Models\PaymentCategory;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Salary' => [
                'Salaries to permanent staff',
                'Wages of contract staff',
                'Wages of casual staff',
                'Government contributions to permanent staff Pensions',
            ],
            'Allowance' => [
                'Allowances to permanent staff',
                'Per diem',
            ],
            'Operational' => [
                'Uniforms, clothing, bedding',
                'Office supplies',
                'Printing',
                'Medical supplies',
                'Other material and supplies',
                'Miscellaneous equipment',
                'Agricultural, forestry and marine inputs',
                'Official entertainment',
            ],
            'Utility' => [
                'Electricity charges',
                'Telecommunication charges',
                'Water and other utilities',
            ],
            'Transport' => [
                'Fuel and lubricants',
                'Transport fees',
                'Freight',
            ],
            'Maintenance' => [
                'Maintenance and repair of vehicles and other transport',
                'Maintenance and repair of plant, machinery and equipment',
                'Maintenance and repair of buildings, furnishings and fixtures',
                'Maintenance and repair of infrastructure',
            ],
            'Professional Service' => [
                'Contracted professional services',
                'Advertising',
                'Insurance',
                'Fees and charges',
            ],
            'Training' => [
                'Local training',
            ],
            'Procurement' => [
                'Purchase of vehicles and other vehicular transport',
                'Purchase of plant, machinery and equipment',
                'Purchase of buildings, furnishings and fixtures',
            ],
            'Grant' => [
                'Grants, contributions and subsidies to institutions and enterprises',
                'Grants and gratuities to individuals',
            ],
            'Compensation' => [
                'Compensation to individuals and institutions',
            ],
            'Financial Charge' => [
                'Payments of interest and bank charges on domestic public debt',
            ],
        ];

        foreach ($types as $categoryName => $items) {
            $category = PaymentCategory::where('name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            foreach ($items as $name) {
                PaymentType::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ]
                );
            }
        }
    }
}