<?php

namespace App\Http\Controllers\Site\Account;

use App\Http\Controllers\Controller;
use App\Services\Site\SiteNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The site bell: its contents (polled by the header), opening a notice, "mark all read", deleting one, and "clear all". */
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

    public function destroy(Request $request, string $notification, SiteNotifications $notifications): JsonResponse|RedirectResponse
    {
        $notifications->delete($request->user(), $notification);

        return $request->expectsJson() ? response()->json($notifications->bell($request->user())) : back();
    }

    public function clear(Request $request, SiteNotifications $notifications): JsonResponse|RedirectResponse
    {
        $notifications->clear($request->user());

        return $request->expectsJson() ? response()->json(['unread' => 0, 'items' => []]) : back();
    }
}
