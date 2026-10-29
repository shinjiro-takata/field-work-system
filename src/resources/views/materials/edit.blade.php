@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/materials.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>材料を編集</h1>

<div class="form-container">
    <form action="{{ route('materials.update', $material->id) }}" method="POST">
        {{-- @csrf = フォーム送信の正当性を確認する合言葉（トークン）を埋め込む。@method('PUT') = 更新リクエストとして送る --}}
        @csrf
        @method('PUT')

        {{-- 材料名（必須。old() = 入力エラーで戻ったときは直前の入力値、なければ現在の値） --}}
        <div class="form-group">
            <label for="name">材料名 <span class="required">*</span></label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $material->name) }}"
                required
                class="form-input">
            @error('name')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 単位（必須。レジン使用量の履歴がある材料は変更不可で、保存時にエラーになる） --}}
        <div class="form-group">
            <label for="unit">単位 <span class="required">*</span></label>
            <input
                type="text"
                id="unit"
                name="unit"
                value="{{ old('unit', $material->unit) }}"
                required
                class="form-input">
            @error('unit')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 更新・キャンセルボタン --}}
        <div class="form-actions">
            <button type="submit" class="btn">✏️ 更新</button>
            <a href="{{ route('materials.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<div style="margin-top: 30px;">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

@endsection