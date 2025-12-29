<?php
require_once 'config.php';
$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Build query
$where_conditions = [];
$count_where = "WHERE 1=1";
$query_where = "WHERE 1=1";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $search_condition = " AND (p.description LIKE '%{$search_escaped}%' OR p.location LIKE '%{$search_escaped}%')";
    $count_where .= $search_condition;
    $query_where .= $search_condition;
}

if ($user_filter > 0) {
    $user_condition = " AND p.user_id = {$user_filter}";
    $count_where .= $user_condition;
    $query_where .= $user_condition;
}

// Count total posts
$count_query = "SELECT COUNT(*) as total FROM post p {$count_where}";
$total_posts = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $per_page);

// Fetch posts
$posts_query = "SELECT p.*, u.name as user_name, u.email as user_email 
                FROM post p 
                JOIN user u ON p.user_id = u.id 
                {$query_where}
                ORDER BY p.date DESC, p.time DESC 
                LIMIT {$per_page} OFFSET {$offset}";
$posts = $conn->query($posts_query);

// Get all users for filter
$users = $conn->query("SELECT id, name FROM user ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Stories - World Tour</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .results-info {
            color: #666;
            font-size: 1rem;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .post-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .post-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .post-content {
            padding: 1.5rem;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .post-author {
            font-weight: 600;
            color: #667eea;
        }
        
        .post-location {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .post-description {
            color: #666;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.6;
        }
        
        .post-link {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .post-link:hover {
            text-decoration: underline;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.6rem 1rem;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid #667eea;
            transition: background 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .no-results h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .posts-grid {
                grid-template-columns: 1fr;
            }
            
            .action-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">🌍 World Tour</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="destinations.php">Destinations</a></li>
                <li><a href="hotels.php">Hotels</a></li>
                <li><a href="posts.php">Travel Stories</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="my_trips.php">My Trips</a></li>
                    <li><a href="create_post.php">Share Story</a></li>
                    <li><a href="gifts.php">Gifts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="page-header">
        <h1>📖 Travel Stories</h1>
        <p>Real experiences from travelers around the world</p>
    </div>

    <div class="container">
        <!-- Action Bar -->
        <div class="action-bar">
            <div class="results-info">
                Showing <strong><?= $posts->num_rows ?></strong> of <strong><?= $total_posts ?></strong> stories
            </div>
            <?php if (isLoggedIn()): ?>
                <a href="create_post.php" class="btn">
                    <span>+</span> Share Your Story
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">🔍 Search Stories</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input"
                        placeholder="Search by location or description..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">👤 Filter by Traveler</label>
                    <select name="user" class="form-select">
                        <option value="0">All Travelers</option>
                        <?php 
                        $users->data_seek(0);
                        while ($user = $users->fetch_assoc()): 
                        ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Search</button>
            </form>
        </div>
        
        <!-- Posts Grid -->
        <?php if ($posts->num_rows > 0): ?>
            <div class="posts-grid">
                <?php while ($post = $posts->fetch_assoc()): ?>
                    <div class="post-card">
                        <div class="post-image">
                            <?php if ($post['image']): ?>
                                <img src="uploads/<?= htmlspecialchars($post['image']) ?>" 
                                    alt="<?= htmlspecialchars($post['location']) ?>">
                            <?php else: ?>
                                📷
                            <?php endif; ?>
                        </div>
                        
                        <div class="post-content">
                            <div class="post-meta">
                                <span>By <span class="post-author"><?= htmlspecialchars($post['user_name']) ?></span></span>
                                <span><?= formatDate($post['date']) ?></span>
                            </div>
                            
                            <h3 class="post-location">📍 <?= htmlspecialchars($post['location']) ?></h3>
                            
                            <p class="post-description">
                                <?= htmlspecialchars($post['description']) ?>
                            </p>
                            
                            <a href="post_detail.php?id=<?= $post['post_id'] ?>" class="post-link">
                                Read Full Story <span>→</span>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $user_filter ? '&user=' . $user_filter : '' ?>">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled">← Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $user_filter ? '&user=' . $user_filter : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $user_filter ? '&user=' . $user_filter : '' ?>">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <h2>📭 No stories found</h2>
                <p style="color:#666;margin-bottom:2rem;">
                    <?php if ($search || $user_filter): ?>
                        Try adjusting your filters or <a href="posts.php" style="color:#667eea;">browse all stories</a>
                    <?php else: ?>
                        Be the first to share your travel experience!
                    <?php endif; ?>
                </p>
                <?php if (isLoggedIn()): ?>
                    <a href="create_post.php" class="btn">Share Your Story</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>