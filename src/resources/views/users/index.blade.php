@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
<h1>アカウント管理</h1>

<div class="action-buttons">
    <a href="{{ route('users.create') }}" class="btn">🆕 新規アカウント作成</a>
</div>

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
                <td>
                    @if($user->is_admin)
                    <span class="badge badge-admin">管理者</span>
                    @else
                    <span class="badge badge-general">一般</span>
                    @endif
                </td>
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

<div class="pagination-wrapper">
    {{ $users->links() }}
</div>

<div class="action-buttons">
    <a href="{{ route('portal') }}" class="btn btn-secondary">← ホームに戻る</a>
</div>

@endsection