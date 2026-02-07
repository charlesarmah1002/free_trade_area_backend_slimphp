<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Products;
use App\Models\BusinessAccount;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utilities\CustomFunctions;

class ProductsController
{
    public function get_products(Request $request, Response $response)
    {
        try {
            $data = Products::join('business_accounts', 'products.business_id', '=', 'business_accounts.id')
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
                ->where("products.id", '=', $args['id'])
                ->select([
                    "products.id",
                    "products.name",
                    "products.price",
                    "products.details",
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

    public function edit_product(Request $request, Response $response, array $args)
    {
        $errors = [];
        $form_data = $request->getParsedBody();

        // using custom sanitize input function 
        $custom_functions = new CustomFunctions;
        $product_id = $custom_functions->sanitizeInput($args["id"], "int");

        if (empty($product_id)) {
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
            if (!Products::find($args['id'])) {
                throw new Exception("Product ID is invalid");
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
                "errors" => false,
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

        // name, business id, price, details and image
        if(empty($form_data)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => [
                    "name" => "Product name is required",
                    "price" => "Product price is required",
                    "business_name" => "Business name is required",
                    "details" => "Product details is required"
                ]
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // checking sanitized inputs
        if (empty($form_data['name'])) {
            $errors['name'] = "Product name is invalid";
        }

        if (
            empty($form_data['price']) ||
            !preg_match('/^\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?$/', $form_data['price'])
        ) {
            $errors['price'] = "Product price is invalid";
        }


        if (empty($form_data['business_id'])) {
            $errors['business_id'] = "Business information invalid";
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => $errors
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        try {
            // sanitized inputs
            $product_details['name'] = $custom_functions->sanitizeInput($form_data['name'], "string");
            $product_details['price'] = $custom_functions->sanitizeInput($form_data['price'], "float");
            $product_details['business_id'] = $custom_functions->sanitizeInput($form_data['business_id'], "int");

            Products::create([
                "name" => $product_details['name'],
                "price" => $product_details['price'],
                "business_id" => $product_details['business_id']
            ]);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Product created successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => false,
                "message" => $e->getMessage()
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }

    public function delete_product(Request $request, Response $response, array $args)
    {
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
            Products::destroy($args['id']);

            $response->getBody()->write(json_encode([
                "success" => true,
                "message" => "Product deleted successfully"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                "errors" => true,
                "message" => "Product not deleted"
            ]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }
    }
}