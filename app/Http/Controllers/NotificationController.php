<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Models\ReminderLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi dalam aplikasi untuk pengguna yang login (FR-07, kanal In_App pada reminder_logs).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => ReminderLog::query()
                ->with(['rule.template', 'assignment.cycle.vendor', 'assignment.assessor'])
                ->where('recipient_id', $request->user()->id)
                ->where('channel', Channel::InApp->value)
                ->latest('scheduled_at')
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
