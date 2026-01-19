<?php

namespace App\Service;

class JwtService
{
    private string $secret = 'CHANGE_ME_SECRET_KEY';


    public function generate(array $payload, int $ttl = 3600): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + $ttl;
        $payload = base64_encode(json_encode($payload));

        $signature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        $signature = base64_encode($signature);

        return "$header.$payload.$signature";
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $validSignature = base64_encode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($validSignature, $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);

        if ($data['exp'] < time()) {
            return null;
        }

        return $data;
    }
}
