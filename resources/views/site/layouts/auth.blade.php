{{--
    Account pages: the form on one side, the platform's promise on the other.
    Sections: title (browser tab), heading, lead, form.
--}}
@extends('site.layouts.app')

@section('content')
    <section class="ns-auth">
        <div class="ns-auth__main">
            <div class="ns-auth__box">
                <h1 class="t-title ns-auth__heading">@yield('heading')</h1>
                <p class="t-body ns-muted ns-auth__lead">@yield('lead')</p>

                @include('site.partials.flash')

                @yield('form')
            </div>
        </div>

        @include('site.partials.auth-aside')
    </section>
@endsection
