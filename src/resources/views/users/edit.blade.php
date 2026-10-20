@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
<h1>アカウント編集</h1>

<div class="form-container">
    <form action="{{ route('users.update', $user) }}" method="POST" class="user-form">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name">名前 <span class="required">*</span></label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required class="form-input">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="email">メールアドレス <span class="required">*</span></label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="form-input">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-section">
            <h3>パスワード変更</h3>
            <p class="form-hint">パスワードを変更する場合のみ入力してください。</p>

            <div class="form-group">
                <label for="password">新しいパスワード</label>
                <input id="password" type="password" name="password" class="form-input">
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">新しいパスワード（確認）</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-input">
            </div>
        </div>

        <div class="form-group checkbox-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_admin" value="1" {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                <span class="checkbox-text">管理者権限</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">✏️ 更新</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<div class="action-buttons">
    <a href="{{ route('portal') }}" class="btn btn-secondary">← ホームに戻る</a>
</div>

@endsection