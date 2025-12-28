<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Check if email is already taken by another user
    $check_stmt = $conn->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email address already in use by another account";
    } else {
        // Update profile
        if (!empty($new_password)) {
            // Verify current password
            $verify_stmt = $conn->prepare("SELECT password FROM user WHERE id = ?");
            $verify_stmt->bind_param("i", $_SESSION['user_id']);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $user_data = $verify_result->fetch_assoc();
            
            if (!password_verify($current_password, $user_data['password'])) {
                $error = "Current password is incorrect";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match";
            } elseif (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters long";
            } else {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user SET name=?, email=?, address=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $address, $hashed_password, $_SESSION['user_id']);
            }
            $verify_stmt->close();
        } else {
            // Update without password change
            $stmt = $conn->prepare("UPDATE user SET name=?, email=?, address=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $address, $_SESSION['user_id']);
        }
        
        if (empty($error)) {
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $success = "Profile updated successfully!";
            } else {
                $error = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user statistics
$stats = [];
$stats['trips'] = $conn->query("SELECT COUNT(*) as count FROM trip_plan WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$stats['posts'] = $conn->query("SELECT COUNT(*) as count FROM post WHERE user_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$stats['gifts_sent'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE sender_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$stats['gifts_received'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE receiver_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];

// Recent activity
$recent_trips = $conn->query("SELECT * FROM trip_plan WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY created_at DESC LIMIT 5");
$recent_posts = $conn->query("SELECT * FROM post WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY date DESC, time DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - World Tour</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            color: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .profile-name {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .profile-email {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            margin-top: 1rem;
            font-size: 0.9rem;
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
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
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
        
        .btn {
            padding: 0.8rem 2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
        }
        
        .activity-date {
            color: #999;
            font-size: 0.9rem;
        }
        
        .empty-message {
            text-align: center;
            color: #666;
            padding: 2rem;
        }
        
        .password-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }
        
        .password-section h4 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        @media (max-width: 968px) {
            .grid-2col {
                grid-template-columns: 1fr;
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
                <li><a href="posts.php">Travel Stories</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">👤</div>
            <h1 class="profile-name"><?= htmlspecialchars($user['name']) ?></h1>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
            <div class="profile-badge">
                <?= $user['user_type'] === 'admin' ? '👑 Administrator' : '✈️ Traveler' ?>
            </div>
            <div style="margin-top:1rem;opacity:0.9;">
                Member since <?= formatDate($user['join_date']) ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✈️</div>
                <div class="stat-number"><?= $stats['trips'] ?></div>
                <div class="stat-label">Trip Plans</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?= $stats['posts'] ?></div>
                <div class="stat-label">Travel Stories</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎁</div>
                <div class="stat-number"><?= $stats['gifts_sent'] ?></div>
                <div class="stat-label">Gifts Sent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎉</div>
                <div class="stat-number"><?= $stats['gifts_received'] ?></div>
                <div class="stat-label">Gifts Received</div>
            </div>
        </div>
        
        <div class="grid-2col">
            <!-- Edit Profile -->
            <div class="card">
                <h2 class="card-title">✏️ Edit Profile</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-input"
                            value="<?= htmlspecialchars($user['name']) ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input"
                            value="<?= htmlspecialchars($user['email']) ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea 
                            name="address" 
                            class="form-textarea"><?= htmlspecialchars($user['address']) ?></textarea>
                    </div>
                    
                    <div class="password-section">
                        <h4>🔒 Change Password (Optional)</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input 
                                type="password" 
                                name="current_password" 
                                class="form-input">
                            <div class="form-help">Required only if changing password</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input 
                                type="password" 
                                name="new_password" 
                                class="form-input"
                                minlength="6">
                            <div class="form-help">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                class="form-input">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" style="margin-top:1.5rem;width:100%;">
                        Update Profile
                    </button>
                </form>
            </div>
            
            <!-- Recent Activity -->
            <div>
                <div class="card" style="margin-bottom:2rem;">
                    <h2 class="card-title">📅 Recent Trip Plans</h2>
                    
                    <?php if ($recent_trips->num_rows > 0): ?>
                        <ul class="activity-list">
                            <?php while ($trip = $recent_trips->fetch_assoc()): ?>
                                <li class="activity-item">
                                    <div>
                                        <div class="activity-title">
                                            <a href="trip_detail.php?id=<?= $trip['place_id'] ?>" style="color:#667eea;text-decoration:none;">
                                                <?= htmlspecialchars($trip['title']) ?>
                                            </a>
                                        </div>
                                        <div class="activity-date"><?= formatDate($trip['created_at']) ?></div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <a href="my_trips.php" style="display:block;text-align:center;margin-top:1rem;color:#667eea;text-decoration:none;font-weight:500;">
                            View All Trips →
                        </a>
                    <?php else: ?>
                        <div class="empty-message">No trips planned yet</div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h2 class="card-title">📝 Recent Posts</h2>
                    
                    <?php if ($recent_posts->num_rows > 0): ?>
                        <ul class="activity-list">
                            <?php while ($post = $recent_posts->fetch_assoc()): ?>
                                <li class="activity-item">
                                    <div>
                                        <div class="activity-title">
                                            <a href="post_detail.php?id=<?= $post['post_id'] ?>" style="color:#667eea;text-decoration:none;">
                                                📍 <?= htmlspecialchars($post['location']) ?>
                                            </a>
                                        </div>
                                        <div class="activity-date"><?= formatDate($post['date']) ?></div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <a href="posts.php?user=<?= $_SESSION['user_id'] ?>" style="display:block;text-align:center;margin-top:1rem;color:#667eea;text-decoration:none;font-weight:500;">
                            View All Posts →
                        </a>
                    <?php else: ?>
                        <div class="empty-message">No posts yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>