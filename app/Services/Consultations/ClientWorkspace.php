<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Notifications\ConsultationNotification;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * "My clients": what a nutritionist may do for a person they are linked to (the owner's
 * decisions for part 3):
 *  - see the profile and health record (read only: the person owns their data);
 *  - choose the diet template and set any criterion, each with an optional note the person
 *    sees; what they set is locked for the person (PersonalCriteria);
 *  - end the link, with an optional reason emailed to the person.
 * Every step checks the link is accepted and theirs; anything else is "not found". The person
 * gets one email per editing session (NOTIFY_EVERY_MINUTES), not one per criterion.
 */
class ClientWorkspace
{
    public const NOTIFY_EVERY_MINUTES = 30;

    public function __construct(
        private readonly PersonalCriteria $criteria,
        private readonly ConsultationService $consultations,
    ) {}

    /** The links this nutritionist has now (accepted, theirs). */
    public function clients(User $nutritionist): Builder
    {
        return ConsultationRequest::query()
            ->where('nutritionist_id', $nutritionist->getKey())
            ->where('status', ConsultationStatus::Accepted);
    }

    public function chooseTemplate(User $nutritionist, ConsultationRequest $link, string $template, ?string $note = null): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->criteria->setTemplateByNutritionist($link->user, $template, $nutritionist, self::clean($note));
        $this->changed($link);
    }

    public function resetTemplate(User $nutritionist, ConsultationRequest $link): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->criteria->resetTemplateByNutritionist($link->user, $nutritionist);
        $this->changed($link);
    }

    public function setCriterion(User $nutritionist, ConsultationRequest $link, string $criterion, float $value, ?string $note = null): void
    {
        $this->ensureLinked($nutritionist, $link);

        [$min, $max] = PersonalCriteria::range($criterion);
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("[$criterion] must be between $min and $max.");
        }

        $this->criteria->setByNutritionist($link->user, $criterion, $value, $nutritionist, self::clean($note));
        $this->changed($link);
    }

    public function resetCriterion(User $nutritionist, ConsultationRequest $link, string $criterion): void
    {
        $this->ensureLinked($nutritionist, $link);
        PersonalCriteria::range($criterion);
        $this->criteria->resetByNutritionist($link->user, $criterion, $nutritionist);
        $this->changed($link);
    }

    public function end(User $nutritionist, ConsultationRequest $link, ?string $reason = null): void
    {
        $this->ensureLinked($nutritionist, $link);
        $this->consultations->end($nutritionist, $link, $reason);
    }

    private function ensureLinked(User $nutritionist, ConsultationRequest $link): void
    {
        abort_unless(
            $link->nutritionist_id === $nutritionist->getKey() && $link->refresh()->isAccepted(),
            404,
        );
    }

    /** One email per editing session: none if the person was told in the last half hour. */
    private function changed(ConsultationRequest $link): void
    {
        if ($link->client_notified_at?->gt(now()->subMinutes(self::NOTIFY_EVERY_MINUTES))) {
            return;
        }

        $link->forceFill(['client_notified_at' => now()])->save();
        $this->consultations->mail($link->user, ConsultationNotification::criteriaUpdated($link));
    }

    private static function clean(?string $note): ?string
    {
        return filled($note) ? trim($note) : null;
    }
}
