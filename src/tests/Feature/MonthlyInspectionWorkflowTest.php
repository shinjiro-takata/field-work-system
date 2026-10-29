<?php

namespace Tests\Feature;

use App\Models\InspectionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MonthlyInspectionWorkflowTestの画面表示・保存・編集・出力の動作を確認する。
 */
class MonthlyInspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // 点検画面を利用できるよう、各テストの開始時にテスト用ユーザーでログインする。
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => '佐藤']));
    }

    /**
     * 月次点検画面に対象設備と温度の単位が表示されることを確認する。
     */
    public function test_monthly_page_renders_groups_and_numeric_temperature(): void
    {
        $this->get(route('inspections.monthly.create'))
            ->assertOk()
            ->assertSee('月次点検')
            ->assertSee('月初め又は保全担当者停止時点検')
            ->assertSee('ミキサー（3）')
            ->assertSee('真空ポンプ（2）')
            ->assertSee('暖房室')
            ->assertSee('集塵機（2）')
            ->assertSee('℃');
    }

    /**
     * 月次点検の新規登録・確認・編集・CSV出力を一連で確認する。
     */
    public function test_can_create_view_edit_and_export_monthly_record(): void
    {
        $this->post(route('inspections.monthly.store'), [
            'inspection_date' => '2026-10-02',
            'inspector' => '鈴木一郎',
            'results' => [
                'm-mixer1-belt-high' => ['status' => 'ok'],
                'm-heater-temp' => ['value' => '42.5'],
            ],
        ])->assertRedirect();

        $record = InspectionRecord::ofType('monthly')->first();
        $this->assertSame('42.5', $record->results['m-heater-temp']['value']);

        $this->get(route('inspections.monthly.show', $record))
            ->assertOk()
            ->assertSee('42.5');

        $this->put(route('inspections.monthly.update', $record), [
            'editor_name' => '田中',
            'inspector' => '鈴木一郎',
            'results' => ['m-heater-temp' => ['value' => '45']],
        ])->assertRedirect(route('inspections.monthly.show', $record));

        $this->assertSame('佐藤', $record->fresh()->editLogs()->first()->editor_name);

        $csv = $this->get(route('inspections.monthly.export'))->streamedContent();
        $this->assertStringContainsString('暖房室', $csv);
        $this->assertStringContainsString('℃', $csv);
    }

    /**
     * 月次点検の画面と保存が、他の点検種別から独立していることを確認する。
     */
    public function test_monthly_is_isolated_from_other_types(): void
    {
        $daily = InspectionRecord::create([
            'inspection_type' => 'daily',
            'inspection_date' => '2026-10-02',
            'inspector' => '日常担当',
            'results' => ['x' => ['status' => 'ok']],
        ]);

        $this->get(route('inspections.monthly.index'))->assertOk()->assertDontSee('日常担当');
        $this->get(route('inspections.monthly.show', $daily))->assertNotFound();

        $this->post(route('inspections.monthly.store'), [
            'inspection_date' => '2026-10-02',
            'inspector' => '月次担当',
            'results' => ['m-heater-temp' => ['value' => '40']],
        ])->assertSessionHasNoErrors();
    }

    /**
     * 月次点検の空入力と数値でない測定値を拒否することを確認する。
     */
    public function test_monthly_rejects_empty_and_non_numeric_input(): void
    {
        $this->post(route('inspections.monthly.store'), [
            'inspection_date' => '2026-10-02',
            'inspector' => '鈴木',
        ])->assertSessionHasErrors('results');

        $this->post(route('inspections.monthly.store'), [
            'inspection_date' => '2026-10-02',
            'inspector' => '鈴木',
            'results' => ['m-heater-temp' => ['value' => 'abc']],
        ])->assertSessionHasErrors('results.m-heater-temp.value');
    }
}
