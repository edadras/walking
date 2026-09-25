<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notification\PushePushSender;
use App\Domain\Notification\PushSender;
use App\Domain\Notification\RoutingPushSender;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushSendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pushe_targets_the_device_id_and_carries_routing_data(): void
    {
        Http::fake([PushePushSender::URL => Http::response(['hashed_id' => 'x'], 201)]);
        $device = Device::factory()->create(['push_provider' => 'pushe', 'push_token' => 'android-id-123']);

        (new PushePushSender('api-token', 'app-id'))->send($device, 'سفارش', 'ارسال شد', ['type' => 'order', 'id' => 'o1']);

        Http::assertSent(fn (Request $r) => $r->url() === PushePushSender::URL
            && $r->header('Authorization') === ['Token api-token']
            && $r['app_ids'] === 'app-id'
            && $r['data'] === ['title' => 'سفارش', 'content' => 'ارسال شد']
            && (array) $r['custom_content'] === ['type' => 'order', 'id' => 'o1']
            && $r['filters'] === ['device_id' => ['android-id-123']]);
    }

    public function test_pushe_ignores_devices_registered_elsewhere(): void
    {
        Http::fake();
        $device = Device::factory()->create(['push_provider' => 'fcm', 'push_token' => 'fcm-token']);
        (new PushePushSender('t', 'a'))->send($device, 't', 'b');
        Http::assertNothingSent();
    }

    public function test_routing_picks_the_devices_provider(): void
    {
        $sent = new \ArrayObject;
        $spy = fn (string $name) => new class($name, $sent) implements PushSender
        {
            public function __construct(private string $name, private \ArrayObject $sent) {}

            public function send(Device $device, string $title, string $body, array $data = []): void
            {
                $this->sent[] = $this->name;
            }
        };
        $router = new RoutingPushSender(['fcm' => $spy('fcm'), 'pushe' => $spy('pushe')], $spy('log'));

        foreach (['pushe', 'fcm', 'unknown'] as $provider) {
            $router->send(Device::factory()->create(['push_provider' => $provider, 'push_token' => 't']), 't', 'b');
        }
        $this->assertSame(['pushe', 'fcm', 'log'], $sent->getArrayCopy());
    }
}
