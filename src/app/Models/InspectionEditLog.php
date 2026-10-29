<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 点検記録を編集した人と、編集前後の値を保存するモデル。
 *
 * @property int $id
 * @property int $inspection_record_id
 * @property string $editor_name
 * @property array<string, mixed> $changes
 * @property \Illuminate\Support\Carbon $created_at
 */
class InspectionEditLog extends Model
{
    // 作成日時は記録するが、更新日時は使わない履歴専用のデータ。
    public $timestamps = false;

    // 点検記録・編集者・変更内容・編集日時だけをまとめて保存できるようにする。
    protected $fillable = [
        'inspection_record_id',
        'editor_name',
        'changes',
        'created_at',
    ];

    // 変更内容を配列、作成日時を日時型としてPHPで扱えるようにする。
    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];
}
