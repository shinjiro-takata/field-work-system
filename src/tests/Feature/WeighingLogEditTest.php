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

    public function test_log_can_be_edited_and_the_editor_and_changes_are_recorded(): void
    {
        $creator = User::factory()->create(['name' => '登録者']);
        $editor = User::factory()->create(['name' => '修正者']);
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $creator->id]);
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

        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T10:30',
            'notes' => '修正後の備考',
            'materials' => [$material->id => 2.25],
        ])->assertRedirect(route('logs.index'));

        $log->refresh()->load('materials');
        $this->assertSame('修正後の備考', $log->notes);
        $this->assertEquals(2.25, (float) $log->materials->first()->pivot->actual_quantity);

        $editLog = ProductionLogEditLog::firstOrFail();
        $this->assertSame('修正者', $editLog->editor_name);
        $this->assertEquals(1.5, $editLog->before_data['materials'][0]['actual_quantity']);
        $this->assertEquals(2.25, $editLog->after_data['materials'][0]['actual_quantity']);

        $this->get(route('logs.edit', $log))
            ->assertOk()
            ->assertSee('修正者')
            ->assertSee('1.500 → 2.250', false);

        $this->get(route('logs.index'))->assertOk()->assertSee('修正済み');
    }

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

        $this->post(route('logs.store'), $payload)->assertSessionHasErrors('notes');
        $this->assertSame(0, ProductionLog::count());

        $this->post(route('logs.store'), $payload + ['notes' => '誤って使用'])->assertRedirect(route('logs.index'));
        $log = ProductionLog::firstOrFail();
        $this->assertEquals(0.5, (float) $log->materials()->find($outside->id)->pivot->actual_quantity);
        $this->get(route('logs.index'))->assertOk()->assertSee('レシピ外');

        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'notes' => '誤って使用',
            'materials' => [$inRecipe->id => 1],
        ])->assertRedirect(route('logs.index'));
        $this->assertNull($log->materials()->find($outside->id));
        $this->get(route('logs.edit', $log))->assertSee('削除（0.500 kg）', false);

        $this->put(route('logs.update', $log), [
            'weighed_at' => '2026-10-01T09:00',
            'materials' => [$inRecipe->id => 1],
            'extra_materials' => [['material_id' => $inRecipe->id, 'quantity' => 1]],
        ])->assertSessionHasErrors();
    }

    public function test_unchanged_submission_does_not_create_an_edit_record(): void
    {
        $user = User::factory()->create();
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'Recipe', 'created_by' => $user->id]);
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
