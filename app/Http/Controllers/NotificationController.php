<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class NotificationController
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse|Redirector
    {
        $request->user()->notifications()->where('id', $notification->id)->update(['read_at' => now()]);

        return redirect()->route('notifications.index');
    }
}
