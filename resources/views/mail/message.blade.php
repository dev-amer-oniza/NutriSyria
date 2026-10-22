@extends('mail.layout')

@section('intro')
    @foreach ($lines as $line)
        <p style="margin:0 0 8px;color:#56615a;">{{ $line }}</p>
    @endforeach
@endsection

@section('body')
    @if ($actionUrl)
        <tr><td align="center" style="padding:16px 32px 24px;">
            <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 28px;background:#0f3d32;border-radius:999px;color:#fbf9f5;font-size:16px;font-weight:600;text-decoration:none;">{{ $actionLabel }}</a>
        </td></tr>
    @endif
@endsection

@section('footnote')
    @if ($footnote)
        <p style="margin:0;">{{ $footnote }}</p>
    @endif
@endsection
