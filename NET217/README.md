# Book Borrowing System (PHP)

A complete PHP-based book borrowing system that implements the full user flow from the provided flowchart. The system includes user authentication, book search with advanced filtering, borrowing and returning books with penalty calculation, and user dashboard.

## Features

- **User Authentication**: Login and signup system with secure password hashing
- **Book Search**: Advanced search by title, author, ISBN, or keywords with genre and date filters
- **Borrowing System**: Complete borrowing flow with availability checking and terms agreement
- **Return System**: Book return functionality with automatic penalty calculation for overdue books
- **User Dashboard**: Personal dashboard with statistics and settings management
- **Responsive Design**: Modern, mobile-friendly interface with gradient styling

## System Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Web server (Apache, Nginx, or PHP built-in server)

## Files

- `config.php` - Database configuration and helper functions
- `setup_database.php` - Database initialization script with sample books
- `index.php` - Login and signup page
- `dashboard.php` - User dashboard with settings
- `search_books.php` - Book search with filters
- `book_details.php` - Individual book details page
- `borrow_book.php` - Book borrowing confirmation
- `my_books.php` - User's borrowed books management
- `logout.php` - User logout handler

## Setup Instructions

1. **Database Setup**
   - Create a MySQL database named `book_borrowing_system`
   - Update database credentials in `config.php` if needed
   - Run `setup_database.php` to create tables and insert sample books

2. **Web Server Configuration**
   - Place all files in your web server's document root
   - Ensure PHP is properly configured
   - Set appropriate file permissions

3. **Access the System**
   - Navigate to `http://localhost/your-folder/` in your browser
   - Create a new account or login with existing credentials

## Database Schema

### Users Table
- `id` - Primary key
- `username` - Unique username
- `email` - User email address
- `password` - Hashed password
- `full_name` - User's full name
- `phone` - Phone number (optional)
- `created_at` - Account creation timestamp

### Books Table
- `id` - Primary key
- `title` - Book title
- `author` - Book author
- `isbn` - ISBN number (unique)
- `genre` - Book genre (Fiction, Science, History, Thesis)
- `publication_date` - Publication date
- `description` - Book description
- `total_copies` - Total number of copies
- `available_copies` - Currently available copies

### Borrow Transactions Table
- `id` - Primary key
- `user_id` - Foreign key to users table
- `book_id` - Foreign key to books table
- `borrow_date` - When the book was borrowed
- `due_date` - When the book should be returned
- `return_date` - When the book was actually returned
- `penalty_amount` - Calculated penalty for overdue returns
- `status` - Transaction status (borrowed, returned, overdue)

## System Configuration

The system uses the following constants (configurable in `config.php`):
- `BORROW_PERIOD_DAYS`: Default borrow period (14 days)
- `PENALTY_PER_DAY`: Daily penalty amount ($5.00)

## Usage Flow

1. **Login/Signup**: Users create an account or login with existing credentials
2. **Dashboard**: View statistics and manage account settings
3. **Search Books**: Browse and filter the book collection
4. **Book Details**: View detailed information about specific books
5. **Borrow Book**: Confirm borrowing terms and complete the transaction
6. **My Books**: View borrowed books, check due dates, and return books
7. **Return Book**: Return books with automatic penalty calculation

## Sample Books

The system comes with 6 pre-loaded books:
- The Great Gatsby (Fiction)
- To Kill a Mockingbird (Fiction)
- A Brief History of Time (Science)
- Sapiens (History)
- The Origin of Species (Science)
- The Diary of Anne Frank (History)

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Session-based authentication
- CSRF protection on forms

## Browser Compatibility

The system is compatible with all modern browsers:
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
