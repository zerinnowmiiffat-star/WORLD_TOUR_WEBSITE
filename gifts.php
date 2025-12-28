<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle gift actions (accept, decline, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gift_id = intval($_POST['gift_id']);
    $action = $_POST['action'];
    
    // Verify the user is the receiver
    $check_stmt = $conn->prepare("SELECT receiver_id FROM gift WHERE gift_id_no = ?");
    $check_stmt->bind_param("i", $gift_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $gift = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($gift && $gift['receiver_id'] == $_SESSION['user_id']) {
        if ($action === 'accept') {
            $stmt = $conn->prepare("UPDATE gift SET status = 'accepted' WHERE gift_id_no = ?");
            $stmt->bind_param("i", $gift_id);
            if ($stmt->execute()) {
                $success = "Gift accepted!";
            }
            $stmt->close();
        } elseif ($action === 'decline') {
            $stmt = $conn->prepare("UPDATE gift SET status = 'declined' WHERE gift_id_no = ?");
            $stmt->bind_param("i", $gift_id);
            if ($stmt->execute()) {
                $success = "Gift declined";
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM gift WHERE gift_id_no = ?");
            $stmt->bind_param("i", $gift_id);
            if ($stmt->execute()) {
                $success = "Gift deleted";
            }
            $stmt->close();
        }
    }
}

// Fetch received gifts
$received_query = "SELECT g.*, u.name as sender_name, p.location as post_location, tp.title as trip_title
                   FROM gift g
                   JOIN user u ON g.sender_id = u.id
                   LEFT JOIN post p ON g.post_id = p.post_id
                   LEFT JOIN trip_plan tp ON g.place_id = tp.place_id
                   WHERE g.receiver_id = " . $_SESSION['user_id'] . "
                   ORDER BY g.sent_date DESC";
$received_gifts = $conn->query($received_query);

// Fetch sent gifts
$sent_query = "SELECT g.*, u.name as receiver_name, p.location as post_location, tp.title as trip_title
               FROM gift g
               JOIN user u ON g.receiver_id = u.id
               LEFT JOIN post p ON g.post_id = p.post_id
               LEFT JOIN trip_plan tp ON g.place_id = tp.place_id
               WHERE g.sender_id = " . $_SESSION['user_id'] . "
               ORDER BY g.sent_date DESC";
$sent_gifts = $conn->query($sent_query);

// Get statistics
$stats = [];
$stats['received_total'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE receiver_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$stats['received_pending'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE receiver_id = " . $_SESSION['user_id'] . " AND status = 'pending'")->fetch_assoc()['count'];
$stats['received_accepted'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE receiver_id = " . $_SESSION['user_id'] . " AND status = 'accepted'")->fetch_assoc()['count'];
$stats['sent_total'] = $conn->query("SELECT COUNT(*) as count FROM gift WHERE sender_id = " . $_SESSION['user_id'])->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Gifts - World Tour</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
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
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
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
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #ddd;
        }
        
        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .gifts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .gift-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .gift-card:hover {
            transform: translateY(-5px);
        }
        
        .gift-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .gift-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .gift-from {
            text-align: center;
            font-size: 1.1rem;
        }
        
        .gift-content {
            padding: 1.5rem;
        }
        
        .gift-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .gift-item-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.3rem;
        }
        
        .gift-item-value {
            font-weight: 600;
            color: #333;
        }
        
        .gift-message {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 1rem;
            font-style: italic;
        }
        
        .gift-date {
            color: #999;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .gift-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }
        
        .gift-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
            flex: 1;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .gifts-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                overflow-x: auto;
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
                <li><a href="posts.php">Travel Stories</a></li>
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>🎁 My Gifts</h1>
            <p style="opacity:0.9;">Send and receive travel gifts with friends</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📥</div>
                <div class="stat-number"><?= $stats['received_total'] ?></div>
                <div class="stat-label">Gifts Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-number"><?= $stats['received_pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?= $stats['received_accepted'] ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div class="stat-number"><?= $stats['sent_total'] ?></div>
                <div class="stat-label">Gifts Sent</div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('received')">
                📥 Received Gifts (<?= $received_gifts->num_rows ?>)
            </button>
            <button class="tab" onclick="switchTab('sent')">
                📤 Sent Gifts (<?= $sent_gifts->num_rows ?>)
            </button>
        </div>
        
        <!-- Received Gifts Tab -->
        <div id="received-tab" class="tab-content active">
            <?php if ($received_gifts->num_rows > 0): ?>
                <div class="gifts-grid">
                    <?php while ($gift = $received_gifts->fetch_assoc()): ?>
                        <div class="gift-card">
                            <div class="gift-header">
                                <div class="gift-icon">🎁</div>
                                <div class="gift-from">From: <?= htmlspecialchars($gift['sender_name']) ?></div>
                            </div>
                            <div class="gift-content">
                                <div class="gift-date">
                                    Sent on <?= formatDate($gift['sent_date']) ?>
                                </div>
                                
                                <?php if ($gift['post_location']): ?>
                                    <div class="gift-item">
                                        <div class="gift-item-label">📍 Related to Post:</div>
                                        <div class="gift-item-value"><?= htmlspecialchars($gift['post_location']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($gift['trip_title']): ?>
                                    <div class="gift-item">
                                        <div class="gift-item-label">✈️ Related to Trip:</div>
                                        <div class="gift-item-value"><?= htmlspecialchars($gift['trip_title']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($gift['message']): ?>
                                    <div class="gift-message">
                                        💌 "<?= htmlspecialchars($gift['message']) ?>"
                                    </div>
                                <?php endif; ?>
                                
                                <div style="text-align:center;margin-bottom:1rem;">
                                    <span class="gift-status status-<?= $gift['status'] ?>">
                                        <?= ucfirst($gift['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if ($gift['status'] === 'pending'): ?>
                                    <div class="gift-actions">
                                        <form method="POST" style="flex:1;">
                                            <input type="hidden" name="gift_id" value="<?= $gift['gift_id_no'] ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-success">Accept</button>
                                        </form>
                                        <form method="POST" style="flex:1;">
                                            <input type="hidden" name="gift_id" value="<?= $gift['gift_id_no'] ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" class="btn btn-danger">Decline</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="gift_id" value="<?= $gift['gift_id_no'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-secondary" style="width:100%;">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h2>No gifts received yet</h2>
                    <p style="color:#666;">When someone sends you a gift, it will appear here</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sent Gifts Tab -->
        <div id="sent-tab" class="tab-content">
            <?php if ($sent_gifts->num_rows > 0): ?>
                <div class="gifts-grid">
                    <?php 
                    $sent_gifts->data_seek(0);
                    while ($gift = $sent_gifts->fetch_assoc()): 
                    ?>
                        <div class="gift-card">
                            <div class="gift-header">
                                <div class="gift-icon">🎁</div>
                                <div class="gift-from">To: <?= htmlspecialchars($gift['receiver_name']) ?></div>
                            </div>
                            <div class="gift-content">
                                <div class="gift-date">
                                    Sent on <?= formatDate($gift['sent_date']) ?>
                                </div>
                                
                                <?php if ($gift['post_location']): ?>
                                    <div class="gift-item">
                                        <div class="gift-item-label">📍 Related to Post:</div>
                                        <div class="gift-item-value"><?= htmlspecialchars($gift['post_location']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($gift['trip_title']): ?>
                                    <div class="gift-item">
                                        <div class="gift-item-label">✈️ Related to Trip:</div>
                                        <div class="gift-item-value"><?= htmlspecialchars($gift['trip_title']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($gift['message']): ?>
                                    <div class="gift-message">
                                        💌 "<?= htmlspecialchars($gift['message']) ?>"
                                    </div>
                                <?php endif; ?>
                                
                                <div style="text-align:center;">
                                    <span class="gift-status status-<?= $gift['status'] ?>">
                                        <?= ucfirst($gift['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📮</div>
                    <h2>No gifts sent yet</h2>
                    <p style="color:#666;">Share the joy by sending gifts to other travelers!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>