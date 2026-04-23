# Free Trade Area Backend - API Documentation

A PHP backend API built with Slim Framework for managing users, business accounts, and products in a free trade area platform.

## Overview

This API provides endpoints for user authentication, business account management, and product management. All endpoints that require authentication use JWT (JSON Web Token) for authorization.

---

## Users Routes

All routes are prefixed with `/users`

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/users/register` | Register a new user account |
| POST | `/users/login` | Authenticate user and receive tokens |

### Protected Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/users` | Get current user's data |
| GET | `/users/refresh` | Refresh authentication token |
| POST | `/users/update-password` | Update user password |
| POST | `/users/update-email` | Update user email |

---

## Business Account Routes

All routes are prefixed with `/business`

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/business/register` | Register a new business account |
| POST | `/business/verify` | Verify business account credentials |
| GET | `/business` | Get business account data |

### Protected Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/business/update` | Update business account information |
| GET | `/business/products` | Get all products for the business |
| POST | `/business/update-password` | Update business account password |
| GET | `/business/refresh` | Refresh business account token |

---

## Products Routes

All routes are prefixed with `/products` and **require authentication**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/products` | Get all available products |
| GET | `/products/{id}` | Get a specific product by ID |
| GET | `/products/business/{business_id}` | Get all products for a specific business |
| POST | `/products` | Create a new product |
| POST | `/products/{id}` | Update an existing product |
| DELETE | `/products/{id}` | Delete a product |

---

## Authentication

Protected endpoints require a valid JWT token to be included in the request header:

```
Authorization: Bearer <jwt_token>
```

Tokens are obtained through the login/register endpoints and can be refreshed using the refresh endpoints.

---

## Technologies Used

- **Framework**: Slim Framework 4
- **Language**: PHP 8+
- **Authentication**: JWT (Firebase PHP-JWT)
- **Database**: Configured via `src/database.php`
- **Dependencies**: Managed via Composer