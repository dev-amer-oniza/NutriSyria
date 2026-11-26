{{-- The notification bell (SiteNotifications). Param $bell: {unread, items}. Alpine "bell" polls every minute while the tab is visible. --}}
<div class="ns-bell" x-data="bell(@js($bell), @js(['feed' => route('site.notifications'), 'read' => route('site.notifications.read'), 'clear' => route('site.notifications.clear')]))"
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
            @csrf
            <div class="ns-bell__tools" x-show="items.length && ! confirming">
                <button class="ns-link ns-bell__all" type="button" @click="readAll()" x-show="unread">تعليم الكل كمقروء</button>
                <button class="ns-link ns-bell__all ns-bell__clear" type="button" @click="confirming = true">مسح الكل</button>
            </div>
        </div>
        <div class="ns-bell__confirm" x-show="confirming" x-cloak role="alertdialog" aria-label="مسح كل الإشعارات">
            <p>نمسح كل الإشعارات؟ ما بترجع.</p>
            <div>
                <button class="ns-btn ns-btn--primary ns-btn--sm ns-bell__yes" type="button" @click="clearAll()">امسح الكل</button>
                <button class="ns-btn ns-btn--secondary ns-btn--sm" type="button" @click="confirming = false">لا</button>
            </div>
        </div>
        <p class="ns-bell__empty" x-show="! items.length">لا إشعارات بعد.</p>
        <ul class="ns-bell__list">
            <template x-for="item in items" :key="item.id">
                <li class="ns-bell__row">
                    <a class="ns-bell__item" :class="{ 'is-unread': ! item.read }" :href="item.open">
                        <span class="ns-bell__dot" :class="`ns-bell__dot--${item.kind}`"></span>
                        <span>
                            <span class="ns-bell__item-title" x-text="item.title"></span>
                            <span class="ns-bell__item-body" x-show="item.body" x-text="item.body"></span>
                            <span class="ns-bell__time" x-text="item.time"></span>
                        </span>
                    </a>
                    <button class="ns-bell__remove" type="button" @click="remove(item)" :aria-label="`احذف الإشعار: ${item.title}`" title="احذف">
                        @include('site.partials.icon', ['name' => 'close'])
                    </button>
                </li>
            </template>
        </ul>
    </div>
</div>
