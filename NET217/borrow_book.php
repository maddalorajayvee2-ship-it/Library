<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$book_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

if (!$book_id) {
    redirect('search_books.php');
}

// Get book details
$sql = "SELECT * FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('search_books.php');
}

$book = $result->fetch_assoc();

// Check if user already borrowed this book and hasn't returned it
$sql = "SELECT * FROM borrow_transactions WHERE user_id = ? AND book_id = ? AND status = 'borrowed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$existing_borrow = $stmt->get_result()->fetch_assoc();

if ($existing_borrow) {
    $error = "You have already borrowed this book and haven't returned it yet.";
}

// Handle borrow confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_borrow']) && !$error) {
    // Check availability again
    if ($book['available_copies'] <= 0) {
        $error = "This book is no longer available.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create borrow transaction
            $due_date = date('Y-m-d', strtotime('+' . BORROW_PERIOD_DAYS . ' days'));
            $sql = "INSERT INTO borrow_transactions (user_id, book_id, due_date, status) VALUES (?, ?, ?, 'borrowed')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $book_id, $due_date);
            $stmt->execute();
            
            // Update available copies
            $sql = "UPDATE books SET available_copies = available_copies - 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            
            $conn->commit();
            $success = "Book borrowed successfully! Due date: " . date('F j, Y', strtotime($due_date));
            
            // Redirect to my books after 3 seconds
            header("refresh:3;url=my_books.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error borrowing book: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Book - Book Borrowing System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            color: white;
            font-size: 24px;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .user-info {
            color: white;
            font-weight: 500;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .borrow-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .borrow-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .borrow-header h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 28px;
        }
        
        .book-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .book-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .book-author {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .book-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .meta-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .borrow-details {
            margin-bottom: 2rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
        }
        
        .detail-value {
            color: #666;
        }
        
        .detail-value.due-date {
            color: #f57c00;
            font-weight: 600;
        }
        
        .terms-section {
            background: #fff3e0;
            border: 2px solid #ffb74d;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .terms-section h3 {
            color: #f57c00;
            margin-bottom: 1rem;
        }
        
        .terms-list {
            list-style: none;
        }
        
        .terms-list li {
            color: #666;
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .terms-list li:before {
            content: "•";
            color: #f57c00;
            position: absolute;
            left: 0;
            font-weight: bold;
        }
        
        .checkbox-group {
            margin-bottom: 2rem;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        .checkbox-wrapper label {
            color: #333;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.success h3 {
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>📚 Book Borrowing System</h1>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="search_books.php">Search Books</a>
                <a href="my_books.php">My Books</a>
                <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="borrow-card">
            <div class="borrow-header">
                <h2>Borrow Book</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="message error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success">
                    <h3>✓ Successfully Borrowed!</h3>
                    <p><?php echo $success; ?></p>
                    <p>You will be redirected to your books page in 3 seconds...</p>
                </div>
            <?php endif; ?>
            
            <?php if (!$error && !$success): ?>
                <div class="book-summary">
                    <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                    <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                    <div class="book-meta">
                        <span class="meta-tag"><?php echo htmlspecialchars($book['genre']); ?></span>
                        <?php if (!empty($book['isbn'])): ?>
                            <span class="meta-tag">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="borrow-details">
                    <div class="detail-item">
                        <span class="detail-label">Borrower:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Borrow Date:</span>
                        <span class="detail-value"><?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value due-date">
                            <?php echo date('F j, Y', strtotime('+' . BORROW_PERIOD_DAYS . ' days')); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Borrow Period:</span>
                        <span class="detail-value"><?php echo BORROW_PERIOD_DAYS; ?> days</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Late Penalty:</span>
                        <span class="detail-value">$<?php echo number_format(PENALTY_PER_DAY, 2); ?> per day</span>
                    </div>
                </div>
                
                <div class="terms-section">
                    <h3>Borrowing Terms & Conditions</h3>
                    <ul class="terms-list">
                        <li>You must return the book by the due date to avoid penalties</li>
                        <li>Late returns will incur a penalty of $<?php echo number_format(PENALTY_PER_DAY, 2); ?> per day</li>
                        <li>You are responsible for the book's condition while borrowed</li>
                        <li>Lost or damaged books may require replacement cost payment</li>
                        <li>You can renew the book if no one else has reserved it</li>
                        <li>Failure to return books may affect your borrowing privileges</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <div class="checkbox-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the borrowing terms and conditions</label>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="confirm_borrow" class="btn btn-success">Confirm Borrow</button>
                        <a href="book_details.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
