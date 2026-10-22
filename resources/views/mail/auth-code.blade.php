@extends('mail.layout')

@section('intro')
    <p style="margin:0;color:#56615a;">{{ $intro }}</p>
@endsection

@section('body')
    <tr><td align="center" style="padding:20px 32px;">
        <div style="display:inline-block;padding:14px 28px;background:#dfe6dc;border-radius:12px;font-family:Sora,'Courier New',monospace;font-size:34px;font-weight:600;letter-spacing:8px;color:#0f3d32;direction:ltr;">{{ $code }}</div>
    </td></tr>
@endsection

@section('footnote')
    <p style="margin:0;">الرمز صالح لمدة {{ $minutes }} دقائق. إن لم تطلبه، تجاهل هذه الرسالة ولن يتغيّر شيء في حسابك.</p>
@endsection
