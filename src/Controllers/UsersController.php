<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Users;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utilities\CustomFunctions;
use App\Utilities\FirebaseJWT;
use Exception;

class UsersController
{
    private $type;

    public function __construct()
    {
        $this->type = 'user';
    }

    public function create_user_account(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        // checking for email, password and phone_number

        if (!isset($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address";
        }

        if (!isset($form_data['password']) || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
            $errors['password'] = "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character";
        }

        // i need to add validation for phone number but then I have to consider taking it out completely tho
        // could be a detail that you add later after creating the account or could be something that you enter when you place an order and can be changed based on the order

        $check_email = $this->check_user_email($form_data['email']);

        if ($check_email == false) {
            $errors['email'] = "Email has been taken by another user";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $custom_functions = new CustomFunctions;

            $user_account = Users::create([
                'email' => $custom_functions->sanitizeInput($form_data['email'], 'email'),
                'password' => password_hash($form_data['password'], PASSWORD_DEFAULT)
            ]);

            $last_id = $user_account->id;

            $firebaseJWT = new FirebaseJWT;
            $token = $firebaseJWT->generate_token($last_id, 'user');

            $response = $response->withHeader(
                'Set-Cookie',
                'access_token=' . $token['access_token'] . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
            )->withAddedHeader(
                    "Set-Cookie",
                    "refresh_token=" . $token['refresh_token'] . "; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=604800"
                );

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "User Account created successfully",
                "token" => $last_id
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

    public function get_user_data(Request $request, Response $response)
    {
        $token_data = $request->getCookieParams();

        // this function is to get user information
        $refresh_token = $token_data['refresh_token'];
        $access_token = $token_data['access_token'];

        if (!isset($refresh_token) || !isset($access_token)) {
            return $response->withStatus(401);
        }

        $firebaseJWT = new FirebaseJWT;
        $access_token_validation_data = $firebaseJWT->validate_token($access_token);
        $refresh_token_validation_data = $firebaseJWT->validate_refresh_token($refresh_token, $access_token_validation_data['id'], $this->type);
        $user_data_from_validation = $refresh_token_validation_data["data"];
        $user_id = $user_data_from_validation['user_id'];

        try {
            $user_data = Users::select([
                "id",
                "email",
                "created_at",
                "updated_at"
            ])
                ->where("id", "=", $user_id)->first();

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => $user_data
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

    public function verify_user_account(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        if (!isset($form_data['email']) || !filter_var($form_data['email'], FILTER_SANITIZE_EMAIL)) {
            $errors['email'] = "Enter a valid email address";
        }

        $email_check = $this->check_user_email($form_data['email']);

        if ($email_check) {
            $errors['email'] = "Email does not exist";
        }

        if (!isset($form_data['password']) || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
            $errors['password'] = "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $user_account_details = Users::select([
                'id',
                'email',
                'password'
            ])->where('email', '=', $form_data['email'])
                ->first();

            //verify password and then generate the tokens I need to generate for the auth
            if (!password_verify($form_data['password'], $user_account_details['password'])) {
                throw new Exception("Incorrect user password");
            }

            $firebaseJWT = new FirebaseJWT;
            $token = $firebaseJWT->generate_token($user_account_details['id'], 'user');

            $response = $response->withHeader(
                'Set-Cookie',
                'access_token=' . $token['access_token'] . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
            )->withAddedHeader(
                    "Set-Cookie",
                    "refresh_token=" . $token['refresh_token'] . "; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=604800"
                );

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "User log in successful"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "messages" => [$e->getMessage()]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function update_user_email(Request $request, Response $response)
    {
        $errors = [];

        $form_data = $request->getParsedBody();
        $token_data = $request->getCookieParams();

        if (!isset($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email adress";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        $refresh_token = $token_data['refresh_token'];
        $access_token = $token_data['access_token'];

        if (!isset($refresh_token) || !isset($access_token)) {
            return $response->withStatus(401);
        }

        $firebaseJWT = new FirebaseJWT;
        $access_token_validation_data = $firebaseJWT->validate_token($access_token);
        $refresh_token_validation_data = $firebaseJWT->validate_refresh_token($refresh_token, $access_token_validation_data['id'], $this->type);
        $user_data_from_validation = $refresh_token_validation_data["data"];

        try {
            $user_data = Users::select(["email"])
                ->where("id", "=", $user_data_from_validation["user_id"])
                ->first();

            $existing_email_check = $this->check_user_email($user_data['email']);

            if ($existing_email_check == false) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Email already used by another user"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }

            if ($user_data["email"] == $form_data["email"]) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Email update failed"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }

            Users::where("id", "=", $user_data_from_validation['user_id'])
                ->update([
                    "email" => $form_data["email"]
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "User email updated successfully"
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

    private function check_user_email($email)
    {
        $email = Users::select('email')->where('email', '=', $email)->first();

        if (empty($email)) {
            return true;
        }

        return false;
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

    public function update_user_password(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();
        $token_data = $request->getCookieParams();

        $refresh_token = $token_data['refresh_token'];
        $access_token = $token_data['access_token'];

        if (!isset($refresh_token) || !isset($access_token)) {
            return $response->withStatus(401);
        }

        if (!isset($form_data['current_password']) || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['current_password'])) {
            $errors['password'] = "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character";
        }

        if (!isset($form_data['new_password']) || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['new_password'])) {
            $errors['password'] = "Password should be at least 8 characters and, have an uppercase, lowercase, number, and special character";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        $firebaseJWT = new FirebaseJWT;
        $access_token_validation_data = $firebaseJWT->validate_token($access_token);
        $refresh_token_validation_data = $firebaseJWT->validate_refresh_token($refresh_token, $access_token_validation_data['id'], $this->type);
        $user_data_from_validation = $refresh_token_validation_data["data"];
        $user_id = $user_data_from_validation["user_id"];

        try {
            if (!Users::find($user_id)) {
                throw new Exception("User ID is invalid");
            }

            $old_password = Users::select([
                "password"
            ])->where("id", "=", $user_id)
                ->first();

            // now to verify the current password is the one in the db
            if (!password_verify($form_data['current_password'], $old_password['password'])) {
                throw new Exception("Password is incorrect");
            }

            if (password_verify($form_data['new_password'], $old_password['password'])) {
                throw new Exception("New password should be unique and different from old password");
            }

            Users::where("id", "=", $user_id)
                ->update([
                    "password" => password_hash($form_data["new_password"], PASSWORD_DEFAULT)
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => [
                    "Password updated successfully"
                ]
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
}