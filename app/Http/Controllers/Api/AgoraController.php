<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Agora\RtcTokenBuilder2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgoraController extends Controller
{
    /**
     * Token live streaming. Dulu siapa pun bisa meminta token publisher untuk channel toko mana pun, dan
     * tanpa kunci Agora token kosong tetap dikirim (app gagal tanpa pesan).
     */
    public function getToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channelName' => 'required|string|max:64',
            'uid' => 'required|integer|min:0',
            'role' => 'nullable|string',
        ]);

        $appId = config('laravel-agora.agora.app_id');
        $certificate = config('laravel-agora.agora.app_certificate');
        if (blank($appId) || blank($certificate)) {
            return response()->json(['status' => 'error', 'message' => 'Live streaming belum dikonfigurasi.'], 503);
        }

        $user = $request->user();
        $wantsPublisher = ($data['role'] ?? null) === 'RolePublisher';
        // App memakai nama toko tanpa spasi sebagai channel.
        $ownChannels = [(string) $user->id, str_replace(' ', '', trim($user->name))];
        if ($wantsPublisher && ! ($user->roles === User::SELLER && in_array($data['channelName'], $ownChannels, true))) {
            return response()->json(['status' => 'error', 'message' => 'Hanya pemilik toko yang bisa siaran di channel ini.'], 403);
        }

        $token = RtcTokenBuilder2::buildTokenWithUid(
            $appId,
            $certificate,
            $data['channelName'],
            $data['uid'],
            $wantsPublisher ? RtcTokenBuilder2::ROLE_PUBLISHER : RtcTokenBuilder2::ROLE_SUBSCRIBER,
            3600,
            3600,
        );

        return response()->json(['token' => $token]);
    }
}
