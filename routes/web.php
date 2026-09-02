<?php

use App\Http\Controllers\AidController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HousingController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\MedicalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefugeeCardController;
use App\Http\Controllers\RefugeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ShelterController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/favicon.ico', fn () => response()->noContent())->name('favicon');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

    // The assistant reads only; every answer runs a query written in an intent
    // class, so the throttle is about protecting the database from a stuck
    // client rather than about guarding a model budget.
    Route::post('/assistant/ask', [AssistantController::class, 'ask'])->middleware('throttle:40,1')->name('assistant.ask');
    Route::get('/assistant/suggestions', [AssistantController::class, 'suggestions'])->name('assistant.suggestions');

    Route::get('/lookups/refugees', [LookupController::class, 'refugees'])->name('lookups.refugees');
    Route::get('/lookups/households', [LookupController::class, 'households'])->name('lookups.households');

    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('role:admin');

    Route::resource('camps', CampController::class)->except(['show', 'destroy'])->middleware('role:admin,housing_officer');
    Route::resource('shelters', ShelterController::class)->except(['show', 'destroy'])->middleware('role:admin,housing_officer');

    Route::get('/refugees', [RefugeeController::class, 'index'])->name('refugees.index');
    Route::get('/refugees/create', [RefugeeController::class, 'create'])->name('refugees.create')->middleware('role:admin,registration_officer');
    Route::post('/refugees', [RefugeeController::class, 'store'])->name('refugees.store')->middleware('role:admin,registration_officer');
    Route::get('/refugees/{refugee}', [RefugeeController::class, 'show'])->name('refugees.show');
    Route::get('/refugees/{refugee}/card', RefugeeCardController::class)->name('refugees.card');
    Route::post('/refugees/{refugee}/attachments', [AttachmentController::class, 'store'])->name('refugees.attachments.store')->middleware('role:admin,registration_officer,medical_officer');
    Route::get('/refugees/{refugee}/edit', [RefugeeController::class, 'edit'])->name('refugees.edit')->middleware('role:admin,registration_officer');
    Route::put('/refugees/{refugee}', [RefugeeController::class, 'update'])->name('refugees.update')->middleware('role:admin,registration_officer');
    Route::delete('/refugees/{refugee}', [RefugeeController::class, 'destroy'])->name('refugees.destroy')->middleware('role:admin,registration_officer');

    Route::get('/households', [HouseholdController::class, 'index'])->name('households.index');
    Route::get('/households/create', [HouseholdController::class, 'create'])->name('households.create')->middleware('role:admin,registration_officer');
    Route::post('/households', [HouseholdController::class, 'store'])->name('households.store')->middleware('role:admin,registration_officer');
    Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::get('/households/{household}/edit', [HouseholdController::class, 'edit'])->name('households.edit')->middleware('role:admin,registration_officer');
    Route::put('/households/{household}', [HouseholdController::class, 'update'])->name('households.update')->middleware('role:admin,registration_officer');
    Route::post('/households/{household}/members', [HouseholdController::class, 'addMember'])->name('households.members.store')->middleware('role:admin,registration_officer');
    Route::delete('/households/{household}/members/{refugee}', [HouseholdController::class, 'removeMember'])->name('households.members.destroy')->middleware('role:admin,registration_officer');

    Route::prefix('housing')->name('housing.')->middleware('role:admin,housing_officer')->group(function (): void {
        Route::get('/unassigned', [HousingController::class, 'unassigned'])->name('unassigned');
        Route::get('/refugees/{refugee}/transfer', [HousingController::class, 'transferForm'])->name('transfer.form');
        Route::post('/refugees/{refugee}/transfer', [HousingController::class, 'transfer'])->name('transfer');
        Route::post('/households/{household}/transfer', [HousingController::class, 'householdTransfer'])->name('household.transfer');
    });

    Route::prefix('aid')->name('aid.')->middleware('role:admin,aid_officer')->group(function (): void {
        Route::get('/organizations', [AidController::class, 'organizations'])->name('organizations');
        Route::get('/organizations/create', [AidController::class, 'createOrganization'])->name('organizations.create');
        Route::post('/organizations', [AidController::class, 'storeOrganization'])->name('organizations.store');
        Route::get('/organizations/{organization}/edit', [AidController::class, 'editOrganization'])->name('organizations.edit');
        Route::put('/organizations/{organization}', [AidController::class, 'updateOrganization'])->name('organizations.update');
        Route::get('/types', [AidController::class, 'aidTypes'])->name('types');
        Route::get('/types/create', [AidController::class, 'createAidType'])->name('types.create');
        Route::post('/types', [AidController::class, 'storeAidType'])->name('types.store');
        Route::get('/types/{aidType}/edit', [AidController::class, 'editAidType'])->name('types.edit');
        Route::put('/types/{aidType}', [AidController::class, 'updateAidType'])->name('types.update');
        Route::get('/distributions', [AidController::class, 'distributions'])->name('distributions');
        Route::get('/distributions/create', [AidController::class, 'createDistribution'])->name('distributions.create');
        Route::post('/distributions', [AidController::class, 'storeDistribution'])->name('distributions.store');
    });

    Route::prefix('medical')->name('medical.')->middleware('role:admin,medical_officer')->group(function (): void {
        Route::get('/services', [MedicalController::class, 'services'])->name('services');
        Route::get('/services/create', [MedicalController::class, 'createService'])->name('services.create');
        Route::post('/services', [MedicalController::class, 'storeService'])->name('services.store');
        Route::get('/services/{medicalService}/edit', [MedicalController::class, 'editService'])->name('services.edit');
        Route::put('/services/{medicalService}', [MedicalController::class, 'updateService'])->name('services.update');
        Route::get('/records', [MedicalController::class, 'records'])->name('records');
        Route::get('/records/create', [MedicalController::class, 'createRecord'])->name('records.create');
        Route::post('/records', [MedicalController::class, 'storeRecord'])->name('records.store');
        Route::get('/records/{medicalRecord}/edit', [MedicalController::class, 'editRecord'])->name('records.edit');
        Route::put('/records/{medicalRecord}', [MedicalController::class, 'updateRecord'])->name('records.update');
    });

    Route::prefix('security')->name('security.')->middleware('role:admin,security_officer')->group(function (): void {
        Route::get('/checkpoints', [SecurityController::class, 'checkpoints'])->name('checkpoints');
        Route::get('/checkpoints/create', [SecurityController::class, 'createCheckpoint'])->name('checkpoints.create');
        Route::post('/checkpoints', [SecurityController::class, 'storeCheckpoint'])->name('checkpoints.store');
        Route::get('/checkpoints/{checkpoint}/edit', [SecurityController::class, 'editCheckpoint'])->name('checkpoints.edit');
        Route::put('/checkpoints/{checkpoint}', [SecurityController::class, 'updateCheckpoint'])->name('checkpoints.update');
        Route::get('/movements', [SecurityController::class, 'movements'])->name('movements');
        Route::get('/movements/create', [SecurityController::class, 'createMovement'])->name('movements.create');
        Route::post('/movements', [SecurityController::class, 'storeMovement'])->name('movements.store');
        Route::get('/reports', [SecurityController::class, 'reports'])->name('reports');
        Route::get('/reports/create', [SecurityController::class, 'createReport'])->name('reports.create');
        Route::post('/reports', [SecurityController::class, 'storeReport'])->name('reports.store');
    });

    // Per-report authorization lives in ReportRegistry, which also decides which
    // reports appear in the picker, so the routes only require a signed-in user.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/print', [ReportController::class, 'printable'])->name('reports.print');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/resolve', [NotificationController::class, 'resolve'])->name('notifications.resolve');

    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy')->middleware('role:admin,registration_officer,medical_officer');

    // Archiving is a soft delete: rows stay so historical records keep resolving.
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::delete('/archive/{resource}/{id}', [ArchiveController::class, 'archive'])->whereNumber('id')->name('archive.store');
    Route::post('/archive/{resource}/{id}/restore', [ArchiveController::class, 'restore'])->whereNumber('id')->name('archive.restore');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index')->middleware('role:admin,manager');
});
