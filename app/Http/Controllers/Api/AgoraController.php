<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Agora\RtcTokenBuilder2;

class AgoraController extends Controller
{
    public function getToken(Request $request)
    {
        $channelName = $request->channelName;
        $uid = $request->uid;
        $role = $request->role;
        $publisher = $role === 'RolePublisher' ?
            RtcTokenBuilder2::ROLE_PUBLISHER
            :
            RtcTokenBuilder2::ROLE_SUBSCRIBER;
        $appID = config('laravel-agora.agora.app_id');
        $appCertificate = config('laravel-agora.agora.app_certificate');
        $tokenExpirationInSeconds = 3600;
        $privilegeExpirationInSeconds = 3600;

        $token = RtcTokenBuilder2::buildTokenWithUid(
            $appID,
            $appCertificate,
            $channelName,
            $uid,
            $publisher,
            $tokenExpirationInSeconds,
            $privilegeExpirationInSeconds
        );

        return response()->json(['token' => $token]);
    }
}
