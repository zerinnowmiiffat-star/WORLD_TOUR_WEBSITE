<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Fetch all countries for selection
$countries_query = "SELECT country_name FROM destination_country ORDER BY country_name";
$countries = $conn->query($countries_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $schedule = sanitize($_POST['schedule']);
    $budget = floatval($_POST['budget']);
    $selected_countries = $_POST['countries'] ?? [];
    
    if (empty($title) || empty($selected_countries)) {
        $error = "Please fill in all required fields";
    } else {
        $conn->begin_transaction();
        
        try {
            // Insert trip plan
            $stmt = $conn->prepare("INSERT INTO trip_plan (user_id, title, schedule, budget) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issd", $_SESSION['user_id'], $title, $schedule, $budget);
            $stmt->execute();
            $trip_id = $conn->insert_id;
            
            // Insert selected countries
            $stmt2 = $conn->prepare("INSERT INTO trip_plan_places (trip_id, country_name) VALUES (?, ?)");
            foreach ($selected_countries as $country) {
                $stmt2->bind_param("is", $trip_id, $country);
                $stmt2->execute();
            }
            
            $conn->commit();
            $success = "Trip plan created successfully!";
            
            // Redirect to trip details
            header("Location: my_trips.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error creating trip plan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Your Trip - World Tour</title>
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
        }
        
        .navbar-content {
            max-width: 1200px;
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
        
        /* NEW: Added navigation links styling */
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
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 1.5rem;
            text-align: center;
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
            min-height: 100px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            cursor: pointer;
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .required {
            color: red;
        }
        
        /* NEW: Mobile responsive navigation */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- UPDATED NAVIGATION SECTION -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">🌍 World Tour</a>
            <!-- NEW: Added full navigation menu -->
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="destinations.php">Destinations</a></li>
                <li><a href="posts.php">Travel Stories</a></li>
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="create_post.php">Share Story</a></li>
                <li><a href="profile.php">Profile</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin/">Admin</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">Plan Your Dream Trip</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Trip Title <span class="required">*</span></label>
                <input type="text" name="title" class="form-input" 
                    placeholder="e.g., European Adventure 2024" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Schedule</label>
                <textarea name="schedule" class="form-textarea" 
                        placeholder="Describe your travel schedule..."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Budget ($)</label>
                <input type="number" name="budget" class="form-input" 
                    step="0.01" min="0" placeholder="5000">
            </div>
            
            <div class="form-group">
                <label class="form-label">Select Destinations <span class="required">*</span></label>
                <div class="checkbox-group">
                    <?php 
                    $countries->data_seek(0);
                    while ($country = $countries->fetch_assoc()): 
                    ?>
                        <div class="checkbox-item">
                            <input type="checkbox" 
                                name="countries[]" 
                                value="<?= htmlspecialchars($country['country_name']) ?>"
                                id="country_<?= htmlspecialchars($country['country_name']) ?>">
                            <label for="country_<?= htmlspecialchars($country['country_name']) ?>">
                                <?= htmlspecialchars($country['country_name']) ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">Create Trip Plan</button>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>