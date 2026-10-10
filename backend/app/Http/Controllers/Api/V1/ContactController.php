<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Rehberdeki numaraların SHA-256 (E.164 metni) hash'lerinden kayıtlı olanları döner.
     * Hash'ler saklanmaz ve loglanmaz (spec §14).
     */
    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hashes' => ['required', 'array', 'max:2000'],
            'hashes.*' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ], [
            'hashes.max' => 'En fazla 2.000 numara eşleştirilebilir.',
            'hashes.*.regex' => 'Geçersiz numara özeti.',
        ]);

        $hashes = array_values(array_unique(array_map('strtolower', $data['hashes'])));

        $registered = User::query()
            ->whereIn('phone_hash', $hashes)
            ->pluck('phone_hash')
            ->values();

        return response()->json(['registered' => $registered]);
    }
}
