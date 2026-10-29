@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/recipes.css') }}">
@endsection

@section('content')
{{-- ===== レシピ名（タイトル） ===== --}}
<h1>{{ $recipe->name }}</h1>

<div class="recipe-detail">
    {{-- 材料と基準量の一覧 --}}
    <div class="recipe-detail__card">
        <h2>材料一覧</h2>
        <div class="materials-list">
            @if($recipe->materials->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>材料名</th>
                        <th>数量</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipe->materials as $material)
                    <tr>
                        <td>{{ $material->name }}</td>
                        <td>{{ $material->pivot->quantity }}{{ $material->unit }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p>このレシピに材料は登録されていません。</p>
            @endif
        </div>
    </div>

    {{-- 管理者のみ: 編集・削除 --}}
    @if(Auth::user()->is_admin)
    <div class="recipe-detail__actions">
        <a href="{{ route('recipes.edit', $recipe) }}" class="btn">✏️ 編集</a>
        <form action="{{ route('recipes.destroy', $recipe) }}" method="POST" class="delete-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" onclick="return confirm('このレシピを削除しますか？');">🗑️ 削除</button>
        </form>
    </div>
    @endif
</div>

<div class="action-buttons">
    <a href="{{ route('recipes.index') }}" class="btn btn-secondary">← レシピ一覧に戻る</a>
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

@endsection