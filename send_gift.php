<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Get parameters
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id']);
    $message = sanitize($_POST['message']);
    $related_post = !empty($_POST['post_id']) ? intval($_POST['post_id']) : null;
    $related_trip = !empty($_POST['trip_id']) ? intval($_POST['trip_id']) : null;
    
    // Cannot send gift to yourself
    if ($receiver_id == $_SESSION['user_id']) {
        $error = "You cannot send a gift to yourself!";
    } elseif (empty($receiver_id)) {
        $error = "Please select a recipient";
    } else {
        // Insert gift
        $stmt = $conn->prepare("INSERT INTO gift (sender_id, receiver_id, post_id, place_id, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $_SESSION['user_id'], $receiver_id, $related_post, $related_trip, $message);
        
        if ($stmt->execute()) {
            $success = "Gift sent successfully!";
            header("refresh:2;url=gifts.php");
        } else {
            $error = "Error sending gift: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all users except current user
$users = $conn->query("SELECT id, name, email FROM user WHERE id != " . $_SESSION['user_id'] . " ORDER BY name");

// Fetch post details if post_id is provided
$post_details = null;
if ($post_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, u.id as author_id, u.name as author_name FROM post p JOIN user u ON p.user_id = u.id WHERE p.post_id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post_details = $result->fetch_assoc();
    $stmt->close();
    
    if ($post_details) {
        $user_id = $post_details['author_id'];
    }
}

// Fetch trip details if trip_id is provided
$trip_details = null;
if ($trip_id > 0) {
    $stmt = $conn->prepare("SELECT tp.*, u.id as author_id, u.name as author_name FROM trip_plan tp JOIN user u ON tp.user_id = u.id WHERE tp.place_id = ?");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trip_details = $result->fetch_assoc();
    $stmt->close();
    
    if ($trip_details) {
        $user_id = $trip_details['author_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Gift - World Tour</title>
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
            max-width: 800px;
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
        
        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .page-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .gift-icon {
            font-size: 5rem;
            text-align: center;
            margin-bottom: 1rem;
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
        
        .related-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .related-item h3 {
            margin-bottom: 0.5rem;
            color: #667eea;
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
        
        .required {
            color: #e74c3c;
        }
        
        .form-select, .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: transform 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        @media (max-width: 768px) {
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
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <a href="gifts.php" class="back-link">
            <span>←</span> Back to Gifts
        </a>
        
        <div class="card">
            <div class="gift-icon">🎁</div>
            <h1 class="page-title">Send a Gift</h1>
            <p class="page-subtitle">Appreciate someone's travel story or trip plan</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?= htmlspecialchars($success) ?>
                    <br><small>Redirecting to gifts page...</small>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($post_details): ?>
                <div class="related-item">
                    <h3>📍 Related to Post</h3>
                    <p><strong>Location:</strong> <?= htmlspecialchars($post_details['location']) ?></p>
                    <p><strong>By:</strong> <?= htmlspecialchars($post_details['author_name']) ?></p>
                    <p style="margin-top:0.5rem;"><?= htmlspecialchars(substr($post_details['description'], 0, 150)) ?>...</p>
                </div>
            <?php endif; ?>
            
            <?php if ($trip_details): ?>
                <div class="related-item">
                    <h3>✈️ Related to Trip</h3>
                    <p><strong>Trip:</strong> <?= htmlspecialchars($trip_details['title']) ?></p>
                    <p><strong>By:</strong> <?= htmlspecialchars($trip_details['author_name']) ?></p>
                    <?php if ($trip_details['budget']): ?>
                        <p><strong>Budget:</strong> $<?= number_format($trip_details['budget'], 2) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($post_id > 0): ?>
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                <?php endif; ?>
                
                <?php if ($trip_id > 0): ?>
                    <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">
                        Send to <span class="required">*</span>
                    </label>
                    <select name="receiver_id" class="form-select" required>
                        <option value="">Select recipient...</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?= $user['id'] ?>" <?= ($user['id'] == $user_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-help">Choose who you want to send this gift to</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Personal Message
                    </label>
                    <textarea 
                        name="message" 
                        class="form-textarea"
                        placeholder="Write a personal message to accompany your gift..."
                        maxlength="500"></textarea>
                    <div class="form-help">Optional: Add a personal touch to your gift (max 500 characters)</div>
                </div>
                
                <button type="submit" class="btn">
                    🎁 Send Gift
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>