<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * 材料編集フォームの入力チェックを行うクラス（フォームリクエスト）。
 */
class UpdateMaterialRequest extends FormRequest
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
     * 入力チェックのルール（required: 必須 / string: 文字列 / max: 最大文字数 / unique: 重複不可）。
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 材料名（255文字以内。自分自身のレコードは重複判定から除外する）
            'name' => 'required|string|max:255|unique:materials,name,' . $this->route('material')->id,
            // 単位（例: kg, g。255文字以内）
            'unit' => 'required|string|max:255',
        ];
    }

    /**
     * ルールに違反したときに画面へ出すエラーメッセージ（「項目名.ルール名」ごとに指定）。
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => '材料名は必須です',
            'name.unique' => 'この材料名は既に登録されています',
            'name.max' => '材料名は255文字以内で入力してください',
            'unit.required' => '単位は必須です',
            'unit.max' => '単位は255文字以内で入力してください',
        ];
    }
}
