<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('index.php');
}

// Get book ID
$book_id = $_GET['id'] ?? 0;

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

// Get similar books (same genre)
$sql = "SELECT * FROM books WHERE genre = ? AND id != ? ORDER BY title LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $book['genre'], $book_id);
$stmt->execute();
$similar_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Book Details</title>
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
        
        .book-details {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .book-header {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .book-cover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 300px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            padding: 1rem;
        }
        
        .book-info h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 32px;
        }
        
        .book-meta {
            margin-bottom: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: #666;
        }
        
        .meta-label {
            font-weight: 600;
            margin-right: 0.5rem;
            color: #333;
            min-width: 100px;
        }
        
        .genre-tag {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .genre-tag.fiction {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .genre-tag.science {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .genre-tag.history {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .genre-tag.thesis {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .availability {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .availability-status {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .available {
            color: #4caf50;
        }
        
        .unavailable {
            color: #f44336;
        }
        
        .book-actions {
            display: flex;
            gap: 1rem;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        
        .book-description {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }
        
        .book-description h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .book-description p {
            color: #666;
            line-height: 1.6;
        }
        
        .similar-books {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .similar-books h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }
        
        .similar-books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .similar-book-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .similar-book-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .similar-book-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 14px;
            line-height: 1.3;
        }
        
        .similar-book-author {
            color: #666;
            font-size: 12px;
            margin-bottom: 0.5rem;
        }
        
        .similar-book-availability {
            font-size: 12px;
            font-weight: 500;
        }
        
        .similar-book-availability.available {
            color: #4caf50;
        }
        
        .similar-book-availability.unavailable {
            color: #f44336;
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
            
            .book-header {
                grid-template-columns: 1fr;
            }
            
            .book-actions {
                flex-direction: column;
            }
            
            .similar-books-grid {
                grid-template-columns: 1fr;
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
        <div class="book-details">
            <div class="book-header">
                <div class="book-cover">
                    📖
                </div>
                
                <div class="book-info">
                    <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <span class="meta-label">Author:</span>
                            <span><?php echo htmlspecialchars($book['author']); ?></span>
                        </div>
                        
                        <?php if (!empty($book['isbn'])): ?>
                            <div class="meta-item">
                                <span class="meta-label">ISBN:</span>
                                <span><?php echo htmlspecialchars($book['isbn']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <span class="meta-label">Genre:</span>
                            <span class="genre-tag <?php echo strtolower($book['genre']); ?>">
                                <?php echo htmlspecialchars($book['genre']); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($book['publication_date'])): ?>
                            <div class="meta-item">
                                <span class="meta-label">Published:</span>
                                <span><?php echo date('F j, Y', strtotime($book['publication_date'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="availability">
                        <div class="availability-status <?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
                            <?php echo $book['available_copies'] > 0 ? '✓ Available' : '✗ Currently Unavailable'; ?>
                        </div>
                        <div>
                            <?php echo $book['available_copies']; ?> of <?php echo $book['total_copies']; ?> copies available
                        </div>
                    </div>
                    
                    <div class="book-actions">
                        <?php if ($book['available_copies'] > 0): ?>
                            <a href="borrow_book.php?id=<?php echo $book['id']; ?>" class="btn btn-success">Borrow This Book</a>
                        <?php else: ?>
                            <button class="btn btn-success" disabled>Currently Unavailable</button>
                        <?php endif; ?>
                        <a href="search_books.php" class="btn btn-secondary">Back to Search</a>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($book['description'])): ?>
                <div class="book-description">
                    <h2>Description</h2>
                    <p><?php echo htmlspecialchars($book['description']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($similar_books)): ?>
            <div class="similar-books">
                <h2>Similar Books</h2>
                <div class="similar-books-grid">
                    <?php foreach ($similar_books as $similar_book): ?>
                        <a href="book_details.php?id=<?php echo $similar_book['id']; ?>" class="similar-book-card">
                            <div class="similar-book-title"><?php echo htmlspecialchars($similar_book['title']); ?></div>
                            <div class="similar-book-author">by <?php echo htmlspecialchars($similar_book['author']); ?></div>
                            <div class="similar-book-availability <?php echo $similar_book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
                                <?php echo $similar_book['available_copies'] > 0 ? 'Available' : 'Unavailable'; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
