<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use App\Services\PhoneLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function requestOtp(RequestOtpRequest $request, OtpService $otp): Response
    {
        $otp->send($request->phone(), $request->ip());

        return response()->noContent();
    }

    public function verify(VerifyOtpRequest $request, PhoneLogin $login): JsonResponse
    {
        $result = $login->login(
            $request->phone(),
            $request->string('code')->toString(),
            $request->string('device_name')->toString(),
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'is_new' => $result['is_new'],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
