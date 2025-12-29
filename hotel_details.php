<?php
require_once 'config.php';

$conn = getDBConnection();
$success = '';
$error = '';

// Get hotel code
$hotel_code = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hotel_code <= 0) {
    header("Location: destinations.php");
    exit();
}

// Fetch hotel details
$stmt = $conn->prepare("SELECT h.*, tp.country_name 
                        FROM hotel h 
                        LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                        WHERE h.hotel_code = ?");
$stmt->bind_param("i", $hotel_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: destinations.php");
    exit();
}

$hotel = $result->fetch_assoc();
$stmt->close();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $rating = intval($_POST['rating']);
    $comment = sanitize($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5";
    } elseif (empty($comment)) {
        $error = "Please write a comment";
    } else {
        // Check if user already reviewed this hotel
        $check_stmt = $conn->prepare("SELECT review_id FROM review WHERE user_id = ? AND hotel_code = ?");
        $check_stmt->bind_param("ii", $_SESSION['user_id'], $hotel_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing review
            $stmt = $conn->prepare("UPDATE review SET rating = ?, comment = ?, review_date = CURRENT_TIMESTAMP WHERE user_id = ? AND hotel_code = ?");
            $stmt->bind_param("isii", $rating, $comment, $_SESSION['user_id'], $hotel_code);
        } else {
            // Insert new review
            $stmt = $conn->prepare("INSERT INTO review (user_id, hotel_code, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $_SESSION['user_id'], $hotel_code, $rating, $comment);
        }
        
        if ($stmt->execute()) {
            $success = "Review submitted successfully!";
            // Refresh to show new review
            header("refresh:1;url=hotel_detail.php?id=$hotel_code");
        } else {
            $error = "Error submitting review: " . $stmt->error;
        }
        $stmt->close();
        $check_stmt->close();
    }
}

// Fetch reviews for this hotel
$reviews_query = "SELECT r.*, u.name as user_name 
                  FROM review r 
                  JOIN user u ON r.user_id = u.id 
                  WHERE r.hotel_code = ? 
                  ORDER BY r.review_date DESC";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $hotel_code);
$stmt->execute();
$reviews = $stmt->get_result();
$stmt->close();

// Calculate average rating
$avg_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM review WHERE hotel_code = ?";
$stmt = $conn->prepare($avg_query);
$stmt->bind_param("i", $hotel_code);
$stmt->execute();
$rating_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if current user has already reviewed
$user_review = null;
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT * FROM review WHERE user_id = ? AND hotel_code = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $hotel_code);
    $stmt->execute();
    $user_review = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel['hotel_name']) ?> - World Tour</title>
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
        
        .hotel-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .hotel-name {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .hotel-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .hotel-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .hotel-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .info-grid {
            display: grid;
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .discount-banner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            text-align: center;
            font-weight: 600;
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
        
        .review-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
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
        
        .rating-input {
            display: flex;
            gap: 0.5rem;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 2rem;
        }
        
        .rating-input input[type="radio"] {
            display: none;
        }
        
        .rating-input label {
            cursor: pointer;
            color: #ddd;
            transition: color 0.2s;
        }
        
        .rating-input input[type="radio"]:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: #ffc107;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            min-height: 120px;
            resize: vertical;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: transform 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .reviews-list {
            display: grid;
            gap: 1.5rem;
        }
        
        .review-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .review-author {
            font-weight: 600;
            color: #667eea;
        }
        
        .review-date {
            color: #999;
            font-size: 0.9rem;
        }
        
        .review-rating {
            color: #ffc107;
            font-size: 1.2rem;
        }
        
        .review-comment {
            color: #555;
            line-height: 1.8;
        }
        
        .login-prompt {
            background: #e7f3ff;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .login-prompt a {
            color: #667eea;
            font-weight: bold;
        }
        
        @media (max-width: 968px) {
            .hotel-details {
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
                <?php if (isLoggedIn()): ?>
                    <li><a href="my_trips.php">My Trips</a></li>
                    <li><a href="gifts.php">Gifts</a></li>
                    <li><a href="create_post.php">Share Story</a></li>
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

    <div class="container">
        <a href="destinations.php" class="back-link">
            <span>←</span> Back to Destinations
        </a>
        
        <!-- Hotel Header -->
        <div class="hotel-header">
            <h1 class="hotel-name">🏨 <?= htmlspecialchars($hotel['hotel_name']) ?></h1>
            
            <div class="hotel-meta">
                <div class="hotel-meta-item">
                    📍 <?= htmlspecialchars($hotel['place_name']) ?>
                </div>
                <?php if ($hotel['country_name']): ?>
                    <div class="hotel-meta-item">
                        🌍 <?= htmlspecialchars($hotel['country_name']) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($rating_data['total_reviews'] > 0): ?>
                <div class="rating-display">
                    <span class="stars">
                        <?= str_repeat('⭐', round($rating_data['avg_rating'])) ?>
                    </span>
                    <span><?= number_format($rating_data['avg_rating'], 1) ?></span>
                    <span style="color:#999;">(<?= $rating_data['total_reviews'] ?> reviews)</span>
                </div>
            <?php else: ?>
                <div style="color:#999;">No reviews yet - be the first to review!</div>
            <?php endif; ?>
        </div>
        
        <div class="hotel-details">
            <!-- Left Column - Reviews -->
            <div>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <!-- Review Form -->
                <?php if (isLoggedIn()): ?>
                    <div class="review-form">
                        <h3 style="margin-bottom:1rem;">
                            <?= $user_review ? 'Update Your Review' : 'Write a Review' ?>
                        </h3>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Your Rating</label>
                                <div class="rating-input">
                                    <input type="radio" name="rating" value="5" id="star5" 
                                           <?= ($user_review && $user_review['rating'] == 5) ? 'checked' : '' ?> required>
                                    <label for="star5">⭐</label>
                                    
                                    <input type="radio" name="rating" value="4" id="star4"
                                           <?= ($user_review && $user_review['rating'] == 4) ? 'checked' : '' ?>>
                                    <label for="star4">⭐</label>
                                    
                                    <input type="radio" name="rating" value="3" id="star3"
                                           <?= ($user_review && $user_review['rating'] == 3) ? 'checked' : '' ?>>
                                    <label for="star3">⭐</label>
                                    
                                    <input type="radio" name="rating" value="2" id="star2"
                                           <?= ($user_review && $user_review['rating'] == 2) ? 'checked' : '' ?>>
                                    <label for="star2">⭐</label>
                                    
                                    <input type="radio" name="rating" value="1" id="star1"
                                           <?= ($user_review && $user_review['rating'] == 1) ? 'checked' : '' ?>>
                                    <label for="star1">⭐</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Your Review</label>
                                <textarea 
                                    name="comment" 
                                    class="form-textarea"
                                    placeholder="Share your experience at this hotel..."
                                    required><?= $user_review ? htmlspecialchars($user_review['comment']) : '' ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn">
                                <?= $user_review ? '✏️ Update Review' : '📝 Submit Review' ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <h3 style="margin-bottom:1rem;">Want to review this hotel?</h3>
                        <p><a href="login.php">Login</a> or <a href="register.php">Register</a> to share your experience!</p>
                    </div>
                <?php endif; ?>
                
                <!-- Reviews List -->
                <div class="card" style="margin-top:2rem;">
                    <h2 class="card-title">Guest Reviews (<?= $rating_data['total_reviews'] ?>)</h2>
                    
                    <?php if ($reviews->num_rows > 0): ?>
                        <div class="reviews-list">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div>
                                            <div class="review-author">
                                                <?= htmlspecialchars($review['user_name']) ?>
                                            </div>
                                            <div class="review-rating">
                                                <?= str_repeat('⭐', $review['rating']) ?>
                                            </div>
                                        </div>
                                        <div class="review-date">
                                            <?= formatDate($review['review_date']) ?>
                                        </div>
                                    </div>
                                    <div class="review-comment">
                                        <?= htmlspecialchars($review['comment']) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center;color:#666;padding:2rem;">
                            No reviews yet. Be the first to share your experience!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column - Hotel Info -->
            <div>
                <div class="card">
                    <h2 class="card-title">Hotel Information</h2>
                    
                    <div class="info-grid">
                        <?php if ($hotel['address']): ?>
                            <div class="info-item">
                                <span class="info-label">📍 Address</span>
                                <span class="info-value"><?= htmlspecialchars($hotel['address']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hotel['rating']): ?>
                            <div class="info-item">
                                <span class="info-label">⭐ Hotel Rating</span>
                                <span class="info-value"><?= number_format($hotel['rating'], 1) ?>/5.0</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hotel['price_range']): ?>
                            <div class="info-item">
                                <span class="info-label">💰 Price Range</span>
                                <span class="info-value"><?= htmlspecialchars($hotel['price_range']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <span class="info-label">🏷️ Hotel ID</span>
                            <span class="info-value">#<?= $hotel['hotel_code'] ?></span>
                        </div>
                    </div>
                    
                    <?php if ($hotel['discount_details']): ?>
                        <div class="discount-banner">
                            🎉 Special Offer: <?= htmlspecialchars($hotel['discount_details']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>