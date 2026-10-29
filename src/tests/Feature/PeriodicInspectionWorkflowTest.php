<?php

namespace Tests\Feature;

use App\Models\InspectionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PeriodicInspectionWorkflowTestの画面表示・保存・編集・出力の動作を確認する。
 */
class PeriodicInspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    // 点検画面を利用できるよう、各テストの開始時にテスト用ユーザーでログインする。
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => '佐藤']));
    }

    /**
     * 定期点検画面に油種・規定量・補充交換の説明が表示されることを確認する。
     */
    public function test_periodic_page_shows_equipment_oil_and_capacity(): void
    {
        $this->get(route('inspections.periodic.create'))
            ->assertOk()
            ->assertSee('ミキサーNo.3')
            ->assertSee('リングコーン変速機')
            ->assertSee('メカニックオイル220／約9L', false)
            ->assertSee('プレス機')
            ->assertSee('6月：交換、12月：補充')
            ->assertSee('補充')
            ->assertSee('交換');
    }

    /**
     * 油の作業種類と量を保存し、確認画面とCSVに出力できることを確認する。
     */
    public function test_can_save_view_and_export_oil_amounts(): void
    {
        $this->post(route('inspections.periodic.store'), [
            'inspection_date' => '2026-06-30',
            'inspector' => '鈴木一郎',
            'results' => [
                'p-mixer1-reducer' => ['action' => 'refill', 'value' => '1.5'],
                'p-mixer2-pump' => ['value' => '6'],
            ],
        ])->assertRedirect();

        $record = InspectionRecord::ofType('periodic')->first();
        $this->assertSame('1.5', $record->results['p-mixer1-reducer']['value']);
        $this->assertSame('refill', $record->results['p-mixer1-reducer']['action']);
        $this->assertSame('replace', $record->results['p-mixer2-pump']['action']);

        $this->get(route('inspections.periodic.show', $record))->assertOk()->assertSee('1.5');

        $csv = $this->get(route('inspections.periodic.export'))->streamedContent();
        $this->assertStringContainsString('減速部', $csv);
        $this->assertStringContainsString('L', $csv);
    }

    /**
     * 数値でない量、許可されない作業、必要な入力の欠落を拒否することを確認する。
     */
    public function test_rejects_non_numeric_amount_and_empty_input(): void
    {
        $this->post(route('inspections.periodic.store'), [
            'inspection_date' => '2026-06-30',
            'inspector' => '鈴木',
            'results' => ['p-mixer1-reducer' => ['action' => 'refill', 'value' => 'abc']],
        ])->assertSessionHasErrors('results.p-mixer1-reducer.value');

        // 選択肢が複数ある機器は、量だけでは保存できない。
        $this->post(route('inspections.periodic.store'), [
            'inspection_date' => '2026-06-30',
            'inspector' => '鈴木',
            'results' => ['p-mixer1-reducer' => ['value' => '3']],
        ])->assertSessionHasErrors('results.p-mixer1-reducer.action');

        // 補充のみの機器に交換は指定できない。
        $this->post(route('inspections.periodic.store'), [
            'inspection_date' => '2026-06-30',
            'inspector' => '鈴木',
            'results' => ['p-mixer1-bearing' => ['action' => 'replace', 'value' => '3']],
        ])->assertSessionHasErrors('results.p-mixer1-bearing.action');

        $this->post(route('inspections.periodic.store'), [
            'inspection_date' => '2026-06-30',
            'inspector' => '鈴木',
        ])->assertSessionHasErrors('results');
    }
}
