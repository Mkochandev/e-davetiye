<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * @param  string  $phone  E.164
     */
    public function send(string $phone, string $message): void;
}
