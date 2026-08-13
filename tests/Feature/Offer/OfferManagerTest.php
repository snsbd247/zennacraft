<?php

namespace Tests\Feature\Offer;

use App\Modules\AdminAuth\Models\Role;
use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Promotion\Models\Offer;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesPermissionSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): StaffUser
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesPermissionSeeder::class);
        $staff = StaffUser::create(['name' => 'Offer Owner', 'email' => 'offer-owner@zennacraft.test', 'phone' => '+8801700000122', 'password' => 'Password123!', 'status' => 'active']);
        $staff->roles()->attach(Role::where('slug', 'owner')->firstOrFail());

        return $staff;
    }

    public function test_index_and_create_render(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->get(route('offers.index'))->assertOk()->assertSee('Offers');
        $this->actingAs($owner, 'staff')->get(route('offers.create'))->assertOk()->assertSee('Where it shows');
    }

    public function test_store_creates_offer_with_placement(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'staff')->post(route('offers.store'), [
            'name' => 'Free Mustard Oil', 'placement' => 'cart_free_gift',
            'threshold_amount' => 3000, 'reward_text' => '500ml Mustard Oil', 'active' => '1',
        ])->assertRedirect(route('offers.index'));

        $offer = Offer::where('name', 'Free Mustard Oil')->firstOrFail();
        $this->assertSame('cart_free_gift', $offer->placement);
        $this->assertEquals(3000.0, (float) $offer->threshold_amount);
        $this->assertTrue($offer->active);
    }

    public function test_active_for_returns_top_active_offer(): void
    {
        $this->owner();
        Offer::create(['name' => 'Off', 'placement' => 'cart_free_gift', 'threshold_amount' => 1000, 'active' => false, 'sort_order' => 0]);
        $on = Offer::create(['name' => 'On', 'placement' => 'cart_free_gift', 'threshold_amount' => 2000, 'active' => true, 'sort_order' => 1]);
        $this->assertSame($on->id, Offer::activeFor('cart_free_gift')->id);
    }

    public function test_toggle_and_destroy(): void
    {
        $owner = $this->owner();
        $offer = Offer::create(['name' => 'X', 'placement' => 'cart_free_gift', 'threshold_amount' => 500, 'active' => true]);
        $this->actingAs($owner, 'staff')->postJson(route('offers.toggle', $offer))->assertOk()->assertJson(['active' => false]);
        $this->actingAs($owner, 'staff')->deleteJson(route('offers.destroy', $offer))->assertOk();
        $this->assertDatabaseMissing('offers', ['id' => $offer->id]);
    }
}
