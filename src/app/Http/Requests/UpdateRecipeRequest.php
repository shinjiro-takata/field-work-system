<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * レシピ編集フォームの入力チェックを行うクラス（フォームリクエスト）。
 */
class UpdateRecipeRequest extends FormRequest
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
     *  - required: 必須 / nullable: 空でもよい / string: 文字列 / max: 最大文字数
     *  - unique: テーブル内で重複しない / array: 配列 / exists: 指定テーブルに存在するID
     *  - numeric: 数値 / min: 最小値
     *
     * @return array
     */
    public function rules()
    {
        return [
            // レシピ名（255文字以内。自分自身のレコードは重複判定から除外する）
            'name' => 'required|string|max:255|unique:recipes,name,' . $this->route('recipe')->id,
            // レシピに使う材料のID一覧（materials テーブルに存在するもの）
            'material_ids' => 'required|array',
            'material_ids.*' => 'required|exists:materials,id',
            // 材料ごとの基準量（[材料ID => 量]、0以上）
            'materials' => 'required|array',
            'materials.*' => 'required|numeric|min:0',
            // 画面で新しく追加する材料（任意。名前と単位が両方入力されたときだけ登録される）
            'new_material_name' => 'nullable|string|max:255',
            'new_material_unit' => 'nullable|string|max:255',
            'new_material_quantity' => 'nullable|numeric|min:0',
        ];
    }
}
