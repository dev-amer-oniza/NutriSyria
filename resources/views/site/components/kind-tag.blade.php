{{-- «مطعم» or «براند» (App\Enums\BrandKind), the same tag everywhere. Param $kind (BrandKind). --}}
<span class="ns-rest__tag ns-kind ns-kind--{{ $kind->value }}">{{ $kind->getLabel() }}</span>
