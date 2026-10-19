@extends('layouts.app')

@section('title', '現場業務システム')
@section('brand', '現場業務システム')

@section('content')
<form method="POST" action="/login">
    @csrf

    <div>
        <label for="email">メールアドレス</label>
        <input id="email" type="email" name="email" required value="{{ old('email') }}" autofocus>
        @error('email')
            <span>{{ $message }}</span>
        @enderror
    </div>
    <div>
        <label for="password">パスワード</label>
        <input id="password" type="password" name="password" required>
        @error('password')
            <span>{{ $message }}</span>
        @enderror
    </div>
    <button type="submit">
        ログイン
    </button>
</form>
@endsection