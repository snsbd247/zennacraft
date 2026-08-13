<?php

namespace Tests\Feature\Category;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Catalog\Models\Category;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create([
            'name' => 'Owner', 'email' => 'cat-owner@zennacraft.test',
            'phone' => '+8801700000066', 'password' => 'Password123!', 'status' => 'active',
        ]);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_all_level_index_and_create_pages_render(): void
    {
        $owner = $this->owner();
        foreach (['main', 'sub', 'subsub'] as $lv) {
            $this->actingAs($owner, 'staff')->get(route("categories.$lv.index"))->assertOk();
            $this->actingAs($owner, 'staff')->get(route("categories.$lv.create"))->assertOk();
        }
    }

    public function test_main_and_sub_and_subsub_categories_create_with_correct_hierarchy(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'staff')->post(route('categories.main.store'), ['name' => 'Panjabi'])
            ->assertRedirect(route('categories.main.index'));
        $main = Category::where('name', 'Panjabi')->firstOrFail();
        $this->assertNull($main->parent_id);
        $this->assertSame('active', $main->status); // sensible defaults, not set on the form

        $this->actingAs($owner, 'staff')->post(route('categories.sub.store'), ['name' => 'Karchupi', 'parent_id' => $main->id])
            ->assertRedirect(route('categories.sub.index'));
        $sub = Category::where('name', 'Karchupi')->firstOrFail();
        $this->assertSame($main->id, $sub->parent_id);

        $this->actingAs($owner, 'staff')->post(route('categories.subsub.store'), ['name' => 'Hand Stitch', 'parent_id' => $sub->id])
            ->assertRedirect(route('categories.subsub.index'));
        $subsub = Category::where('name', 'Hand Stitch')->firstOrFail();
        $this->assertSame($sub->id, $subsub->parent_id);

        // level filtering: sub index shows Karchupi, subsub shows Hand Stitch
        $this->actingAs($owner, 'staff')->get(route('categories.sub.index'))->assertSee('Karchupi')->assertDontSee('Hand Stitch');
        $this->actingAs($owner, 'staff')->get(route('categories.subsub.index'))->assertSee('Hand Stitch');
    }

    public function test_toggle_and_discount_via_ajax(): void
    {
        $owner = $this->owner();
        $main = Category::create(['name' => 'Shirt', 'slug' => 'shirt', 'status' => 'active']);
        $sub = Category::create(['name' => 'Tee', 'slug' => 'tee', 'status' => 'active', 'parent_id' => $main->id]);

        $this->actingAs($owner, 'staff')->postJson(route('categories.toggle', $sub))
            ->assertOk()->assertJson(['status' => 'inactive']);
        $this->assertSame('inactive', $sub->fresh()->status);

        $this->actingAs($owner, 'staff')->postJson(route('categories.discount', $sub), ['discount_percent' => 25])
            ->assertOk()->assertJson(['discount_percent' => 25]);
        $this->assertEquals(25.0, (float) $sub->fresh()->discount_percent);
    }

    public function test_delete_is_blocked_while_children_exist(): void
    {
        $owner = $this->owner();
        $main = Category::create(['name' => 'Kids', 'slug' => 'kids', 'status' => 'active']);
        $sub = Category::create(['name' => 'Boys', 'slug' => 'boys', 'status' => 'active', 'parent_id' => $main->id]);

        $this->actingAs($owner, 'staff')->deleteJson(route('categories.destroy', $main))->assertStatus(422);
        $this->assertDatabaseHas('categories', ['id' => $main->id]);

        $this->actingAs($owner, 'staff')->deleteJson(route('categories.destroy', $sub))->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $sub->id]);
    }
}
