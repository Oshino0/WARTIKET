<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_banner_with_crop_position(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('Atur bagian gambar yang terlihat');

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'title' => 'Festival',
                'image' => UploadedFile::fake()->image('festival.jpg', 1200, 400),
                'link_url' => 'https://example.test/festival',
                'sort_order' => 1,
                'image_position_x' => 72,
                'image_position_y' => 35,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Banner ditambahkan.');

        $banner = Banner::sole();

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'sort_order' => 1,
            'image_position_x' => 72,
            'image_position_y' => 35,
        ]);
        Storage::disk('public')->assertExists($banner->image);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('object-position: 72% 35%;', false);
    }

    public function test_admin_can_edit_banner_order_crop_and_image(): void
    {
        Storage::fake('public');
        $oldImage = 'banners/old.jpg';
        Storage::disk('public')->put($oldImage, 'old image');
        $banner = Banner::create([
            'title' => 'Old banner',
            'image' => $oldImage,
            'sort_order' => 1,
            'image_position_x' => 50,
            'image_position_y' => 50,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.banners.edit', $banner))
            ->assertOk()
            ->assertSee('Ubah banner')
            ->assertSee('value="50"', false);

        $this->put(route('admin.banners.update', $banner), [
            'title' => 'Updated banner',
            'link_url' => 'https://example.test/updated',
            'sort_order' => 3,
            'image_position_x' => 25,
            'image_position_y' => 65,
        ])
            ->assertRedirect(route('admin.banners.index'))
            ->assertSessionHas('status', 'Banner diperbarui.');

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id,
            'title' => 'Updated banner',
            'image' => $oldImage,
            'sort_order' => 3,
            'image_position_x' => 25,
            'image_position_y' => 65,
        ]);
        Storage::disk('public')->assertExists($oldImage);

        $this->put(route('admin.banners.update', $banner), [
            'title' => 'Updated banner',
            'link_url' => 'https://example.test/updated',
            'sort_order' => 3,
            'image_position_x' => 25,
            'image_position_y' => 65,
            'image' => UploadedFile::fake()->image('replacement.jpg', 1200, 400),
        ])->assertRedirect(route('admin.banners.index'));

        $updatedBanner = $banner->fresh();
        $this->assertNotSame($oldImage, $updatedBanner->image);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($updatedBanner->image);
    }

    public function test_equal_banner_orders_remain_equal_and_are_displayed_by_id(): void
    {
        $first = Banner::create([
            'title' => 'First banner',
            'image' => 'banners/first.jpg',
            'sort_order' => 1,
        ]);
        $second = Banner::create([
            'title' => 'Second banner',
            'image' => 'banners/second.jpg',
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['First banner', 'Second banner']);

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password12',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
