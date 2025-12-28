<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $post_id = intval($_GET['delete']);
    
    // Get image filename before deletion
    $stmt = $conn->prepare("SELECT image FROM post WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();
    
    // Delete post
    $stmt = $conn->prepare("DELETE FROM post WHERE post_id = ?");
    $stmt->bind_param("i", $post_id);
    if ($stmt->execute()) {
        // Delete image file if exists
        if ($post && $post['image'] && file_exists("../uploads/" . $post['image'])) {
            unlink("../uploads/" . $post['image']);
        }
        $success = "Post deleted successfully";
    } else {
        $error = "Error deleting post";
    }
    $stmt->close();
}

// Filter options
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$query = "SELECT p.*, u.name as user_name, u.email as user_email 
        FROM post p 
        JOIN user u ON p.user_id = u.id 
        WHERE 1=1";

if ($filter_user > 0) {
    $query .= " AND p.user_id = " . $filter_user;
}

if (!empty($search)) {
    $query .= " AND (p.description LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR p.location LIKE '%" . $conn->real_escape_string($search) . "%')";
}

$query .= " ORDER BY p.date DESC, p.time DESC";

$posts = $conn->query($query);

// Get all users for filter
$users = $conn->query("SELECT id, name FROM user ORDER BY name");

// Get statistics
$stats = [];
$stats['total_posts'] = $conn->query("SELECT COUNT(*) as count FROM post")->fetch_assoc()['count'];
$stats['posts_today'] = $conn->query("SELECT COUNT(*) as count FROM post WHERE date = CURDATE()")->fetch_assoc()['count'];
$stats['posts_this_month'] = $conn->query("SELECT COUNT(*) as count FROM post WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
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
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
        }
        
        .btn {
            padding: 0.7rem 1.5rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-small {
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
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
            grid-template-columns: 1fr 1fr auto;
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
            gap: 1.5rem;
        }
        
        .post-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        
        .post-user {
            font-weight: 600;
            color: #667eea;
        }
        
        .post-location {
            color: #333;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .post-description {
            color: #666;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .post-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 968px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .posts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">📝 Admin Panel</a>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="manage_countries.php">Countries</a></li>
                <li><a href="manage_places.php">Places</a></li>
                <li><a href="manage_hotels.php">Hotels</a></li>
                <li><a href="manage_users.php">Users</a></li>
                <li><a href="../index.php">View Site</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Manage Posts</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_posts'] ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['posts_today'] ?></div>
                <div class="stat-label">Posts Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['posts_this_month'] ?></div>
                <div class="stat-label">Posts This Month</div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input"
                        placeholder="Search by description or location..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Filter by User</label>
                    <select name="user" class="form-select">
                        <option value="0">All Users</option>
                        <?php 
                        $users->data_seek(0);
                        while ($user = $users->fetch_assoc()): 
                        ?>
                            <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Apply Filters</button>
            </form>
        </div>
        
        <!-- Posts Grid -->
        <?php if ($posts->num_rows > 0): ?>
            <div class="posts-grid">
                <?php while ($post = $posts->fetch_assoc()): ?>
                    <div class="post-card">
                        <div class="post-image">
                            <?php if ($post['image']): ?>
                                <img src="../uploads/<?= htmlspecialchars($post['image']) ?>" alt="Post image">
                            <?php else: ?>
                                📷
                            <?php endif; ?>
                        </div>
                        
                        <div class="post-content">
                            <div class="post-meta">
                                <span class="post-user"><?= htmlspecialchars($post['user_name']) ?></span>
                                <span><?= formatDate($post['date']) ?></span>
                            </div>
                            
                            <div class="post-location">
                                📍 <?= htmlspecialchars($post['location']) ?>
                            </div>
                            
                            <div class="post-description">
                                <?= htmlspecialchars($post['description']) ?>
                            </div>
                            
                            <div class="post-actions">
                                <a href="../post_detail.php?id=<?= $post['post_id'] ?>" 
                                class="btn btn-small" 
                                target="_blank">View</a>
                                <a href="?delete=<?= $post['post_id'] ?><?= $filter_user ? '&user=' . $filter_user : '' ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                                class="btn btn-small btn-danger"
                                onclick="return confirm('Are you sure you want to delete this post?')">
                                Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <p style="text-align:center;color:#666;padding:2rem;">
                    No posts found. <?= $search || $filter_user ? 'Try adjusting your filters.' : '' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>