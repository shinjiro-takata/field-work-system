@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
<h1>新規アカウント作成</h1>

<div class="form-container">
    <form action="{{ route('users.store') }}" method="POST" class="user-form">
        @csrf

        <div class="form-group">
            <label for="name">名前 <span class="required">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required class="form-input">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="email">メールアドレス <span class="required">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="form-input">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="password">パスワード <span class="required">*</span></label>
            <input id="password" type="password" name="password" required class="form-input">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">パスワード（確認） <span class="required">*</span></label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="form-input">
        </div>

        <div class="form-group checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}>
                <span class="checkbox-text">管理者権限として作成</span>
            </label>
        </div>

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