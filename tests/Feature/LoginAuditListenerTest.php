<?php

namespace Tests\Feature;

use App\Listeners\LogSuccessfulLogin;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\UserAgentParser;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginAuditListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_records_login_context_and_keeps_only_latest_ten_records(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.25',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
        ]);

        $listener = new LogSuccessfulLogin($request);

        foreach (range(1, 11) as $number) {
            $listener->handle(new Login('web', $user, false));
        }

        $this->assertCount(10, LoginHistory::where('user_id', $user->id)->get());
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '203.0.113.25',
        ]);
    }

    public function test_user_agent_parser_returns_a_clear_platform_and_browser_label(): void
    {
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

        $this->assertSame('iPhone - Safari', UserAgentParser::describe($userAgent));
    }

    public function test_authentication_login_event_is_recorded_once(): void
    {
        $user = User::factory()->create();

        Auth::login($user);

        $this->assertSame(1, LoginHistory::where('user_id', $user->id)->count());
    }
}
