<?php
// student/books.php - Digital Library: Books
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "3") {
    header("location:../");
    exit;
}

$student_id = isset($account_id) ? $account_id : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null);
if (!$student_id) die("Unable to identify student. Please log in again.");

// Build $student array for header compatibility
$student = [
    'fname' => $fname,
    'lname' => $lname,
    'id' => $account_id,
    'display_image' => $img ?? null,
    'gender' => $gender ?? '',
    'email' => $email ?? ''
];

// Sample book data (replace with database later)
$books = [
    ['title' => 'Introduction to Programming', 'author' => 'John Doe', 'category' => 'Computer Science', 'cover' => 'https://via.placeholder.com/150x200?text=Book+Cover', 'available' => true],
    ['title' => 'Advanced Mathematics', 'author' => 'Jane Smith', 'category' => 'Mathematics', 'cover' => 'https://via.placeholder.com/150x200?text=Book+Cover', 'available' => true],
    ['title' => 'Physics for Beginners', 'author' => 'Albert Einstein', 'category' => 'Science', 'cover' => 'https://via.placeholder.com/150x200?text=Book+Cover', 'available' => false],
    ['title' => 'World History', 'author' => 'David McCullough', 'category' => 'History', 'cover' => 'https://via.placeholder.com/150x200?text=Book+Cover', 'available' => true],
    ['title' => 'English Literature', 'author' => 'William Shakespeare', 'category' => 'Literature', 'cover' => 'https://via.placeholder.com/150x200?text=Book+Cover', 'available' => true],
];
?>
<?php require 'inc/header.php'; ?>
<?php require 'inc/sidebar.php'; ?>

<main class="app-content">
    <div class="app-title">
        <div><h1><i class="bi bi-book me-2"></i> Digital Library – Books</h1></div>
        <ul class="app-breadcrumb breadcrumb">
            <li class="breadcrumb-item"><a href="student">Dashboard</a></li>
            <li class="breadcrumb-item active">Books</li>
        </ul>
    </div>

    <!-- Search & Filter Bar -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <div class="search-card">
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Search by title, author, or category...">
                    <button class="btn btn-primary" type="button" id="searchBtn"><i class="bi bi-search"></i> Search</button>
                </div>
                <div class="mt-2 text-center">
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="all">All</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="Computer Science">Computer Science</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="Mathematics">Mathematics</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="Science">Science</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="History">History</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-category="Literature">Literature</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Books Grid -->
    <div class="row" id="booksGrid">
        <?php foreach ($books as $book): ?>
            <div class="col-md-3 col-sm-6 mb-4 book-item" data-title="<?php echo strtolower($book['title']); ?>" data-author="<?php echo strtolower($book['author']); ?>" data-category="<?php echo strtolower($book['category']); ?>">
                <div class="book-card">
                    <div class="book-cover">
                        <img src="<?php echo $book['cover']; ?>" alt="Cover">
                        <?php if (!$book['available']): ?>
                            <div class="unavailable-badge">Currently Unavailable</div>
                        <?php endif; ?>
                    </div>
                    <div class="book-info">
                        <h5 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                        <p class="book-author"><i class="bi bi-person"></i> <?php echo htmlspecialchars($book['author']); ?></p>
                        <p class="book-category"><span class="badge bg-secondary"><?php echo htmlspecialchars($book['category']); ?></span></p>
                        <div class="book-actions">
                            <?php if ($book['available']): ?>
                                <a href="#" class="btn btn-sm btn-primary borrow-btn" data-title="<?php echo htmlspecialchars($book['title']); ?>"><i class="bi bi-bookmark-plus"></i> Borrow</a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-clock"></i> Notify Me</button>
                            <?php endif; ?>
                            <a href="#" class="btn btn-sm btn-outline-secondary preview-btn" data-title="<?php echo htmlspecialchars($book['title']); ?>"><i class="bi bi-eye"></i> Preview</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination placeholder -->
    <div class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination">
                <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
        </nav>
    </div>
</main>

<div id="scrollTopBtn" class="scroll-top-btn" title="Scroll to top">
    <i class="bi bi-arrow-up"></i>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
$(document).ready(function(){
    // Search and filter functionality
    function filterBooks() {
        var searchTerm = $('#searchInput').val().toLowerCase();
        var activeCategory = $('.filter-btn.active').data('category');
        if (!activeCategory || activeCategory === 'all') activeCategory = null;

        $('.book-item').each(function(){
            var title = $(this).data('title');
            var author = $(this).data('author');
            var category = $(this).data('category');
            var matchesSearch = title.includes(searchTerm) || author.includes(searchTerm);
            var matchesCategory = (activeCategory === null || category === activeCategory);
            if (matchesSearch && matchesCategory) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    $('#searchInput').on('keyup', filterBooks);
    $('#searchBtn').on('click', filterBooks);

    $('.filter-btn').on('click', function(){
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        filterBooks();
    });
    $('.filter-btn[data-category="all"]').addClass('active');

    // Borrow button alert
    $('.borrow-btn').on('click', function(e){
        e.preventDefault();
        var title = $(this).data('title');
        Swal.fire({
            title: 'Request Borrow',
            text: `You are about to request "${title}". Please visit the library to collect your book.`,
            icon: 'info',
            confirmButtonText: 'OK, I understand'
        });
    });

    // Preview button alert
    $('.preview-btn').on('click', function(e){
        e.preventDefault();
        var title = $(this).data('title');
        Swal.fire({
            title: 'Preview',
            text: `Preview for "${title}" is not yet available online. Please check the physical copy in the library.`,
            icon: 'info',
            confirmButtonText: 'Close'
        });
    });
});

// Scroll to Top Button
const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) scrollBtn.classList.add('show');
    else scrollBtn.classList.remove('show');
});
scrollBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<style>
/* Books page specific styles */
.app-content {
    background: transparent !important;
}
.search-card, .book-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    border-radius: 28px;
    padding: 1.2rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}
.search-card {
    margin-bottom: 1rem;
}
.book-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s;
}
.book-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}
.book-cover {
    position: relative;
    text-align: center;
    margin-bottom: 1rem;
}
.book-cover img {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 5px 10px rgba(0,0,0,0.1);
}
.unavailable-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(220,53,69,0.9);
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: bold;
}
.book-info {
    flex: 1;
}
.book-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}
.book-author {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
.book-category {
    margin-bottom: 0.8rem;
}
.book-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.filter-btn.active {
    background-color: #4361ee;
    color: white;
    border-color: #4361ee;
}
.breadcrumb {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 0.5rem 1rem;
}
.app-title h1 {
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #2d3a5e);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.scroll-top-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 52px;
    height: 52px;
    background: linear-gradient(145deg, #00c6ff, #0072ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.6rem;
    cursor: pointer;
    box-shadow: 0 6px 0 #003f7f, 0 8px 18px rgba(0,0,0,0.25);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}
.scroll-top-btn.show { opacity: 1; visibility: visible; }
@media (max-width: 768px) {
    .book-cover img { max-height: 150px; }
    .book-title { font-size: 0.9rem; }
}
</style>
</body>
</html>