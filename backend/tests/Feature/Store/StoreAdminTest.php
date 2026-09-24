<?php

namespace Tests\Feature\Store;

use App\Domain\Store\OrderService;
use App\Domain\Wallet\WalletService;
use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Filament\Admin\Resources\Products\RelationManagers\CodesRelationManager;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesProducts;
use Tests\TestCase;

class StoreAdminTest extends TestCase
{
    use CreatesProducts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        FeatureFlag::query()->updateOrCreate(['key' => 'store'], ['is_enabled' => true, 'rollout_percent' => 100]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_store_manager_manages_catalogue_and_imports_codes_without_seeing_them(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::StoreManager)->create(), 'admin');
        $product = $this->codeProduct(1);

        foreach (['/admin/products', '/admin/products/create', '/admin/categories', '/admin/orders', "/admin/products/{$product->slug}/edit"] as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(CodesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('import', data: ['codes' => "AAA-111\nBBB-222\nAAA-111\nGIFT-{$product->id}-1"])
            ->assertNotified()
            ->assertDontSee('AAA-111');

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertTrue(AuditLog::query()->where('action', 'product.codes_imported')->exists());
    }

    public function test_order_fulfilment_actions(): void
    {
        $admin = Admin::factory()->role(AdminRole::StoreManager)->create();
        $this->actingAs($admin, 'admin');
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 500, TransactionType::Adjustment, 'seed', 'seed');
        $product = $this->product(['type' => 'service', 'stock' => null, 'point_price' => 200]);
        [$order] = app(OrderService::class)->place($user, [['product_id' => $product->public_id, 'quantity' => 1]], 'k-'.str_repeat('a', 20));

        $this->get("/admin/orders/{$order->public_id}")->assertOk()->assertSee($product->name);
        Livewire::test(ViewOrder::class, ['record' => $order->public_id])->callAction('processing');
        Livewire::test(ViewOrder::class, ['record' => $order->public_id])->callAction('refund', ['note' => 'ناموجود']);

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(500, $user->wallet->fresh()->available_balance);
    }

    public function test_other_roles_cannot_open_the_store_admin(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::ContentEditor)->create(), 'admin');
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
    }
}
