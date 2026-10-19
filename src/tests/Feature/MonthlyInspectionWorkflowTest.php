<?php

namespace Tests\Feature;

use App\Models\InspectionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyInspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // 設備点検はログイン必須のため、全テストでログイン済みにする。
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => '佐藤']));
    }

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
