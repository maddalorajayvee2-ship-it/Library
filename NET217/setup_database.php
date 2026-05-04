<?php
require_once 'config.php';

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->select_db(DB_NAME);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    echo "Error creating users table: " . $conn->error;
}

// Create books table
$sql = "CREATE TABLE IF NOT EXISTS books (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) UNIQUE,
    genre ENUM('Fiction', 'Science', 'History', 'Thesis') NOT NULL,
    publication_date DATE,
    description TEXT,
    total_copies INT(11) DEFAULT 1,
    available_copies INT(11) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    echo "Error creating books table: " . $conn->error;
}

// Create borrow_transactions table
$sql = "CREATE TABLE IF NOT EXISTS borrow_transactions (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    book_id INT(11) NOT NULL,
    borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NOT NULL,
    return_date TIMESTAMP NULL,
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
)";

if (!$conn->query($sql)) {
    echo "Error creating borrow_transactions table: " . $conn->error;
}

// Insert sample books
$sample_books = [
    ['The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 'Fiction', '1925-04-10', 'A classic American novel'],
    ['To Kill a Mockingbird', 'Harper Lee', '9780061120084', 'Fiction', '1960-07-11', 'A gripping tale of racial injustice'],
    ['A Brief History of Time', 'Stephen Hawking', '9780553380163', 'Science', '1988-03-01', 'Exploration of cosmology for general readers'],
    ['Sapiens', 'Yuval Noah Harari', '9780062316097', 'History', '2011-09-04', 'A brief history of humankind'],
    ['The Origin of Species', 'Charles Darwin', '9781503280786', 'Science', '1859-11-24', 'Foundation of evolutionary biology'],
    ['The Diary of Anne Frank', 'Anne Frank', '9780553296983', 'History', '1947-06-25', 'A young girl\'s diary during WWII']
];

foreach ($sample_books as $book) {
    $sql = "INSERT IGNORE INTO books (title, author, isbn, genre, publication_date, description) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $book[0], $book[1], $book[2], $book[3], $book[4], $book[5]);
    $stmt->execute();
}

echo "Database setup completed successfully!";
echo "<br><a href='index.php'>Go to Login Page</a>";
?>
