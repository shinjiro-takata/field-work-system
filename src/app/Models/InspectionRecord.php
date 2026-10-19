<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $inspection_type
 * @property \Illuminate\Support\Carbon $inspection_date
 * @property string $inspector
 * @property string|null $note
 * @property array<string, mixed> $results
 * @property string|null $last_edited_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InspectionEditLog> $editLogs
 */
class InspectionRecord extends Model
{
    // コントローラーで受け付ける点検項目だけを一括代入の対象にする。
    protected $fillable = [
        'inspection_type',
        'inspection_date',
        'inspector',
        'note',
        'results',
        'last_edited_by',
    ];

    // デフォルトの点検種別は日常点検とする。
    protected $attributes = [
        'inspection_type' => 'daily',
    ];

    // 点検日とJSON形式の点検結果を、PHPで扱いやすい値に変換する。
    protected $casts = [
        'inspection_date' => 'date:Y-m-d',
        'results' => 'array',
    ];

    /**
     * 指定された点検種別の記録に絞り込む。
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('inspection_type', $type);
    }

    /**
     * この点検記録に紐づく編集履歴を取得する。
     */
    public function editLogs(): HasMany
    {
        return $this->hasMany(InspectionEditLog::class)->latest();
    }
}
