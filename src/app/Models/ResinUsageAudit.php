<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レジン使用記録の監査履歴（誰がいつ何を変えたか）を表すモデル。
 * action は updated（修正）/ deleted（削除）など、before_data・after_data は変更前後の内容。
 * 作成日時（created_at）が「いつ」に当たる。
 */
class ResinUsageAudit extends Model
{
    // 一括保存（create など）を許可するカラム
    protected $fillable = [
        'action',
        'usage_date',
        'changed_by',
        'changed_by_name',
        'before_data',
        'after_data',
    ];

    // 取得時に日付型・配列へ自動変換する（before_data / after_data は DB 上は JSON 文字列）
    protected $casts = [
        'usage_date' => 'date',
        'before_data' => 'array',
        'after_data' => 'array',
    ];
}
