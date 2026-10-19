<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $inspection_record_id
 * @property string $editor_name
 * @property array<string, mixed> $changes
 * @property \Illuminate\Support\Carbon $created_at
 */
class InspectionEditLog extends Model
{
    // 編集履歴はupdated_atではなく、明示的に記録した編集日時を使用する。
    public $timestamps = false;

    // 点検記録・編集者・変更内容・編集日時だけを一括代入可能にする。
    protected $fillable = [
        'inspection_record_id',
        'editor_name',
        'changes',
        'created_at',
    ];

    // 編集履歴を読み込む際、変更内容と日時を対応するPHPの型に変換する。
    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];
}
