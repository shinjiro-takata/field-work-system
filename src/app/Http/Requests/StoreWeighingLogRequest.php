<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeighingLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    protected function prepareForValidation()
    {
        // 未入力の追加行は無視する
        $extras = array_filter((array) $this->input('extra_materials', []), function ($row) {
            return ($row['material_id'] ?? '') !== '' || ($row['quantity'] ?? '') !== '';
        });
        $this->merge(['extra_materials' => array_values($extras)]);
    }

    public function rules()
    {
        return [
            'recipe_id' => 'required|exists:recipes,id',
            'weighed_at' => 'required|date_format:Y-m-d\TH:i',
            'notes' => [Rule::requiredIf(!empty($this->input('extra_materials'))), 'nullable', 'string'],
            'materials.*' => 'required|numeric|min:0',
            'extra_materials' => 'nullable|array',
            'extra_materials.*.material_id' => 'required|exists:materials,id|distinct',
            'extra_materials.*.quantity' => 'required|numeric|min:0|max:999999.99',
        ];
    }

    public function messages()
    {
        return [
            'notes.required' => 'レシピ外の材料を使用した場合は、備考に理由を入力してください。',
        ];
    }
}
