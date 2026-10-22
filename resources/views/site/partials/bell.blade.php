{{-- The notification bell (SiteNotifications). Param $bell: {unread, items}. Alpine "bell" polls every minute while the tab is visible. --}}
<div class="ns-bell" x-data="bell(@js($bell), @js(['feed' => route('site.notifications'), 'read' => route('site.notifications.read')]))"
     @keydown.escape.window="open = false" @click.outside="open = false">
    <button class="ns-btn ns-btn--ghost ns-btn--icon ns-bell__button" type="button" aria-haspopup="true"
            :aria-expanded="open" aria-expanded="false" @click="toggle()"
            :aria-label="unread ? `الإشعارات، ${unread} جديدة` : 'الإشعارات'" aria-label="الإشعارات">
        @include('site.partials.icon', ['name' => 'bell'])
        <span class="ns-bell__count ns-num" x-show="unread" x-text="unread > 9 ? '9+' : unread" x-cloak>{{ $bell['unread'] ?: '' }}</span>
    </button>

    <div class="ns-bell__panel" x-show="open" x-cloak x-transition.opacity role="region" aria-label="الإشعارات">
        <div class="ns-bell__head">
            <p class="ns-bell__title">الإشعارات</p>
            <form method="post" action="{{ route('site.notifications.read') }}" @submit.prevent="readAll()" x-show="unread">
                @csrf
                <button class="ns-link ns-bell__all" type="submit">تعليم الكل كمقروء</button>
            </form>
        </div>
        <p class="ns-bell__empty" x-show="! items.length">لا إشعارات بعد.</p>
        <ul class="ns-bell__list">
            <template x-for="item in items" :key="item.id">
                <li>
                    <a class="ns-bell__item" :class="{ 'is-unread': ! item.read }" :href="item.open">
                        <span class="ns-bell__dot" :class="`ns-bell__dot--${item.kind}`"></span>
                        <span>
                            <span class="ns-bell__item-title" x-text="item.title"></span>
                            <span class="ns-bell__item-body" x-show="item.body" x-text="item.body"></span>
                            <span class="ns-bell__time" x-text="item.time"></span>
                        </span>
                    </a>
                </li>
            </template>
        </ul>
    </div>
</div>
