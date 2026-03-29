<?php

namespace App\Controllers;

use App\Models\BusinessAccount;
use App\Models\Products;
use App\Utilities\CustomFunctions;
use App\Utilities\FirebaseJWT;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\BusinessRefreshTokens;

class BusinessAccountController
{
    private $type;

    public function __construct()
    {
        $this->type = 'business';
    }

    public function create_business_account(Request $request, Response $response)
    {
        $form_data = $request->getParsedBody();

        $errors = [];

        if (
            !isset($form_data['first_name']) || !isset($form_data['last_name']) ||
            (!preg_match("/^[A-Za-z]+([ .'-][A-Za-z]+)*$/", $form_data['first_name'])) ||
            (!preg_match("/^[A-Za-z]+([ .'-][A-Za-z]+)*$/", $form_data['last_name']))
        ) {
            $errors['name'] = "First name and last name should have only alphabets and are required";
        }

        if (!isset($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address e.g. example@gmail.com";
        }

        if (!isset($form_data['password']) || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
            $errors['password'] = "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character";
        }

        if (empty($form_data['business_name'])) {
            $errors['business_name'] = "Business names are required";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!$this->is_email_available($form_data['email'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Email already registered to an account"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if ($this->business_name_checker($form_data['business_name'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Business name is already registered to an account"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        $custom_functions = new CustomFunctions;

        try {
            $business_account = BusinessAccount::create([
                "first_name" => $form_data['first_name'],
                "last_name" => $form_data['last_name'],
                "email" => $form_data['email'],
                "password" => password_hash($form_data["password"], PASSWORD_DEFAULT),
                "business_name" => $custom_functions->sanitizeInput($form_data['business_name'], "string")
            ]);

            $last_id = $business_account->id;

            // generate token
            $firebaseJWT = new FirebaseJWT;
            $token = $firebaseJWT->generate_token($last_id, 'business');

            $response = $response->withHeader(
                'Set-Cookie',
                'access_token=' . $token['access_token'] . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
            )->withAddedHeader(
                    "Set-Cookie",
                    "refresh_token=" . $token['refresh_token'] . "; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=604800"
                );

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Business Account created successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => [$e->getMessage()]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function validate_business_account(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        if (!isset($form_data['email'])) {
            $errors['email'] = 'Enter a valid email address e.g. example@gmail.com';
        }

        if (!isset($form_data['password'])) {
            $errors['password'] = 'Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character';
        }

        if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address e.g. example@gmail.com";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now check the db for the email and retieve that and the password from the database
        try {
            $email_verified = BusinessAccount::select(
                'email',
                'password',
                'id'
            )->where(
                    'email',
                    $form_data['email']
                )->first();

            if (empty($email_verified)) {
                throw new Exception("Email account doesn't exist");
            }

            if (!password_verify($form_data['password'], $email_verified->password)) {
                throw new Exception("Password incorrect");
            }

            // generate token
            $firebaseJWT = new FirebaseJWT;
            $token = $firebaseJWT->generate_token($email_verified->id, 'business');

            $response = $response->withHeader(
                'Set-Cookie',
                'access_token=' . $token['access_token'] . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
            )->withAddedHeader(
                    'Set-Cookie',
                    'refresh_token=' . $token['refresh_token'] . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=604800'
                );

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Logged in successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function update_business_account(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        $cookies = $request->getCookieParams();
        $auth_token = $cookies['access_token'] ?? null;

        if (!isset($auth_token)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        $firebaseJWT = new FirebaseJWT;
        $extracted_data = $firebaseJWT->validate_token($auth_token);

        if (!$extracted_data || !isset($extracted_data['id'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid or expired token"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        if (empty($form_data)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => [
                    "name" => "Full name is required",
                    "business_name" => "Business name is required"
                ]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (empty($form_data['first_name']) || empty($form_data['last_name'])) {
            $errors['name'] = "First name and last name should have only alphabets and are required";
        }

        if (!isset($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address e.g. example@gmail.com";
        }

        if (empty($form_data['business_name'])) {
            $errors['business_name'] = "Business name is invalid";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $data_from_token = BusinessRefreshTokens::where("id", "=", $extracted_data["id"])->first();

            if (empty($data_from_token)) {
                throw new Exception("Invalid token");
            }

            $business_finder = BusinessAccount::find($data_from_token["business_id"]);

            if (!$business_finder) {
                throw new Exception("Invalid business ID provided");
            }

            $email_taken = BusinessAccount::where('email', $form_data['email'])
                ->where('id', '!=', $data_from_token["business_id"])
                ->first();

            if ($email_taken) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Email is already registered to another account"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }

            $business_finder->update([
                'first_name' => $form_data['first_name'],
                'last_name' => $form_data['last_name'],
                'business_name' => $form_data['business_name'],
                'email' => $form_data['email']
            ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Business account details updated successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function update_password(Request $request, Response $response)
    {
        $form_data = $request->getParsedBody();

        // grab data from headers
        $cookies = $request->getCookieParams();
        $auth_token = $cookies['access_token'];

        if (!isset($auth_token)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_data = $firebaseJWT->validate_token($auth_token);

        // sanitization of info
        $custom_function = new CustomFunctions;
        $business_id = $custom_function->sanitizeInput($extracted_data['data']->id, "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid ID provided"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            // now to update only the password field
            if (!BusinessAccount::find($business_id)) {
                throw new Exception("Account ID is invalid");
            }

            BusinessAccount::where('id', $business_id)
                ->update([
                    "password" => password_hash($form_data['password'], PASSWORD_DEFAULT)
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Password set successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function get_business_data(Request $request, Response $response)
    {
        $cookie = $request->getCookieParams();

        if (!isset($cookie) || !isset($cookie["access_token"])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        $userToken = $cookie['access_token'];

        $custom_functions = new FirebaseJWT;
        $userData = $custom_functions->validate_token($userToken);

        if (empty($userData)) {
            $response->getBody()->write(json_encode([
                "verified" => false,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        $response->getBody()->write(json_encode([
            "verified" => true,
            "message" => "Authorized"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }

    private function is_email_available($email)
    {
        return !BusinessAccount::where('email', $email)->exists();
    }

    private function business_name_checker($business_name)
    {
        $business_name_to_check = BusinessAccount::select('business_name')
            ->where('business_name', $business_name)
            ->first();

        if (empty($business_name_to_check)) {
            return false;
        }

        return true;
    }

    public function check_refresher_token(Request $request, Response $response)
    {
        $tokens = $request->getCookieParams();

        if (!isset($tokens['refresh_token']) || !isset($tokens['access_token'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        $jwt = new FirebaseJWT;

        $access_token_data = $jwt->decode_token_without_validation($tokens['access_token']);

        if (!$access_token_data || !isset($access_token_data['id'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid token"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        $refresh_token_data = $jwt->validate_refresh_token($tokens['refresh_token'], $access_token_data['id'], $this->type);

        if (!isset($refresh_token_data['success']) || $refresh_token_data['success'] !== true) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid or expired refresh token"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        $new_access_token = $jwt->generate_access_token($access_token_data['id'], $this->type);

        $response = $response->withHeader(
            'Set-Cookie',
            'access_token=' . $new_access_token . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
        );

        $response->getBody()->write(json_encode([
            "success" => true,
            "message" => "Access token refreshed successfully"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }

    public function get_business_products(Request $request, Response $response)
    {
        $cookie = $request->getCookieParams();
        $access_token = $cookie['access_token'];
        $refresh_token = $cookie['refresh_token'];

        if (!isset($access_token)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_token_data = $firebaseJWT->validate_token($access_token);
        $extracted_business_id = $firebaseJWT->validate_refresh_token($refresh_token, $extracted_token_data['id'], $this->type);

        // sanitization of info
        $custom_function = new CustomFunctions;
        $business_id = $custom_function->sanitizeInput($extracted_business_id['business_id'], "int");

        if (!isset($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid ID provided"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $data = Products::join('business_accounts', 'products.business_id', '=', 'business_accounts.id')
                ->where('products.business_id', '=', $business_id)
                ->select([
                    "products.id",
                    "products.name",
                    "products.price",
                    "products.image_url",
                    "business_accounts.business_name",
                    "products.created_at"
                ])
                ->get();

            $response->getBody()->write(json_encode([
                "success" => true,
                "data" => $data
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    // add a delete route for when users want to delete their accounts
}
