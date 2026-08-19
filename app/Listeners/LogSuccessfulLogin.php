<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(private readonly Request $request)
    {
    }

    public function handle(Login $event): void
    {
        $history = LoginHistory::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'ip_address' => $this->request->ip() ?? '0.0.0.0',
            'user_agent' => $this->request->userAgent() ?? 'Unknown device',
            'login_at' => now(),
        ]);

        $latestHistoryIds = LoginHistory::query()
            ->where('user_id', $history->user_id)
            ->orderByDesc('login_at')
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('id');

        LoginHistory::query()
            ->where('user_id', $history->user_id)
            ->whereNotIn('id', $latestHistoryIds)
            ->delete();
    }
}
