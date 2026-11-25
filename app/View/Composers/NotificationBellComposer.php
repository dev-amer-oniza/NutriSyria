<?php

namespace App\View\Composers;

use App\Services\Daily\Weighing;
use App\Services\Site\SiteNotifications;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell's first contents for a signed-in person (the header then polls for more). */
class NotificationBellComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly SiteNotifications $notifications,
        private readonly Weighing $weighing,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        // The weekly "صار وقت توزن", added before the bell is read (checked once a day).
        if ($user) {
            $this->weighing->remindIfDue($user);
        }
        $view->with('bell', $user ? $this->notifications->bell($user) : null);
    }
}
