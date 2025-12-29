<?php
require_once 'config.php';

// Ensure connection
if (!function_exists('getDBConnection')) { die("DB Error"); }
$conn = getDBConnection();

// Get ID
$hotel_code = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hotel_code <= 0) {
    header("Location: hotels.php");
    exit();
}

// Fetch Hotel Info
$stmt = $conn->prepare("SELECT h.*, tp.country_name 
                        FROM hotel h 
                        LEFT JOIN tourist_place tp ON h.place_name = tp.place_name 
                        WHERE h.hotel_code = ?");
$stmt->bind_param("i", $hotel_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: hotels.php");
    exit();
}

$hotel = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel['hotel_name']) ?> - Details</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; margin:0; padding:0; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; }
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .back-btn { display: inline-block; margin-bottom: 1rem; color: #667eea; text-decoration: none; font-weight: 500; }
        
        .detail-card { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; }
        .detail-header { background: #333; color: white; padding: 2rem; position: relative; }
        .detail-title { margin: 0; font-size: 2.5rem; }
        .detail-meta { margin-top: 10px; opacity: 0.8; font-size: 1.1rem; }
        
        .detail-body { padding: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .info-row { border-bottom: 1px solid #eee; padding: 10px 0; display: flex; justify-content: space-between; }
        .label { font-weight: bold; color: #555; }
        
        .discount-box { background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-top: 1rem; text-align: center; border: 1px solid #c3e6cb; }

        @media (max-width: 768px) { .detail-body { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="navbar">
        <div style="max-width:1000px; margin:0 auto;">
            <a href="index.php" style="color:white; text-decoration:none; font-weight:bold; font-size:1.2rem;">🌍 World Tour</a>
        </div>
    </div>

    <div class="container">
        <a href="hotels.php" class="back-btn">← Back to Hotels List</a>
        
        <div class="detail-card">
            <div class="detail-header">
                <h1 class="detail-title"><?= htmlspecialchars($hotel['hotel_name']) ?></h1>
                <div class="detail-meta">
                    📍 <?= htmlspecialchars($hotel['place_name']) ?> 
                    <?= $hotel['country_name'] ? ' | 🌍 ' . htmlspecialchars($hotel['country_name']) : '' ?>
                </div>
            </div>
            
            <div class="detail-body">
                <div>
                    <h3>Hotel Information</h3>
                    <div class="info-row">
                        <span class="label">Price Range</span>
                        <span><?= htmlspecialchars($hotel['price_range']) ?></span>
                    </div>
                    <?php if ($hotel['address']): ?>
                    <div class="info-row">
                        <span class="label">Address</span>
                        <span style="text-align:right; max-width:60%;"><?= htmlspecialchars($hotel['address']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="label">Official Rating</span>
                        <span><?= number_format($hotel['rating'], 1) ?> / 5.0</span>
                    </div>
                </div>
                
                <div>
                    <?php if ($hotel['discount_details']): ?>
                        <div class="discount-box">
                            <strong>🎉 Special Offer:</strong><br>
                            <?= htmlspecialchars($hotel['discount_details']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top:2rem; text-align:center; color:#777;">
                        <p>Book this hotel directly by contacting our travel agents.</p>
                        <button style="background:#667eea; color:white; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">Contact Agent</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CONNECTING TO REVIEWS.PHP -->
        <!-- This includes the review logic directly into this page -->
        <?php include 'reviews.php'; ?>
        
    </div>
</body>
</html>
<?php $conn->close(); ?>