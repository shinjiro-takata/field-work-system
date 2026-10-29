<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Material;
use App\Models\ResinUsageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    // 未ログイン利用者がホームを開いたときの認証を確認します。ログイン画面へリダイレクトされることが期待結果です。
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    // ログイン後のホームに各アプリへのリンクが表示されることを確認します。設備点検とレジン使用量確認表の入口が含まれることが期待結果です。
    public function test_home_links_to_both_apps(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee(route('index'), false)
            ->assertSee(route('resin-usage.index'), false)
            ->assertSee('レジン使用量確認表');
    }

    // レジンメニューから履歴・新規入力へ移動できることを確認します。各画面に必要な見出し、リンク、入力欄が表示されることが期待結果です。
    public function test_resin_usage_menu_links_to_history_and_new_entry_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $resinOne = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $resinTwo = Material::create(['name' => 'Material B', 'unit' => 'kg']);
        $setting = new ResinUsageSetting([
            'material_one_id' => $resinOne->id,
            'material_two_id' => $resinTwo->id,
            'material_one_initial_stock' => 10,
            'material_two_initial_stock' => 20,
        ]);
        $setting->id = 1;
        $setting->save();

        // メニューに履歴・新規入力と、共通ヘッダーのリンクが表示されることを確認します。
        $this->get(route('resin-usage.index'))
            ->assertOk()
            ->assertSee('レジン使用量確認表')
            ->assertSee('履歴の確認')
            ->assertSee('新規入力')
            ->assertSee(route('resin-usage.history'), false)
            ->assertSee(route('resin-usage.create'), false)
            ->assertSee('<a href="' . route('resin-usage.index') . '" class="navbar__logo">レジン使用量確認表</a>', false)
            ->assertSee('<a href="' . route('portal') . '" class="navbar__home-btn">ホーム</a>', false);

        // 記録がない履歴画面と、新規入力への導線が表示されることを確認します。
        $this->get(route('resin-usage.history'))
            ->assertOk()
            ->assertSee('レジン使用量の履歴')
            ->assertSee('登録された履歴はありません')
            ->assertSee(route('resin-usage.index'), false);

        // 初回在庫と材料別入力欄を含む新規入力画面が表示されることを確認します。
        $this->get(route('resin-usage.create'))
            ->assertOk()
            ->assertSee('レジン使用量の新規入力')
            ->assertSee('設定済み初回在庫')
            ->assertSee('10.000 kg')
            ->assertSee('name="usage_date"', false)
            ->assertSee('name="items[' . $resinOne->id . '][replenished_quantity]"', false)
            ->assertSee('name="supervisor_name"', false);
    }
}
