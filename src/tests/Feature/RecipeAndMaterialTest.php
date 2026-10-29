<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\ResinUsageItem;
use App\Models\ResinUsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// レシピ（登録・更新・削除）と材料マスタの管理画面の動作を確認するテストです。
class RecipeAndMaterialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $this->actingAs($this->admin);
    }

    // 既存の材料を選んでレシピを登録すると、基準量と許容量が中間テーブルに保存されることを確認します。
    public function test_recipe_can_be_created_with_quantity_and_tolerance(): void
    {
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);

        $this->post(route('recipes.store'), [
            'name' => 'レシピ1',
            'material_ids' => [$material->id],
            'materials' => [$material->id => 5],
            'tolerances' => [$material->id => 0.2],
        ])->assertRedirect(route('recipes.index'));

        $recipe = Recipe::where('name', 'レシピ1')->firstOrFail();
        $pivot = $recipe->materials()->firstOrFail()->pivot;
        $this->assertEquals(5, (float) $pivot->quantity);
        $this->assertEquals(0.2, (float) $pivot->tolerance);
    }

    // レシピ登録と同時に入力した新規材料が、材料マスタにも登録されレシピに含まれることを確認します。
    public function test_recipe_creation_can_add_a_new_material_at_the_same_time(): void
    {
        $existing = Material::create(['name' => 'Material A', 'unit' => 'kg']);

        $this->post(route('recipes.store'), [
            'name' => 'レシピ2',
            'material_ids' => [$existing->id],
            'materials' => [$existing->id => 1],
            'new_material_name' => '新材料',
            'new_material_unit' => 'g',
            'new_material_quantity' => 3,
            'new_material_tolerance' => 0.5,
        ])->assertRedirect(route('recipes.index'));

        $newMaterial = Material::where('name', '新材料')->firstOrFail();
        $recipe = Recipe::where('name', 'レシピ2')->firstOrFail();
        $this->assertCount(2, $recipe->materials);
        $pivot = $recipe->materials->firstWhere('id', $newMaterial->id)->pivot;
        $this->assertEquals(3, (float) $pivot->quantity);
        $this->assertEquals(0.5, (float) $pivot->tolerance);
    }

    // 材料なし・レシピ名の重複は拒否されることを確認します。
    public function test_recipe_creation_rejects_missing_materials_and_duplicate_names(): void
    {
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        Recipe::create(['name' => '既存レシピ', 'created_by' => $this->admin->id]);

        $this->post(route('recipes.store'), ['name' => '材料なし'])
            ->assertSessionHasErrors(['material_ids', 'materials']);

        $this->post(route('recipes.store'), [
            'name' => '既存レシピ',
            'material_ids' => [$material->id],
            'materials' => [$material->id => 1],
        ])->assertSessionHasErrors('name');
    }

    // レシピ更新では、チェックを外した材料が外れ、量が更新されることを確認します。
    public function test_recipe_update_replaces_the_material_list(): void
    {
        $materialA = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $materialB = Material::create(['name' => 'Material B', 'unit' => 'kg']);
        $recipe = Recipe::create(['name' => 'レシピ', 'created_by' => $this->admin->id]);
        $recipe->materials()->attach([
            $materialA->id => ['quantity' => 1, 'tolerance' => 0.1],
            $materialB->id => ['quantity' => 2, 'tolerance' => 0.1],
        ]);

        $this->put(route('recipes.update', $recipe), [
            'name' => 'レシピ（更新）',
            'material_ids' => [$materialA->id],
            'materials' => [$materialA->id => 4],
            'tolerances' => [$materialA->id => 0.3],
        ])->assertRedirect(route('recipes.index'));

        $recipe->refresh()->load('materials');
        $this->assertSame('レシピ（更新）', $recipe->name);
        $this->assertCount(1, $recipe->materials);
        $this->assertEquals(4, (float) $recipe->materials->first()->pivot->quantity);
    }

    // レシピを削除できることを確認します。
    public function test_recipe_can_be_deleted(): void
    {
        $recipe = Recipe::create(['name' => '削除対象', 'created_by' => $this->admin->id]);

        $this->delete(route('recipes.destroy', $recipe))->assertRedirect(route('recipes.index'));
        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    }

    // 一般の作業者でもレシピを閲覧できるが、登録はできないことを確認します。
    public function test_worker_can_view_but_not_create_recipes(): void
    {
        $recipe = Recipe::create(['name' => '閲覧用', 'created_by' => $this->admin->id]);
        $worker = User::factory()->create(['is_admin' => false]);
        $this->actingAs($worker);

        $this->get(route('recipes.index'))->assertOk()->assertSee('閲覧用');
        $this->get(route('recipes.show', $recipe))->assertOk();
        $this->post(route('recipes.store'), ['name' => 'x'])->assertForbidden();
    }

    // 材料の登録で、必須・重複が拒否され、正しい入力は保存されることを確認します。
    public function test_material_creation_validates_and_saves(): void
    {
        Material::create(['name' => '既存材料', 'unit' => 'kg']);

        $this->post(route('materials.store'), ['name' => '', 'unit' => ''])
            ->assertSessionHasErrors(['name', 'unit']);
        $this->post(route('materials.store'), ['name' => '既存材料', 'unit' => 'kg'])
            ->assertSessionHasErrors('name');

        $this->post(route('materials.store'), ['name' => '新しい材料', 'unit' => 'g'])
            ->assertRedirect(route('materials.index'));
        $this->assertDatabaseHas('materials', ['name' => '新しい材料', 'unit' => 'g']);
    }

    // レジン使用量の履歴がある材料は、単位変更と削除ができないことを確認します。
    public function test_material_used_in_resin_history_cannot_change_unit_or_be_deleted(): void
    {
        $material = Material::create(['name' => 'Material A', 'unit' => 'kg']);
        $this->createResinHistoryFor($material);

        $this->put(route('materials.update', $material), ['name' => 'Material A', 'unit' => 'g'])
            ->assertSessionHasErrors('unit');
        $this->assertSame('kg', $material->fresh()->unit);

        $this->delete(route('materials.destroy', $material))
            ->assertSessionHas('status', 'レジン使用量の履歴がある材料は削除できません。');
        $this->assertDatabaseHas('materials', ['id' => $material->id]);
    }

    // 履歴のない材料は、単位変更も削除もできることを確認します。
    public function test_unused_material_can_change_unit_and_be_deleted(): void
    {
        $material = Material::create(['name' => 'Material B', 'unit' => 'kg']);

        $this->put(route('materials.update', $material), ['name' => 'Material B', 'unit' => 'g'])
            ->assertRedirect(route('materials.index'));
        $this->assertSame('g', $material->fresh()->unit);

        $this->delete(route('materials.destroy', $material))->assertRedirect(route('materials.index'));
        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    // レジン使用量の履歴（記録と明細）を直接作成するための補助メソッドです。
    private function createResinHistoryFor(Material $material): void
    {
        $record = ResinUsageRecord::create([
            'usage_date' => '2026-10-01',
            'operator_name' => '作業者',
            'created_by' => $this->admin->id,
        ]);
        $record->items()->create([
            'material_id' => $material->id,
            'opening_stock' => 10,
            'used_quantity' => 1,
            'replenished_quantity' => 0,
            'calculated_stock' => 9,
            'actual_stock' => 9,
            'closing_stock' => 9,
            'adjustment_quantity' => 0,
        ]);
    }
}
