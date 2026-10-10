<?php

namespace App\Filament\Salon\Pages\Auth;

use App\Services\PhoneNumber;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Tek alan: `@` içeriyorsa e-posta, değilse telefon olarak normalize edilir (spec §7).
 */
class SalonLogin extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('E-posta veya telefon')
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim($data['login']);

        if (str_contains($login, '@')) {
            return ['email' => mb_strtolower($login), 'password' => $data['password']];
        }

        // Geçersiz numarada da aynı genel hata: hangi hesapların var olduğu belli olmasın.
        $phone = app(PhoneNumber::class)->tryNormalize($login) ?? $this->throwFailureValidationException();

        return ['phone' => $phone, 'password' => $data['password']];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
