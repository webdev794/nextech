<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_admin_sets_a_categorys_details_and_used_ones_are_locked(): void
    {
        Sanctum::actingAs($this->admin());
        $category = Category::create(['name' => 'Drones', 'slug' => 'drones', 'is_active' => true]);

        $this->getJson("/api/admin/categories/{$category->id}/details")->assertOk()->assertJsonPath('data.own', false);

        $fields = $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => [
            ['label' => 'Flight time', 'type' => 'number', 'unit' => 'min', 'required' => true],
            ['label' => 'Camera', 'type' => 'select', 'options' => ['4K', '1080p']],
            ['label' => 'Foldable', 'type' => 'checkbox'],
            ['label' => 'Sensors', 'type' => 'multiselect', 'options' => ['GPS', 'Lidar']],
        ]])->assertOk()->assertJsonPath('data.own', true)->json('data.fields');
        $this->assertSame(['flight_time', 'camera', 'foldable', 'sensors'], array_column($fields, 'key'));

        // Sellers get them (with the common details after), and a yes/no checkbox accepts Yes/No.
        $attrs = ProductCatalog::attributesFor($category->fresh());
        $this->assertSame('flight_time', $attrs[0]['key']);
        $this->assertSame(['Yes', 'No'], $attrs[2]['options']);
        $this->assertContains('model_number', array_column($attrs, 'key'));
        $this->assertArrayHasKey('product_details.foldable', ProductCatalog::listingErrors(['product_details' => ['foldable' => 'Maybe']], $category->fresh(), [], [], true, false));

        Product::query()->forceCreate(['name' => 'Sky 1', 'slug' => 'sky-1', 'sku' => 'SKY-1', 'category_id' => $category->id, 'price_cents' => 1000, 'product_details' => ['camera' => '4K', 'flight_time' => '30']]);

        // Used detail can't go; used choice can't go; renaming is fine.
        $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => [$fields[1], $fields[2], $fields[3]]])
            ->assertStatus(422)->assertJsonFragment(['“Flight time” is used by 1 product — it can’t be removed.']);
        $fields[1]['options'] = ['1080p'];
        $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => $fields])
            ->assertStatus(422)->assertJsonFragment(['“Camera”: the choice “4K” is used by 1 product — it can’t be removed.']);
        $fields[1]['options'] = ['4K', '1080p', '8K'];
        $fields[0]['label'] = 'Max flight time';
        $fields[0]['type'] = 'text';
        $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => $fields])
            ->assertStatus(422)->assertJsonFragment(['“Max flight time” is used by products — its kind of box can’t change.']);
        $fields[0]['type'] = 'number';
        unset($fields[3]); // unused: can be removed
        $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => array_values($fields)])->assertOk()
            ->assertJsonPath('data.fields.0.label', 'Max flight time')->assertJsonPath('data.usage.camera.options.4K', 1);

        // Going back to the parent's list would drop details products use.
        $this->putJson("/api/admin/categories/{$category->id}/details", ['fields' => null])->assertStatus(422);
    }

    public function test_a_subcategory_uses_its_parents_list_until_it_has_its_own(): void
    {
        Sanctum::actingAs($this->admin());
        $parent = Category::create(['name' => 'Drones', 'slug' => 'drones', 'is_active' => true]);
        $child = Category::create(['name' => 'Racing drones', 'slug' => 'racing-drones', 'is_active' => true, 'parent_id' => $parent->id]);
        $this->putJson("/api/admin/categories/{$parent->id}/details", ['fields' => [['label' => 'Flight time', 'type' => 'number']]])->assertOk();

        $this->getJson("/api/admin/categories/{$child->id}/details")->assertOk()
            ->assertJsonPath('data.own', false)->assertJsonPath('data.inherited_from', 'Drones')->assertJsonPath('data.fields.0.key', 'flight_time');
        $this->assertSame('flight_time', ProductCatalog::attributesFor($child->fresh())[0]['key']);
    }

    public function test_admin_edits_the_common_details_every_category_gets(): void
    {
        Sanctum::actingAs($this->admin());
        $category = Category::create(['name' => 'Drones', 'slug' => 'drones', 'is_active' => true]);
        $fields = $this->getJson('/api/admin/categories/common-details')->assertOk()->json('data.fields');

        // Core details can't go; one that another depends on can't go.
        $without = array_values(array_filter($fields, fn ($f) => $f['key'] !== 'warranty'));
        $this->putJson('/api/admin/categories/common-details', ['fields' => $without])->assertStatus(422);
        $without = array_values(array_filter($fields, fn ($f) => $f['key'] !== 'power_source'));
        $this->putJson('/api/admin/categories/common-details', ['fields' => $without])->assertStatus(422);

        // Add one, remove an unused one: every category now asks for it.
        $next = array_values(array_filter($fields, fn ($f) => $f['key'] !== 'special_features'));
        $next[] = ['label' => 'Country of assembly', 'type' => 'text'];
        $this->putJson('/api/admin/categories/common-details', ['fields' => $next])->assertOk();
        $keys = array_column(ProductCatalog::attributesFor($category->fresh()), 'key');
        $this->assertContains('country_of_assembly', $keys);
        $this->assertNotContains('special_features', $keys);
    }
}
