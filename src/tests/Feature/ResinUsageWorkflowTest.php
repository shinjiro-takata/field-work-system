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

    public function test_daily_usage_is_summed_by_material_and_inventory_adjustment_carries_forward(): void
    {
        $this->createProductionLog('2026-10-01 09:00:00', [
            $this->resinOne->id => 2.5,
            $this->resinTwo->id => 1.25,
        ]);
        $this->createProductionLog('2026-10-01 14:00:00', [
            $this->resinOne->id => 0.5,
        ]);

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

    public function test_supervisor_confirmation_is_saved_and_historical_corrections_recalculate_following_stock(): void
    {
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

    public function test_date_change_loads_usage_without_a_confirmation_button_and_existing_dates_cannot_be_entered_again(): void
    {
        $this->get(route('resin-usage.create'))
            ->assertOk()
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertDontSee('この日の使用量を確認');

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

    public function test_record_updates_and_deletions_are_audited_and_visible_in_history(): void
    {
        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => ['opening_stock' => 10, 'replenished_quantity' => 0, 'actual_stock' => 10],
                $this->resinTwo->id => ['opening_stock' => 20, 'replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

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

        $this->post(route('resin-usage.store'), [
            'usage_date' => '2026-10-02',
            'operator_name' => '作業者',
            'items' => [
                $this->resinOne->id => ['replenished_quantity' => 0, 'actual_stock' => 9],
                $this->resinTwo->id => ['replenished_quantity' => 0, 'actual_stock' => 20],
            ],
        ])->assertRedirect(route('resin-usage.history'));

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

    public function test_only_admin_can_configure_resin_materials_and_changes_preserve_previous_records(): void
    {
        $this->get(route('resin-usage.settings.edit'))->assertForbidden();

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
        $recipe = Recipe::create([
            'name' => '非公開レシピ',
            'created_by' => $this->worker->id,
        ]);
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
