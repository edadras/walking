<?php

namespace Tests\Feature\Insights;

use App\Enums\AdminRole;
use App\Enums\TicketStatus;
use App\Filament\Admin\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Notifications\UserNotification;
use Database\Seeders\PlatformSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\SignsDeviceRequests;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    private function open(string $subject = 'امتیازم اضافه نشد')
    {
        return $this->authedJson('POST', '/api/v1/support/tickets', ['category' => 'points', 'subject' => $subject, 'body' => 'دیروز ۸ هزار قدم زدم ولی امتیازی نگرفتم.']);
    }

    public function test_user_opens_replies_and_sees_staff_answers_but_not_internal_notes(): void
    {
        $user = $this->loginAs();
        $this->authedJson('GET', '/api/v1/support/categories')->assertOk()->assertJsonFragment(['id' => 'points']);
        $id = $this->open()->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.messages.0.from', 'me')->json('data.id');

        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $agent = Admin::factory()->role(AdminRole::Support)->create();
        $this->actingAs($agent, 'admin');
        $this->get('/admin/support-tickets')->assertOk();
        Livewire::test(ViewSupportTicket::class, ['record' => $id])
            ->callAction('note', ['body' => 'سشن‌ها را بررسی کردم، در صف بررسی بودند.'])
            ->callAction('reply', ['body' => 'امتیاز شما پس از بررسی آزاد می‌شود.', 'status' => 'awaiting_user']);
        Notification::assertSentTo($user, UserNotification::class, fn ($n) => $n->category === 'support');

        $ticket = SupportTicket::query()->where('public_id', $id)->sole();
        $this->assertSame(TicketStatus::AwaitingUser, $ticket->status);
        $this->assertSame($agent->id, $ticket->assigned_admin_id);

        $res = $this->authedJson('GET', "/api/v1/support/tickets/{$id}")->assertOk();
        $this->assertSame(['me', 'support'], array_column($res->json('data.messages'), 'from'));
        $res->assertDontSee('سشن‌ها را بررسی کردم');

        $this->authedJson('POST', "/api/v1/support/tickets/{$id}/messages", ['body' => 'ممنون، درست شد'])->assertOk()->assertJsonPath('data.status', 'awaiting_support');
        $this->authedJson('POST', "/api/v1/support/tickets/{$id}/close")->assertOk()->assertJsonPath('data.can_reply', false);
        $this->authedJson('POST', "/api/v1/support/tickets/{$id}/messages", ['body' => 'یک سوال دیگر'])->assertStatus(409);
    }

    public function test_open_ticket_limit_and_privacy(): void
    {
        $this->loginAs('09121111111');
        foreach (range(1, 3) as $i) {
            $this->open("مشکل شماره {$i}")->assertCreated();
        }
        $this->open('مشکل چهارم')->assertStatus(409)->assertJsonPath('error.code', 'too_many_open');
        $id = SupportTicket::query()->value('public_id');

        $this->device = null;
        $this->deviceKey = null;
        $this->loginAs('09122222222');
        $this->authedJson('GET', "/api/v1/support/tickets/{$id}")->assertNotFound();
        $this->authedJson('GET', '/api/v1/support/tickets')->assertJsonCount(0, 'data');
    }

    public function test_only_support_roles_see_the_desk(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(Admin::factory()->role(AdminRole::StoreManager)->create(), 'admin');
        $this->get('/admin/support-tickets')->assertForbidden();
    }
}
