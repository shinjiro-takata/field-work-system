@extends('layouts.equipment')

@section('title', $type['name'])

@section('content')
    {{-- 点検項目が未登録の点検種別に対する準備中画面。 --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">FACILITY MANAGEMENT</p>
            <h1>{{ $type['name'] }}</h1>
            <p class="page-description">{{ $type['description'] }}</p>
        </div>
        <a class="button" href="{{ route('home') }}">ホームに戻る</a>
    </div>

    <div class="panel">
        <div class="empty-state">この点検は準備中です。点検項目の登録後にご利用いただけます。</div>
    </div>
@endsection
