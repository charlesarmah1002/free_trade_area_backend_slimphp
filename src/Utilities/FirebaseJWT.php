<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Models\BusinessAccount;
use App\Models\UserRefreshTokens;
use Cloudinary\Exception\Error;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;
use App\Models\BusinessRefreshTokens;

class FirebaseJWT
{
    private $secret_key;
    private $token_signature;

    public function __construct()
    {
        $this->secret_key = $_ENV['JWT_SECRET_KEY'];
        $this->token_signature = $_ENV['TOKEN_SIGNATURE'];
    }

    public function generate_token($id, $type)
    {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $refresherPayload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'type' => 'refresh'
        ];

        $refreshToken = JWT::encode($refresherPayload, $this->secret_key, 'HS256');
        $token_hash = hash_hmac('sha256', $refreshToken, $this->token_signature);

        // record acess tokens into database
        if ($type == "business") {
            $refreshToken = BusinessRefreshTokens::create([
                'business_id' => $id,
                'token_hash' => $token_hash
            ]);
        } else if ($type == "user") {
            $refreshToken = UserRefreshTokens::create([
                'user_id' => $id,
                'token_hash' => $token_hash
            ]);
        }

        $refreshId = $refreshToken->id;

        return [
            "access_token" => $this->generate_access_token($refreshId, $type),
            "refresh_token" => $token_hash
        ];
    }

    public function generate_access_token($id, $identifier)
    {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $accessPayload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'type' => 'access',
            'id' => $id,
            'identifier' => $identifier
        ];

        $accessToken = JWT::encode($accessPayload, $this->secret_key, 'HS256');
        return $accessToken;
    }

    public function validate_token($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, 'HS256'));

            if ($decoded->type != 'access') {
                throw new Error("Invalid token type");
            }

            return (array) $decoded; // Return decoded data as an array
        } catch (Exception $e) {
            // Handle any exceptions (expired token, invalid signature, etc.)
            return [
                "error" => true,
                "message" => $e->getMessage()
            ];
        }
    }

    public function validate_refresh_token(string $hashed_token, int $id, string $type): array
    {
        try {
            // Resolve model dynamically
            switch ($type) {
                case 'business':
                    $model = new BusinessRefreshTokens();
                    $id_field = 'business_id';
                    break;

                case 'user':
                    $model = new UserRefreshTokens();
                    $id_field = 'user_id';
                    break;

                default:
                    throw new Exception('Invalid validation request');
            }

            $token_data = $model->where('id', $id)->first();

            if (!$token_data) {
                throw new Exception('Invalid token');
            }

            // Check revoked
            if ($token_data['revoked']) {
                throw new Exception('Token revoked');
            }

            // Validate hash
            if (!hash_equals($token_data['token_hash'], $hashed_token)) {
                throw new Exception('Unauthorized');
            }

            $datetime = $token_data['created_at'];
            $date_and_timing = $datetime->format('Y-m-d H:i:s');

            // Check expiration (7 days)
            if ($this->check_token_expiration($date_and_timing, 604800)) {
                $model->where('id', $id)->update(['revoked' => 1]);

                throw new Exception('Validation token expired');
            }

            return [
                "success" => true,
                $id_field => $token_data[$id_field],
                "identifier" => $type
            ];

        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    private function check_token_expiration( $datetime, $expiration_time)
    {
        if ($expiration_time < 0) {
            throw new \InvalidArgumentException("Expiration time must be non-negative");
        }

        $createdAt = strtotime($datetime);

        if ($createdAt === false) {
            return true; // invalid date → treat as expired
        }

        return time() >= ($createdAt + $expiration_time);
    }
}