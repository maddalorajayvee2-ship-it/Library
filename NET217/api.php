<?php
// api.php
require_once __DIR__ . '/src/library.php';

$action = $_GET['action'] ?? null;
if (!$action) {
    jsonResponse(['error' => 'Action parameter is required'], 400);
}

try {
    switch ($action) {
        case 'signup':
            $body = getRequestBody();
            if (empty($body['username']) || empty($body['password'])) {
                jsonResponse(['error' => 'Username and password are required'], 400);
            }
            $user = createUser($body['username'], $body['password']);
            jsonResponse(['message' => 'User created', 'user' => $user], 201);
            break;

        case 'login':
            $body = getRequestBody();
            if (empty($body['username']) || empty($body['password'])) {
                jsonResponse(['error' => 'Username and password are required'], 400);
            }
            $user = authenticateUser($body['username'], $body['password']);
            jsonResponse(['message' => 'Login successful', 'user' => $user]);
            break;

        case 'profile':
            $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$userId) {
                jsonResponse(['error' => 'user_id is required'], 400);
            }
            $user = getUserById($userId);
            jsonResponse(['user' => $user]);
            break;

        case 'update_profile':
            $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$userId) {
                jsonResponse(['error' => 'user_id is required'], 400);
            }
            $body = getRequestBody();
            $updated = updateUser($userId, $body);
            jsonResponse(['message' => 'Profile updated', 'user' => $updated]);
            break;

        case 'search':
            $criteria = [
                'query' => $_GET['query'] ?? null,
                'genre' => $_GET['genre'] ?? null,
                'year_from' => $_GET['year_from'] ?? null,
                'year_to' => $_GET['year_to'] ?? null,
            ];
            $books = searchBooks($criteria);
            jsonResponse(['books' => $books]);
            break;

        case 'borrow':
            $body = getRequestBody();
            $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
            $bookId = isset($body['book_id']) ? (int)$body['book_id'] : 0;
            if (!$userId || !$bookId) {
                jsonResponse(['error' => 'user_id and book_id are required'], 400);
            }
            $borrow = borrowBook($userId, $bookId);
            jsonResponse(['message' => 'Book borrowed successfully', 'borrow' => $borrow], 201);
            break;

        case 'return':
            $body = getRequestBody();
            $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
            $borrowId = isset($body['borrow_id']) ? (int)$body['borrow_id'] : 0;
            if (!$userId || !$borrowId) {
                jsonResponse(['error' => 'user_id and borrow_id are required'], 400);
            }
            $result = returnBook($userId, $borrowId);
            jsonResponse(['message' => 'Book returned', 'result' => $result]);
            break;

        case 'borrowed_list':
            $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$userId) {
                jsonResponse(['error' => 'user_id is required'], 400);
            }
            $list = getBorrowedBooks($userId);
            jsonResponse(['borrowed' => $list]);
            break;

        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
} catch (Exception $ex) {
    jsonResponse(['error' => $ex->getMessage()], 500);
}
