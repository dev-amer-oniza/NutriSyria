{{--
    A captioned event photo: WebP with a JPEG fallback, a small and a full size, and a link to
    the full photo. Params: $src (path under public/ without extension, e.g. images/about/reroot),
    $small (width of the "-{small}" file), $full (full width), $height (full height), $alt,
    $title, $caption, $sizes (the sizes attribute), $class (optional).
--}}
<figure @class(['ns-photo', $class ?? null])>
    <a class="ns-photo__frame" href="{{ asset($src.'.jpg') }}" target="_blank" rel="noopener">
        <picture>
            <source type="image/webp" srcset="{{ asset($src.'-'.$small.'.webp') }} {{ $small }}w, {{ asset($src.'.webp') }} {{ $full }}w" sizes="{{ $sizes }}">
            <img src="{{ asset($src.'-'.$small.'.jpg') }}" srcset="{{ asset($src.'-'.$small.'.jpg') }} {{ $small }}w, {{ asset($src.'.jpg') }} {{ $full }}w" sizes="{{ $sizes }}"
                 width="{{ $full }}" height="{{ $height }}" alt="{{ $alt }}" loading="lazy" decoding="async">
        </picture>
        <span class="ns-photo__zoom">@include('site.partials.icon', ['name' => 'search', 'size' => 'sm'])<span>الصورة كاملة</span></span>
    </a>
    <figcaption>
        <strong class="ns-photo__title">{{ $title }}</strong>
        <span>{{ $caption }}</span>
    </figcaption>
</figure>
