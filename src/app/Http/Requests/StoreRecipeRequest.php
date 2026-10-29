<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * レシピ新規登録フォームの入力チェックを行うクラス（フォームリクエスト）。
 */
class StoreRecipeRequest extends FormRequest
{
    /**
     * この操作を実行してよいか。管理者のみ許可する（'admin-only' は権限ルールの名前）。
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::user()->can('admin-only');
    }

    /**
     * 入力チェックのルール。
     *  - required: 必須 / string: 文字列 / max: 最大文字数 / unique: テーブル内で重複しない
     *  - array: 配列 / exists: 指定テーブルに存在するID / numeric: 数値 / min: 最小値
     *
     * @return array
     */
    public function rules()
    {
        return [
            // レシピ名（255文字以内、他のレシピと重複不可）
            'name' => 'required|string|max:255|unique:recipes,name',
            // レシピに使う材料のID一覧（materials テーブルに存在するもの）
            'material_ids' => 'required|array',
            'material_ids.*' => 'required|exists:materials,id',
            // 材料ごとの基準量（[材料ID => 量]、0以上）
            'materials' => 'required|array',
            'materials.*' => 'required|numeric|min:0',
        ];
    }
}
