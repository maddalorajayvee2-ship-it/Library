<?php
// src/library.php
require_once __DIR__ . '/database.php';

function jsonResponse($data, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getRequestBody(): array
{
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return [];
    }
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'Invalid JSON request body'], 400);
    }
    return $decoded;
}

function createUser(string $username, string $password): array
{
    $pdo = getDatabase();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $stmt->execute([':username' => $username, ':password_hash' => $hash]);
        $id = (int)$pdo->lastInsertId();
        return ['id' => $id, 'username' => $username];
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            jsonResponse(['error' => 'Username already exists'], 409);
        }
        throw $ex;
    }
}

function authenticateUser(string $username, string $password): array
{
    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    unset($user['password_hash']);
    return $user;
}

function getUserById(int $userId): array
{
    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT id, username, display_name, email FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        jsonResponse(['error' => 'User not found'], 404);
    }
    return $user;
}

function updateUser(int $userId, array $fields): array
{
    $pdo = getDatabase();
    $allowed = ['display_name', 'email', 'password'];
    $params = [];
    $sets = [];

    if (isset($fields['password'])) {
        $params['password_hash'] = password_hash($fields['password'], PASSWORD_DEFAULT);
        $sets[] = 'password_hash = :password_hash';
    }
    if (isset($fields['display_name'])) {
        $params['display_name'] = $fields['display_name'];
        $sets[] = 'display_name = :display_name';
    }
    if (isset($fields['email'])) {
        $params['email'] = $fields['email'];
        $sets[] = 'email = :email';
    }

    if (empty($sets)) {
        jsonResponse(['error' => 'No valid fields provided'], 400);
    }

    $params['id'] = $userId;
    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return getUserById($userId);
}

function searchBooks(array $criteria): array
{
    $pdo = getDatabase();
    $conditions = [];
    $params = [];

    if (!empty($criteria['query'])) {
        $conditions[] = '(title LIKE :query OR author LIKE :query OR isbn LIKE :query)';
        $params[':query'] = '%' . $criteria['query'] . '%';
    }
    if (!empty($criteria['genre'])) {
        $conditions[] = 'genre = :genre';
        $params[':genre'] = $criteria['genre'];
    }
    if (!empty($criteria['year_from'])) {
        $conditions[] = 'publication_year >= :year_from';
        $params[':year_from'] = (int)$criteria['year_from'];
    }
    if (!empty($criteria['year_to'])) {
        $conditions[] = 'publication_year <= :year_to';
        $params[':year_to'] = (int)$criteria['year_to'];
    }

    $sql = 'SELECT id, title, author, isbn, genre, publication_year, available FROM books';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY title ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getBookById(int $bookId): array
{
    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT * FROM books WHERE id = :id');
    $stmt->execute([':id' => $bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) {
        jsonResponse(['error' => 'Book not found'], 404);
    }
    return $book;
}

function borrowBook(int $userId, int $bookId): array
{
    $pdo = getDatabase();
    $book = getBookById($bookId);
    if ((int)$book['available'] !== 1) {
        jsonResponse(['error' => 'Book is currently unavailable'], 409);
    }

    $borrowDate = (new DateTime())->format('Y-m-d');
    $dueDate = (new DateTime('+14 days'))->format('Y-m-d');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO borrows (user_id, book_id, borrow_date, due_date) VALUES (:user_id, :book_id, :borrow_date, :due_date)');
        $stmt->execute([
            ':user_id' => $userId,
            ':book_id' => $bookId,
            ':borrow_date' => $borrowDate,
            ':due_date' => $dueDate,
        ]);

        $stmt = $pdo->prepare('UPDATE books SET available = 0 WHERE id = :id');
        $stmt->execute([':id' => $bookId]);

        $pdo->commit();

        return [
            'borrow_id' => (int)$pdo->lastInsertId(),
            'book' => $book,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
        ];
    } catch (PDOException $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function returnBook(int $userId, int $borrowId): array
{
    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT * FROM borrows WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $borrowId, ':user_id' => $userId]);
    $borrow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$borrow) {
        jsonResponse(['error' => 'Borrow record not found'], 404);
    }
    if ($borrow['return_date'] !== null) {
        jsonResponse(['error' => 'Book already returned'], 409);
    }

    $returnDate = new DateTime();
    $dueDate = new DateTime($borrow['due_date']);
    $penalty = 0.0;
    if ($returnDate > $dueDate) {
        $interval = $returnDate->diff($dueDate);
        $lateDays = $interval->days;
        $penalty = round($lateDays * 1.50, 2);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE borrows SET return_date = :return_date, penalty = :penalty WHERE id = :id');
        $stmt->execute([
            ':return_date' => $returnDate->format('Y-m-d'),
            ':penalty' => $penalty,
            ':id' => $borrowId,
        ]);

        $stmt = $pdo->prepare('UPDATE books SET available = 1 WHERE id = :book_id');
        $stmt->execute([':book_id' => $borrow['book_id']]);

        $pdo->commit();

        return [
            'borrow_id' => (int)$borrowId,
            'book_id' => (int)$borrow['book_id'],
            'returned_at' => $returnDate->format('Y-m-d'),
            'penalty' => $penalty,
        ];
    } catch (PDOException $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function getBorrowedBooks(int $userId): array
{
    $pdo = getDatabase();
    $stmt = $pdo->prepare(
        'SELECT b.id AS borrow_id, books.id AS book_id, books.title, books.author, books.genre, books.isbn, books.publication_year,
            b.borrow_date, b.due_date, b.return_date, b.penalty
        FROM borrows b
        JOIN books ON b.book_id = books.id
        WHERE b.user_id = :user_id
        ORDER BY b.borrow_date DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addSampleBooks(): void
{
    $pdo = getDatabase();
    $existing = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
    if ($existing > 0) {
        return;
    }

    $books = [
        ['The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 'Fiction', 1925],
        ['1984', 'George Orwell', '9780451524935', 'Science Fiction', 1949],
        ['A Brief History of Time', 'Stephen Hawking', '9780553380163', 'Science', 1988],
        ['Sapiens', 'Yuval Noah Harari', '9780062316097', 'History', 2011],
        ['Clean Code', 'Robert C. Martin', '9780132350884', 'Technology', 2008],
    ];

    $stmt = $pdo->prepare('INSERT INTO books (title, author, isbn, genre, publication_year, available) VALUES (:title, :author, :isbn, :genre, :publication_year, 1)');
    foreach ($books as $book) {
        $stmt->execute([
            ':title' => $book[0],
            ':author' => $book[1],
            ':isbn' => $book[2],
            ':genre' => $book[3],
            ':publication_year' => $book[4],
        ]);
    }
}
