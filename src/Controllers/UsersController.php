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

        $response->getBody()->write(json_encode([
            $form_data
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
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

    private function check_user_email($email)
    {
        $email = Users::select('email')->where('email', '=', $email)->first();

        if (empty($email)) {
            return true;
        }

        return false;
    }

    public function check_refresher_token (Request $request, Response $response){
        
    }
}