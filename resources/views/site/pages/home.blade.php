@extends('site.layouts.app')

@section('content')
    @include('site.sections.home.hero')
    @include('site.sections.home.features')
    @include('site.sections.home.dishes')
    @include('site.sections.home.steps')
    @include('site.sections.home.trust')
    @include('site.sections.home.goals')
@endsection
