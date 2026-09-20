<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsSender
{
    public function send(string $phone, string $message, ?string $reference = null): void
    {
        $driver = config('saklshi.sms.driver', 'log');

        if ($driver === 'log') {
            Log::info('SMS suppressed by log driver', [
                'phone' => $phone,
                'reference' => $reference,
                'message' => app()->environment('testing') ? $message : '[redacted]',
            ]);
            return;
        }

        if ($driver !== 'smsoffice') {
            throw new RuntimeException('Unsupported SMS driver.');
        }

        $key = (string) config('saklshi.sms.key');
        $sender = (string) config('saklshi.sms.sender');

        if ($key === '' || $sender === '') {
            throw new RuntimeException('SMS provider is not configured.');
        }

        $destination = ltrim(preg_replace('/\D+/', '', $phone), '0');

        $payload = [
            'key' => $key,
            'destination' => $destination,
            'sender' => $sender,
            'content' => $message,
        ];

        if ($reference) {
            $payload['reference'] = mb_substr($reference, 0, 20);
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(8)
            ->retry(2, 250)
            ->post('https://smsoffice.ge/api/v2/send/', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('SMS provider request failed.');
        }

        $data = $response->json();

        if (! is_array($data) || ($data['Success'] ?? false) !== true) {
            throw new RuntimeException('SMS provider rejected the message.');
        }
    }
}
