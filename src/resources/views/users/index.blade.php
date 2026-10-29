@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル・新規作成ボタン ===== --}}
<h1>アカウント管理</h1>

<div class="action-buttons">
    <a href="{{ route('users.create') }}" class="btn">🆕 新規アカウント作成</a>
</div>

{{-- ===== アカウント一覧の表（列 = ID・名前・メール・権限・操作） ===== --}}
<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>名前</th>
                <th>メール</th>
                <th>権限</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                {{-- 権限の表示（管理者 / 一般） --}}
                <td>
                    @if($user->is_admin)
                    <span class="badge badge-admin">管理者</span>
                    @else
                    <span class="badge badge-general">一般</span>
                    @endif
                </td>
                {{-- 編集リンクと削除ボタン（削除は確認ダイアログを出す） --}}
                <td>
                    <div class="action-links">
                        <a href="{{ route('users.edit', $user) }}" class="action-link">✏️ 編集</a>
                        <form action="{{ route('users.destroy', $user) }}" method="POST" class="delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-link action-link-danger" onclick="return confirm('{{ $user->name }} のアカウントを削除しますか？');"> 🗑️ 削除</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">アカウントがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ページ送り（1ページ20件） --}}
<div class="pagination-wrapper">
    {{ $users->links() }}
</div>

<div class="action-buttons">
    <a href="{{ route('portal') }}" class="btn btn-secondary">← ホームに戻る</a>
</div>

@endsection