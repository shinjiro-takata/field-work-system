<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * アカウント（ユーザー）の一覧・作成・編集・削除を行う管理者向けコントローラ。
 */
class UserManagementController extends Controller
{
    /** アカウント一覧を新しい順に20件ずつ表示する。 */
    public function index()
    {
        $users = User::orderBy('id', 'desc')->paginate(20);
        return view('users.index', compact('users'));
    }

    /** アカウント作成画面を表示する。 */
    public function create()
    {
        return view('users.create');
    }

    /** アカウントを作成する。管理者チェックの有無で権限（role）も決める。 */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $isAdmin = (bool) ($validated['is_admin'] ?? false);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // パスワードはそのまま保存せず、ハッシュ化（元に戻せない文字列に変換）して保存する
            'password' => Hash::make($validated['password']),
            'is_admin' => $isAdmin,
            'role' => $isAdmin ? 'admin' : 'worker',
        ]);

        return redirect()->route('users.index')->with('status', 'アカウントを作成しました。');
    }

    /** アカウント編集画面を表示する。 */
    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    /** アカウントを更新する。パスワードは入力があったときだけ変更する。 */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $willBeAdmin = (bool) ($validated['is_admin'] ?? false);

        // 最後の1人の管理者を一般ユーザーに降格すると、管理画面に入れる人がいなくなるため禁止する
        if ($user->is_admin && !$willBeAdmin && $this->isLastAdmin($user)) {
            return redirect()->route('users.edit', $user)
                ->withInput()
                ->withErrors(['is_admin' => '最後の管理者は一般ユーザーに変更できません。']);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_admin = $willBeAdmin;
        $user->role = $user->is_admin ? 'admin' : 'worker';

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('status', 'アカウントを更新しました。');
    }

    /** アカウントを削除する。ログイン中の自分自身は削除できない。 */
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('status', '自分自身のアカウントは削除できません。');
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'アカウントを削除しました。');
    }

    /** 指定したユーザーが、システムに残っている唯一の管理者かどうかを返す。 */
    private function isLastAdmin(User $user): bool
    {
        return $user->is_admin && User::where('is_admin', true)->where('id', '!=', $user->id)->doesntExist();
    }
}
