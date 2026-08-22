<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $rows = AuditLog::with('user')
            ->when($request->user_id, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->module, fn ($query, $module) => $query->where('module', $module))
            ->when($request->action, fn ($query, $action) => $query->where('action', $action))
            ->when($request->sensitivity, fn ($query, $sensitivity) => $query->where('sensitivity', $sensitivity))
            ->when($request->from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'rows' => $rows,
            'users' => User::pluck('name', 'id'),
            'modules' => AuditLog::query()->select('module')->distinct()->pluck('module'),
            'sensitivities' => ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'حرجة'],
        ]);
    }
}
