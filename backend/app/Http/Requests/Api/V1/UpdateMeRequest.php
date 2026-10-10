<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            // Açık rıza henüz verilmediyse zorunlu (M4 profil tamamlama).
            'kvkk_accepted' => [
                $this->user()->kvkk_accepted_at === null ? 'required' : 'sometimes',
                'accepted',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'ad soyad',
            'kvkk_accepted' => 'açık rıza onayı',
        ];
    }
}
