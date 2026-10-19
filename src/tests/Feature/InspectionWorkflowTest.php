<?php

namespace Tests\Feature;

use App\Models\InspectionEditLog;
use App\Models\InspectionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // 設備点検はログイン必須のため、全テストでログイン済みにする。
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => '佐藤']));
    }

    public function test_home_links_to_every_inspection_type(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['日常点検', '週次点検', '月次点検', '定期点検'])
            ->assertSee(route('inspections.create'), false)
            ->assertSee(route('inspections.weekly.create'), false)
            ->assertSee(route('inspections.monthly.create'), false)
            ->assertSee(route('inspections.periodic.create'), false);

        $this->get(route('inspections.weekly.create'))->assertOk()->assertSee('週次点検');
        $this->get(route('inspections.monthly.create'))->assertOk()->assertSee('月次点検');
        $this->get(route('inspections.periodic.create'))->assertOk()->assertSee('定期点検');
    }

    public function test_daily_inspection_page_uses_the_requested_title_without_summary_cards(): void
    {
        $this->get(route('inspections.create'))
            ->assertOk()
            ->assertSee('<h1>日常点検</h1>', false)
            ->assertSee('<a class="brand" href="' . route('home') . '">設備点検</a>', false)
            ->assertDontSee('<i class="online-dot">', false)
            ->assertDontSee('設備点検チェックリスト')
            ->assertDontSee('圧力単位')
            ->assertDontSee('summary-grid');
    }

    public function test_pressure_readings_are_saved_and_shown_as_numeric_values(): void
    {
        $response = $this->post(route('inspections.store'), [
            'inspection_date' => '2026-10-08',
            'inspector' => '佐藤',
            'results' => [
                'g0-i0' => ['status' => 'ok'],
                'g2-i1' => ['pressure' => '-0.065'],
            ],
        ]);

        $record = InspectionRecord::firstOrFail();
        $response->assertRedirect(route('inspections.show', $record));
        $this->assertSame('-0.065', $record->results['g2-i1']['pressure']);
        $this->get(route('inspections.show', $record))
            ->assertOk()
            ->assertSee('-0.065')
            ->assertSee('MPa');
    }

    public function test_history_is_read_only_and_editing_creates_an_editor_audit_log(): void
    {
        $record = InspectionRecord::create([
            'inspection_date' => '2026-10-08',
            'inspector' => '佐藤',
            'results' => [
                'g0-i0' => ['status' => 'ok'],
                'g2-i1' => ['pressure' => '0.12'],
            ],
        ]);

        $this->get(route('inspections.index'))
            ->assertOk()
            ->assertSee('記録を確認')
            ->assertDontSee(route('inspections.edit', $record), false);

        $this->get(route('inspections.show', $record))
            ->assertOk()
            ->assertSee('編集する')
            ->assertSee('0.12');

        $this->get(route('inspections.edit', $record))
            ->assertOk()
            ->assertSee('編集者')
            ->assertSee('name="results[g0-i0][status]" value="ok" checked', false);

        $response = $this->put(route('inspections.update', $record), [
            'inspector' => '佐藤',
            'editor_name' => '鈴木',
            'note' => '圧力値を確認',
            'results' => [
                'g0-i0' => ['status' => 'ng'],
                'g2-i1' => ['pressure' => '0.18'],
            ],
        ]);

        $response->assertRedirect(route('inspections.show', $record));
        $record->refresh();
        $this->assertSame('佐藤', $record->last_edited_by);
        $this->assertSame('0.18', $record->results['g2-i1']['pressure']);
        $this->assertSame(1, InspectionEditLog::where('inspection_record_id', $record->id)->count());
        $this->get(route('inspections.show', $record))
            ->assertOk()
            ->assertSee('佐藤 が編集')
            ->assertSee('編集履歴')
            ->assertSee('圧力値を確認')
            ->assertSee('0.12 MPa')
            ->assertSee('0.18 MPa');
    }

    public function test_editor_name_and_at_least_one_result_are_required(): void
    {
        $record = InspectionRecord::create([
            'inspection_date' => '2026-10-08',
            'inspector' => '佐藤',
            'results' => ['g0-i0' => ['status' => 'ok']],
        ]);

        $this->from(route('inspections.edit', $record))
            ->put(route('inspections.update', $record), [
                'inspector' => '佐藤',
                'editor_name' => '鈴木',
                'results' => [],
            ])
            ->assertRedirect(route('inspections.edit', $record))
            ->assertSessionHasErrors(['results']);
    }

    public function test_csv_contains_numeric_pressure_and_editor_columns(): void
    {
        InspectionRecord::create([
            'inspection_date' => '2026-10-08',
            'inspector' => '=HYPERLINK("https://example.invalid")',
            'last_edited_by' => '鈴木',
            'results' => ['g2-i1' => ['pressure' => '0.125']],
        ]);

        $response = $this->get(route('inspections.export'));
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('圧力値（MPa）', $response->streamedContent());
        $this->assertStringContainsString('0.125', $response->streamedContent());
        $this->assertStringContainsString('鈴木', $response->streamedContent());
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_new_record_requires_at_least_one_result(): void
    {
        $this->from(route('inspections.create'))
            ->post(route('inspections.store'), [
                'inspection_date' => '2026-10-08',
                'inspector' => '佐藤',
                'results' => [],
            ])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors(['results']);

        $this->assertDatabaseCount('inspection_records', 0);
    }

    public function test_pressure_result_rejects_non_numeric_values_and_unknown_item_ids(): void
    {
        $this->from(route('inspections.create'))
            ->post(route('inspections.store'), [
                'inspection_date' => '2026-10-08',
                'inspector' => '佐藤',
                'results' => [
                    'g0-i0' => ['status' => 'ok'],
                    'g2-i1' => ['pressure' => 'not-a-number'],
                    'g99-i99' => ['status' => 'ok'],
                ],
            ])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors(['results', 'results.g2-i1.pressure']);

        $this->assertDatabaseCount('inspection_records', 0);
    }
}
