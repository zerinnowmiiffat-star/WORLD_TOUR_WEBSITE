<?php
require_once 'config.php';

// --- Helper Functions ---
if (!function_exists('getDBConnection')) {
    die("Database connection function getDBConnection() not defined in config.php");
}
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }
}
// ------------------------

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$country_filter = isset($_GET['country']) ? sanitize($_GET['country']) : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';

// Build Query
$where_conditions = "WHERE 1=1";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions .= " AND (h.hotel_name LIKE '%{$search_escaped}%' OR h.place_name LIKE '%{$search_escaped}%')";
}
if (!empty($country_filter)) {
    $country_escaped = $conn->real_escape_string($country_filter);
    $where_conditions .= " AND tp.country_name = '{$country_escaped}'";
}
if (!empty($rating_filter)) {
    $rating_min = floatval($rating_filter);
    $where_conditions .= " AND h.rating >= {$rating_min}";
}

// Counts and Data
$count_query = "SELECT COUNT(*) as total FROM hotel h LEFT JOIN tourist_place tp ON h.place_name = tp.place_name {$where_conditions}";
$total_result = $conn->query($count_query);
$total_hotels = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_hotels / $per_page);

$hotels_query = "SELECT h.*, tp.country_name,
                 (SELECT AVG(rating) FROM review WHERE hotel_code = h.hotel_code) as avg_review,
                 (SELECT COUNT(*) FROM review WHERE hotel_code = h.hotel_code) as review_count
                 FROM hotel h 
                 LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                 {$where_conditions}
                 ORDER BY h.hotel_name
                 LIMIT {$per_page} OFFSET {$offset}";
$hotels = $conn->query($hotels_query);

$countries = $conn->query("SELECT DISTINCT country_name FROM destination_country ORDER BY country_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Hotels - World Tour</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; }
        .navbar-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { color: white; text-decoration: none; }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 2rem; text-align: center; }
        
        .hotels-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem; margin-top: 2rem; }
        .hotel-card { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; transition: transform 0.3s; }
        .hotel-card:hover { transform: translateY(-5px); }
        .hotel-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; text-align: center; font-size: 3rem; }
        .hotel-content { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .hotel-name { font-size: 1.3rem; margin-bottom: 0.5rem; color: #333; }
        .hotel-location { color: #666; margin-bottom: 1rem; }
        
        .btn-view { display: block; text-align: center; padding: 0.8rem; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: auto; }
        .btn-view:hover { background: #5568d3; }
        
        .filter-section { background: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .filter-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; }
        .form-input, .form-select, .btn { padding: 0.8rem; border-radius: 8px; border: 1px solid #ddd; width: 100%; }
        .btn { background: #667eea; color: white; cursor: pointer; border: none; }
        
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; }
        .pagination a { padding: 0.5rem 1rem; border: 1px solid #667eea; color: #667eea; text-decoration: none; border-radius: 4px; }
        .pagination .active { background: #667eea; color: white; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" style="color:white; font-size:1.5rem; font-weight:bold; text-decoration:none;">🌍 World Tour</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="destinations.php">Destinations</a></li>
                <li><a href="hotels.php">Hotels</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="page-header">
        <h1>🏨 Browse Hotels</h1>
        <p>Find your perfect stay</p>
    </div>

    <div class="container">
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <input type="text" name="search" class="form-input" placeholder="Search hotels..." value="<?= htmlspecialchars($search) ?>">
                <select name="country" class="form-select">
                    <option value="">All Countries</option>
                    <?php if ($countries) while ($c = $countries->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($c['country_name']) ?>" <?= $country_filter == $c['country_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['country_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select name="rating" class="form-select">
                    <option value="">Any Rating</option>
                    <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>4+ Stars</option>
                </select>
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>

        <?php if ($hotels && $hotels->num_rows > 0): ?>
            <div class="hotels-grid">
                <?php while ($hotel = $hotels->fetch_assoc()): ?>
                    <div class="hotel-card">
                        <div class="hotel-icon">🏨</div>
                        <div class="hotel-content">
                            <h3 class="hotel-name"><?= htmlspecialchars($hotel['hotel_name']) ?></h3>
                            <div class="hotel-location">📍 <?= htmlspecialchars($hotel['place_name']) ?></div>
                            <div style="margin-bottom:1rem; color:#ffc107;">
                                <?= $hotel['avg_review'] ? str_repeat('⭐', round($hotel['avg_review'])) . ' (' . $hotel['review_count'] . ' reviews)' : 'No reviews yet' ?>
                            </div>
                            
                            <!-- LINKING TO HOTEL_DETAIL.PHP which includes REVIEWS.PHP -->
                            <a href="hotel_detail.php?id=<?= $hotel['hotel_code'] ?>" class="btn-view">View Details & Reviews</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination logic same as before -->
             <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <p style="text-align:center; padding:2rem;">No hotels found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>