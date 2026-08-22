<?php

namespace Database\Seeders;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\MedicalService;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\ResidencyTransfer;
use App\Models\Role;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Camp-Demo-2026';

    public function run(): void
    {
        $roles = [
            'admin' => 'مدير النظام',
            'manager' => 'الإدارة المركزية',
            'registration_officer' => 'موظف التسجيل',
            'housing_officer' => 'مسؤول السكن',
            'aid_officer' => 'مسؤول المساعدات',
            'medical_officer' => 'الموظف الطبي',
            'security_officer' => 'مسؤول الأمن',
        ];

        foreach ($roles as $name => $display) {
            Role::updateOrCreate(['name' => $name], [
                'display_name' => $display,
                'description' => 'دور '.$display,
            ]);
        }

        // Demo accounts. The password satisfies the application's own policy, so a
        // seeded account can immediately be edited through the admin screen without
        // the form rejecting the password it was created with. Override it per
        // environment with SEED_PASSWORD, and change it before any real deployment.
        $seedPassword = Hash::make(env('SEED_PASSWORD', self::DEMO_PASSWORD));

        foreach ($roles as $name => $display) {
            User::updateOrCreate(
                ['email' => str_replace('_officer', '', $name).'@camp.local'],
                [
                    'name' => $display,
                    'password' => $seedPassword,
                    'role_id' => Role::where('name', $name)->value('id'),
                    'status' => 'active',
                ]
            );
        }

        $campA = Camp::updateOrCreate(['name' => 'مخيم السلام'], [
            'location' => 'القطاع الشمالي',
            'capacity' => 500,
            'status' => 'active',
        ]);

        $campB = Camp::updateOrCreate(['name' => 'مخيم النور'], [
            'location' => 'القطاع الجنوبي',
            'capacity' => 350,
            'status' => 'active',
        ]);

        $shelterA = Shelter::updateOrCreate(['camp_id' => $campA->id, 'code' => 'A-01'], [
            'type' => 'tent',
            'capacity' => 5,
            'status' => 'active',
        ]);

        Shelter::updateOrCreate(['camp_id' => $campA->id, 'code' => 'A-02'], [
            'type' => 'caravan',
            'capacity' => 4,
            'status' => 'active',
        ]);

        Shelter::updateOrCreate(['camp_id' => $campB->id, 'code' => 'B-01'], [
            'type' => 'room',
            'capacity' => 6,
            'status' => 'active',
        ]);

        Checkpoint::updateOrCreate(['camp_id' => $campA->id, 'name' => 'البوابة الرئيسية'], [
            'location' => 'المدخل الشرقي',
            'status' => 'active',
        ]);

        $refugee = Refugee::updateOrCreate(['document_number' => 'DOC-1001'], [
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'last_name' => 'الخالد',
            'gender' => 'male',
            'date_of_birth' => '1990-01-12',
            'nationality' => 'سوري',
            'phone' => '0999999999',
            'marital_status' => 'متزوج',
            'status' => 'active',
            'current_camp_id' => $campA->id,
            'current_shelter_id' => $shelterA->id,
            'housing_status' => 'assigned',
            'presence_status' => 'inside',
        ]);

        ResidencyTransfer::firstOrCreate([
            'refugee_id' => $refugee->id,
            'transfer_type' => 'initial',
        ], [
            'to_camp_id' => $campA->id,
            'to_shelter_id' => $shelterA->id,
            'reason' => 'بيانات أولية',
            'transferred_at' => now(),
        ]);

        $household = Household::updateOrCreate(['household_code' => 'HH-1001'], [
            'head_of_household_id' => $refugee->id,
            'status' => 'active',
        ]);

        $refugee->update([
            'household_id' => $household->id,
            'relation_to_head' => 'رب الأسرة',
        ]);

        $organization = Organization::updateOrCreate(['name' => 'الهلال المحلي'], [
            'contact_name' => 'منسق الدعم',
            'phone' => '0111111111',
            'status' => 'active',
        ]);

        AidType::updateOrCreate(['organization_id' => $organization->id, 'name' => 'سلة غذائية'], [
            'unit' => 'سلة',
            'status' => 'active',
        ]);

        MedicalService::updateOrCreate(['name' => 'معاينة عامة'], [
            'description' => 'خدمة كشف طبي عام',
            'status' => 'active',
        ]);
    }
}
