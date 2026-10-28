<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Notifikasi FCM opsional. Dulu dikirim langsung dari controller: tanpa kredensial Firebase atau dengan
 * token kosong request ikut gagal 500 (update resi & callback pembayaran), dan resi membaca kolom `fcm_id`
 * yang tidak ada sehingga pembeli tidak pernah menerima notifikasi.
 */
class PushNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('firebase.projects.app.credentials'));
    }

    public function send(?User $user, string $title, string $body): bool
    {
        if (! $user?->fcm_token || ! $this->isConfigured()) {
            return false;
        }

        try {
            app('firebase.messaging')->send(
                CloudMessage::new()
                    ->toToken($user->fcm_token)
                    ->withNotification(Notification::create($title, $body))
            );

            return true;
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim notifikasi FCM', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
