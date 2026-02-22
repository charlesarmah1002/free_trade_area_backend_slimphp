<?php

namespace App\Controllers;

use App\Models\BusinessAccount;
use App\Utilities\CustomFunctions;
use App\Utilities\FirebaseJWT;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BusinessAccountController
{

    public function create_business_account(Request $request, Response $response)
    {
        $form_data = $request->getParsedBody();

        $errors = [];

        if (
            (!preg_match('/^[A-Za-z]+$/', $form_data['first_name'])) ||
            (!preg_match('/^[A-Za-z]+$/', $form_data['last_name']))
        ) {
            $errors['name'] = "First name and last name should have only alphabets and are required";
        }

        if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address e.g. example@gmail.com";
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
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

        if (!$this->email_checker($form_data['email'])) {
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
        $token = $firebaseJWT->generate_token($last_id, $form_data['email']);

        $response = $response->withHeader(
            'Set-Cookie',
            'token=' . $token . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
        );

        $response->getBody()->write(json_encode([
            "success" => true,
            "message" => "Business Account created successfully"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }

    public function validate_business_account(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

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
            $token = $firebaseJWT->generate_token($email_verified['id'], $email_verified['email']);

            $response = $response->withHeader(
                'Set-Cookie',
                'token=' . $token . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900'
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

        // grab data from headers
        $cookies = $request->getCookieParams();
        $authToken = $cookies['token'];

        if (empty($authToken)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_data = $firebaseJWT->validate_token($authToken);

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

        if (empty($form_data)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => [
                    "name" => "Full name is required",
                    "business_name" => "Business name is required"
                ]
            ]));
            return $response->withHeader("Content-Type", "application/json");
        }

        if (empty($form_data['first_name']) || empty($form_data['last_name'])) {
            $errors['name'] = "First name and last name should have only alphabets and are required";
        }

        if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
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

        if ($this->email_checker($form_data['email'])) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Email is not registered"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now checking the database for the id and email
        try {
            if (!BusinessAccount::find($business_id)) {
                throw new Exception("Account ID is invalid");
            }

            BusinessAccount::where('id', '=', $business_id)
                ->update([
                    'first_name' => $form_data['first_name'],
                    "last_name" => $form_data['last_name'],
                    "business_name" => $form_data['business_name'],
                    "email" => $form_data['email']
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Business Account details updated successfully"
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
        $authToken = $cookies['token'];

        if (empty($authToken)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_data = $firebaseJWT->validate_token($authToken);

        // sanitization of info
        $custom_function = new CustomFunctions;
        $business_id = $custom_function->sanitizeInput($extracted_data['data']->id, "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid ID provided",
                $cookies['token']
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $form_data['password'])) {
            $response->getBody()->write(json_encode([
                "errors" => false,
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

        $response->getBody()->write(json_encode([]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }

    public function get_business_data(Request $request, Response $response) {
        $cookie = $request->getCookieParams();

        $userToken = $cookie['token'];

        $custom_functions = new FirebaseJWT;
        $userData = $custom_functions->validate_token($userToken);

        $userId = $userData['data'];

        $response->getBody()->write(json_encode([
            $userId->id,
            $userId->email
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }

    private function email_checker($email)
    {
        $email = BusinessAccount::select('email')->where('email', $email)->first();

        if (empty($email)) {
            return true;
        }

        return false;
    }

    private function business_name_checker($business_name)
    {
        $business_name_to_check = BusinessAccount::select('business_name')->where('business_name', $business_name);

        if (empty($business_name_to_check)) {
            return true;
        }

        return false;
    }
}