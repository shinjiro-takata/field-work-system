@extends('layouts.app')

@section('content')
{{-- 新規ユーザー登録フォーム（送信先 /register。@csrf = 送信の正当性を確認する合言葉） --}}
<form method="POST" action="/register">
    @csrf

    {{-- 名前 --}}
    <div>
        <label for="name">名前</label>
        <input id="name" type="text" name="name" required value="{{ old('name') }}" autofocus>
        @error('name')
            <span>{{ $message }}</span>
        @enderror
    </div>
    {{-- メールアドレス（ログインIDとして使う） --}}
    <div>
        <label for="email">メールアドレス</label>
        <input id="email" type="email" name="email" required value="{{ old('email') }}">
        @error('email')
            <span>{{ $message }}</span>
        @enderror
    </div>
    {{-- パスワードと、入力ミス防止の確認用欄（同じ内容を2回入力させる） --}}
    <div>
        <label for="password">パスワード</label>
        <input id="password" type="password" name="password" required>
        @error('password')
            <span>{{ $message }}</span>
        @enderror
    </div>
    <div>
        <label for="password_confirmation">パスワード確認</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>
        @error('password_confirmation')
            <span>{{ $message }}</span>
        @enderror
    </div>
    <button type="submit">
        登録する
    </button>
</form>
@endsection