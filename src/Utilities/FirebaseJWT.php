<?php

declare(strict_types=1);

namespace App\Utilities;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class FirebaseJWT {
    private $secret_key;

    public function __construct()
    {
        $this->secret_key = $_ENV['JWT_SECRET_KEY'];
    }

    public function generate_token($id, $email) {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $payload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'data' => [
                'id' => $id,
                'email' => $email
            ]
        ];

        $token = JWT::encode($payload, $this->secret_key, 'HS256');
        return $token;
    }

    public function validate_token($token) {
        try {
            // Decode the JWT
            // $decoded = JWT::decode($jwt, $this->secretKey, ['HS256']);

            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));

            return (array) $decoded; // Return decoded data as an array
        } catch (Exception $e) {
            // Handle any exceptions (expired token, invalid signature, etc.)
            return [
                "error" => true,
                "message" => $e->getMessage()
            ];
        }
    }
}