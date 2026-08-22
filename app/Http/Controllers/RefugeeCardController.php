<?php

namespace App\Http\Controllers;

use App\Models\Refugee;
use App\Services\AuditLogService;
use Illuminate\View\View;

class RefugeeCardController extends Controller
{
    /**
     * Printable identification badge, carrying a scannable Code 128 of the badge code
     * so checkpoints can log a movement without typing the refugee's details.
     */
    public function __invoke(Refugee $refugee, AuditLogService $auditLog): View
    {
        $refugee->load(['currentCamp', 'currentShelter', 'household']);

        $auditLog->log('print_card', 'refugees', $refugee, 'طباعة بطاقة تعريف للاجئ', 'high');

        return view('refugees.card', ['refugee' => $refugee]);
    }
}
