<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\ProductionLog;
use App\Models\Recipe;
use App\Models\ResinUsageRecord;
use App\Models\ResinUsageSetting;
use App\Models\ResinUsageAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResinUsageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $worker;
    protected $resinOne;
    protected $resinTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->worker = User::factory()->create(['name' => '作業者']);
        $this->actingAs($this->worker);

        $this->resinOne = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $this->resinTwo = Material::create(['name' => 'Material B', 'unit' => 'kg']);

        $setting = new ResinUsageSetting([
            'material_one_id' => $this->resinOne->id,
            'material_two_id' => $this->resinTwo->id,
            'material_one_initial_stock' => 10,
            'material_two_initial_stock' => 20,
        ]);
        $setting->id = 1;
        $setting->save();
    }

    // 材料別の日次使用量、差異表示、翌日の在庫引き継ぎを確認します。使用量と在庫計算が正しく保存され、次回入力の基準になることが期待結果です。
    public function test_daily_usage_is_summed_by_material_and_inventory_adjustment_carries_forward(): void
    {
        // 同じ日に複数回登録した計量ログから、材料ごとに合算されるデータを準備します。
        $this->createProductionLog('2026-10-01 09:00:00', [
            $this->resinOne->id => 2.5,
            $this->resinTwo->id => 1.25,
        ]);
        $this->createProductionLog('2026-10-01 14:00:00', [
            $this->resinOne->id => 0.5,
        ]);

        // 初日の記録を保存し、使用量・計算在庫・実在庫との差異が記録されることを確認します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => [
                    'replenished_quantity' => 2,
                    'actual_stock' => 9.1,
                    'adjustment_reason' => '実使用量を確認して補正',
                ],
                $this->resinTwo->id => [
                    'replenished_quantity' => 0,
                    'actual_stock' => 18.75,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $first = ResinUsageRecord::whereDate('usage_date', '2026-10-01')->firstOrFail();
        $firstOne = $first->items()->where('material_id', $this->resinOne->id)->firstOrFail();
        $this->assertEquals(3.0, (float) $firstOne->used_quantity);
        $this->assertEquals(9.0, (float) $firstOne->calculated_stock);
        $this->assertEquals(9.1, (float) $firstOne->closing_stock);
        $this->assertEquals(0.1, (float) $firstOne->adjustment_quantity);

        // 入力・修正・履歴画面に、引き継いだ在庫や差異の見出しが正しく表示されることを確認します。
        $this->get(route('resin-usage.create', ['date' => '2026-10-02']))
            ->assertOk()
            ->assertSee('前回在庫')
            ->assertSee('9.100 kg')
            ->assertDontSee('name="items[' . $this->resinOne->id . '][opening_stock]"', false);

        $this->get(route('resin-usage.edit', $first))
            ->assertOk()
            ->assertSee('差異（実在庫 − 計算在庫）')
            ->assertSee('difference-' . $this->resinOne->id, false)
            ->assertDontSee('前回の調整量');

        $this->get(route('resin-usage.history'))
            ->assertOk()
            ->assertSee('<th>差異</th>', false)
            ->assertSee('resin-usage-difference')
            ->assertSee('resin-usage-unconfirmed')
            ->assertDontSee('<th>調整量</th>', false);

        // 翌日の記録は初日の実在庫を前回在庫として使い、使用量0も扱えることを確認します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-02',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => [
                    'replenished_quantity' => 1,
                    'actual_stock' => 10.1,
                ],
                $this->resinTwo->id => [
                    'replenished_quantity' => 0,
                    'actual_stock' => 18.75,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $secondOne = ResinUsageRecord::whereDate('usage_date', '2026-10-02')
            ->firstOrFail()
            ->items()
            ->where('material_id', $this->resinOne->id)
            ->firstOrFail();
        $this->assertEquals(9.1, (float) $secondOne->opening_stock);
        $this->assertEquals(10.1, (float) $secondOne->calculated_stock);
        $this->assertEquals(0.0, (float) $secondOne->used_quantity);
    }

    // 上司確認の保存と過去記録の修正後に続く在庫が再計算されることを確認します。修正後の実在庫が翌日の前回在庫になることが期待結果です。
    public function test_supervisor_confirmation_is_saved_and_historical_corrections_recalculate_following_stock(): void
    {
        // 1日目を上司確認済みで保存し、翌日分も作って修正後の在庫引き継ぎを確認します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'supervisor_confirmed' => '1',
            'supervisor_name' => '上司',
            'items' => [
                $this->resinOne->id => [
                    'opening_stock' => 10,
                    'replenished_quantity' => 0,
                    'actual_stock' => 10,
                ],
                $this->resinTwo->id => [
                    'opening_stock' => 20,
                    'replenished_quantity' => 0,
                    'actual_stock' => 20,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        // 翌日分を先に作成し、過去記録の修正が後続在庫に反映されるようにします。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-02',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => ['replenished_quantity' => 0, 'actual_stock' => 10],
                $this->resinTwo->id => ['replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $first = ResinUsageRecord::whereDate('usage_date', '2026-10-01')->firstOrFail();
        $this->assertSame('上司', $first->supervisor_name);
        $this->assertNotNull($first->supervisor_confirmed_at);

        // 1日目の実在庫を修正し、差異理由も合わせて保存します。
        $this->put(route('resin-usage.update', $first), [
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => [
                    'opening_stock' => 10,
                    'replenished_quantity' => 0,
                    'actual_stock' => 9,
                    'adjustment_reason' => '在庫の目視確認による修正',
                ],
                $this->resinTwo->id => [
                    'opening_stock' => 20,
                    'replenished_quantity' => 0,
                    'actual_stock' => 20,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $secondOne = ResinUsageRecord::whereDate('usage_date', '2026-10-02')
            ->firstOrFail()
            ->items()
            ->where('material_id', $this->resinOne->id)
            ->firstOrFail();
        $this->assertEquals(9.0, (float) $secondOne->opening_stock);
        $this->assertEquals(9.0, (float) $secondOne->calculated_stock);
    }

    // 計算在庫と実在庫が異なる場合の入力チェックを確認します。差異理由がないと保存されず、理由の入力エラーが返ることが期待結果です。
    public function test_adjustment_reason_is_required_when_actual_stock_differs_from_calculated_stock(): void
    {
        $this->from(route('resin-usage.create', ['date' => '2026-10-01']))
            ->post(route('resin-usage.store'), [
                'usage_date' => '2026-10-01',
                'operator_name' => '作業者',
                'items' => [
                    $this->resinOne->id => [
                        'opening_stock' => 10,
                        'replenished_quantity' => 0,
                        'actual_stock' => 9,
                    ],
                    $this->resinTwo->id => [
                        'opening_stock' => 20,
                        'replenished_quantity' => 0,
                        'actual_stock' => 20,
                    ],
                ],
            ])
            ->assertRedirect(route('resin-usage.create', ['date' => '2026-10-01']))
            ->assertSessionHasErrors(['items.' . $this->resinOne->id . '.adjustment_reason']);

        $this->assertSame(0, ResinUsageRecord::count());
    }

    // 日付変更時の自動読み込みと同じ日付の二重登録防止を確認します。既存記録の日付は再登録できず、1件だけ残ることが期待結果です。
    public function test_date_change_loads_usage_without_a_confirmation_button_and_existing_dates_cannot_be_entered_again(): void
    {
        // 日付を変えるだけで計量ログが読み込まれ、別ボタンが不要なことを確認します。
        $this->get(route('resin-usage.create'))
            ->assertOk()
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertDontSee('この日の使用量を確認');

        // すでに登録されている日付を作成し、再入力を試します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => [
                    'opening_stock' => 10,
                    'replenished_quantity' => 0,
                    'actual_stock' => 10,
                ],
                $this->resinTwo->id => [
                    'opening_stock' => 20,
                    'replenished_quantity' => 0,
                    'actual_stock' => 20,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        // 登録済みの日付を新規入力画面で開くと、履歴へ案内されることを確認します。
        $this->get(route('resin-usage.create', ['date' => '2026-10-01']))
            ->assertRedirect(route('resin-usage.history'))
            ->assertSessionHas('status', 'この日付の記録はすでに登録されています。修正する場合は履歴から選択してください。');

        $this->from(route('resin-usage.create', ['date' => '2026-10-01']))
            ->post(route('resin-usage.store'), [
                'usage_date' => '2026-10-01',
                'operator_name' => '作業者',
                'items' => [
                    $this->resinOne->id => [
                        'opening_stock' => 10,
                        'replenished_quantity' => 0,
                        'actual_stock' => 10,
                    ],
                    $this->resinTwo->id => [
                        'opening_stock' => 20,
                        'replenished_quantity' => 0,
                        'actual_stock' => 20,
                    ],
                ],
            ])
            ->assertRedirect(route('resin-usage.create', ['date' => '2026-10-01']))
            ->assertSessionHasErrors('usage_date');

        $this->assertSame(1, ResinUsageRecord::whereDate('usage_date', '2026-10-01')->count());
    }

    // 修正・削除の実行者と変更内容が履歴に残り、削除後は後続在庫が再計算されることを確認します。
    public function test_record_updates_and_deletions_are_audited_and_visible_in_history(): void
    {
        // 1日目の記録を作成し、登録後に編集・削除・監査履歴を確認します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => ['opening_stock' => 10, 'replenished_quantity' => 0, 'actual_stock' => 10],
                $this->resinTwo->id => ['opening_stock' => 20, 'replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        // 作成した記録を修正し、変更前後と実行者が監査データに保存されることを確認します。
        $record = $this->recordForDate('2026-10-01');
        $this->put(route('resin-usage.update', $record), [
            'operator_name' => '修正後の作業者',
            'items' => [
                $this->resinOne->id => [
                    'opening_stock' => 10,
                    'replenished_quantity' => 0,
                    'actual_stock' => 9,
                    'adjustment_reason' => '在庫差異',
                ],
                $this->resinTwo->id => ['opening_stock' => 20, 'replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $updateAudit = ResinUsageAudit::where('action', 'updated')->firstOrFail();
        $this->assertSame('作業者', $updateAudit->changed_by_name);
        $this->assertSame('作業者', $updateAudit->before_data['operator_name']);
        $this->assertSame('修正後の作業者', $updateAudit->after_data['operator_name']);

        // 翌日の記録も用意し、前日の削除後に在庫が再計算されることを確認します。
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-02',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => ['replenished_quantity' => 0, 'actual_stock' => 9],
                $this->resinTwo->id => ['replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        // 1日目を削除し、削除内容の監査記録と2日目の在庫補正を確認します。
        $this->delete(route('resin-usage.destroy', $record))
            ->assertRedirect(route('resin-usage.history'));

        $deleteAudit = ResinUsageAudit::where('action', 'deleted')->firstOrFail();
        $this->assertSame('作業者', $deleteAudit->changed_by_name);
        $this->assertSame('2026-10-01', $deleteAudit->before_data['usage_date']);
        $this->assertNull($deleteAudit->after_data);
        $this->assertDatabaseMissing('resin_usage_records', ['id' => $record->id]);
        $nextItem = $this->recordForDate('2026-10-02')->items()->where('material_id', $this->resinOne->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $nextItem->opening_stock);
        $this->assertEquals(10.0, (float) $nextItem->calculated_stock);

        $this->get(route('resin-usage.history'))
            ->assertOk()
            ->assertSee('修正・削除の履歴')
            ->assertSee('修正後の作業者')
            ->assertSee('在庫差異')
            ->assertSee('削除');
    }

    // 管理者のみ設定でき、材料変更後も過去記録が維持されることを確認します。新しい記録には新材料とその初回在庫が使われることが期待結果です。
    public function test_only_admin_can_configure_resin_materials_and_changes_preserve_previous_records(): void
    {
        $this->get(route('resin-usage.settings.edit'))->assertForbidden();

        // 一般利用者は設定を変更できず、管理者は現行材料と初回在庫を保存できることを確認します。
        $newResinOne = Material::create(['name' => 'Material C', 'unit' => 'kg']);
        $newResinTwo = Material::create(['name' => 'Material D', 'unit' => 'kg']);
        $admin = User::factory()->create(['name' => '管理者', 'is_admin' => true]);
        $this->actingAs($admin)
            ->put(route('resin-usage.settings.update'), [
                'material_one_id' => $this->resinOne->id,
                'material_two_id' => $this->resinTwo->id,
                'material_one_initial_stock' => 10,
                'material_two_initial_stock' => 20,
            ])
            ->assertRedirect(route('resin-usage.settings.edit'));

        // 旧材料で記録を作ってから、管理者が設定を新材料に切り替えます。
        $this->actingAs($this->worker);
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => [
                    'opening_stock' => 10,
                    'replenished_quantity' => 0,
                    'actual_stock' => 10,
                ],
                $this->resinTwo->id => [
                    'opening_stock' => 20,
                    'replenished_quantity' => 0,
                    'actual_stock' => 20,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $this->actingAs($admin)
            ->put(route('resin-usage.settings.update'), [
                'material_one_id' => $newResinOne->id,
                'material_two_id' => $newResinTwo->id,
                'material_one_initial_stock' => 5,
                'material_two_initial_stock' => 7,
            ])
            ->assertRedirect(route('resin-usage.settings.edit'));

        // 過去の記録は旧材料のまま表示でき、新規入力は新材料の設定を使うことを確認します。
        $this->assertEquals($newResinOne->id, ResinUsageSetting::find(1)->material_one_id);
        $this->actingAs($this->worker)
            ->get(route('resin-usage.edit', $this->recordForDate('2026-10-01')))
            ->assertOk()
            ->assertSee('Material A')
            ->assertSee('Material B')
            ->assertSee('name="items[' . $this->resinOne->id . '][replenished_quantity]"', false)
            ->assertSee('name="items[' . $this->resinTwo->id . '][replenished_quantity]"', false)
            ->assertDontSee('Material C');

        $this->get(route('resin-usage.create', ['date' => '2026-10-02']))
            ->assertOk()
            ->assertSee('Material C')
            ->assertSee('Material D')
            ->assertSee('設定済み初回在庫')
            ->assertSee('5.000 kg');

        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-02',
            'operator_name' => '作業者',
            'items' => [
                $newResinOne->id => [
                    'replenished_quantity' => 1,
                    'actual_stock' => 6,
                ],
                $newResinTwo->id => [
                    'replenished_quantity' => 0,
                    'actual_stock' => 7,
                ],
            ],
        ])->assertRedirect(route('resin-usage.history'));

        $newRecordMaterialIds = $this->recordForDate('2026-10-02')->items()->pluck('material_id')->sort()->values()->all();
        $this->assertEquals([$newResinOne->id, $newResinTwo->id], $newRecordMaterialIds);
        $newRecordItems = $this->recordForDate('2026-10-02')->items()->get()->keyBy('material_id');
        $this->assertEquals(5.0, (float) $newRecordItems[$newResinOne->id]->opening_stock);
        $this->assertEquals(7.0, (float) $newRecordItems[$newResinTwo->id]->opening_stock);
    }

    private function recordForDate(string $date): ResinUsageRecord
    {
        return ResinUsageRecord::whereDate('usage_date', $date)->firstOrFail();
    }

    private function createProductionLog(string $weighedAt, array $quantities): void
    {
        // レシピ名は重複できないため、同じテスト内で複数回呼んでも同じレシピを使い回します。
        $recipe = Recipe::firstOrCreate(
            ['name' => '非公開レシピ'],
            ['created_by' => $this->worker->id]
        );
        $log = ProductionLog::create([
            'user_id' => $this->worker->id,
            'recipe_id' => $recipe->id,
            'weighed_at' => $weighedAt,
        ]);
        $materials = [];
        foreach ($quantities as $materialId => $quantity) {
            $materials[$materialId] = ['actual_quantity' => $quantity];
        }
        $log->materials()->attach($materials);
    }
}
