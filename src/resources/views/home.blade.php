@extends('layouts.equipment')

@section('title', 'ホーム')

@section('content')
    {{-- 画面タイトル。 --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">FACILITY MANAGEMENT</p>
            <h1>点検メニュー</h1>
            <p class="page-description">行う点検を選択してください。</p>
        </div>
    </div>

    {{-- 点検種別ごとのカード。各カードから点検画面へ移動する。 --}}
    <div class="type-grid">
        @foreach ($types as $type)
            <a class="type-card" href="{{ route($type['route']) }}">
                <span class="type-card-name">{{ $type['name'] }}</span>
                <span class="type-card-description">{{ $type['description'] }}</span>
                <span class="type-card-status {{ $type['available'] ? '' : 'is-pending' }}">
                    {{ $type['available'] ? '点検を入力する' : '準備中' }}
                </span>
            </a>
        @endforeach
    </div>
@endsection
