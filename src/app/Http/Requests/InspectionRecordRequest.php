<?php

namespace App\Http\Requests;

use App\Support\EquipmentChecklist;
use App\Support\InspectionResultMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 日常点検フォームの入力値を検証し、保存処理に渡すデータを整える。
 */
class InspectionRecordRequest extends FormRequest
{
    /**
     * 点検フォームは公開画面から利用できる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション（入力値が正しいかの確認）ルールを、チェックリストに沿って作る。
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In|\Illuminate\Validation\Rules\Unique>>
     */
    public function rules(): array
    {
        $itemIds = array_keys(EquipmentChecklist::items());
        $rules = [
            'note' => ['nullable', 'string', 'max:1000'],
            'results' => ['nullable', 'array:' . implode(',', $itemIds)],
        ];

        foreach (EquipmentChecklist::items() as $id => $item) {
            $allowedResultKeys = ($item['type'] ?? null) === 'pressure'
                ? 'pressure'
                : 'status';
            $rules['results.' . $id] = ['nullable', 'array:' . $allowedResultKeys];

            if (($item['type'] ?? null) === 'pressure') {
                $rules['results.' . $id . '.pressure'] = ['nullable', 'numeric'];
            } else {
                $rules['results.' . $id . '.status'] = ['nullable', Rule::in(['ok', 'ng'])];
            }
        }

        if ($this->isMethod('post')) {
            $rules['inspection_date'] = [
                'required',
                'date_format:Y-m-d',
                Rule::unique('inspection_records', 'inspection_date')->where('inspection_type', 'daily'),
            ];
        }

        return $rules;
    }

    /**
     * 点検日重複時に、次の操作が分かるメッセージを表示する。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inspection_date.unique' => 'この日付の点検記録は登録済みです。履歴から記録を確認し、必要に応じて編集してください。',
        ];
    }

    /**
     * 検証済みフォーム値を保存用に整え、マッパー（入力形式を保存形式へ変換する処理）に渡す。
     *
     * @return array{
     *     inspection_date?: string,
     *     inspector: string,
     *     editor_name?: string,
     *     note?: string|null,
     *     results: array<string, array{status: 'ok'|'ng'}|array{pressure: numeric-string}>
     * }
     */
    public function inspectionData(): array
    {
        $data = $this->validated();
        // 担当者・編集者はフォーム入力ではなくログインユーザー名で記録する。
        if ($this->isMethod('post')) {
            $data['inspector'] = $this->user()->name;
        } else {
            $data['editor_name'] = $this->user()->name;
        }
        $data['results'] = app(InspectionResultMapper::class)->map($data['results'] ?? []);

        if ($data['results'] === []) {
            throw ValidationException::withMessages([
                'results' => '少なくとも1項目の点検結果または圧力値を入力してください。',
            ]);
        }

        return $data;
    }
}
