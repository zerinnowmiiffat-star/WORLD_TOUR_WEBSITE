<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $place_name = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tourist_place WHERE place_name = ?");
    $stmt->bind_param("s", $place_name);
    if ($stmt->execute()) {
        $success = "Place deleted successfully";
    } else {
        $error = "Error deleting place";
    }
    $stmt->close();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $place_name = sanitize($_POST['place_name']);
    $country_name = sanitize($_POST['country_name']);
    $type = sanitize($_POST['type']);
    $category = sanitize($_POST['category']);
    $image = '';
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload = uploadImage($_FILES['image']);
        if ($upload['success']) {
            $image = $upload['filename'];
        } else {
            $error = $upload['message'];
        }
    }
    
    $is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] === 'true';
    $old_name = isset($_POST['old_name']) ? $_POST['old_name'] : '';
    
    if (empty($error)) {
        if ($is_edit && !empty($old_name)) {
            // Update existing place
            if (!empty($image)) {
                $stmt = $conn->prepare("UPDATE tourist_place SET place_name=?, country_name=?, type=?, category=?, image=? WHERE place_name=?");
                $stmt->bind_param("ssssss", $place_name, $country_name, $type, $category, $image, $old_name);
            } else {
                $stmt = $conn->prepare("UPDATE tourist_place SET place_name=?, country_name=?, type=?, category=? WHERE place_name=?");
                $stmt->bind_param("sssss", $place_name, $country_name, $type, $category, $old_name);
            }
        } else {
            // Insert new place
            $stmt = $conn->prepare("INSERT INTO tourist_place (place_name, country_name, type, category, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $place_name, $country_name, $type, $category, $image);
        }
        
        if ($stmt->execute()) {
            $success = $is_edit ? "Place updated successfully" : "Place added successfully";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get place for editing
$edit_place = null;
if (isset($_GET['edit'])) {
    $edit_name = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM tourist_place WHERE place_name = ?");
    $stmt->bind_param("s", $edit_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_place = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all countries for dropdown
$countries = $conn->query("SELECT country_name FROM destination_country ORDER BY country_name");

// Fetch all places
$places = $conn->query("SELECT tp.*, dc.place as country_place 
                        FROM tourist_place tp 
                        LEFT JOIN destination_country dc ON tp.country_name = dc.country_name 
                        ORDER BY tp.place_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tourist Places - Admin</title>
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
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
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
        
        .image-preview {
            margin-top: 0.5rem;
            max-width: 200px;
            border-radius: 8px;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        @media (max-width: 968px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">🌍 Admin Panel</a>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="manage_countries.php">Countries</a></li>
                <li><a href="manage_places.php">Places</a></li>
                <li><a href="manage_hotels.php">Hotels</a></li>
                <li><a href="manage_users.php">Users</a></li>
                <li><a href="../index.php">View Site</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Manage Tourist Places</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Add/Edit Form -->
            <div class="card">
                <h2 class="card-title">
                    <?= $edit_place ? 'Edit Place' : 'Add New Place' ?>
                </h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php if ($edit_place): ?>
                        <input type="hidden" name="is_edit" value="true">
                        <input type="hidden" name="old_name" value="<?= htmlspecialchars($edit_place['place_name']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Place Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="place_name" 
                            class="form-input"
                            value="<?= $edit_place ? htmlspecialchars($edit_place['place_name']) : '' ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Country <span class="required">*</span>
                        </label>
                        <select name="country_name" class="form-select" required>
                            <option value="">Select Country</option>
                            <?php 
                            $countries->data_seek(0);
                            while ($country = $countries->fetch_assoc()): 
                            ?>
                                <option value="<?= htmlspecialchars($country['country_name']) ?>"
                                    <?= ($edit_place && $edit_place['country_name'] === $country['country_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($country['country_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Type <span class="required">*</span>
                        </label>
                        <select name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="Monument" <?= ($edit_place && $edit_place['type'] === 'Monument') ? 'selected' : '' ?>>Monument</option>
                            <option value="Museum" <?= ($edit_place && $edit_place['type'] === 'Museum') ? 'selected' : '' ?>>Museum</option>
                            <option value="Natural" <?= ($edit_place && $edit_place['type'] === 'Natural') ? 'selected' : '' ?>>Natural</option>
                            <option value="Beach" <?= ($edit_place && $edit_place['type'] === 'Beach') ? 'selected' : '' ?>>Beach</option>
                            <option value="Temple" <?= ($edit_place && $edit_place['type'] === 'Temple') ? 'selected' : '' ?>>Temple</option>
                            <option value="Park" <?= ($edit_place && $edit_place['type'] === 'Park') ? 'selected' : '' ?>>Park</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Category <span class="required">*</span>
                        </label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Historical" <?= ($edit_place && $edit_place['category'] === 'Historical') ? 'selected' : '' ?>>Historical</option>
                            <option value="Cultural" <?= ($edit_place && $edit_place['category'] === 'Cultural') ? 'selected' : '' ?>>Cultural</option>
                            <option value="Adventure" <?= ($edit_place && $edit_place['category'] === 'Adventure') ? 'selected' : '' ?>>Adventure</option>
                            <option value="Religious" <?= ($edit_place && $edit_place['category'] === 'Religious') ? 'selected' : '' ?>>Religious</option>
                            <option value="Nature" <?= ($edit_place && $edit_place['category'] === 'Nature') ? 'selected' : '' ?>>Nature</option>
                            <option value="Entertainment" <?= ($edit_place && $edit_place['category'] === 'Entertainment') ? 'selected' : '' ?>>Entertainment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                        <?php if ($edit_place && $edit_place['image']): ?>
                            <img src="../uploads/<?= htmlspecialchars($edit_place['image']) ?>" 
                                 alt="Current image" class="image-preview">
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn">
                        <?= $edit_place ? 'Update Place' : 'Add Place' ?>
                    </button>
                    
                    <?php if ($edit_place): ?>
                        <a href="manage_places.php" class="btn" style="background:#6c757d;margin-left:0.5rem;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Places List -->
            <div class="card">
                <h2 class="card-title">All Tourist Places</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Place Name</th>
                            <th>Country</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($places->num_rows > 0): ?>
                            <?php while ($place = $places->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($place['place_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($place['country_name']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($place['type']) ?></span></td>
                                    <td><?= htmlspecialchars($place['category']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?edit=<?= urlencode($place['place_name']) ?>" 
                                            class="btn btn-small">Edit</a>
                                            <a href="?delete=<?= urlencode($place['place_name']) ?>" 
                                            class="btn btn-small btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this place?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#666;padding:2rem;">
                                    No places found. Add your first place!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>