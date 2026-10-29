<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * アカウント編集フォームの入力チェックを行うクラス（フォームリクエスト）。
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * この操作を実行してよいか。ログイン中の管理者のみ許可する。
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    /**
     * 入力チェックのルール。
     *  - required: 必須 / string: 文字列 / max: 最大文字数 / email: メール形式
     *  - unique: 重複不可 / min: 最小文字数 / confirmed: 「確認用」欄（password_confirmation）と一致
     *  - nullable: 空でもよい / boolean: 真偽値（チェックボックス）
     *
     * @return array
     */
    public function rules()
    {
        // 編集対象のユーザー（URLから取得）
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            // メールアドレス（自分自身は重複判定から除外する）
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // パスワードは任意。空欄なら変更しない
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    /**
     * ルールに違反したときに画面へ出すエラーメッセージ（「項目名.ルール名」ごとに指定）。
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => '名前は必須です。',
            'name.string' => '名前は文字列である必要があります。',
            'name.max' => '名前は255文字以下である必要があります。',
            'email.required' => 'メールアドレスは必須です。',
            'email.string' => 'メールアドレスは文字列である必要があります。',
            'email.email' => '有効なメールアドレスを入力してください。',
            'email.max' => 'メールアドレスは255文字以下である必要があります。',
            'email.unique' => 'このメールアドレスは既に使用されています。',
            'password.min' => 'パスワードは8文字以上である必要があります。',
            'password.confirmed' => 'パスワードの確認が一致しません。',
            'is_admin.boolean' => '管理者フラグは真偽値である必要があります。',
        ];
    }
}
