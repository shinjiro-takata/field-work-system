<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * ユーザーをログアウトさせる。
     */
    public function destroy(Request $request)
    {
        // ログイン状態を解除する
        Auth::guard('web')->logout();

        // セッション（ログイン中の記憶）を破棄する
        $request->session()->invalidate();

        // CSRFトークン（フォーム送信の正当性を確認する合言葉）を作り直す
        $request->session()->regenerateToken();

        return redirect('/login')->with('message', 'ログアウトしました。');
    }
}
