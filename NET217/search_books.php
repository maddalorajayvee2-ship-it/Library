<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('index.php');
}

$search_query = '';
$selected_genre = '';
$year_from = '';
$year_to = '';
$books = [];

// Handle search
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $search_query = sanitize_input($_GET['search'] ?? '');
    $selected_genre = sanitize_input($_GET['genre'] ?? '');
    $year_from = sanitize_input($_GET['year_from'] ?? '');
    $year_to = sanitize_input($_GET['year_to'] ?? '');
    
    // Build SQL query
    $sql = "SELECT * FROM books WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($search_query)) {
        $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR description LIKE ?)";
        $search_param = "%$search_query%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= 'ssss';
    }
    
    if (!empty($selected_genre)) {
        $sql .= " AND genre = ?";
        $params[] = $selected_genre;
        $types .= 's';
    }
    
    if (!empty($year_from)) {
        $sql .= " AND YEAR(publication_date) >= ?";
        $params[] = $year_from;
        $types .= 'i';
    }
    
    if (!empty($year_to)) {
        $sql .= " AND YEAR(publication_date) <= ?";
        $params[] = $year_to;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY title";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
} else {
    // Show all books on initial load
    $sql = "SELECT * FROM books ORDER BY title";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books - Book Borrowing System</title>
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
        
        .search-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .search-section h2 {
            color: #333;
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .results-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .results-header h2 {
            color: #333;
        }
        
        .results-count {
            color: #666;
            font-size: 16px;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .book-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 2px solid transparent;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .book-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .book-author {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .book-isbn {
            color: #999;
            font-size: 14px;
            margin-bottom: 1rem;
        }
        
        .book-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .meta-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .meta-tag.fiction {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .meta-tag.science {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .meta-tag.history {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .meta-tag.thesis {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .book-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 1rem;
            max-height: 60px;
            overflow: hidden;
        }
        
        .book-availability {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .availability-status {
            font-weight: 500;
        }
        
        .available {
            color: #4caf50;
        }
        
        .unavailable {
            color: #f44336;
        }
        
        .book-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .btn-small:hover {
            transform: translateY(-2px);
        }
        
        .btn-borrow {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
        }
        
        .btn-small:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .no-results h3 {
            margin-bottom: 1rem;
            color: #333;
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
            
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .books-grid {
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
        <div class="search-section">
            <h2>🔍 Search Books</h2>
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="search">Search (Title, Author, ISBN, Keywords)</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Enter search terms...">
                </div>
                
                <div class="form-group">
                    <label for="genre">Genre</label>
                    <select id="genre" name="genre">
                        <option value="">All Genres</option>
                        <option value="Fiction" <?php echo $selected_genre === 'Fiction' ? 'selected' : ''; ?>>Fiction</option>
                        <option value="Science" <?php echo $selected_genre === 'Science' ? 'selected' : ''; ?>>Science</option>
                        <option value="History" <?php echo $selected_genre === 'History' ? 'selected' : ''; ?>>History</option>
                        <option value="Thesis" <?php echo $selected_genre === 'Thesis' ? 'selected' : ''; ?>>Thesis</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year_from">Publication Year From</label>
                    <input type="number" id="year_from" name="year_from" value="<?php echo htmlspecialchars($year_from); ?>" placeholder="e.g., 2000" min="1800" max="<?php echo date('Y'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="year_to">Publication Year To</label>
                    <input type="number" id="year_to" name="year_to" value="<?php echo htmlspecialchars($year_to); ?>" placeholder="e.g., 2023" min="1800" max="<?php echo date('Y'); ?>">
                </div>
            </form>
            
            <div class="search-buttons">
                <button type="submit" form="search-form" class="btn">Search</button>
                <a href="search_books.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        </div>
        
        <div class="results-section">
            <div class="results-header">
                <h2>Search Results</h2>
                <div class="results-count">
                    <?php echo count($books); ?> book(s) found
                </div>
            </div>
            
            <?php if (empty($books)): ?>
                <div class="no-results">
                    <h3>No books found</h3>
                    <p>Try adjusting your search criteria or browse all books.</p>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                            <?php if (!empty($book['isbn'])): ?>
                                <div class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                            <?php endif; ?>
                            
                            <div class="book-meta">
                                <span class="meta-tag <?php echo strtolower($book['genre']); ?>">
                                    <?php echo htmlspecialchars($book['genre']); ?>
                                </span>
                                <?php if (!empty($book['publication_date'])): ?>
                                    <span class="meta-tag">
                                        <?php echo date('Y', strtotime($book['publication_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($book['description'])): ?>
                                <div class="book-description">
                                    <?php echo htmlspecialchars(substr($book['description'], 0, 100)) . '...'; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="book-availability">
                                <span class="availability-status <?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
                                    <?php echo $book['available_copies'] > 0 ? '✓ Available' : '✗ Unavailable'; ?>
                                </span>
                                <span>
                                    <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?> copies
                                </span>
                            </div>
                            
                            <div class="book-actions">
                                <a href="book_details.php?id=<?php echo $book['id']; ?>" class="btn-small btn-view">View Details</a>
                                <?php if ($book['available_copies'] > 0): ?>
                                    <a href="borrow_book.php?id=<?php echo $book['id']; ?>" class="btn-small btn-borrow">Borrow</a>
                                <?php else: ?>
                                    <button class="btn-small btn-borrow" disabled>Unavailable</button>
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
