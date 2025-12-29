<?php
require_once 'config.php';

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$country_filter = isset($_GET['country']) ? sanitize($_GET['country']) : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';

// Build query
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

// Count total hotels
$count_query = "SELECT COUNT(*) as total 
                FROM hotel h 
                LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                {$where_conditions}";
$total_hotels = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_hotels / $per_page);

// Fetch hotels with review count and average rating
$hotels_query = "SELECT h.*, tp.country_name,
                 (SELECT AVG(rating) FROM review WHERE hotel_code = h.hotel_code) as avg_review,
                 (SELECT COUNT(*) FROM review WHERE hotel_code = h.hotel_code) as review_count
                 FROM hotel h 
                 LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                 {$where_conditions}
                 ORDER BY h.hotel_name
                 LIMIT {$per_page} OFFSET {$offset}";
$hotels = $conn->query($hotels_query);

// Get all countries for filter
$countries = $conn->query("SELECT DISTINCT country_name FROM destination_country ORDER BY country_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Hotels - World Tour</title>
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
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
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
            grid-template-columns: 2fr 1fr 1fr auto;
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
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
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
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .results-info {
            margin-bottom: 1.5rem;
            color: #666;
        }
        
        .hotels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .hotel-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .hotel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .hotel-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            font-size: 3rem;
        }
        
        .hotel-content {
            padding: 1.5rem;
        }
        
        .hotel-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .hotel-location {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        
        .hotel-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stars {
            color: #ffc107;
        }
        
        .hotel-info {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
        }
        
        .discount-badge {
            background: #28a745;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .hotel-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-view {
            flex: 1;
            text-align: center;
            padding: 0.7rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .btn-view:hover {
            background: #5568d3;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.6rem 1rem;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            border: 2px solid #667eea;
            transition: all 0.3s;
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
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .hotels-grid {
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
                <li><a href="hotels.php">Hotels</a></li>
                <li><a href="posts.php">Travel Stories</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="my_trips.php">My Trips</a></li>
                    <li><a href="gifts.php">Gifts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="page-header">
        <h1>🏨 Browse Hotels</h1>
        <p>Find the perfect accommodation for your journey</p>
    </div>

    <div class="container">
        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">🔍 Search Hotels</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input"
                        placeholder="Search by hotel name or place..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">All Countries</option>
                        <?php while ($country = $countries->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($country['country_name']) ?>"
                                <?= $country_filter == $country['country_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country['country_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Min Rating</label>
                    <select name="rating" class="form-select">
                        <option value="">Any Rating</option>
                        <option value="4.5" <?= $rating_filter == '4.5' ? 'selected' : '' ?>>4.5+ ⭐</option>
                        <option value="4.0" <?= $rating_filter == '4.0' ? 'selected' : '' ?>>4.0+ ⭐</option>
                        <option value="3.5" <?= $rating_filter == '3.5' ? 'selected' : '' ?>>3.5+ ⭐</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Search</button>
            </form>
        </div>
        
        <div class="results-info">
            Showing <strong><?= $hotels->num_rows ?></strong> of <strong><?= $total_hotels ?></strong> hotels
        </div>
        
        <!-- Hotels Grid -->
        <?php if ($hotels->num_rows > 0): ?>
            <div class="hotels-grid">
                <?php while ($hotel = $hotels->fetch_assoc()): ?>
                    <div class="hotel-card">
                        <div class="hotel-icon">🏨</div>
                        
                        <div class="hotel-content">
                            <h3 class="hotel-name"><?= htmlspecialchars($hotel['hotel_name']) ?></h3>
                            
                            <div class="hotel-location">
                                📍 <?= htmlspecialchars($hotel['place_name']) ?>
                                <?php if ($hotel['country_name']): ?>
                                    , <?= htmlspecialchars($hotel['country_name']) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($hotel['avg_review']): ?>
                                <div class="hotel-rating">
                                    <span class="stars">
                                        <?= str_repeat('⭐', round($hotel['avg_review'])) ?>
                                    </span>
                                    <span><?= number_format($hotel['avg_review'], 1) ?></span>
                                    <span style="color:#999;">(<?= $hotel['review_count'] ?> reviews)</span>
                                </div>
                            <?php elseif ($hotel['rating']): ?>
                                <div class="hotel-rating">
                                    <span class="stars">
                                        <?= str_repeat('⭐', round($hotel['rating'])) ?>
                                    </span>
                                    <span><?= number_format($hotel['rating'], 1) ?></span>
                                    <span style="color:#999;">(Hotel rating)</span>
                                </div>
                            <?php else: ?>
                                <div style="color:#999;font-size:0.9rem;margin-bottom:0.5rem;">
                                    No reviews yet
                                </div>
                            <?php endif; ?>
                            
                            <div class="hotel-info">
                                <?php if ($hotel['price_range']): ?>
                                    <div class="info-item">
                                        <span>💰 Price Range:</span>
                                        <strong><?= htmlspecialchars($hotel['price_range']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($hotel['discount_details']): ?>
                                <span class="discount-badge">
                                    🎉 Special Offer
                                </span>
                            <?php endif; ?>
                            
                            <div class="hotel-actions">
                                <a href="hotel_detail.php?id=<?= $hotel['hotel_code'] ?>" class="btn-view">
                                    View Details & Reviews
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $country_filter ? '&country=' . urlencode($country_filter) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled">← Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $country_filter ? '&country=' . urlencode($country_filter) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $country_filter ? '&country=' . urlencode($country_filter) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <h2>🔍 No hotels found</h2>
                <p style="color:#666;margin:1rem 0;">
                    Try adjusting your search criteria or <a href="hotels.php" style="color:#667eea;">browse all hotels</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>