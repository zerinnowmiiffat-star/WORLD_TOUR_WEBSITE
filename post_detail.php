<?php
require_once 'config.php';

$conn = getDBConnection();
$error = '';

// Get post ID
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    header("Location: posts.php");
    exit();
}

// Fetch post details
$stmt = $conn->prepare("SELECT p.*, u.name as user_name, u.email as user_email 
                        FROM post p 
                        JOIN user u ON p.user_id = u.id 
                        WHERE p.post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: posts.php");
    exit();
}

$post = $result->fetch_assoc();
$stmt->close();

// Check if current user is the post owner
$is_owner = isLoggedIn() && $_SESSION['user_id'] == $post['user_id'];
$is_admin = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['location']) ?> - World Tour</title>
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
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .post-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .post-image-large {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }
        
        .post-image-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .post-content {
            padding: 2rem;
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #eee;
        }
        
        .post-meta {
            flex: 1;
        }
        
        .post-location {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .post-info {
            display: flex;
            gap: 1.5rem;
            color: #666;
            font-size: 0.95rem;
        }
        
        .post-author {
            font-weight: 600;
            color: #667eea;
        }
        
        .post-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
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
        
        .post-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #444;
            white-space: pre-wrap;
        }
        
        .post-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .detail-value {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .post-image-large {
                height: 250px;
            }
            
            .post-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .post-location {
                font-size: 1.5rem;
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
                <li><a href="posts.php">Travel Stories</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="my_trips.php">My Trips</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
                <?php if (isLoggedIn() && !$is_owner): ?>
                    <a href="send_gift.php?post_id=<?= $post['post_id'] ?>&user_id=<?= $post['user_id'] ?>" 
                    class="btn" style="background:#28a745;">
                        🎁 Send Gift
                    </a>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <a href="posts.php" class="back-link">
            <span>←</span> Back to Travel Stories
        </a>
        
        <div class="post-card">
            <div class="post-image-large">
                <?php if ($post['image']): ?>
                    <img src="uploads/<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['location']) ?>">
                <?php else: ?>
                    📷
                <?php endif; ?>
            </div>
            
            <div class="post-content">
                <div class="post-header">
                    <div class="post-meta">
                        <h1 class="post-location">📍 <?= htmlspecialchars($post['location']) ?></h1>
                        <div class="post-info">
                            <span>By <span class="post-author"><?= htmlspecialchars($post['user_name']) ?></span></span>
                            <span>📅 <?= formatDate($post['date']) ?></span>
                            <span>🕐 <?= date('g:i A', strtotime($post['time'])) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($is_owner || $is_admin): ?>
                        <div class="post-actions">
                            <?php if ($is_admin): ?>
                                <a href="admin/manage_posts.php" class="btn">Manage</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="post-description">
                    <?= htmlspecialchars($post['description']) ?>
                </div>
                
                <div class="post-details">
                    <div class="detail-row">
                        <span class="detail-label">Post ID</span>
                        <span class="detail-value">#<?= $post['post_id'] ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Posted on</span>
                        <span class="detail-value"><?= formatDate($post['date']) ?> at <?= date('g:i A', strtotime($post['time'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Author</span>
                        <span class="detail-value"><?= htmlspecialchars($post['user_name']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>