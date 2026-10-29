<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 点検種別・点検日・担当者・結果を1件ずつ保存するモデル。
 *
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
    // 登録・更新時にまとめて保存してよい項目を明示し、意図しない値の保存を防ぐ。
    protected $fillable = [
        'inspection_type',
        'inspection_date',
        'inspector',
        'note',
        'results',
        'last_edited_by',
    ];

    // 種別を指定しない記録は日常点検として扱う。
    protected $attributes = [
        'inspection_type' => 'daily',
    ];

    // 日付とJSON形式の結果を、PHPの日時・配列として読み書きできるようにする。
    protected $casts = [
        'inspection_date' => 'date:Y-m-d',
        'results' => 'array',
    ];

    /**
     * クエリ（データベース検索）を指定された点検種別の記録に絞り込む。
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
