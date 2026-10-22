<?php

namespace App\Http\Controllers\Site\Account;

use App\Http\Controllers\Controller;
use App\Services\Site\SiteNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The site bell: its contents (polled by the header), opening a notice, and "mark all read". */
class NotificationController extends Controller
{
    public function index(Request $request, SiteNotifications $notifications): JsonResponse
    {
        return response()->json($notifications->bell($request->user()));
    }

    public function open(Request $request, string $notification, SiteNotifications $notifications): RedirectResponse
    {
        return redirect()->to($notifications->open($request->user(), $notification));
    }

    public function readAll(Request $request, SiteNotifications $notifications): JsonResponse|RedirectResponse
    {
        $notifications->markAllRead($request->user());

        return $request->expectsJson() ? response()->json(['unread' => 0]) : back();
    }
}
