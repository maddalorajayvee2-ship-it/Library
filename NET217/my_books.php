<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';
$filter = $_GET['filter'] ?? 'all';

// Handle book return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book'])) {
    $transaction_id = $_POST['transaction_id'];
    
    // Get transaction details
    $sql = "SELECT bt.*, b.title, b.author FROM borrow_transactions bt 
            JOIN books b ON bt.book_id = b.id 
            WHERE bt.id = ? AND bt.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $transaction_id, $user_id);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    
    if ($transaction && $transaction['status'] == 'borrowed') {
        // Calculate penalty if overdue
        $penalty = calculate_penalty($transaction['due_date'], date('Y-m-d'));
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update transaction
            $sql = "UPDATE borrow_transactions SET return_date = NOW(), penalty_amount = ?, status = 'returned' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $penalty, $transaction_id);
            $stmt->execute();
            
            // Update book availability
            $sql = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $transaction['book_id']);
            $stmt->execute();
            
            $conn->commit();
            
            if ($penalty > 0) {
                $message = "Book returned successfully! Penalty: $" . number_format($penalty, 2);
            } else {
                $message = "Book returned successfully!";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error returning book: " . $e->getMessage();
        }
    }
}

// Get borrowed books
$sql = "SELECT bt.*, b.title, b.author, b.isbn, b.genre FROM borrow_transactions bt 
        JOIN books b ON bt.book_id = b.id 
        WHERE bt.user_id = ?";

if ($filter === 'overdue') {
    $sql .= " AND bt.status = 'borrowed' AND bt.due_date < CURDATE()";
} elseif ($filter === 'returned') {
    $sql .= " AND bt.status = 'returned'";
} elseif ($filter === 'current') {
    $sql .= " AND bt.status = 'borrowed'";
}

$sql .= " ORDER BY bt.borrow_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books - Book Borrowing System</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 28px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 2rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            color: #666;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        
        .filter-tab:hover {
            color: #667eea;
        }
        
        .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .books-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .books-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .book-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .book-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .book-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .book-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 18px;
        }
        
        .book-author {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .book-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-borrowed {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-returned {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .status-overdue {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        
        .detail-value.overdue {
            color: #d32f2f;
        }
        
        .book-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .no-books {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-books h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
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
            
            .filter-tabs {
                overflow-x: auto;
            }
            
            .book-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .book-actions {
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
        <div class="page-header">
            <h2>My Borrowed Books</h2>
            <div class="filter-tabs">
                <a href="my_books.php?filter=current" class="filter-tab <?php echo $filter === 'current' ? 'active' : ''; ?>">
                    Currently Borrowed
                </a>
                <a href="my_books.php?filter=overdue" class="filter-tab <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                    Overdue
                </a>
                <a href="my_books.php?filter=returned" class="filter-tab <?php echo $filter === 'returned' ? 'active' : ''; ?>">
                    Returned
                </a>
                <a href="my_books.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Books
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="books-section">
            <?php if (empty($books)): ?>
                <div class="no-books">
                    <h3>No books found</h3>
                    <p>
                        <?php
                        switch($filter) {
                            case 'current':
                                echo "You don't have any currently borrowed books.";
                                break;
                            case 'overdue':
                                echo "You don't have any overdue books. Great job!";
                                break;
                            case 'returned':
                                echo "You haven't returned any books yet.";
                                break;
                            default:
                                echo "You haven't borrowed any books yet.";
                        }
                        ?>
                    </p>
                    <?php if ($filter !== 'all'): ?>
                        <p><a href="my_books.php?filter=all">View all books</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-header">
                                <div class="book-info">
                                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                                    <?php if (!empty($book['isbn'])): ?>
                                        <div class="book-author">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-status <?php 
                                    echo $book['status'] === 'borrowed' && $book['due_date'] < date('Y-m-d') ? 'status-overdue' : 'status-' . $book['status']; 
                                ?>">
                                    <?php 
                                    if ($book['status'] === 'borrowed' && $book['due_date'] < date('Y-m-d')) {
                                        echo 'Overdue';
                                    } else {
                                        echo ucfirst($book['status']);
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="book-details">
                                <div class="detail-item">
                                    <span class="detail-label">Borrow Date</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Due Date</span>
                                    <span class="detail-value <?php echo $book['due_date'] < date('Y-m-d') && $book['status'] === 'borrowed' ? 'overdue' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                    </span>
                                </div>
                                <?php if ($book['status'] === 'returned'): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Return Date</span>
                                        <span class="detail-value"><?php echo date('M j, Y', strtotime($book['return_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($book['penalty_amount'] > 0): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Penalty</span>
                                        <span class="detail-value overdue">$<?php echo number_format($book['penalty_amount'], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Genre</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($book['genre']); ?></span>
                                </div>
                            </div>
                            
                            <div class="book-actions">
                                <?php if ($book['status'] === 'borrowed'): ?>
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="transaction_id" value="<?php echo $book['id']; ?>">
                                        <button type="submit" name="return_book" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to return this book?')">
                                            Return Book
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="book_details.php?id=<?php echo $book['book_id']; ?>" class="btn btn-primary">
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
