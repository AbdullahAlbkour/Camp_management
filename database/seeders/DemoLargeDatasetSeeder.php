<?php

namespace Database\Seeders;

use App\Models\AidType;
use App\Models\Camp;
use App\Models\Checkpoint;
use App\Models\Household;
use App\Models\MedicalService;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Refugee;
use App\Models\Shelter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoLargeDatasetSeeder extends Seeder
{
    private const REFUGEE_COUNT = 2000;
    private const HOUSEHOLD_COUNT = 200;
    private const CAMP_COUNT = 10;
    private const SHELTERS_PER_CAMP = 35;
    private const MEDICAL_RECORD_COUNT = 800;
    private const SECURITY_REPORT_COUNT = 500;
    private const MOVEMENT_COUNT = 1500;
    private const AID_DISTRIBUTION_COUNT = 700;

    public function run(): void
    {
        DB::disableQueryLog();
        mt_srand(20260513);

        $this->clearDemoData();

        $now = now();
        $adminId = User::whereHas('role', fn ($query) => $query->where('name', 'admin'))->value('id')
            ?? User::query()->value('id');
        $medicalUserId = User::whereHas('role', fn ($query) => $query->where('name', 'medical_officer'))->value('id') ?? $adminId;
        $securityUserId = User::whereHas('role', fn ($query) => $query->where('name', 'security_officer'))->value('id') ?? $adminId;
        $housingUserId = User::whereHas('role', fn ($query) => $query->where('name', 'housing_officer'))->value('id') ?? $adminId;
        $aidUserId = User::whereHas('role', fn ($query) => $query->where('name', 'aid_officer'))->value('id') ?? $adminId;

        $camps = $this->seedCamps($now);
        $sheltersByCamp = $this->seedShelters($camps, $now);
        $checkpointsByCamp = $this->seedCheckpoints($camps, $now);
        $medicalServices = $this->seedMedicalServices($now);
        $aidTypes = $this->seedAidTypes($now);

        $this->seedRefugees($camps, $sheltersByCamp, $now);

        $refugees = Refugee::query()
            ->where('document_number', 'like', 'DEMO-%')
            ->orderBy('document_number')
            ->get(['id', 'current_camp_id', 'current_shelter_id', 'document_number']);

        $this->seedHouseholds($refugees, $now);
        $this->seedResidencyTransfers($refugees, $housingUserId, $now);
        $this->seedMedicalRecords($refugees, $medicalServices, $medicalUserId, $now);
        $this->seedSecurityReports($refugees, $securityUserId, $now);
        $this->seedEntryExitLogs($refugees, $checkpointsByCamp, $securityUserId, $now);
        $this->seedAidDistributions($refugees, $aidTypes, $aidUserId, $now);
        $this->seedOperationalNotifications($now);

        $this->command?->info('Demo dataset created: 2000 refugees, 200 households, 10 camps, medical/security/movement/aid records.');
    }

    private function clearDemoData(): void
    {
        DB::table('notifications')->whereIn('type', [
            'housing_unassigned_summary',
            'medical_followup_summary',
            'security_high_risk_summary',
            'movement_outside_summary',
            'aid_distribution_summary',
        ])->delete();

        $demoRefugeeIds = Refugee::where('document_number', 'like', 'DEMO-%')->pluck('id');
        $demoHouseholdIds = Household::where('household_code', 'like', 'HH-DEMO-%')->pluck('id');

        if ($demoHouseholdIds->isNotEmpty()) {
            DB::table('aid_distributions')->whereIn('household_id', $demoHouseholdIds)->delete();
            DB::table('households')->whereIn('id', $demoHouseholdIds)->delete();
        }

        if ($demoRefugeeIds->isNotEmpty()) {
            DB::table('aid_distributions')->whereIn('refugee_id', $demoRefugeeIds)->delete();
            DB::table('medical_records')->whereIn('refugee_id', $demoRefugeeIds)->delete();
            DB::table('security_reports')->whereIn('refugee_id', $demoRefugeeIds)->delete();
            DB::table('entry_exit_logs')->whereIn('refugee_id', $demoRefugeeIds)->delete();
            DB::table('residency_transfers')->whereIn('refugee_id', $demoRefugeeIds)->delete();
            DB::table('notifications')
                ->whereIn('related_type', [Refugee::class, 'refugee', 'refugees'])
                ->whereIn('related_id', $demoRefugeeIds)
                ->delete();
            DB::table('refugees')->whereIn('id', $demoRefugeeIds)->delete();
        }
    }

    private function seedCamps($now): Collection
    {
        $campNames = [
            ['مخيم السلام', 'القطاع الشمالي'],
            ['مخيم النور', 'القطاع الجنوبي'],
            ['مخيم الأمل', 'القطاع الشرقي'],
            ['مخيم الرحمة', 'القطاع الغربي'],
            ['مخيم الياسمين', 'المنطقة الوسطى'],
            ['مخيم الزيتون', 'الطريق الدائري'],
            ['مخيم الكرامة', 'السهل الشمالي'],
            ['مخيم الندى', 'المنطقة الخدمية'],
            ['مخيم الفرات', 'المحور النهري'],
            ['مخيم الحياة', 'المدخل الرئيسي'],
        ];

        foreach ($campNames as $index => [$name, $location]) {
            Camp::updateOrCreate(
                ['name' => $name],
                [
                    'location' => $location,
                    'capacity' => 520 + ($index * 30),
                    'status' => 'active',
                    'notes' => 'بيانات تجريبية موسعة لاختبار لوحة التحكم والتقارير.',
                    'updated_at' => $now,
                ]
            );
        }

        return Camp::query()
            ->whereIn('name', array_column($campNames, 0))
            ->orderBy('id')
            ->take(self::CAMP_COUNT)
            ->get();
    }

    private function seedShelters(Collection $camps, $now): array
    {
        $types = ['tent', 'caravan', 'room'];
        $sheltersByCamp = [];

        foreach ($camps as $campIndex => $camp) {
            for ($i = 1; $i <= self::SHELTERS_PER_CAMP; $i++) {
                $type = $types[($i + $campIndex) % count($types)];
                $code = 'D'.str_pad((string) ($campIndex + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

                Shelter::updateOrCreate(
                    ['camp_id' => $camp->id, 'code' => $code],
                    [
                        'type' => $type,
                        'capacity' => $type === 'room' ? 8 : ($type === 'caravan' ? 6 : 5),
                        'status' => $i % 29 === 0 ? 'maintenance' : 'active',
                        'notes' => 'وحدة تجريبية لاختبار الإشغال والتخصيص.',
                        'updated_at' => $now,
                    ]
                );
            }

            $sheltersByCamp[$camp->id] = Shelter::query()
                ->where('camp_id', $camp->id)
                ->where('code', 'like', 'D'.str_pad((string) ($campIndex + 1), 2, '0', STR_PAD_LEFT).'-%')
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id', 'camp_id', 'capacity']);
        }

        return $sheltersByCamp;
    }

    private function seedCheckpoints(Collection $camps, $now): array
    {
        $checkpointNames = [
            ['البوابة الرئيسية', 'المدخل الرئيسي'],
            ['بوابة الخدمات', 'منطقة الخدمات'],
            ['بوابة الطوارئ', 'المحيط الأمني'],
        ];
        $checkpointsByCamp = [];

        foreach ($camps as $camp) {
            foreach ($checkpointNames as [$name, $location]) {
                Checkpoint::updateOrCreate(
                    ['camp_id' => $camp->id, 'name' => $name],
                    [
                        'location' => $location,
                        'status' => 'active',
                        'updated_at' => $now,
                    ]
                );
            }

            $checkpointsByCamp[$camp->id] = Checkpoint::where('camp_id', $camp->id)
                ->orderBy('id')
                ->pluck('id')
                ->values()
                ->all();
        }

        return $checkpointsByCamp;
    }

    private function seedMedicalServices($now): Collection
    {
        $services = [
            ['معاينة عامة', 'كشف طبي عام وتقييم أولي للحالة.'],
            ['متابعة مزمنة', 'متابعة حالات الضغط والسكري والأمراض المزمنة.'],
            ['إسعاف أولي', 'استجابة أولية للحوادث والإصابات البسيطة.'],
            ['صحة أطفال', 'خدمات الرعاية الصحية للأطفال واللقاحات.'],
            ['صحة نسائية', 'استشارات ومتابعة صحية للنساء.'],
            ['دعم نفسي', 'جلسات دعم نفسي واجتماعي.'],
        ];

        foreach ($services as [$name, $description]) {
            MedicalService::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'status' => 'active',
                    'updated_at' => $now,
                ]
            );
        }

        return MedicalService::whereIn('name', array_column($services, 0))->orderBy('id')->get(['id']);
    }

    private function seedAidTypes($now): Collection
    {
        $organization = Organization::updateOrCreate(
            ['name' => 'مركز الدعم الإنساني التجريبي'],
            [
                'contact_name' => 'منسق المساعدات',
                'phone' => '0110000000',
                'email' => 'support@example.local',
                'status' => 'active',
                'notes' => 'جهة داعمة تجريبية للبيانات الموسعة.',
                'updated_at' => $now,
            ]
        );

        $types = [
            ['سلة غذائية', 'سلة'],
            ['بطانية', 'قطعة'],
            ['حزمة نظافة', 'حزمة'],
            ['دواء أساسي', 'علبة'],
            ['مياه شرب', 'كرتونة'],
        ];

        foreach ($types as [$name, $unit]) {
            AidType::updateOrCreate(
                ['organization_id' => $organization->id, 'name' => $name],
                [
                    'unit' => $unit,
                    'description' => 'نوع مساعدة تجريبي ضمن بيانات الاختبار.',
                    'status' => 'active',
                    'updated_at' => $now,
                ]
            );
        }

        return AidType::where('organization_id', $organization->id)->orderBy('id')->get(['id']);
    }

    private function seedRefugees(Collection $camps, array $sheltersByCamp, $now): void
    {
        $firstNames = ['محمد', 'أحمد', 'خالد', 'عمر', 'محمود', 'علي', 'حسن', 'يوسف', 'سارة', 'فاطمة', 'مريم', 'هبة', 'نور', 'رنا', 'ليلى', 'آية'];
        $fatherNames = ['عبدالله', 'محمود', 'إبراهيم', 'محمد', 'أحمد', 'خالد', 'مصطفى', 'حسين', 'سعيد', 'طارق'];
        $lastNames = ['الخالد', 'المحمد', 'الحسن', 'العلي', 'اليوسف', 'الحموي', 'الحلبي', 'الديري', 'العمر', 'الناصر'];
        $maritalStatuses = ['أعزب/عزباء', 'متزوج/ة', 'أرمل/ة', 'مطلق/ة'];
        $nationalities = ['سوري', 'سوري', 'سوري', 'سوري', 'فلسطيني'];
        $shelterSlotsByCamp = [];
        $shelterSlotCursor = [];

        foreach ($sheltersByCamp as $campId => $shelters) {
            $shelterSlotsByCamp[$campId] = [];
            foreach ($shelters as $shelter) {
                for ($slot = 0; $slot < $shelter->capacity; $slot++) {
                    $shelterSlotsByCamp[$campId][] = $shelter->id;
                }
            }
            $shelterSlotCursor[$campId] = 0;
        }

        $rows = [];

        for ($i = 1; $i <= self::REFUGEE_COUNT; $i++) {
            $camp = $camps[($i - 1) % $camps->count()];
            $hasShelter = $i <= 1920 && isset($shelterSlotsByCamp[$camp->id][$shelterSlotCursor[$camp->id]]);
            $shelterId = $hasShelter ? $shelterSlotsByCamp[$camp->id][$shelterSlotCursor[$camp->id]++] : null;
            $gender = $i % 2 === 0 ? 'female' : 'male';
            $year = 1948 + ($i % 68);
            $birthDate = CarbonImmutable::create($year, (($i % 12) + 1), (($i % 27) + 1))->toDateString();

            $rows[] = [
                'first_name' => $firstNames[$i % count($firstNames)],
                'father_name' => $fatherNames[($i * 3) % count($fatherNames)],
                'last_name' => $lastNames[($i * 5) % count($lastNames)],
                'gender' => $gender,
                'date_of_birth' => $birthDate,
                'nationality' => $nationalities[$i % count($nationalities)],
                'document_number' => 'DEMO-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'phone' => '09'.str_pad((string) (30000000 + $i), 8, '0', STR_PAD_LEFT),
                'marital_status' => $maritalStatuses[$i % count($maritalStatuses)],
                'status' => $i % 97 === 0 ? 'inactive' : 'active',
                'current_camp_id' => $camp->id,
                'current_shelter_id' => $shelterId,
                'housing_status' => $shelterId ? 'assigned' : 'unassigned',
                'presence_status' => $i % 9 === 0 ? 'outside' : 'inside',
                'household_id' => null,
                'relation_to_head' => null,
                'notes' => 'لاجئ تجريبي ضمن مجموعة البيانات الموسعة.',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('refugees')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('refugees')->insert($rows);
        }
    }

    private function seedHouseholds(Collection $refugees, $now): void
    {
        $relations = ['زوج/زوجة', 'ابن/ابنة', 'ابن/ابنة', 'والد/والدة', 'أخ/أخت', 'قريب'];
        $memberCursor = 0;

        for ($i = 1; $i <= self::HOUSEHOLD_COUNT; $i++) {
            $memberCount = 4 + ($i % 5);
            $members = $refugees->slice($memberCursor, $memberCount)->values();
            $memberCursor += $memberCount;

            if ($members->isEmpty()) {
                break;
            }

            $head = $members->first();
            $household = Household::create([
                'household_code' => 'HH-DEMO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'head_of_household_id' => $head->id,
                'status' => 'active',
                'notes' => 'أسرة تجريبية لاختبار البحث والملف العائلي.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($members as $index => $member) {
                DB::table('refugees')->where('id', $member->id)->update([
                    'household_id' => $household->id,
                    'relation_to_head' => $index === 0 ? 'رب الأسرة' : $relations[$index % count($relations)],
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedResidencyTransfers(Collection $refugees, ?int $housingUserId, $now): void
    {
        $rows = [];

        foreach ($refugees as $index => $refugee) {
            $rows[] = [
                'refugee_id' => $refugee->id,
                'from_camp_id' => null,
                'to_camp_id' => $refugee->current_camp_id,
                'from_shelter_id' => null,
                'to_shelter_id' => $refugee->current_shelter_id,
                'transfer_type' => $refugee->current_shelter_id ? 'initial' : 'unassignment',
                'reason' => $refugee->current_shelter_id ? 'تسكين أولي ضمن البيانات التجريبية.' : 'حالة بدون سكن لاختبار التنبيهات.',
                'transferred_by' => $housingUserId,
                'transferred_at' => $now->copy()->subDays($index % 90),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('residency_transfers')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('residency_transfers')->insert($rows);
        }
    }

    private function seedMedicalRecords(Collection $refugees, Collection $medicalServices, ?int $medicalUserId, $now): void
    {
        $diagnoses = [
            'زكام وارتفاع حرارة بسيط',
            'متابعة ضغط الدم',
            'مراجعة سكري دورية',
            'إصابة سطحية تحتاج تعقيم',
            'حالة تنفسية خفيفة',
            'استشارة نفسية اجتماعية',
            'متابعة لقاح طفل',
        ];
        $rows = [];

        for ($i = 1; $i <= self::MEDICAL_RECORD_COUNT; $i++) {
            $refugee = $refugees[($i * 7) % $refugees->count()];
            $needsFollowUp = $i % 4 === 0;

            $rows[] = [
                'refugee_id' => $refugee->id,
                'medical_service_id' => $medicalServices[$i % $medicalServices->count()]->id,
                'camp_id' => $refugee->current_camp_id,
                'record_date' => $now->copy()->subDays($i % 180)->toDateString(),
                'diagnosis' => $diagnoses[$i % count($diagnoses)],
                'notes' => $needsFollowUp ? 'تحتاج متابعة قريبة حسب توصية الفريق الطبي.' : 'لا توجد ملاحظات حرجة.',
                'needs_follow_up' => $needsFollowUp,
                'follow_up_date' => $needsFollowUp ? $now->copy()->addDays(($i % 21) + 1)->toDateString() : null,
                'recorded_by' => $medicalUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 400) {
                DB::table('medical_records')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('medical_records')->insert($rows);
        }
    }

    private function seedSecurityReports(Collection $refugees, ?int $securityUserId, $now): void
    {
        $incidentTypes = ['مشادة كلامية', 'خروج دون تصريح', 'فقدان وثيقة', 'مخالفة تعليمات', 'تجمع غير منسق', 'حريق بسيط'];
        $severityPool = ['low', 'low', 'medium', 'medium', 'high', 'critical'];
        $rows = [];

        for ($i = 1; $i <= self::SECURITY_REPORT_COUNT; $i++) {
            $refugee = $refugees[($i * 11) % $refugees->count()];
            $severity = $severityPool[$i % count($severityPool)];

            $rows[] = [
                'refugee_id' => $refugee->id,
                'camp_id' => $refugee->current_camp_id,
                'incident_type' => $incidentTypes[$i % count($incidentTypes)],
                'severity' => $severity,
                'report_date' => $now->copy()->subDays($i % 120)->toDateString(),
                'description' => 'تقرير أمني تجريبي لاختبار التصفية ولوحة المخاطر.',
                'action_taken' => $severity === 'critical' || $severity === 'high' ? 'تم فتح متابعة أمنية وتوثيق الإجراء.' : 'تم التنبيه وتسجيل الملاحظة.',
                'reported_by' => $securityUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 250) {
                DB::table('security_reports')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('security_reports')->insert($rows);
        }
    }

    private function seedEntryExitLogs(Collection $refugees, array $checkpointsByCamp, ?int $securityUserId, $now): void
    {
        $reasons = ['زيارة خدمات', 'مراجعة طبية', 'تصريح عمل مؤقت', 'زيارة عائلية', 'عودة من مراجعة خارجية'];
        $rows = [];

        for ($i = 1; $i <= self::MOVEMENT_COUNT; $i++) {
            $refugee = $refugees[($i * 13) % $refugees->count()];
            $checkpointIds = $checkpointsByCamp[$refugee->current_camp_id] ?? [];

            if ($checkpointIds === []) {
                continue;
            }

            $rows[] = [
                'refugee_id' => $refugee->id,
                'camp_id' => $refugee->current_camp_id,
                'checkpoint_id' => $checkpointIds[$i % count($checkpointIds)],
                'movement_type' => $i % 2 === 0 ? 'entry' : 'exit',
                'movement_datetime' => $now->copy()->subMinutes($i * 37)->toDateTimeString(),
                'reason' => $reasons[$i % count($reasons)],
                'recorded_by' => $securityUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('entry_exit_logs')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('entry_exit_logs')->insert($rows);
        }
    }

    private function seedAidDistributions(Collection $refugees, Collection $aidTypes, ?int $aidUserId, $now): void
    {
        $households = Household::where('household_code', 'like', 'HH-DEMO-%')->orderBy('household_code')->get(['id']);
        $householdCampMap = DB::table('refugees')
            ->whereNotNull('household_id')
            ->where('document_number', 'like', 'DEMO-%')
            ->orderBy('id')
            ->get(['household_id', 'current_camp_id'])
            ->unique('household_id')
            ->pluck('current_camp_id', 'household_id');
        $rows = [];

        for ($i = 1; $i <= self::AID_DISTRIBUTION_COUNT; $i++) {
            $isHouseholdAid = $i % 3 !== 0 && $households->isNotEmpty();
            $refugee = $refugees[($i * 5) % $refugees->count()];
            $household = $isHouseholdAid ? $households[$i % $households->count()] : null;

            $rows[] = [
                'aid_type_id' => $aidTypes[$i % $aidTypes->count()]->id,
                'refugee_id' => $isHouseholdAid ? null : $refugee->id,
                'household_id' => $isHouseholdAid ? $household->id : null,
                'camp_id' => $isHouseholdAid ? ($householdCampMap[$household->id] ?? $refugee->current_camp_id) : $refugee->current_camp_id,
                'quantity' => $isHouseholdAid ? (($i % 4) + 1) : (($i % 3) + 1),
                'distribution_date' => $now->copy()->subDays($i % 90)->toDateString(),
                'distributed_by' => $aidUserId,
                'notes' => $isHouseholdAid ? 'توزيع مساعدة لأسرة تجريبية.' : 'توزيع مساعدة للاجئ تجريبي.',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 350) {
                DB::table('aid_distributions')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('aid_distributions')->insert($rows);
        }
    }

    private function seedOperationalNotifications($now): void
    {
        $unassigned = Refugee::where('housing_status', 'unassigned')->count();
        $followups = DB::table('medical_records')->where('needs_follow_up', true)->count();
        $highSecurity = DB::table('security_reports')->whereIn('severity', ['high', 'critical'])->count();
        $outside = Refugee::where('presence_status', 'outside')->count();
        $aidToday = DB::table('aid_distributions')->whereDate('distribution_date', $now->toDateString())->count();

        $items = [
            [
                'roles' => ['housing_officer', 'manager', 'admin'],
                'type' => 'housing_unassigned_summary',
                'title' => 'حالات بدون سكن تحتاج تخصيص',
                'body' => 'يوجد '.$unassigned.' لاجئ بدون وحدة سكنية مخصصة.',
                'active' => $unassigned > 0,
            ],
            [
                'roles' => ['medical_officer', 'manager', 'admin'],
                'type' => 'medical_followup_summary',
                'title' => 'متابعات طبية مفتوحة',
                'body' => 'يوجد '.$followups.' سجل طبي يحتاج متابعة.',
                'active' => $followups > 0,
            ],
            [
                'roles' => ['security_officer', 'manager', 'admin'],
                'type' => 'security_high_risk_summary',
                'title' => 'تقارير أمنية عالية الخطورة',
                'body' => 'يوجد '.$highSecurity.' تقرير أمني بدرجة عالية أو حرجة.',
                'active' => $highSecurity > 0,
            ],
            [
                'roles' => ['security_officer', 'manager', 'admin'],
                'type' => 'movement_outside_summary',
                'title' => 'حالات خارج المخيم',
                'body' => 'يوجد '.$outside.' لاجئ مسجل خارج المخيم حاليًا.',
                'active' => $outside > 0,
            ],
            [
                'roles' => ['aid_officer', 'manager', 'admin'],
                'type' => 'aid_distribution_summary',
                'title' => 'متابعة توزيع المساعدات',
                'body' => 'تم تسجيل '.$aidToday.' عملية توزيع بتاريخ اليوم.',
                'active' => $aidToday > 0,
            ],
        ];

        foreach ($items as $item) {
            if (! $item['active']) {
                continue;
            }

            foreach (array_unique($item['roles']) as $role) {
                Notification::create([
                    'target_role' => $role,
                    'type' => $item['type'],
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'status' => 'unread',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
