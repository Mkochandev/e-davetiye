<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\Phone;
use App\Services\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30', new Phone(mobileOnly: true)],
        ];
    }

    /**
     * E.164 numara.
     */
    public function phone(): string
    {
        return app(PhoneNumber::class)->normalize($this->input('phone'));
    }
}
