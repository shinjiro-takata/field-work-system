<?php

namespace Tests\Feature;

use App\Models\InspectionEditLog;
use App\Models\InspectionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyInspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // 設備点検はログイン必須のため、全テストでログイン済みにする。
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => '佐藤']));
    }

    public function test_weekly_inspection_page_renders_all_expected_groups_and_items(): void
    {
        $this->get(route('inspections.weekly.create'))
            ->assertOk()
            ->assertSee('<h1>週次点検</h1>', false)
            ->assertSee('プラッター')
            ->assertSee('ミキサー（1）')
            ->assertSee('ミキサー（2）')
            ->assertSee('ミキサー（3）')
            ->assertSee('電子天秤（可塑剤計量）')
            ->assertSee('電子天秤（顔料・安定剤計量）')
            ->assertSee('電子天秤（レオロシール計量）')
            ->assertSee('可塑剤ポンプ室')
            ->assertSee('溶剤ポンプ室')
            ->assertSee('集塵機（1）')
            ->assertSee('集塵機（2）')
            ->assertSee('m/s')
            ->assertSee('※異常が発生した場合は修理依頼書を作成し設備担当者まで連絡');
    }

    public function test_can_create_and_view_weekly_inspection_record(): void
    {
        $response = $this->post(route('inspections.weekly.store'), [
            'inspection_date' => '2026-10-17',
            'inspector' => '鈴木一郎',
            'note' => '週次点検完了。集塵機も問題なし。',
            'results' => [
                'w-platter-battery' => ['status' => 'ok'],
                'w-mixer1-pump-oil' => ['status' => 'ok'],
                'w-mixer1-trap-oil' => ['status' => 'ok'],
                'w-dust1-hood-wind' => ['value' => '5.2'],
                'w-dust2-hood-wind' => ['value' => '4.8'],
            ],
        ]);

        $record = InspectionRecord::ofType('weekly')->first();
        $this->assertNotNull($record);
        $this->assertSame('weekly', $record->inspection_type);
        $this->assertSame('佐藤', $record->inspector);

        $response->assertRedirect(route('inspections.weekly.show', $record));

        $this->get(route('inspections.weekly.show', $record))
            ->assertOk()
            ->assertSee('週次点検記録の確認')
            ->assertSee('5.2')
            ->assertSee('4.8')
            ->assertSee('m/s')
            ->assertSee('佐藤');
    }

    public function test_can_edit_weekly_inspection_and_tracks_editor_log(): void
    {
        $record = InspectionRecord::create([
            'inspection_type' => 'weekly',
            'inspection_date' => '2026-10-17',
            'inspector' => '鈴木一郎',
            'note' => '初期登録',
            'results' => [
                'w-dust1-hood-wind' => ['value' => '5.2'],
                'w-mixer1-pump-oil' => ['status' => 'ok'],
            ],
        ]);

        $this->get(route('inspections.weekly.edit', $record))
            ->assertOk()
            ->assertSee('週次点検記録を編集');

        $response = $this->put(route('inspections.weekly.update', $record), [
            'inspector' => '鈴木一郎',
            'editor_name' => '佐藤保全',
            'note' => '集塵機1の風速を再測定',
            'results' => [
                'w-dust1-hood-wind' => ['value' => '5.5'],
                'w-mixer1-pump-oil' => ['status' => 'ng'],
            ],
        ]);

        $response->assertRedirect(route('inspections.weekly.show', $record));

        $record->refresh();
        $this->assertSame('佐藤', $record->last_edited_by);
        $this->assertSame('5.5', $record->results['w-dust1-hood-wind']['value']);
        $this->assertSame('ng', $record->results['w-mixer1-pump-oil']['status']);

        $this->assertDatabaseHas('inspection_edit_logs', [
            'inspection_record_id' => $record->id,
            'editor_name' => '佐藤',
        ]);

        $this->get(route('inspections.weekly.show', $record))
            ->assertOk()
            ->assertSee('佐藤 が編集')
            ->assertSee('5.5')
            ->assertSee('要対応');
    }

    public function test_weekly_history_isolates_weekly_from_daily(): void
    {
        $daily = InspectionRecord::create([
            'inspection_type' => 'daily',
            'inspection_date' => '2026-10-17',
            'inspector' => '日常担当',
            'results' => ['g0-i0' => ['status' => 'ok']],
        ]);

        $weekly = InspectionRecord::create([
            'inspection_type' => 'weekly',
            'inspection_date' => '2026-10-17',
            'inspector' => '週次担当',
            'results' => ['w-platter-battery' => ['status' => 'ok']],
        ]);

        $this->get(route('inspections.weekly.index'))
            ->assertOk()
            ->assertSee('週次担当')
            ->assertDontSee('日常担当');

        $this->get(route('inspections.index'))
            ->assertOk()
            ->assertSee('日常担当')
            ->assertDontSee('週次担当');
    }

    public function test_can_export_weekly_inspection_csv(): void
    {
        InspectionRecord::create([
            'inspection_type' => 'weekly',
            'inspection_date' => '2026-10-17',
            'inspector' => '鈴木一郎',
            'note' => '特記事項なし',
            'results' => [
                'w-dust1-hood-wind' => ['value' => '5.2'],
                'w-platter-battery' => ['status' => 'ok'],
            ],
        ]);

        $response = $this->get(route('inspections.weekly.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('測定値', $content);
        $this->assertStringContainsString('集塵機（1）', $content);
        $this->assertStringContainsString('5.2', $content);
    }

    public function test_weekly_inspection_requires_at_least_one_result(): void
    {
        $this->from(route('inspections.weekly.create'))
            ->post(route('inspections.weekly.store'), [
                'inspection_date' => '2026-10-17',
                'inspector' => '鈴木一郎',
                'results' => [],
            ])
            ->assertRedirect(route('inspections.weekly.create'))
            ->assertSessionHasErrors('results');
    }

    public function test_weekly_inspection_prevents_duplicate_date_within_weekly(): void
    {
        InspectionRecord::create([
            'inspection_type' => 'weekly',
            'inspection_date' => '2026-10-17',
            'inspector' => '鈴木一郎',
            'results' => ['w-platter-battery' => ['status' => 'ok']],
        ]);

        $this->from(route('inspections.weekly.create'))
            ->post(route('inspections.weekly.store'), [
                'inspection_date' => '2026-10-17',
                'inspector' => '山田二郎',
                'results' => ['w-platter-battery' => ['status' => 'ok']],
            ])
            ->assertRedirect(route('inspections.weekly.create'))
            ->assertSessionHasErrors('inspection_date');
    }
}
