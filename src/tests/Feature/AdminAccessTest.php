<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 管理者専用画面（アカウント管理・材料管理・レシピ編集）の権限と、アカウント操作を確認するテストです。
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['name' => '管理者', 'is_admin' => true, 'role' => 'admin']);
    }

    private function worker(): User
    {
        return User::factory()->create(['name' => '作業者', 'is_admin' => false, 'role' => 'worker']);
    }

    // 一般の作業者は管理者専用の画面へアクセスできず、403（権限なし）になることを確認します。
    public function test_worker_cannot_open_admin_only_pages(): void
    {
        $this->actingAs($this->worker());

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('materials.index'))->assertForbidden();
        $this->get(route('recipes.create'))->assertForbidden();
        $this->get(route('resin-usage.settings.edit'))->assertForbidden();
    }

    // 作業者がアカウント作成・削除のリクエストを直接送っても、実行されないことを確認します。
    public function test_worker_cannot_create_or_delete_accounts(): void
    {
        $worker = $this->worker();
        $target = User::factory()->create();
        $this->actingAs($worker);

        $this->post(route('users.store'), [
            'name' => '新人',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
        $this->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    // 未ログインの利用者は、管理者専用画面でもログイン画面へ移動することを確認します。
    public function test_guest_is_redirected_to_login_for_admin_pages(): void
    {
        $this->get(route('users.index'))->assertRedirect('/login');
    }

    // 管理者がアカウントを作成すると、パスワードが暗号化され、管理者チェックに応じて role が設定されることを確認します。
    public function test_admin_can_create_accounts_with_role_and_hashed_password(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('users.store'), [
            'name' => '新しい管理者',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => 1,
        ])->assertRedirect(route('users.index'));

        $this->post(route('users.store'), [
            'name' => '新しい作業者',
            'email' => 'worker2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('users.index'));

        $newAdmin = User::where('email', 'admin2@example.com')->firstOrFail();
        $this->assertTrue((bool) $newAdmin->is_admin);
        $this->assertSame('admin', $newAdmin->role);
        $this->assertNotSame('password123', $newAdmin->password);

        $newWorker = User::where('email', 'worker2@example.com')->firstOrFail();
        $this->assertFalse((bool) $newWorker->is_admin);
        $this->assertSame('worker', $newWorker->role);
    }

    // メールアドレスの重複、パスワードの短さ・確認不一致が拒否されることを確認します。
    public function test_account_creation_validates_email_and_password(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create(['email' => 'dup@example.com']);

        $this->post(route('users.store'), [
            'name' => '重複',
            'email' => 'dup@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    // アカウント更新で、パスワード未入力なら元のパスワードが維持され、権限の切り替えが role に反映されることを確認します。
    public function test_account_update_keeps_password_when_blank_and_updates_role(): void
    {
        $this->actingAs($this->admin());
        $target = User::factory()->create(['is_admin' => false, 'role' => 'worker']);
        $oldPassword = $target->password;

        $this->put(route('users.update', $target), [
            'name' => '更新後の名前',
            'email' => $target->email,
            'is_admin' => 1,
        ])->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame('更新後の名前', $target->name);
        $this->assertSame('admin', $target->role);
        $this->assertSame($oldPassword, $target->password);
    }

    // 最後の1人の管理者は一般ユーザーへ降格できず、他に管理者がいれば降格できることを確認します。
    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
        ])->assertRedirect(route('users.edit', $admin))
            ->assertSessionHasErrors('is_admin');
        $this->get(route('users.edit', $admin))->assertSee('最後の管理者は一般ユーザーに変更できません。');
        $this->assertTrue((bool) $admin->fresh()->is_admin);

        $second = $this->admin();
        $this->put(route('users.update', $second), [
            'name' => $second->name,
            'email' => $second->email,
        ])->assertRedirect(route('users.index'));
        $this->assertFalse((bool) $second->fresh()->is_admin);
    }

    // 管理者は他のアカウントを削除できるが、自分自身は削除できないことを確認します。
    public function test_admin_can_delete_others_but_not_self(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $this->actingAs($admin);

        $this->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status', '自分自身のアカウントは削除できません。');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->delete(route('users.destroy', $other))->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }
}
