@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>新規アカウント作成</h1>

<div class="form-container">
    <form action="{{ route('users.store') }}" method="POST" class="user-form">
        {{-- @csrf = フォーム送信の正当性を確認する合言葉（トークン）を埋め込む --}}
        @csrf

        {{-- 名前（必須） --}}
        <div class="form-group">
            <label for="name">名前 <span class="required">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required class="form-input">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>

        {{-- メールアドレス（必須。ログインIDとして使う。他のユーザーと重複不可） --}}
        <div class="form-group">
            <label for="email">メールアドレス <span class="required">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="form-input">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>

        {{-- パスワード（8文字以上）と、入力ミス防止の確認用欄（password_confirmation と一致が必要） --}}
        <div class="form-group">
            <label for="password">パスワード <span class="required">*</span></label>
            <input id="password" type="password" name="password" required class="form-input">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">パスワード（確認） <span class="required">*</span></label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="form-input">
        </div>

        {{-- 管理者権限のチェックボックス（チェックすると管理者として作成） --}}
        <div class="form-group checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}>
                <span class="checkbox-text">管理者権限として作成</span>
            </label>
        </div>

        {{-- 作成・キャンセルボタン --}}
        <div class="form-actions">
            <button type="submit" class="btn">✅ 作成</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<div class="action-buttons">
    <a href="{{ route('portal') }}" class="btn btn-secondary">← ホームに戻る</a>
</div>

@endsection