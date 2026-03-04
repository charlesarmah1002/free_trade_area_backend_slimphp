<?php

declare(strict_types=1);

namespace App\Utilities;

use Cloudinary\Exception\Error;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;
use App\Models\RefreshTokens;

class FirebaseJWT
{
    private $secret_key;
    private $token_signature;

    public function __construct()
    {
        $this->secret_key = $_ENV['JWT_SECRET_KEY'];
        $this->token_signature = $_ENV['TOKEN_SIGNATURE'];
    }

    public function generate_token($id)
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
        $refreshToken = RefreshTokens::create([
            'business_id' => $id,
            'token_hash' => $token_hash
        ]);

        $refreshId = $refreshToken->id;

        return [
            "access_token" => $this->generate_access_token($refreshId),
            "refresh_token" => $token_hash
        ];
    }

    public function generate_access_token($id)
    {
        $issued_at = time();
        // make the token valid for 10 days
        $expiration_date = $issued_at + 864000;

        $accessPayload = [
            'exp' => $expiration_date,
            'iat' => time(),
            'nbf' => time(),
            'type' => 'access',
            'id' => $id
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

    public function validate_refresh_token ($hashed_token, $id) {
        try {
            $token_data = RefreshTokens::where('id', '=', $id)->first();

            $token_data_hash = $token_data['token_hash'];
            $valid_token_hash = hash_equals($token_data_hash, $hashed_token);

            if (!$valid_token_hash) {
                throw new Error('Unauthorized');
            }
            
            return [
                "success" => true,
                "data" => $valid_token_hash
            ];
        }catch (Exception $e) {
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }
}