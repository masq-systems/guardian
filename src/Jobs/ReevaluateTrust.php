<?php

declare(strict_types=1);

namespace Guardian\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Guardian\Facades\Guardian;
use Guardian\Models\SuspicionEvent;
use Guardian\Models\TrustProfile;

class ReevaluateTrust implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        TrustProfile::query()
            ->whereNot('state', 'trusted')
            ->whereNull('banned_at')
            ->with('subject')
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    if ($profile->subject !== null) {
                        Guardian::reassess($profile->subject, $profile->track);
                    }
                }
            });

        $this->prune();
    }

    private function prune(): void
    {
        $keepDays = config('guardian.prune_after_days');

        if (! is_numeric($keepDays)) {
            return;
        }

        SuspicionEvent::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', CarbonImmutable::now()->subDays((int) $keepDays))
            ->delete();
    }
}
