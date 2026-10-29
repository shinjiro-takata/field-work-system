<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * ログインするユーザー（作業者・管理者）を表すモデル。テーブル: users
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * まとめて保存（create / update）してよい項目。
     * is_admin は管理者かどうか、role は 'admin'（管理者）または 'worker'（作業者）。
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
    ];

    /**
     * 配列・JSON に変換するときに隠す項目（パスワード等を画面やAPIに出さないため）。
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * DBから読み出すときに自動で型変換する項目（is_admin は 0/1 ではなく true/false になる）。
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    /** このユーザーが登録した計量ログ（1対多）。 */
    public function productionLogs()
    {
        return $this->hasMany(ProductionLog::class);
    }

    /** このユーザーが作成したレシピ（1対多）。 */
    public function createdRecipes()
    {
        return $this->hasMany(Recipe::class, 'created_by');
    }

    /** このユーザーが最後に更新したレシピ（1対多）。 */
    public function updatedRecipes()
    {
        return $this->hasMany(Recipe::class, 'updated_by');
    }
}
