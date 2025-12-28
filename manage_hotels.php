<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $hotel_code = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM hotel WHERE hotel_code = ?");
    $stmt->bind_param("i", $hotel_code);
    if ($stmt->execute()) {
        $success = "Hotel deleted successfully";
    } else {
        $error = "Error deleting hotel";
    }
    $stmt->close();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotel_name = sanitize($_POST['hotel_name']);
    $place_name = sanitize($_POST['place_name']);
    $address = sanitize($_POST['address']);
    $rating = !empty($_POST['rating']) ? floatval($_POST['rating']) : null;
    $price_range = sanitize($_POST['price_range']);
    $discount_details = sanitize($_POST['discount_details']);
    $is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] === 'true';
    $hotel_code = isset($_POST['hotel_code']) ? intval($_POST['hotel_code']) : 0;
    
    if (empty($hotel_name) || empty($place_name)) {
        $error = "Please fill in all required fields";
    } elseif ($rating !== null && ($rating < 0 || $rating > 5)) {
        $error = "Rating must be between 0 and 5";
    } else {
        if ($is_edit && $hotel_code > 0) {
            // Update existing hotel
            $stmt = $conn->prepare("UPDATE hotel SET hotel_name=?, place_name=?, address=?, rating=?, price_range=?, discount_details=? WHERE hotel_code=?");
            $stmt->bind_param("sssdssi", $hotel_name, $place_name, $address, $rating, $price_range, $discount_details, $hotel_code);
        } else {
            // Insert new hotel
            $stmt = $conn->prepare("INSERT INTO hotel (hotel_name, place_name, address, rating, price_range, discount_details) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdss", $hotel_name, $place_name, $address, $rating, $price_range, $discount_details);
        }
        
        if ($stmt->execute()) {
            $success = $is_edit ? "Hotel updated successfully" : "Hotel added successfully";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get hotel for editing
$edit_hotel = null;
if (isset($_GET['edit'])) {
    $edit_code = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM hotel WHERE hotel_code = ?");
    $stmt->bind_param("i", $edit_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_hotel = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all tourist places for dropdown
$places = $conn->query("SELECT place_name, country_name FROM tourist_place ORDER BY place_name");

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : '';

// Build query
$where_conditions = "WHERE 1=1";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions .= " AND (h.hotel_name LIKE '%{$search_escaped}%' OR h.place_name LIKE '%{$search_escaped}%' OR h.address LIKE '%{$search_escaped}%')";
}
if (!empty($rating_filter)) {
    $rating_min = floatval($rating_filter);
    $where_conditions .= " AND h.rating >= {$rating_min}";
}

// Count total hotels
$count_query = "SELECT COUNT(*) as total FROM hotel h {$where_conditions}";
$total_hotels = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_hotels / $per_page);

// Fetch hotels
$hotels_query = "SELECT h.*, tp.country_name 
                 FROM hotel h 
                 LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                 {$where_conditions}
                 ORDER BY h.hotel_code DESC 
                 LIMIT {$per_page} OFFSET {$offset}";
$hotels = $conn->query($hotels_query);

// Get statistics
$stats = [];
$stats['total_hotels'] = $conn->query("SELECT COUNT(*) as count FROM hotel")->fetch_assoc()['count'];
$stats['avg_rating'] = $conn->query("SELECT AVG(rating) as avg FROM hotel WHERE rating IS NOT NULL")->fetch_assoc()['avg'];
$stats['high_rated'] = $conn->query("SELECT COUNT(*) as count FROM hotel WHERE rating >= 4.5")->fetch_assoc()['count'];
$stats['with_discounts'] = $conn->query("SELECT COUNT(*) as count FROM hotel WHERE discount_details IS NOT NULL AND discount_details != ''")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hotels - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
        }
        
        .btn {
            padding: 0.7rem 1.5rem;
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
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-small {
            padding: 0.4rem 1rem;
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
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #333;
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
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
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
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1rem;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1.5rem;
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
        
        @media (max-width: 968px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">🏨 Admin Panel</a>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="manage_countries.php">Countries</a></li>
                <li><a href="manage_places.php">Places</a></li>
                <li><a href="manage_hotels.php">Hotels</a></li>
                <li><a href="manage_users.php">Users</a></li>
                <li><a href="manage_posts.php">Posts</a></li>
                <li><a href="../index.php">View Site</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Manage Hotels</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_hotels'] ?></div>
                <div class="stat-label">Total Hotels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['avg_rating'], 1) ?>⭐</div>
                <div class="stat-label">Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['high_rated'] ?></div>
                <div class="stat-label">Highly Rated (4.5+)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['with_discounts'] ?></div>
                <div class="stat-label">With Discounts</div>
            </div>
        </div>
        
        <div class="grid">
            <!-- Add/Edit Form -->
            <div class="card">
                <h2 class="card-title">
                    <?= $edit_hotel ? 'Edit Hotel' : 'Add New Hotel' ?>
                </h2>
                
                <form method="POST" action="">
                    <?php if ($edit_hotel): ?>
                        <input type="hidden" name="is_edit" value="true">
                        <input type="hidden" name="hotel_code" value="<?= $edit_hotel['hotel_code'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Hotel Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="hotel_name" 
                            class="form-input"
                            value="<?= $edit_hotel ? htmlspecialchars($edit_hotel['hotel_name']) : '' ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Tourist Place <span class="required">*</span>
                        </label>
                        <select name="place_name" class="form-select" required>
                            <option value="">Select Place</option>
                            <?php 
                            $places->data_seek(0);
                            while ($place = $places->fetch_assoc()): 
                            ?>
                                <option value="<?= htmlspecialchars($place['place_name']) ?>"
                                    <?= ($edit_hotel && $edit_hotel['place_name'] === $place['place_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($place['place_name']) ?> (<?= htmlspecialchars($place['country_name']) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea 
                            name="address" 
                            class="form-textarea"><?= $edit_hotel ? htmlspecialchars($edit_hotel['address']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rating (0-5)</label>
                        <input 
                            type="number" 
                            name="rating" 
                            class="form-input"
                            step="0.1"
                            min="0"
                            max="5"
                            value="<?= $edit_hotel ? $edit_hotel['rating'] : '' ?>"
                            placeholder="e.g., 4.5">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Price Range</label>
                        <input 
                            type="text" 
                            name="price_range" 
                            class="form-input"
                            value="<?= $edit_hotel ? htmlspecialchars($edit_hotel['price_range']) : '' ?>"
                            placeholder="e.g., $100-$250">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Discount Details</label>
                        <textarea 
                            name="discount_details" 
                            class="form-textarea"
                            placeholder="Special offers, discounts..."><?= $edit_hotel ? htmlspecialchars($edit_hotel['discount_details']) : '' ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <?= $edit_hotel ? 'Update Hotel' : 'Add Hotel' ?>
                    </button>
                    
                    <?php if ($edit_hotel): ?>
                        <a href="manage_hotels.php" class="btn" style="background:#6c757d;margin-left:0.5rem;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Hotels List -->
            <div>
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="" class="filter-form">
                        <div class="form-group">
                            <label class="form-label">🔍 Search Hotels</label>
                            <input 
                                type="text" 
                                name="search" 
                                class="form-input"
                                placeholder="Search by name, place, or address..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Min Rating</label>
                            <select name="rating" class="form-select">
                                <option value="">All Ratings</option>
                                <option value="4.5" <?= $rating_filter == '4.5' ? 'selected' : '' ?>>4.5+ Stars</option>
                                <option value="4.0" <?= $rating_filter == '4.0' ? 'selected' : '' ?>>4.0+ Stars</option>
                                <option value="3.5" <?= $rating_filter == '3.5' ? 'selected' : '' ?>>3.5+ Stars</option>
                                <option value="3.0" <?= $rating_filter == '3.0' ? 'selected' : '' ?>>3.0+ Stars</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn">Search</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2 class="card-title">All Hotels</h2>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hotel Name</th>
                                <th>Place</th>
                                <th>Rating</th>
                                <th>Price Range</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($hotels->num_rows > 0): ?>
                                <?php while ($hotel = $hotels->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $hotel['hotel_code'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($hotel['hotel_name']) ?></strong>
                                            <?php if (!empty($hotel['discount_details'])): ?>
                                                <br><span class="badge badge-success">💰 Discount</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($hotel['place_name']) ?>
                                            <?php if ($hotel['country_name']): ?>
                                                <br><small style="color:#666;"><?= htmlspecialchars($hotel['country_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($hotel['rating']): ?>
                                                <span class="rating-stars">
                                                    <?= str_repeat('⭐', floor($hotel['rating'])) ?>
                                                </span>
                                                <br><?= number_format($hotel['rating'], 1) ?>
                                            <?php else: ?>
                                                <span style="color:#999;">No rating</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($hotel['price_range']) ?: '-' ?></td>
                                        <td>
                                            <div class="actions">
                                                <a href="?edit=<?= $hotel['hotel_code'] ?>" 
                                                   class="btn btn-small">Edit</a>
                                                <a href="?delete=<?= $hotel['hotel_code'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>" 
                                                   class="btn btn-small btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this hotel?')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;color:#666;padding:2rem;">
                                        No hotels found. <?= $search || $rating_filter ? 'Try adjusting your filters.' : 'Add your first hotel!' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                ← Previous
                            </a>
                        <?php else: ?>
                            <span class="disabled">← Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $rating_filter ? '&rating=' . $rating_filter : '' ?>">
                                Next →
                            </a>
                        <?php else: ?>
                            <span class="disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>