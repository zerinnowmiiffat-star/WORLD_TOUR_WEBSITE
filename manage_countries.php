<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $country = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM destination_country WHERE country_name = ?");
    $stmt->bind_param("s", $country);
    if ($stmt->execute()) {
        $success = "Country deleted successfully";
    } else {
        $error = "Error deleting country";
    }
    $stmt->close();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country_name = sanitize($_POST['country_name']);
    $best_season = sanitize($_POST['best_season']);
    $description = sanitize($_POST['description']);
    $place = sanitize($_POST['place']);
    $is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] === 'true';
    $old_name = isset($_POST['old_name']) ? $_POST['old_name'] : '';
    
    if ($is_edit && !empty($old_name)) {
        // Update existing country
        $stmt = $conn->prepare("UPDATE destination_country SET country_name=?, best_season=?, description=?, place=? WHERE country_name=?");
        $stmt->bind_param("sssss", $country_name, $best_season, $description, $place, $old_name);
    } else {
        // Insert new country
        $stmt = $conn->prepare("INSERT INTO destination_country (country_name, best_season, description, place) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $country_name, $best_season, $description, $place);
    }
    
    if ($stmt->execute()) {
        $success = $is_edit ? "Country updated successfully" : "Country added successfully";
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Get country for editing
$edit_country = null;
if (isset($_GET['edit'])) {
    $edit_name = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM destination_country WHERE country_name = ?");
    $stmt->bind_param("s", $edit_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_country = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all countries
$countries = $conn->query("SELECT * FROM destination_country ORDER BY country_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Countries - Admin</title>
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
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus {
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
            <h1 class="page-title">Manage Destination Countries</h1>
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
                    <?= $edit_country ? 'Edit Country' : 'Add New Country' ?>
                </h2>
                
                <form method="POST" action="">
                    <?php if ($edit_country): ?>
                        <input type="hidden" name="is_edit" value="true">
                        <input type="hidden" name="old_name" value="<?= htmlspecialchars($edit_country['country_name']) ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Country Name <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="country_name" 
                            class="form-input"
                            value="<?= $edit_country ? htmlspecialchars($edit_country['country_name']) : '' ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Best Season <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="best_season" 
                            class="form-input"
                            placeholder="e.g., Spring-Summer"
                            value="<?= $edit_country ? htmlspecialchars($edit_country['best_season']) : '' ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Main City/Place <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="place" 
                            class="form-input"
                            placeholder="e.g., Paris"
                            value="<?= $edit_country ? htmlspecialchars($edit_country['place']) : '' ?>"
                            required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Description <span class="required">*</span>
                        </label>
                        <textarea 
                            name="description" 
                            class="form-textarea"
                            required><?= $edit_country ? htmlspecialchars($edit_country['description']) : '' ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">
                        <?= $edit_country ? 'Update Country' : 'Add Country' ?>
                    </button>
                    
                    <?php if ($edit_country): ?>
                        <a href="manage_countries.php" class="btn" style="background:#6c757d;margin-left:0.5rem;">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Countries List -->
            <div class="card">
                <h2 class="card-title">All Countries</h2>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Country Name</th>
                            <th>Best Season</th>
                            <th>Main Place</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($countries->num_rows > 0): ?>
                            <?php while ($country = $countries->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($country['country_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($country['best_season']) ?></td>
                                    <td><?= htmlspecialchars($country['place']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?edit=<?= urlencode($country['country_name']) ?>" 
                                            class="btn btn-small">Edit</a>
                                            <a href="?delete=<?= urlencode($country['country_name']) ?>" 
                                            class="btn btn-small btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this country?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center;color:#666;padding:2rem;">
                                    No countries found. Add your first country!
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