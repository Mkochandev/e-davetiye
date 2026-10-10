<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    /**
     * FCM token'ını kaydeder. Token başka kullanıcıdaysa (aynı cihazda hesap değişimi) bu kullanıcıya taşınır.
     */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'platform' => ['required', Rule::enum(Platform::class)],
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        Device::updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->noContent();
    }
}
