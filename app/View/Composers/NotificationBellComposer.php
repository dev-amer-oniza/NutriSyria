<?php

namespace App\View\Composers;

use App\Services\Site\SiteNotifications;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell's first contents for a signed-in person (the header then polls for more). */
class NotificationBellComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly SiteNotifications $notifications,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        $view->with('bell', $user ? $this->notifications->bell($user) : null);
    }
}
