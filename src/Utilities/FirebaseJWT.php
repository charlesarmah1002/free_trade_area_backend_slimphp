<?php

declare(strict_types=1);

namespace App\Utilities;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class FirebaseJWT
{
    private $secret_key;

    public function __construct()
    {
        $this->secret_key = $_ENV['JWT_SECRET_KEY'];
    }

    public function generate_token($id, $email, $role)
    {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $refresherPayload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'type' => 'access',
            'data' => [
                'id' => $id,
                'email' => $email,
                'role' => $role
            ]
        ];

        $refreshToken = JWT::encode($refresherPayload, $this->secret_key, 'HS256');
        return [
            "access_token" => $this->generate_access_token(), 
            "refresh_token" => $refreshToken
        ];
    }

    public function generate_access_token() {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $accessPayload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'type' => 'access'
        ];

        $accessToken = JWT::encode($accessPayload, $this->secret_key, 'HS256');
        return $accessToken;
    }

    public function validate_token($token)
    {
        try {
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