<?php
require_once 'config.php';
$conn = getDBConnection();

// Fetch featured destinations (limit 6)
$destinations_query = "SELECT * FROM destination_country ORDER BY country_name LIMIT 6";
$destinations = $conn->query($destinations_query);

// Fetch recent posts (limit 6)
$posts_query = "SELECT p.*, u.name as user_name 
                FROM post p 
                JOIN user u ON p.user_id = u.id 
                ORDER BY p.date DESC, p.time DESC LIMIT 6";
$posts = $conn->query($posts_query);

// Get statistics for the hero section
$total_destinations = $conn->query("SELECT COUNT(*) as count FROM destination_country")->fetch_assoc()['count'];
$total_places = $conn->query("SELECT COUNT(*) as count FROM tourist_place")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM user")->fetch_assoc()['count'];
$total_trips = $conn->query("SELECT COUNT(*) as count FROM trip_plan")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>World Tour - Explore the World, Plan Your Journey, Share Your Story</title>
    <meta name="description" content="Discover amazing destinations, plan your trips, and share your travel experiences with World Tour.">
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
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,101.3C1248,85,1344,75,1392,69.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .hero p {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .section-subtitle {
            font-size: 1.1rem;
            color: #666;
        }
        
        .view-all-link {
            text-align: center;
            margin-top: 2rem;
        }
        
        .view-all-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-all-link a:hover {
            text-decoration: underline;
        }
        
        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        /* Card */
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
            font-size: 2rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .card-content {
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .card-subtitle {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .card-text {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .card-meta {
            color: #999;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .card-link {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .card-link:hover {
            text-decoration: underline;
        }
        
        /* Features Section */
        .features {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 4rem 2rem;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .feature-title {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .feature-text {
            color: #666;
        }
        
        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .footer-links a:hover {
            opacity: 0.8;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .hero-stats {
                gap: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">
                <span>🌍</span> World Tour
            </a>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>🌍 Explore the World</h1>
            <p>Discover amazing destinations, plan your trips, and share your journey</p>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn">Get Started Free</a>
            <?php else: ?>
                <a href="trip_planner.php" class="btn">Plan Your Next Trip</a>
            <?php endif; ?>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $total_destinations ?>+</span>
                    <span class="stat-label">Countries</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_places ?>+</span>
                    <span class="stat-label">Places</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_users ?>+</span>
                    <span class="stat-label">Travelers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $total_trips ?>+</span>
                    <span class="stat-label">Trips Planned</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🗺️</div>
                <h3 class="feature-title">Discover Destinations</h3>
                <p class="feature-text">Explore hundreds of amazing destinations around the world with detailed information</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✈️</div>
                <h3 class="feature-title">Plan Your Trip</h3>
                <p class="feature-text">Create detailed itineraries with budget tracking and schedule planning</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📸</div>
                <h3 class="feature-title">Share Your Story</h3>
                <p class="feature-text">Post your travel experiences and inspire other travelers worldwide</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏨</div>
                <h3 class="feature-title">Find Hotels</h3>
                <p class="feature-text">Browse hotels near tourist attractions with ratings and reviews</p>
            </div>
        </div>
    </section>

    <!-- Featured Destinations -->
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">🌏 Featured Destinations</h2>
            <p class="section-subtitle">Popular places travelers love to visit</p>
        </div>
        
        <div class="grid">
            <?php while ($dest = $destinations->fetch_assoc()): ?>
                <div class="card">
                    <div class="card-image">
                        <?= htmlspecialchars($dest['place']) ?>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?= htmlspecialchars($dest['country_name']) ?></h3>
                        <div class="card-subtitle">📍 <?= htmlspecialchars($dest['place']) ?></div>
                        <p class="card-text">
                            <?= htmlspecialchars(substr($dest['description'], 0, 100)) ?>...
                        </p>
                        <div class="card-meta">
                            🌤️ Best Season: <strong><?= htmlspecialchars($dest['best_season']) ?></strong>
                        </div>
                        <a href="destination_detail.php?country=<?= urlencode($dest['country_name']) ?>" class="card-link">
                            Explore Destination <span>→</span>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="view-all-link">
            <a href="destinations.php">
                View All Destinations <span>→</span>
            </a>
        </div>
    </div>

    <!-- Recent Travel Stories -->
    <div class="container" style="background:#f9f9f9;max-width:100%;padding-top:4rem;padding-bottom:4rem;">
        <div class="section-header">
            <h2 class="section-title">📖 Recent Travel Stories</h2>
            <p class="section-subtitle">Real experiences from our community</p>
        </div>
        
        <div style="max-width:1200px;margin:0 auto;padding:0 2rem;">
            <div class="grid">
                <?php while ($post = $posts->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-image">
                            <?php if ($post['image']): ?>
                                <img src="uploads/<?= htmlspecialchars($post['image']) ?>" 
                                     alt="<?= htmlspecialchars($post['location']) ?>">
                            <?php else: ?>
                                📷
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <div class="card-meta">
                                By <strong><?= htmlspecialchars($post['user_name']) ?></strong> • 
                                <?= formatDate($post['date']) ?>
                            </div>
                            <h3 class="card-title"><?= htmlspecialchars($post['location']) ?></h3>
                            <p class="card-text">
                                <?= htmlspecialchars(substr($post['description'], 0, 120)) ?>...
                            </p>
                            <a href="post_detail.php?id=<?= $post['post_id'] ?>" class="card-link">
                                Read Story <span>→</span>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div class="view-all-link">
                <a href="posts.php">
                    View All Stories <span>→</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <?php if (!isLoggedIn()): ?>
    <section class="hero" style="padding:4rem 2rem;">
        <div class="hero-content">
            <h2 style="font-size:2.5rem;margin-bottom:1rem;">Ready to Start Your Journey?</h2>
            <p style="font-size:1.2rem;margin-bottom:2rem;">Join thousands of travelers planning their dream trips</p>
            <a href="register.php" class="btn">Create Free Account</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="index.php">Home</a>
                <a href="destinations.php">Destinations</a>
                <a href="posts.php">Travel Stories</a>
                <a href="about.php">About Us</a>
                <a href="contact.php">Contact</a>
                <?php if (isAdmin()): ?>
                    <a href="admin/">Admin Panel</a>
                <?php endif; ?>
            </div>
            <p>&copy; <?= date('Y') ?> World Tour. Explore, Plan, Share Your Journey.</p>
            <p style="margin-top:0.5rem;opacity:0.8;font-size:0.9rem;">Made with ❤️ for travelers around the world</p>
        </div>
    </footer>
</body>
</html>
<?php $conn->close(); ?>