<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs (assuming sanitize() is defined in config.php)
    $location = isset($_POST['location']) ? sanitize($_POST['location']) : '';
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $image = '';
    
    // Validate inputs
    if (empty($location) || empty($description)) {
        $error = "Please fill in all required fields";
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            // Assuming uploadImage() is defined in config.php
            $upload = uploadImage($_FILES['image']);
            if ($upload['success']) {
                $image = $upload['filename'];
            } else {
                $error = $upload['message'];
            }
        }
        
        if (empty($error)) {
            // Insert post
            $query = "INSERT INTO post (user_id, location, description, image) VALUES (?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($query)) {
                $stmt->bind_param("isss", $_SESSION['user_id'], $location, $description, $image);
                
                if ($stmt->execute()) {
                    $success = "Post created successfully!";
                    // Redirect to posts page after 2 seconds
                    header("refresh:2;url=posts.php");
                } else {
                    $error = "Error creating post: " . $stmt->error;
                }
                $stmt->close();
            } else {
                // Handle preparation error (e.g., table doesn't exist)
                $error = "Database error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Your Journey - World Tour</title>
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .card {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #666;
            margin-bottom: 2rem;
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #e9ecef;
            color: #495057;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .file-input-label:hover {
            background: #dee2e6;
        }
        
        .file-name {
            display: inline-block;
            margin-left: 1rem;
            color: #667eea;
            font-weight: 500;
        }
        
        .image-preview {
            margin-top: 1rem;
            max-width: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: transform 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .char-counter {
            font-size: 0.85rem;
            color: #666;
            text-align: right;
            margin-top: 0.3rem;
        }
        
        @media (max-width: 768px) {
            .card {
                padding: 2rem 1.5rem;
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
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="create_post.php">Share Story</a></li>
                <li><a href="profile.php">Profile</a></li>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <li><a href="admin/">Admin</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <a href="posts.php" class="back-link">
            <span>←</span> Back to Travel Stories
        </a>
        
        <div class="card">
            <h1 class="page-title">📝 Share Your Journey</h1>
            <p class="page-subtitle">Tell the world about your travel experience</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?= htmlspecialchars($success) ?>
                    <br><small>Redirecting to posts...</small>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <div class="form-group">
                    <label class="form-label">
                        📍 Location <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="location" 
                        class="form-input" 
                        placeholder="e.g., Paris, France"
                        value="<?= isset($_POST['location']) ? htmlspecialchars($_POST['location']) : '' ?>"
                        required
                        maxlength="200">
                    <div class="form-help">Where did you travel?</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        ✍️ Your Story <span class="required">*</span>
                    </label>
                    <textarea 
                        name="description" 
                        id="description"
                        class="form-textarea" 
                        placeholder="Share your experience, tips, and memorable moments..."
                        required
                        maxlength="2000"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> / 2000 characters
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">📷 Photo</label>
                    <div class="file-input-wrapper">
                        <input 
                            type="file" 
                            name="image" 
                            id="imageInput"
                            accept="image/*"
                            onchange="previewImage(this)">
                        <label for="imageInput" class="file-input-label">
                            Choose Image
                        </label>
                        <span class="file-name" id="fileName">No file chosen</span>
                    </div>
                    <div class="form-help">Optional: Add a photo to your story (max 5MB)</div>
                    <img id="imagePreview" class="image-preview" style="display:none;" alt="Preview">
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    📤 Publish Story
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Character counter
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            if (description && charCount) {
                charCount.textContent = description.value.length;
            }
        }
        
        if (description) {
            description.addEventListener('input', updateCharCount);
            updateCharCount();
        }
        
        // Image preview
        function previewImage(input) {
            const fileName = document.getElementById('fileName');
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                fileName.textContent = input.files[0].name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                fileName.textContent = 'No file chosen';
                preview.style.display = 'none';
            }
        }
        
        // Form validation
        const form = document.getElementById('postForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = '📤 Publishing...';
                }
            });
        }
    </script>
</body>
</html>
<?php 
if (isset($conn) && $conn) {
    $conn->close(); 
}
?>