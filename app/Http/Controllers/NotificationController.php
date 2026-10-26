<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->paginate(20);

        // Collect the unread ids first so the page can still highlight them.
        $unread = $notifications->filter(fn ($n) => $n->read_at === null)->pluck('id')->all();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications.index', ['notifications' => $notifications, 'unread' => $unread, 'user' => $user]);
    }
}
