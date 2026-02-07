<?php

declare(strict_types=1);

namespace App\Utilities;

class CustomFunctions 
{
    public function generateUsername($first_name, $last_name) {
        $first_string = substr($first_name, 0, 3);
        $second_string = substr($last_name, 0, 3);
        $figures = str_pad((string) rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $username = $first_string . $second_string . $figures;
        return $username;
    }

    public function sanitizeInput($input, $type = 'string') {
        return match($type) {
            'string' => htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'),
            'email' => filter_var(trim($input), FILTER_SANITIZE_EMAIL),
            'int' => filter_var($input, FILTER_SANITIZE_NUMBER_INT),
            'float' => filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'url' => filter_var($input, FILTER_SANITIZE_URL),
            default => htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'),
        };
    }
}