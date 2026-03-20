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

    public function validate_refresh_token($hashed_token, $id, $type)
    {
        try {
            if ($type == 'business') {
                $token_data = BusinessRefreshTokens::where('id', '=', $id)->first();
            } else if ($type == 'user') {
                $token_data = UserRefreshTokens::where('id', '=', $id)->first();
            } else {
                throw new Error('Invalid validation request');
            }

            $token_data_hash = $token_data['token_hash'];
            $valid_token_hash = hash_equals($token_data_hash, $hashed_token);

            if (!$valid_token_hash) {
                throw new Error('Unauthorized');
            }

            if ($this->check_token_expiration($token_data['created_at'], 604800)) {
                if ($type == 'business') {
                    BusinessRefreshTokens::where('id', $id)
                        ->update([
                            'revoked' => 1
                        ]);
                    throw new Error('Validation token expired');
                } else if ($type == 'user') {
                    UserRefreshTokens::where('id', $id)
                        ->update([
                            'revoked' => 1
                        ]);
                    throw new Error('Validation token expired');
                }
            }

            if ($type == 'business') {
                $business_id = $token_data['business_id'];

                return [
                    "success" => true,
                    "business_id" => $business_id,
                    "identifier" => $type
                ];
            } else {
                $user_id = $token_data['user_id'];

                return [
                    "success" => true,
                    "user_id" => $user_id,
                    "identifier" => $type
                ];
            }
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

    private function check_token_expiration($datetime, $expiration_time)
    {
        // Convert DB datetime to timestamp
        $create_at_time = strtotime($datetime);

        // Current time
        $current_time = time();

        // Check if expired
        return ($current_time - $create_at_time) >= $expiration_time;
    }
}