<?php

namespace App\Http\Requests;

use App\Support\InspectionResultMapper;
use App\Support\InspectionTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 週次・月次・定期点検の入力値を種別ごとに検証し、保存用データを整える。
 */
class ScheduledInspectionRecordRequest extends FormRequest
{
    /**
     * 点検フォームは公開画面から利用できる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * URLのルートで指定された点検種別のキーを返す。
     */
    private function inspectionType(): string
    {
        return InspectionTypes::find((string) $this->route('type'))['key'];
    }

    /**
     * バリデーション（入力値が正しいかの確認）ルールを、点検種別のチェックリストに沿って作る。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->inspectionType();
        $items = InspectionTypes::itemsFor($type);
        $rules = [
            'note' => ['nullable', 'string', 'max:1000'],
            'results' => ['nullable', 'array:' . implode(',', array_keys($items))],
        ];

        foreach ($items as $id => $item) {
            $itemType = $item['type'] ?? null;

            if ($itemType === 'oil') {
                $rules['results.' . $id] = ['nullable', 'array:action,value'];
                $rules['results.' . $id . '.value'] = ['nullable', 'numeric'];
                $rules['results.' . $id . '.action'] = count($item['actions']) > 1
                    ? ['nullable', 'required_with:results.' . $id . '.value', Rule::in($item['actions'])]
                    : ['nullable', Rule::in($item['actions'])];
            } elseif ($itemType === 'numeric') {
                $rules['results.' . $id] = ['nullable', 'array:value'];
                $rules['results.' . $id . '.value'] = ['nullable', 'numeric'];
            } else {
                $rules['results.' . $id] = ['nullable', 'array:status'];
                $rules['results.' . $id . '.status'] = ['nullable', Rule::in(['ok', 'ng'])];
            }
        }

        if ($this->isMethod('post')) {
            $rules['inspection_date'] = [
                'required',
                'date_format:Y-m-d',
                Rule::unique('inspection_records', 'inspection_date')->where('inspection_type', $type),
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
        $name = InspectionTypes::find($this->inspectionType())['name'];

        return [
            'results.*.action.required_with' => '数値を入力した項目は、補充か交換かを選択してください。',
            'inspection_date.unique' => "この日付の{$name}記録は登録済みです。履歴から記録を確認し、必要に応じて編集してください。",
        ];
    }

    /**
     * 検証済みフォーム値を保存用に整え、マッパー（入力形式を保存形式へ変換する処理）に渡す。
     *
     * @return array<string, mixed>
     */
    public function inspectionData(): array
    {
        $type = $this->inspectionType();
        $data = $this->validated();
        $data['inspection_type'] = $type;
        // 担当者・編集者はフォーム入力ではなくログインユーザー名で記録する。
        if ($this->isMethod('post')) {
            $data['inspector'] = $this->user()->name;
        } else {
            $data['editor_name'] = $this->user()->name;
        }
        $data['results'] = app(InspectionResultMapper::class)->map(
            $data['results'] ?? [],
            InspectionTypes::itemsFor($type)
        );

        if ($data['results'] === []) {
            throw ValidationException::withMessages([
                'results' => '少なくとも1つ以上の項目について、点検結果または数値を入力してください。',
            ]);
        }

        return $data;
    }
}
