<?php
require_once 'config.php';
$conn = getDBConnection();

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$season = isset($_GET['season']) ? sanitize($_GET['season']) : '';

// Build query
$query = "SELECT * FROM destination_country WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (country_name LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR description LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR place LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if (!empty($season)) {
    $query .= " AND best_season LIKE '%" . $conn->real_escape_string($season) . "%'";
}

$query .= " ORDER BY country_name";
$destinations = $conn->query($query);

// Get unique seasons for filter
$seasons = $conn->query("SELECT DISTINCT best_season FROM destination_country ORDER BY best_season");

// Get total count
$total_destinations = $conn->query("SELECT COUNT(*) as count FROM destination_country")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Destinations - World Tour</title>
    <meta name="description" content="Browse amazing travel destinations around the world. Find the perfect place for your next adventure.">
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
            font-weight: 500;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.3rem;
            opacity: 0.95;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }
        
        .filter-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .filter-title {
            font-size: 1.3rem;
            color: #333;
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
            transition: border-color 0.3s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
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
        
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .results-text {
            color: #666;
            font-size: 1.1rem;
        }
        
        .clear-filters {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .clear-filters:hover {
            text-decoration: underline;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .card-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            position: relative;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .card-subtitle {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .card-text {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .card-meta {
            color: #999;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .season-badge {
            padding: 0.4rem 1rem;
            background: #e9ecef;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #495057;
            font-weight: 500;
        }
        
        .card-link {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: gap 0.3s;
        }
        
        .card-link:hover {
            gap: 0.8rem;
        }
        
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .no-results-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        
        .no-results h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .no-results p {
            color: #666;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                display: none;
            }
            
            .results-info {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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

    <section class="hero">
        <h1>🌏 Explore Destinations</h1>
        <p>Discover amazing places around the world for your next adventure</p>
    </section>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h2 class="filter-title">🔍 Find Your Perfect Destination</h2>
                <span style="color:#667eea;font-weight:600;"><?= $total_destinations ?> destinations available</span>
            </div>
            
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">🔍 Search Destinations</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input"
                        placeholder="Search by country, city, or description..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">🌤️ Best Season</label>
                    <select name="season" class="form-select">
                        <option value="">All Seasons</option>
                        <?php while ($s = $seasons->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($s['best_season']) ?>" 
                                <?= $season === $s['best_season'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['best_season']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Search</button>
            </form>
        </div>
        
        <!-- Results Info -->
        <?php if ($search || $season): ?>
            <div class="results-info">
                <div class="results-text">
                    Found <strong><?= $destinations->num_rows ?></strong> destination<?= $destinations->num_rows != 1 ? 's' : '' ?>
                </div>
                <a href="destinations.php" class="clear-filters">✕ Clear Filters</a>
            </div>
        <?php endif; ?>
        
        <!-- Destinations Grid -->
        <?php if ($destinations->num_rows > 0): ?>
            <div class="grid">
                <?php while ($dest = $destinations->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-image">
                            <?= htmlspecialchars($dest['place']) ?>
                        </div>
                        <div class="card-content">
                            <h3 class="card-title"><?= htmlspecialchars($dest['country_name']) ?></h3>
                            <div class="card-subtitle">
                                📍 <?= htmlspecialchars($dest['place']) ?>
                            </div>
                            <p class="card-text">
                                <?= htmlspecialchars($dest['description']) ?>
                            </p>
                            <div class="card-meta">
                                <strong>🌤️ Best Time to Visit:</strong> <?= htmlspecialchars($dest['best_season']) ?>
                            </div>
                            <div class="card-footer">
                                <span class="season-badge">
                                    <?= htmlspecialchars($dest['best_season']) ?>
                                </span>
                                <a href="destination_detail.php?country=<?= urlencode($dest['country_name']) ?>" class="card-link">
                                    Explore <span>→</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h2>No destinations found</h2>
                <p>
                    <?php if ($search || $season): ?>
                        Try adjusting your search filters or <a href="destinations.php" style="color:#667eea;font-weight:600;">browse all destinations</a>
                    <?php else: ?>
                        No destinations have been added yet. Check back soon!
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>