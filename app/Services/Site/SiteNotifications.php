<?php

namespace App\Services\Site;

use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

/** The site bell: a person's own notices (format "site"), newest first. */
class SiteNotifications
{
    public const SHOWN = 10;

    /** @return array{unread: int, items: list<array{id: string, kind: string, title: string, body: ?string, time: string, read: bool, open: string}>} */
    public function bell(User $user): array
    {
        return [
            'unread' => $this->query($user)->whereNull('read_at')->count(),
            'items' => $this->query($user)->latest()->limit(self::SHOWN)->get()
                ->map(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    'kind' => $n->data['kind'] ?? 'info',
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? null,
                    'time' => $n->created_at->locale('ar')->diffForHumans(),
                    'read' => $n->read_at !== null,
                    'open' => route('site.notifications.open', $n->id),
                ])->all(),
        ];
    }

    /** Marks one notice read and gives where it points (someone else's id is "not found"). */
    public function open(User $user, string $id): string
    {
        $notification = $this->query($user)->findOrFail($id);
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');

        // Only paths inside the site; anything else goes home.
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('home', absolute: false);
    }

    public function markAllRead(User $user): void
    {
        $this->query($user)->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function query(User $user): MorphMany
    {
        return $user->notifications()->where('data->format', SiteNotification::FORMAT);
    }
}
