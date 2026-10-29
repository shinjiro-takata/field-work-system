<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\ProductionLog;
use App\Models\ProductionLogEditLog;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighingLogEditTest extends TestCase
{
    use RefreshDatabase;

    // 計量ログの修正、修正者と変更前後の保存、画面への変更履歴表示を確認します。保存内容と履歴が一致することが期待結果です。
    public function test_log_can_be_edited_and_the_editor_and_changes_are_recorded(): void
    {
        // 登録者の計量ログを準備し、別の作業者としてログインします。
        $creator = User::factory()->create(['name' => '登録者']);
        $editor = User::factory()->create(['name' => '修正者']);
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $creator->id]);
        // 材料をレシピに含めておく（含めないと「レシピ外材料」として扱われ、通常入力の対象にならない）
        $recipe->materials()->attach($material->id, ['quantity' => 1.5, 'tolerance' => 0.1]);
        $log = ProductionLog::create([
            'user_id' => $creator->id,
            'recipe_id' => $recipe->id,
            'weighed_at' => '2026-10-01 09:00:00',
            'notes' => '元の備考',
        ]);
        $log->materials()->attach([$material->id => ['actual_quantity' => 1.5]]);

        $this->actingAs($editor)
            ->get(route('logs.edit', $log))
            ->assertOk()
            ->assertSee('Material A');

        // 作業者が計量時刻・備考・実使用量を修正します。
        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T10:30',
            'notes' => '修正後の備考',
            'materials' => [$material->id => 2.25],
        ])->assertRedirect(route('logs.index'));

        // 保存値と監査記録に、修正内容・修正者・変更前後が記録されたことを確認します。
        $log->refresh()->load('materials');
        $this->assertSame('修正後の備考', $log->notes);
        $this->assertEquals(2.25, (float) $log->materials->first()->pivot->actual_quantity);

        $editLog = ProductionLogEditLog::firstOrFail();
        $this->assertSame('修正者', $editLog->editor_name);
        $this->assertEquals(1.5, $editLog->before_data['materials'][0]['actual_quantity']);
        $this->assertEquals(2.25, $editLog->after_data['materials'][0]['actual_quantity']);

        // 編集画面と一覧画面でも、記録した修正情報が利用者に表示されることを確認します。
        $this->get(route('logs.edit', $log))
            ->assertOk()
            ->assertSee('修正者')
            ->assertSee('1.500 → 2.250', false);

        $this->get(route('logs.index'))->assertOk()->assertSee('修正済み');
    }

    // レシピ外の材料を理由付きで登録でき、編集で削除できることを確認します。理由なしや重複材料はエラーになることが期待結果です。
    public function test_materials_outside_the_recipe_can_be_added_on_create_and_edit_with_a_reason(): void
    {
        $user = User::factory()->create(['name' => '作業者']);
        $inRecipe = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $outside = Material::create(['name' => 'Material X', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $user->id]);
        $recipe->materials()->attach($inRecipe->id, ['quantity' => 1, 'tolerance' => 0.1]);
        $this->actingAs($user);

        $payload = [
            'recipe_id' => $recipe->id,
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$inRecipe->id => 1],
            'extra_materials' => [['material_id' => $outside->id, 'quantity' => 0.5]],
        ];

        // 理由がない場合は登録を拒否し、記録を作成しないことを確認します。
        $this->post(route('logs.store'), $payload)->assertSessionHasErrors('notes');
        $this->assertSame(0, ProductionLog::count());

        // 理由を加えると登録でき、レシピ外の材料として一覧に表示されることを確認します。
        $this->post(route('logs.store'), $payload + ['notes' => '誤って使用'])->assertRedirect(route('logs.index'));
        $log = ProductionLog::firstOrFail();
        $this->assertEquals(0.5, (float) $log->materials()->find($outside->id)->pivot->actual_quantity);
        $this->get(route('logs.index'))->assertOk()->assertSee('レシピ外');

        // 編集でレシピ外材料を外すと記録から削除され、変更内容が編集画面に表示されることを確認します。
        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'notes' => '誤って使用',
            'materials' => [$inRecipe->id => 1],
        ])->assertRedirect(route('logs.index'));
        $this->assertNull($log->materials()->find($outside->id));
        $this->get(route('logs.edit', $log))->assertSee('削除（0.500 kg）', false);

        // レシピ内の材料をレシピ外材料として重複登録する操作は拒否されることを確認します。
        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$inRecipe->id => 1],
            'extra_materials' => [['material_id' => $inRecipe->id, 'quantity' => 1]],
        ])->assertSessionHasErrors();
    }

    // 計量ログを変更せずに保存した場合の監査記録を確認します。変更記録が追加されないことが期待結果です。
    public function test_unchanged_submission_does_not_create_an_edit_record(): void
    {
        $user = User::factory()->create();
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $user->id]);
        // 材料をレシピに含めておく（含めないと「レシピ外材料」として扱われる）
        $recipe->materials()->attach($material->id, ['quantity' => 1.5, 'tolerance' => 0.1]);
        $log = ProductionLog::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'weighed_at' => '2026-10-01 09:00:00',
        ]);
        $log->materials()->attach([$material->id => ['actual_quantity' => 1.5]]);

        $this->actingAs($user)->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$material->id => 1.5],
        ])->assertRedirect(route('logs.index'));

        $this->assertSame(0, ProductionLogEditLog::count());
    }
}
