<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $trip_id = intval($_GET['delete']);
    
    // Verify ownership
    $check_stmt = $conn->prepare("SELECT user_id FROM trip_plan WHERE place_id = ?");
    $check_stmt->bind_param("i", $trip_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $trip = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($trip && $trip['user_id'] == $_SESSION['user_id']) {
        // Delete trip plan places first (foreign key)
        $conn->query("DELETE FROM trip_plan_places WHERE trip_id = {$trip_id}");
        
        // Delete trip plan
        $stmt = $conn->prepare("DELETE FROM trip_plan WHERE place_id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $success = "Trip deleted successfully";
        } else {
            $error = "Error deleting trip";
        }
        $stmt->close();
    } else {
        $error = "You don't have permission to delete this trip";
    }
}

// Fetch user's trips with destination count
$trips_query = "SELECT tp.*, 
                (SELECT COUNT(*) FROM trip_plan_places WHERE trip_id = tp.place_id) as dest_count 
                FROM trip_plan tp 
                WHERE tp.user_id = " . $_SESSION['user_id'] . " 
                ORDER BY tp.created_at DESC";
$trips = $conn->query($trips_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Trips - World Tour</title>
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
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
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
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
        
        .trips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .trip-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .trip-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }
        
        .trip-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .trip-meta {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .trip-content {
            padding: 1.5rem;
        }
        
        .trip-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 1rem;
            color: #333;
            font-weight: 600;
        }
        
        .trip-schedule {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            color: #666;
            white-space: pre-wrap;
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }
        
        .trip-schedule.collapsed::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(transparent, #f8f9fa);
        }
        
        .trip-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .destinations-list {
            margin-bottom: 1rem;
        }
        
        .destination-tag {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 0.85rem;
            margin: 0.3rem;
            color: #495057;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        
        .empty-state h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .trips-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                <li><a href="create_post.php">Share Story</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">✈️ My Trip Plans</h1>
                <p style="color:#666;margin-top:0.5rem;">Plan, organize, and manage your dream trips</p>
            </div>
            <a href="trip_planner.php" class="btn">
                <span>+</span> Create New Trip
            </a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($trips->num_rows > 0): ?>
            <div class="trips-grid">
                <?php while ($trip = $trips->fetch_assoc()): ?>
                    <?php
                    // Fetch destinations for this trip
                    $destinations_query = "SELECT country_name FROM trip_plan_places WHERE trip_id = " . $trip['place_id'];
                    $destinations = $conn->query($destinations_query);
                    ?>
                    
                    <div class="trip-card">
                        <div class="trip-header">
                            <h3 class="trip-title"><?= htmlspecialchars($trip['title']) ?></h3>
                            <div class="trip-meta">
                                📅 Created on <?= formatDate($trip['created_at']) ?>
                            </div>
                        </div>
                        
                        <div class="trip-content">
                            <div class="trip-info">
                                <div class="info-item">
                                    <span class="info-label">💰 Budget</span>
                                    <span class="info-value">
                                        <?= $trip['budget'] ? '$' . number_format($trip['budget'], 2) : 'Not set' ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📍 Destinations</span>
                                    <span class="info-value"><?= $trip['dest_count'] ?> countries</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($trip['schedule'])): ?>
                                <div class="trip-schedule collapsed">
                                    <strong>📅 Schedule:</strong><br>
                                    <?= htmlspecialchars($trip['schedule']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($destinations->num_rows > 0): ?>
                                <div class="destinations-list">
                                    <strong style="color:#666;font-size:0.9rem;">Destinations:</strong><br>
                                    <?php while ($dest = $destinations->fetch_assoc()): ?>
                                        <span class="destination-tag">
                                            🌍 <?= htmlspecialchars($dest['country_name']) ?>
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="trip-actions">
                                <a href="trip_detail.php?id=<?= $trip['place_id'] ?>" class="btn btn-small">
                                    View Details
                                </a>
                                <a href="?delete=<?= $trip['place_id'] ?>" 
                                class="btn btn-small btn-danger"
                                onclick="return confirm('Are you sure you want to delete this trip? This action cannot be undone.')">
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🗺️</div>
                <h2>No trips yet</h2>
                <p>Start planning your dream adventure today!</p>
                <a href="trip_planner.php" class="btn" style="font-size:1.1rem;padding:1rem 2rem;">
                    <span>+</span> Create Your First Trip
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>