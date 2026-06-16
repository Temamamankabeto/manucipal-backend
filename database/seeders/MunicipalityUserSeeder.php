<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MunicipalityUserSeeder extends Seeder
{
    public function run(): void
    {
        $city = Office::where('type', Office::TYPE_CITY)
            ->orderBy('id')
            ->first();

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@adama.gov.et',
                'phone' => '+251900000001',
                'role' => 'Super Admin',
            ],
            [
                'name' => 'Manager',
                'email' => 'manager@adama.gov.et',
                'phone' => '+251900000002',
                'role' => 'Manager',
            ],
            [
                'name' => 'Head of Development Branch',
                'email' => 'development@adama.gov.et',
                'phone' => '+251900000003',
                'role' => 'Head of Development Branch',
            ],
            [
                'name' => 'Head of Service Branch',
                'email' => 'service@adama.gov.et',
                'phone' => '+251900000004',
                'role' => 'Head of Service Branch',
            ],
            [
                'name' => 'Planning & Budget Team Leader',
                'email' => 'budgetleader@adama.gov.et',
                'phone' => '+251900000005',
                'role' => 'Planning & Budget Team Leader',
            ],
            [
                'name' => 'Planning & Budget Expert',
                'email' => 'budgetexpert@adama.gov.et',
                'phone' => '+251900000006',
                'role' => 'Planning & Budget Expert',
            ],
            [
                'name' => 'Payment Requester',
                'email' => 'paymentrequester@adama.gov.et',
                'phone' => '+251900000007',
                'role' => 'Payment Requester',
            ],
            [
                'name' => 'Procurement Requester',
                'email' => 'procurementrequester@adama.gov.et',
                'phone' => '+251900000008',
                'role' => 'Procurement Requester',
            ],
            [
                'name' => 'Asset Team Leader',
                'email' => 'asset@adama.gov.et',
                'phone' => '+251900000009',
                'role' => 'Asset Team Leader',
            ],
            [
                'name' => 'Machinery Team Leader',
                'email' => 'machinery@adama.gov.et',
                'phone' => '+251900000010',
                'role' => 'Machinery Team Leader',
            ],
            [
                'name' => 'Records Office',
                'email' => 'records@adama.gov.et',
                'phone' => '+251900000011',
                'role' => 'Records Office',
            ],
            [
                'name' => 'Finance Accountant',
                'email' => 'finance@adama.gov.et',
                'phone' => '+251900000012',
                'role' => 'Finance Accountant',
            ],
        ];

        foreach ($users as $user) {
            $this->createUser(
                $user['name'],
                $user['email'],
                $user['phone'],
                $user['role'],
                $city
            );
        }
    }

    protected function createUser(
        string $name,
        string $email,
        string $phone,
        string $role,
        ?Office $office
    ): void {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('Admin@12345'),
                'is_active' => true,
                'admin_level' => null,
                'office_id' => $office?->id,
                'sub_city_id' => null,
                'woreda_id' => null,
                'zone_id' => null,
            ]
        );

        $user->syncRoles([$role]);
    }
}