<?php

namespace Tests\Feature;

use App\Exports\ProductionLogsExport;
use App\Models\Material;
use App\Models\ProductionLog;
use App\Models\ProductionLogEditLog;
use App\Models\ProductionLogDeletion;
use App\Models\Recipe;
use App\Models\ResinUsageRecord;
use App\Models\ResinUsageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

// 計量ログの登録・一覧の判定・Excel出力・修正履歴の文言・レジン使用量との関係を確認するテストです。
class WeighingLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Material $material;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '作業者']);
        $this->actingAs($this->user);

        // 基準量 10、許容量 0.5 のレシピ（正常範囲は 9.5 〜 10.5）を用意します。
        $this->material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $this->recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $this->user->id]);
        $this->recipe->materials()->attach($this->material->id, ['quantity' => 10, 'tolerance' => 0.5]);
    }

    // 指定した実測量で計量ログを作成する補助メソッドです。
    private function createLog(float $actualQuantity, string $weighedAt = '2026-10-01 09:00:00'): ProductionLog
    {
        $log = ProductionLog::create([
            'user_id' => $this->user->id,
            'recipe_id' => $this->recipe->id,
            'weighed_at' => $weighedAt,
        ]);
        $log->materials()->attach([$this->material->id => ['actual_quantity' => $actualQuantity]]);

        return $log;
    }

    // 一覧画面で、強調表示（許容範囲外）されたセルの数を数える補助メソッドです。
    private function countOutOfToleranceCells(): int
    {
        return substr_count($this->get(route('logs.index'))->getContent(), 'class="out-of-tolerance-log"');
    }

    // 登録フォームの入力どおりに、ログと実測量が保存されることを確認します。
    public function test_log_can_be_created_with_actual_quantities(): void
    {
        $this->post(route('logs.store'), [
            'recipe_id' => $this->recipe->id,
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$this->material->id => 10.2],
        ])->assertRedirect(route('logs.index'));

        $log = ProductionLog::firstOrFail();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertEquals(10.2, (float) $log->materials()->first()->pivot->actual_quantity);
    }

    // 必須項目の未入力や、数値でない実測量が拒否されることを確認します。
    public function test_log_creation_validates_required_fields(): void
    {
        $this->post(route('logs.store'), [])->assertSessionHasErrors(['recipe_id', 'weighed_at']);

        $this->post(route('logs.store'), [
            'recipe_id' => $this->recipe->id,
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$this->material->id => 'abc'],
        ])->assertSessionHasErrors('materials.' . $this->material->id);

        $this->assertSame(0, ProductionLog::count());
    }

    // レシピ外の材料を使ったのに備考が空の場合は拒否され、備考があれば保存されることを確認します。
    public function test_extra_material_requires_a_reason_note(): void
    {
        $extra = Material::create(['name' => 'Extra', 'unit' => 'kg']);
        $payload = [
            'recipe_id' => $this->recipe->id,
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$this->material->id => 10],
            'extra_materials' => [['material_id' => $extra->id, 'quantity' => 1]],
        ];

        $this->post(route('logs.store'), $payload)->assertSessionHasErrors('notes');
        $this->assertSame(0, ProductionLog::count());

        $this->post(route('logs.store'), $payload + ['notes' => '補充のため'])->assertRedirect(route('logs.index'));
        $this->assertCount(2, ProductionLog::firstOrFail()->materials);
    }

    // レシピ内の材料を「レシピ外」欄に入力した場合は拒否されることを確認します。
    public function test_recipe_material_cannot_be_entered_as_extra_material(): void
    {
        $this->post(route('logs.store'), [
            'recipe_id' => $this->recipe->id,
            'weighed_at' => '2026-10-01T09:00',
            'notes' => '理由',
            'materials' => [$this->material->id => 10],
            'extra_materials' => [['material_id' => $this->material->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('extra_materials.0.material_id');
    }

    // 許容範囲の境界値: 上限・下限ちょうどは正常、わずかに超えると強調表示されることを確認します。
    public function test_tolerance_boundaries_are_judged_correctly(): void
    {
        $this->createLog(10.5);
        $this->createLog(9.5);
        $this->assertSame(0, $this->countOutOfToleranceCells());

        $this->createLog(10.51);
        $this->assertSame(1, $this->countOutOfToleranceCells());

        $this->createLog(9.49);
        $this->assertSame(2, $this->countOutOfToleranceCells());
    }

    // 許容量が0の材料は範囲チェックされず、強調表示されないことを確認します。
    public function test_zero_tolerance_is_not_checked(): void
    {
        $this->recipe->materials()->updateExistingPivot($this->material->id, ['tolerance' => 0]);
        $this->createLog(99);

        $this->assertSame(0, $this->countOutOfToleranceCells());
    }

    // レシピに無い材料は、量にかかわらず強調表示され、「レシピ外」と表示されることを確認します。
    public function test_off_recipe_material_is_highlighted(): void
    {
        $extra = Material::create(['name' => 'Extra', 'unit' => 'kg']);
        $log = $this->createLog(10);
        $log->materials()->attach([$extra->id => ['actual_quantity' => 1]]);

        $response = $this->get(route('logs.index'))->assertOk()->assertSee('レシピ外');
        $this->assertSame(1, substr_count($response->getContent(), 'class="out-of-tolerance-log"'));
    }

    // 日付・レシピでの絞り込みが一覧に反映されることを確認します。
    public function test_index_filters_by_date_range(): void
    {
        $this->createLog(10, '2026-09-01 09:00:00')->update(['notes' => '九月の記録']);
        $this->createLog(10, '2026-10-15 09:00:00')->update(['notes' => '十月の記録']);

        $this->get(route('logs.index', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk()
            ->assertSee('十月の記録', false)
            ->assertDontSee('九月の記録', false);
    }

    // 備考がある場合だけ「有」ボタンが出て、無い場合は出ないことを確認します。
    public function test_notes_button_is_shown_only_when_notes_exist(): void
    {
        $this->createLog(10);
        $this->get(route('logs.index'))->assertDontSee('btn btn-secondary note-open', false);

        ProductionLog::firstOrFail()->update(['notes' => '備考あり']);
        $this->get(route('logs.index'))->assertSee('btn btn-secondary note-open', false);
    }

    // Excel出力の中身（見出し・材料別の実測量・合計行）が正しいことを確認します。
    public function test_export_array_contains_rows_and_totals(): void
    {
        $this->createLog(10.5, '2026-10-01 09:00:00');
        $this->createLog(9.25, '2026-10-02 09:00:00');
        $logs = ProductionLog::with('recipe', 'materials')->orderBy('weighed_at')->get();

        $rows = (new ProductionLogsExport($logs, Material::all()))->array();

        $this->assertSame(['日時', 'レシピ', '備考', 'Material A'], $rows[0]);
        $this->assertSame(10.5, $rows[1][3]);
        $this->assertSame(9.25, $rows[2][3]);
        $this->assertSame(['合計', '', '', 19.75], $rows[3]);
    }

    // エクスポートの画面操作で、xlsxファイルのダウンロードが実行されることを確認します。
    public function test_export_route_downloads_an_xlsx_file(): void
    {
        // ファイル名に現在時刻が入るため、時刻を固定して期待するファイル名を決めます。
        Carbon::setTestNow('2026-10-01 12:34:56');
        Excel::fake();
        $this->createLog(10);

        $this->get(route('logs.export'))->assertOk();

        Excel::assertDownloaded('production_logs_20261001_123456.xlsx');
        Carbon::setTestNow();
    }

    // 修正履歴の文言: 日時・備考・材料の変更、追加、削除がそれぞれ文章になることを確認します。
    public function test_edit_log_change_descriptions(): void
    {
        $material = ['material_id' => 1, 'name' => 'Material A', 'unit' => 'kg', 'actual_quantity' => 1.5];
        $added = ['material_id' => 2, 'name' => 'Material B', 'unit' => 'kg', 'actual_quantity' => 2.0];

        $editLog = new ProductionLogEditLog([
            'before_data' => [
                'weighed_at' => '2026-10-01 09:00',
                'notes' => null,
                'materials' => [$material, ['material_id' => 3, 'name' => 'Material C', 'unit' => 'g', 'actual_quantity' => 4.0]],
            ],
            'after_data' => [
                'weighed_at' => '2026-10-01 10:30',
                'notes' => '修正後',
                'materials' => [array_merge($material, ['actual_quantity' => 2.25]), $added],
            ],
        ]);

        $this->assertSame([
            '計量日時：2026-10-01 09:00 → 2026-10-01 10:30',
            '備考：（なし） → 修正後',
            'Material A：1.500 → 2.250 kg',
            'Material B：追加 2.000 kg',
            'Material C：削除（4.000 g）',
        ], $editLog->changeDescriptions());
    }

    // 変更がない項目は、修正履歴の文言に含まれないことを確認します。
    public function test_edit_log_change_descriptions_are_empty_without_changes(): void
    {
        $data = [
            'weighed_at' => '2026-10-01 09:00',
            'notes' => '同じ',
            'materials' => [['material_id' => 1, 'name' => 'A', 'unit' => 'kg', 'actual_quantity' => 1.0]],
        ];

        $this->assertSame([], (new ProductionLogEditLog(['before_data' => $data, 'after_data' => $data]))->changeDescriptions());
    }

    // 登録済みのレジン使用量は、計量ログを後から修正しても自動では変わらない（仕様）ことを確認します。
    public function test_editing_a_log_does_not_change_an_existing_resin_record(): void
    {
        $setting = new ResinUsageSetting([
            'material_one_id' => $this->material->id,
            'material_two_id' => Material::create(['name' => 'Material B', 'unit' => 'kg'])->id,
            'material_one_initial_stock' => 100,
            'material_two_initial_stock' => 100,
        ]);
        $setting->id = 1;
        $setting->save();

        $log = $this->createLog(10, '2026-10-01 09:00:00');
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->material->id => ['replenished_quantity' => 0, 'actual_stock' => 90],
                $setting->material_two_id => ['replenished_quantity' => 0, 'actual_stock' => 100],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$this->material->id => 20],
        ])->assertRedirect(route('logs.index'));

        $record = ResinUsageRecord::whereDate('usage_date', '2026-10-01')->firstOrFail();
        $item = $record->items()->where('material_id', $this->material->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $item->used_quantity);
    }

    // 一般の作業者でも計量ログを削除でき、削除前の内容・削除者が削除履歴に残り、一覧に表示されることを確認します。
    public function test_any_logged_in_user_can_delete_a_log_and_the_deletion_is_recorded(): void
    {
        $log = $this->createLog(10.5);
        $log->update(['notes' => '削除する備考']);
        $deleter = User::factory()->create(['name' => '削除者', 'is_admin' => false]);
        $this->actingAs($deleter);

        $this->delete(route('logs.destroy', $log))
            ->assertRedirect(route('logs.index'))
            ->assertSessionHas('status', '計量ログを削除しました。');

        $this->assertDatabaseMissing('production_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('production_log_materials', ['production_log_id' => $log->id]);

        $deletion = ProductionLogDeletion::firstOrFail();
        $this->assertSame('削除者', $deletion->deleted_by_name);
        $this->assertSame('Recipe', $deletion->recipe_name);
        $this->assertSame('作業者', $deletion->registered_by_name);
        $this->assertSame(['備考：削除する備考', 'Material A：10.500 kg'], $deletion->contentDescriptions());

        $this->get(route('logs.index'))
            ->assertOk()
            ->assertSee('削除履歴')
            ->assertSee('削除者')
            ->assertSee('Material A：10.500 kg');
    }

    // 削除履歴が無いときは「削除履歴はありません。」と表示し、ログがあれば各行に削除ボタンが出ることを確認します。
    public function test_index_shows_delete_button_and_empty_deletion_history(): void
    {
        $this->createLog(10);

        $this->get(route('logs.index'))
            ->assertOk()
            ->assertSee('削除履歴はありません。')
            ->assertSee('この計量ログを削除しますか？');
    }

    // 備考なし・レシピ外材料ありのログを削除しても、材料が全て履歴に残り、備考の行は出ないことを確認します。
    public function test_deleting_a_log_without_notes_but_with_extra_material_is_recorded(): void
    {
        $extra = Material::create(['name' => 'Extra', 'unit' => 'g']);
        $log = $this->createLog(10);
        $log->materials()->attach([$extra->id => ['actual_quantity' => 3]]);

        $this->delete(route('logs.destroy', $log))->assertRedirect(route('logs.index'));

        $deletion = ProductionLogDeletion::firstOrFail();
        $this->assertSame(['Material A：10.000 kg', 'Extra：3.000 g'], $deletion->contentDescriptions());
    }

    // 削除しても、他のログや登録済みのレジン使用量記録には影響しないことを確認します。
    public function test_deleting_a_log_keeps_other_logs_and_resin_records(): void
    {
        $setting = new ResinUsageSetting([
            'material_one_id' => $this->material->id,
            'material_two_id' => Material::create(['name' => 'Material B', 'unit' => 'kg'])->id,
            'material_one_initial_stock' => 100,
            'material_two_initial_stock' => 100,
        ]);
        $setting->id = 1;
        $setting->save();

        $deleted = $this->createLog(10, '2026-10-01 09:00:00');
        $kept = $this->createLog(5, '2026-10-01 13:00:00');
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->material->id => ['replenished_quantity' => 0, 'actual_stock' => 85],
                $setting->material_two_id => ['replenished_quantity' => 0, 'actual_stock' => 100],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $this->delete(route('logs.destroy', $deleted))->assertRedirect(route('logs.index'));

        $this->assertDatabaseHas('production_logs', ['id' => $kept->id]);
        $item = ResinUsageRecord::whereDate('usage_date', '2026-10-01')->firstOrFail()
            ->items()->where('material_id', $this->material->id)->firstOrFail();
        $this->assertEquals(15.0, (float) $item->used_quantity);
    }

    // 未ログインの利用者は計量ログを削除できないことを確認します。
    public function test_guest_cannot_delete_a_log(): void
    {
        $log = $this->createLog(10);
        auth()->logout();

        $this->delete(route('logs.destroy', $log))->assertRedirect('/login');
        $this->assertDatabaseHas('production_logs', ['id' => $log->id]);
    }

    // ログイン・ログアウトの基本動作を確認します。
    public function test_login_and_logout(): void
    {
        auth()->logout();
        $user = User::factory()->create(['email' => 'login@example.com']);

        $this->post('/login', ['email' => 'login@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors();
        $this->assertGuest();

        $this->post('/login', ['email' => 'login@example.com', 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));
        $this->assertGuest();
    }
}
