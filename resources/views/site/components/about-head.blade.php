{{-- A numbered section head on "About us". Params: $no ('01'), $title, $en (the English title), $id. --}}
<header class="ns-about__head">
    <span class="ns-about__no ns-num">{{ $no }}</span>
    <h2 class="ns-about__title" id="{{ $id }}">{{ $title }}</h2>
    <p class="ns-about__en ns-latin" lang="en">{{ $en }}</p>
</header>
