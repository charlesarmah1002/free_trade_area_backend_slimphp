<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Products;
use App\Models\BusinessAccount;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utilities\CustomFunctions;
use App\Utilities\FirebaseJWT;

class ProductsController
{
    public function get_products(Request $request, Response $response)
    {
        try {
            $product_data = Products::join('business_accounts', 'products.business_id', '=', 'business_accounts.id')
                ->select([
                    "products.id",
                    "products.name",
                    "products.price",
                    "products.details",
                    "products.image_url",
                    "business_accounts.business_name",
                    "products.created_at",
                    "products.business_id"
                ])
                ->get();

            $response->getBody()->write(json_encode([
                "success" => true,
                "data" => $product_data
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

    // i need to write a function that returns the list of products based on the business
    public function get_products_by_business(Request $request, Response $response, array $args)
    {
        $custom_functions = new CustomFunctions;
        $business_id = $custom_functions->sanitizeInput($args["business_id"], "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid product ID"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $data = Products::join('business_accounts', 'products.business_id', '=', 'business_accounts.id')
                ->where('business_accounts.id', '=', $business_id)
                ->select([
                    "products.id",
                    "products.name",
                    "products.price",
                    "products.details",
                    "products.image_url",
                    "business_accounts.business_name",
                    "products.created_at"
                ])->get();

            if (empty($data)) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Products not found"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data
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

    public function get_product(Request $request, Response $response, array $args)
    {
        $custom_functions = new CustomFunctions;
        $product_id = $custom_functions->sanitizeInput($args["id"], "int");

        if (empty($product_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid product ID"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $data = Products::join('business_accounts', 'products.business_id', '=', 'business_accounts.id')
                ->where("products.id", '=', $product_id)
                ->select([
                    "products.id",
                    "products.name",
                    "products.price",
                    "products.details",
                    "products.image_url",
                    "business_accounts.business_name",
                    "products.created_at"
                ])
                ->first();

            if (!$data) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Product not found"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(401);
            }

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

    public function edit_product(Request $request, Response $response, array $args)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        // todo: rewrite code for editing the product, needs to take the id of business account and validate to edit the product

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
        $business_id = $custom_function->sanitizeInput($extracted_data['id'], "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid Business ID provided"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // using custom sanitize input function 
        $custom_functions = new CustomFunctions;
        $product_id = $custom_functions->sanitizeInput($args["id"], "int");

        if (!isset($product_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid product ID"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        //sanitize all the necessary data
        $product_details['name'] = $custom_functions->sanitizeInput($form_data['name'], "string");
        $product_details['price'] = $custom_functions->sanitizeInput($form_data['price'], "float");

        //check if the data was valid and passed sanitization
        if (empty($product_details['name'])) {
            $errors['name'] = "Product name is invalid";
        }

        if (
            empty($product_details['price']) ||
            !preg_match('/^\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?$/', $product_details['price'])
        ) {
            $errors['price'] = "Product price is invalid";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $product_details_from_db = Products::find($product_id);

            if (!$product_details_from_db) {
                throw new Exception("Product ID is invalid");
            }

            if($product_details_from_db["business_id"] != $business_id) {
                throw new Exception("You are not authorized to make changes to this product");
            }

            Products::where('id', $args['id'])
                ->update([
                    "name" => $product_details['name'],
                    "price" => $product_details['price'],
                ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Product updated successfully"
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

    public function create_product(Request $request, Response $response)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        $custom_functions = new CustomFunctions;
        $product_details = [];

        $cookie = $request->getCookieParams();
        $access_token = $cookie['access_token'];
        $refresh_token = $cookie['refresh_token'];

        if (!isset($access_token) || !isset($refresh_token)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_token_data = $firebaseJWT->validate_token($access_token);
        $extracted_business_data = $firebaseJWT->validate_refresh_token($refresh_token, $extracted_token_data['id'], $extracted_token_data['identifier']);
        $extracted_business_id = $extracted_business_data['data'];

        // sanitization of info
        $custom_function = new CustomFunctions;
        $business_id = $custom_function->sanitizeInput($extracted_business_id['business_id'], "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Unauthorized"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now to confirm that it is in the database
        $business_verification = BusinessAccount::find($business_id);

        if (!$business_verification) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Unathorized"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(401);
        }

        // name, business id, price, details and image
        if (empty($form_data)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => [
                    "name" => "Product name is required",
                    "price" => "Product price is required",
                    "details" => "Product details is required"
                ]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // checking sanitized inputs
        if (!isset($form_data['name'])) {
            $errors['name'] = "Product name is invalid";
        }

        if (
            !isset($form_data['price']) ||
            !preg_match('/^\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?$/', $form_data['price'])
        ) {
            $errors['price'] = "Product price is invalid";
        }

        if (
            !isset($form_data['details'])
        ) {
            $errors['details'] = "Product details are required";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            $product_details['name'] = $custom_functions->sanitizeInput($form_data['name'], "string");
            $product_details['price'] = $custom_functions->sanitizeInput($form_data['price'], "float");

            Products::create([
                "name" => $product_details['name'],
                "price" => $product_details['price'],
                "business_id" => $business_id,
                "details" => $form_data["details"]
            ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Product created successfully"
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

    public function delete_product(Request $request, Response $response, array $args)
    {
        $cookie = $request->getCookieParams();
        $access_token = $cookie['access_token'];
        $refresh_token = $cookie['refresh_token'];

        if (!isset($access_token) || !isset($refresh_token)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Access denied"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // now send the token to the firebase jwt class to extrac the info
        $firebaseJWT = new FirebaseJWT;
        $extracted_token_data = $firebaseJWT->validate_token($access_token);
        $extracted_business_id = $firebaseJWT->validate_refresh_token($refresh_token, $extracted_token_data['id'], $extracted_token_data['identifier']);

        // sanitization of info
        $custom_function = new CustomFunctions;
        $business_id = $custom_function->sanitizeInput($extracted_business_id['business_id'], "int");

        if (empty($business_id)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid ID provided"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // using my sanitization function to check the id
        $custom_function = new CustomFunctions;

        if (empty($custom_function->sanitizeInput($args['id'], "int"))) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Invalid product ID"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            // what if I check if the business_id on the product entry is the same and then I delete the entry later

            $product_business_id = Products::where('id', $args['id'])
                ->select(['business_id'])
                ->first();

            if (!$product_business_id) {
                $response->getBody()->write(json_encode([
                    "errors" => true,
                    "message" => "Product not found"
                ]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(404);
            }

            if ($product_business_id['business_id'] != $business_id) {
                throw new Exception("Business ID missmatch");
            }

            Products::destroy($args['id']);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Product deleted successfully"
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