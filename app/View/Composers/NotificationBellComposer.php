<?php

namespace App\View\Composers;

use App\Services\Consultations\ConsultationService;
use App\Services\Nutrition\LiverUpdate;
use App\Services\Nutrition\RecoveryReminder;
use App\Services\Site\SiteNotifications;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The bell's first contents for a signed-in person (the header then polls for more), after any recovery reminder now due. */
class NotificationBellComposer
{
    public function __construct(
        private readonly Request $request,
        private readonly SiteNotifications $notifications,
        private readonly RecoveryReminder $recovery,
        private readonly LiverUpdate $liver,
        private readonly ConsultationService $consultations,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        // Once per request: a reminder due now shows in this very bell.
        if ($user && ! $this->request->attributes->get('recovery_checked')) {
            $this->request->attributes->set('recovery_checked', true);
            $this->recovery->check($user);
            $this->liver->notifyOnce($user, $this->consultations->isManaged($user));
        }

        $view->with('bell', $user ? $this->notifications->bell($user) : null);
    }
}
