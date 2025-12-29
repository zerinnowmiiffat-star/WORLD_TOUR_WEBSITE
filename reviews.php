<?php
/**
 * reviews.php
 * This file handles fetching and submitting reviews.
 * It is designed to be INCLUDED inside hotel_detail.php
 * It expects $conn, $hotel_code, and user session to be available.
 */

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) {
        $rating = intval($_POST['rating']);
        $comment = htmlspecialchars(stripslashes(trim($_POST['comment'])));
        $user_id = $_SESSION['user_id'];
        
        if ($rating < 1 || $rating > 5) {
            echo "<div class='alert alert-error'>Rating must be between 1 and 5.</div>";
        } elseif (empty($comment)) {
            echo "<div class='alert alert-error'>Please write a comment.</div>";
        } else {
            // Check for existing review
            $check = $conn->prepare("SELECT review_id FROM review WHERE user_id = ? AND hotel_code = ?");
            $check->bind_param("ii", $user_id, $hotel_code);
            $check->execute();
            
            if ($check->get_result()->num_rows > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE review SET rating = ?, comment = ?, review_date = CURRENT_TIMESTAMP WHERE user_id = ? AND hotel_code = ?");
                $stmt->bind_param("isii", $rating, $comment, $user_id, $hotel_code);
                $msg = "Review updated!";
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO review (user_id, hotel_code, rating, comment) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $user_id, $hotel_code, $rating, $comment);
                $msg = "Review submitted!";
            }
            
            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>$msg</div>";
                // Refresh to show changes
                echo "<meta http-equiv='refresh' content='1'>";
            } else {
                echo "<div class='alert alert-error'>Error: " . $conn->error . "</div>";
            }
            $stmt->close();
            $check->close();
        }
    } else {
        echo "<div class='alert alert-error'>You must be logged in to review.</div>";
    }
}

// Fetch Reviews
$reviews_query = "SELECT r.*, u.name as user_name 
                  FROM review r 
                  JOIN user u ON r.user_id = u.id 
                  WHERE r.hotel_code = ? 
                  ORDER BY r.review_date DESC";
$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $hotel_code);
$stmt->execute();
$reviews_result = $stmt->get_result();
$stmt->close();

// Check if user has reviewed (for form pre-fill)
$my_review = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM review WHERE user_id = ? AND hotel_code = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $hotel_code);
    $stmt->execute();
    $my_review = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<div class="reviews-section" id="reviews">
    <h3 style="margin-bottom:1.5rem; border-bottom:2px solid #ddd; padding-bottom:0.5rem;">
        📝 Reviews & Comments
    </h3>

    <!-- Review Form -->
    <div class="review-form-box">
        <?php if (isset($_SESSION['user_id'])): ?>
            <h4><?= $my_review ? 'Edit Your Review' : 'Leave a Review' ?></h4>
            <form method="POST" action="">
                <input type="hidden" name="submit_review" value="1">
                
                <div class="form-group">
                    <label>Rating:</label>
                    <div class="star-rating-input">
                        <?php 
                        $curr = $my_review ? $my_review['rating'] : 5;
                        for($i=5; $i>=1; $i--) {
                            $chk = ($curr == $i) ? 'checked' : '';
                            echo "<input type='radio' name='rating' value='$i' id='st$i' $chk required><label for='st$i'>★</label>";
                        } 
                        ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Comment:</label>
                    <textarea name="comment" class="form-control" placeholder="How was your stay?" required><?= $my_review ? htmlspecialchars($my_review['comment']) : '' ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit"><?= $my_review ? 'Update Review' : 'Post Review' ?></button>
            </form>
        <?php else: ?>
            <div class="login-alert">
                <a href="login.php">Login</a> or <a href="register.php">Register</a> to leave a review.
            </div>
        <?php endif; ?>
    </div>

    <!-- Reviews List -->
    <div class="reviews-list">
        <?php if ($reviews_result->num_rows > 0): ?>
            <?php while($row = $reviews_result->fetch_assoc()): ?>
                <div class="review-item">
                    <div class="review-header">
                        <span class="review-author"><?= htmlspecialchars($row['user_name']) ?></span>
                        <span class="review-date"><?= date('M d, Y', strtotime($row['review_date'])) ?></span>
                    </div>
                    <div class="review-stars">
                        <?= str_repeat('★', $row['rating']) ?><span style="color:#e0e0e0"><?= str_repeat('★', 5-$row['rating']) ?></span>
                    </div>
                    <div class="review-text">
                        <?= nl2br(htmlspecialchars($row['comment'])) ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-reviews">No reviews yet. Be the first to share your experience!</p>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Styles specific to reviews.php component */
    .reviews-section { margin-top: 3rem; background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .review-form-box { background: #f9f9f9; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; border: 1px solid #eee; }
    .form-control { width: 100%; padding: 0.8rem; border: 1px solid #ccc; border-radius: 5px; min-height: 80px; margin-top: 0.5rem; }
    
    .star-rating-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
    .star-rating-input input { display: none; }
    .star-rating-input label { font-size: 24px; color: #ddd; cursor: pointer; }
    .star-rating-input input:checked ~ label, .star-rating-input label:hover, .star-rating-input label:hover ~ label { color: #ffc107; }
    
    .btn-submit { background: #28a745; color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 5px; cursor: pointer; margin-top: 1rem; font-weight: bold; }
    .btn-submit:hover { background: #218838; }
    
    .review-item { border-bottom: 1px solid #eee; padding: 1.5rem 0; }
    .review-item:last-child { border-bottom: none; }
    .review-header { display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold; color: #555; }
    .review-date { font-weight: normal; color: #999; font-size: 0.9rem; }
    .review-stars { color: #ffc107; margin-bottom: 8px; }
    .review-text { color: #333; line-height: 1.5; }
    
    .login-alert { background: #e9ecef; padding: 1rem; text-align: center; border-radius: 5px; color: #555; }
    .login-alert a { color: #007bff; font-weight: bold; text-decoration: none; }
    .alert { padding: 10px; margin-bottom: 10px; border-radius: 5px; }
    .alert-success { background: #d4edda; color: #155724; }
    .alert-error { background: #f8d7da; color: #721c24; }
    .no-reviews { text-align: center; color: #777; font-style: italic; padding: 2rem; }
</style>