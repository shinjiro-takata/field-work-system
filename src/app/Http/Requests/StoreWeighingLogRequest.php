<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 計量ログ登録フォームの入力チェックを行うクラス。
 * フォームリクエスト = コントローラに届く前に入力値を検査する専用クラス。
 */
class StoreWeighingLogRequest extends FormRequest
{
    /**
     * この操作を実行してよいか。ログイン済みユーザーなら誰でも登録できるため常に true。
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * バリデーション（入力チェック）の前に入力値を整える。
     */
    protected function prepareForValidation()
    {
        // 未入力の追加行は無視する
        $extras = array_filter((array) $this->input('extra_materials', []), function ($row) {
            return ($row['material_id'] ?? '') !== '' || ($row['quantity'] ?? '') !== '';
        });
        $this->merge(['extra_materials' => array_values($extras)]);
    }

    /**
     * 入力チェックのルール。
     *  - required: 必須 / exists: 指定テーブルに存在するID / date_format: 日時の書式
     *  - numeric: 数値 / min, max: 最小値・最大値 / distinct: 配列内で重複しない
     *  - nullable: 空でもよい / array: 配列
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 計量するレシピ（recipes テーブルに存在するもの）
            'recipe_id' => 'required|exists:recipes,id',
            // 計量日時（例: 2026-10-29T14:30）
            'weighed_at' => 'required|date_format:Y-m-d\TH:i',
            // 備考。レシピ外の材料を使ったときだけ必須（理由を書かせるため）
            'notes' => [Rule::requiredIf(!empty($this->input('extra_materials'))), 'nullable', 'string'],
            // レシピ内の各材料の実測量（0以上の数値）
            'materials.*' => 'required|numeric|min:0',
            // レシピ外の追加材料（材料IDの重複は不可、量は0〜999999.99）
            'extra_materials' => 'nullable|array',
            'extra_materials.*.material_id' => 'required|exists:materials,id|distinct',
            'extra_materials.*.quantity' => 'required|numeric|min:0|max:999999.99',
        ];
    }

    /**
     * ルールに違反したときに画面へ出すエラーメッセージ（指定したものだけ上書き）。
     *
     * @return array
     */
    public function messages()
    {
        return [
            'notes.required' => 'レシピ外の材料を使用した場合は、備考に理由を入力してください。',
        ];
    }
}
