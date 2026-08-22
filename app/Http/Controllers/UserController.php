<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $rows = User::with('role')->latest()->paginate(20);

        return view('crud.index', [
            'title' => 'المستخدمون',
            'createRoute' => route('users.create'),
            'columns' => [
                ['label' => 'الاسم', 'field' => 'name'],
                ['label' => 'البريد', 'field' => 'email'],
                ['label' => 'الدور', 'field' => 'role.display_name'],
                ['label' => 'الحالة', 'field' => 'status'],
            ],
            'rows' => $rows,
            'editRoute' => 'users.edit',
        ]);
    }

    public function create(): View
    {
        return $this->form(new User, route('users.store'), 'POST', 'إضافة مستخدم');
    }

    public function store(UserRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->payload();
        $user = User::create($data);
        $auditLog->log('create', 'users', $user, 'إضافة مستخدم جديد', 'critical', $data);

        return redirect()->route('users.index')->with('success', 'تمت إضافة المستخدم بنجاح.');
    }

    public function edit(User $user): View
    {
        return $this->form($user, route('users.update', $user), 'PUT', 'تعديل مستخدم');
    }

    public function update(UserRequest $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->payload();
        $user->update($data);
        $auditLog->log('update', 'users', $user, 'تعديل بيانات مستخدم', 'critical', $data);

        return redirect()->route('users.index')->with('success', 'تم تعديل المستخدم بنجاح.');
    }

    private function form(User $user, string $action, string $method, string $title): View
    {
        return view('crud.form', [
            'title' => $title,
            'action' => $action,
            'method' => $method,
            'backRoute' => route('users.index'),
            'model' => $user,
            'fields' => [
                ['name' => 'name', 'label' => 'الاسم', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'البريد الإلكتروني', 'type' => 'email', 'required' => true],
                ['name' => 'password', 'label' => 'كلمة المرور', 'type' => 'password', 'required' => ! $user->exists],
                ['name' => 'role_id', 'label' => 'الدور', 'type' => 'select', 'required' => true, 'options' => Role::pluck('display_name', 'id')],
                ['name' => 'status', 'label' => 'الحالة', 'type' => 'select', 'required' => true, 'options' => ['active' => 'فعال', 'inactive' => 'معطل']],
            ],
        ]);
    }
}
