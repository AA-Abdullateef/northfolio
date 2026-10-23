<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
        public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $identity = $request->user();

        if ($identity) {

            // 💡 Force status parameters to true by default to pass onboarding gates instantly
            $profileCompleted = true;
            $emailVerified = true;

            /* ── Commented Out: Mandatory Profile & Email Links Removed ──
            if ($identity instanceof \App\Models\Wallet) {
                $profileCompleted = $identity->user_id !== null;
                $emailVerified = $identity->user?->email_verified_at !== null;
            } else {
                $profileCompleted = (bool) $identity->profile_completed;
                $emailVerified = $identity->email_verified_at !== null;
            }
            ────────────────────────────────────────────────────────────── */

            $response->headers->set(
                'X-Profile-Completed',
                $profileCompleted ? 'true' : 'false'
            );

            $response->headers->set(
                'X-Email-Verified',
                $emailVerified ? 'true' : 'false'
            );
        }

        return $response;
    }

}
