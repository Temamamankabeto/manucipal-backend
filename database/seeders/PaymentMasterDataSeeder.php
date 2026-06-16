<?php

namespace Database\Seeders;

use App\Models\PaymentCategory;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMasterDataSeeder extends Seeder
{
    /**
     * Seed payment categories and their payment types.
     */
    public function run(): void
    {
        $data = [
            'Salary Payments' => [
                'Permanent Staff Salary',
                'Contract Staff Salary',
                'Casual Worker Payment',
                'Overtime Payment',
                'Allowances',
            ],
            'Procurement Payments' => [
                'Goods Purchase Payment',
                'Service Purchase Payment',
                'Consultancy Payment',
                'Construction Payment',
                'Asset Purchase Payment',
            ],
            'Operational Payments' => [
                'Fuel Payment',
                'Vehicle Maintenance Payment',
                'Building Maintenance Payment',
                'Equipment Maintenance Payment',
                'Utility Payment',
            ],
            'Utility Payments' => [
                'Electricity',
                'Water',
                'Internet',
                'Telephone',
                'Postal Services',
            ],
            'Travel & Training' => [
                'Per Diem',
                'Travel Expense',
                'Training Expense',
                'Workshop Expense',
                'Conference Expense',
            ],
            'Supplier Payments' => [
                'Supplier Invoice Payment',
                'Advance Payment',
                'Partial Payment',
                'Final Settlement',
            ],
            'Project Payments' => [
                'Development Project Payment',
                'Capital Project Payment',
                'Infrastructure Project Payment',
                'Grant Project Payment',
            ],
            'Administrative Payments' => [
                'Office Supplies',
                'Printing',
                'Stationery',
                'Advertising',
                'Insurance',
                'Rent',
            ],
            'Financial Obligations' => [
                'Tax Payment',
                'Pension Contribution',
                'Loan Repayment',
                'Bank Charges',
            ],
            'Social & Public Service Payments' => [
                'Community Support Payment',
                'Compensation Payment',
                'Emergency Relief Payment',
                'Subsidy Payment',
            ],
            'Revenue Refunds' => [
                'Tax Refund',
                'License Fee Refund',
                'Service Fee Refund',
                'Deposit Refund',
            ],
            'Miscellaneous Payments' => [
                'Court Order Payment',
                'Membership Fee',
                'Subscription Fee',
                'Other Authorized Payments',
            ],
        ];

        DB::transaction(function () use ($data): void {
            foreach ($data as $categoryName => $types) {
                $category = PaymentCategory::query()->updateOrCreate(
                    ['name' => $categoryName],
                    ['name' => $categoryName]
                );

                foreach ($types as $typeName) {
                    PaymentType::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'name' => $typeName,
                        ],
                        [
                            'category_id' => $category->id,
                            'name' => $typeName,
                        ]
                    );
                }
            }
        });
    }
}
