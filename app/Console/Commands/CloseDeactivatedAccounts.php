<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloseDeactivatedAccounts extends Command
{
    protected $signature = 'accounts:close-deactivated';

    protected $description = 'Permanently close accounts that have been deactivated for at least 30 days.';

    public function handle(): int
    {
        $closed = 0;

        User::query()
            ->where('account_status', 'deactivated')
            ->where('deactivated_at', '<=', now()->subDays(30))
            ->orderBy('id')
            ->each(function (User $user) use (&$closed): void {
                if ($user->photo) {
                    Storage::disk('public')->delete($user->photo);
                }

                $user->update([
                    'name' => 'Deactivated User',
                    'email' => "closed-{$user->id}-".Str::lower(Str::random(12)).'@greenpool.invalid',
                    'password' => Str::random(64),
                    'auth_provider' => 'closed',
                    'google_id' => null,
                    'photo' => null,
                    'phone_number' => null,
                    'phone_verified_at' => null,
                    'remember_token' => null,
                    'account_status' => 'permanently_closed',
                    'permanently_closed_at' => now(),
                ]);

                $closed++;
            });

        $this->info("Permanently closed {$closed} account(s).");

        return self::SUCCESS;
    }
}
