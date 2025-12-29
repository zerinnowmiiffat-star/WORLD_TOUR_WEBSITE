<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account";
    } else {
        // Check if user exists
        $check = $conn->query("SELECT name FROM user WHERE id = $user_id");
        if ($check->num_rows > 0) {
            $user_name = $check->fetch_assoc()['name'];
            
            $stmt = $conn->prepare("DELETE FROM user WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "User '$user_name' deleted successfully";
            } else {
                $error = "Error deleting user: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "User not found";
        }
    }
}

// Handle User Type Change
if (isset($_POST['change_type'])) {
    $user_id = intval($_POST['user_id']);
    $new_type = $_POST['user_type'];
    
    // Prevent admin from demoting themselves
    if ($user_id == $_SESSION['user_id'] && $new_type != 'admin') {
        $error = "You cannot change your own admin status";
    } else {
        $stmt = $conn->prepare("UPDATE user SET user_type = ? WHERE id = ?");
        $stmt->bind_param("si", $new_type, $user_id);
        if ($stmt->execute()) {
            $success = "User type updated successfully";
        } else {
            $error = "Error updating user type: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Reset Password
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = 'password123'; // Default password
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    if ($stmt->execute()) {
        $success = "Password reset to: password123 (Tell the user to change it)";
    } else {
        $error = "Error resetting password: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    
    // Check email uniqueness
    $check = $conn->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "Email already in use by another user";
    } else {
        $stmt = $conn->prepare("UPDATE user SET name = ?, email = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $address, $user_id);
        if ($stmt->execute()) {
            $success = "User updated successfully";
        } else {
            $error = "Error updating user: " . $stmt->error;
        }
        $stmt->close();
    }
    $check->close();
}

// Handle Bulk Delete
if (isset($_POST['bulk_delete'])) {
    $selected_users = $_POST['selected_users'] ?? [];
    $deleted_count = 0;
    
    foreach ($selected_users as $user_id) {
        $user_id = intval($user_id);
        if ($user_id != $_SESSION['user_id']) { // Don't delete current admin
            $stmt = $conn->prepare("DELETE FROM user WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $deleted_count++;
            }
            $stmt->close();
        }
    }
    
    if ($deleted_count > 0) {
        $success = "Deleted $deleted_count user(s) successfully";
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'join_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$where_conditions = "WHERE 1=1";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions .= " AND (u.name LIKE '%{$search_escaped}%' OR u.email LIKE '%{$search_escaped}%' OR u.address LIKE '%{$search_escaped}%')";
}
if (!empty($type_filter)) {
    $where_conditions .= " AND u.user_type = '{$conn->real_escape_string($type_filter)}'";
}

// Count total users
$count_query = "SELECT COUNT(*) as total FROM user u {$where_conditions}";
$total_users = $conn->query($count_query)->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Fetch users
$users_query = "SELECT u.*, 
                (SELECT COUNT(*) FROM trip_plan WHERE user_id = u.id) as trip_count,
                (SELECT COUNT(*) FROM post WHERE user_id = u.id) as post_count,
                (SELECT COUNT(*) FROM gift WHERE sender_id = u.id) as gifts_sent,
                (SELECT COUNT(*) FROM gift WHERE receiver_id = u.id) as gifts_received,
                (SELECT COUNT(*) FROM review WHERE user_id = u.id) as review_count
                FROM user u 
                {$where_conditions}
                ORDER BY u.{$sort_by} {$sort_order}
                LIMIT {$per_page} OFFSET {$offset}";
$users = $conn->query($users_query);

// Get statistics
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM user")->fetch_assoc()['count'];
$stats['admin_users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE user_type = 'admin'")->fetch_assoc()['count'];
$stats['regular_users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE user_type = 'regular'")->fetch_assoc()['count'];
$stats['new_this_month'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE MONTH(join_date) = MONTH(CURDATE()) AND YEAR(join_date) = YEAR(CURDATE())")->fetch_assoc()['count'];
$stats['active_users'] = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM post WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
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
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
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
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
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
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .table-header {
            padding: 1rem;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e9ecef;
        }
        
        .bulk-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
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
            cursor: pointer;
            user-select: none;
        }
        
        .table th:hover {
            background: #e9ecef;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-admin {
            background: #ffc107;
            color: #856404;
        }
        
        .badge-regular {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .user-stats {
            font-size: 0.85rem;
            color: #666;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            min-height: 100px;
            resize: vertical;
        }
        
        @media (max-width: 968px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table td, .table th {
                padding: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">👥 Admin Panel</a>
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
            <h1 class="page-title">👥 Manage Users</h1>
            <div>
                <a href="index.php" class="btn">← Back to Dashboard</a>
            </div>
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
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['admin_users'] ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['regular_users'] ?></div>
                <div class="stat-label">Regular Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['new_this_month'] ?></div>
                <div class="stat-label">New This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_users'] ?></div>
                <div class="stat-label">Active (30 days)</div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">🔍 Search Users</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input"
                        placeholder="Search by name, email, or address..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">User Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="regular" <?= $type_filter == 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="admin" <?= $type_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="join_date" <?= $sort_by == 'join_date' ? 'selected' : '' ?>>Join Date</option>
                        <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="email" <?= $sort_by == 'email' ? 'selected' : '' ?>>Email</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Order</label>
                    <select name="order" class="form-select">
                        <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Apply Filters</button>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="card">
            <form method="POST" id="bulkForm">
                <div class="table-header">
                    <div>
                        <strong>Showing <?= $users->num_rows ?> of <?= $total_users ?> users</strong>
                    </div>
                    <div class="bulk-actions">
                        <button type="button" onclick="selectAll()" class="btn btn-small">Select All</button>
                        <button type="button" onclick="deselectAll()" class="btn btn-small">Deselect All</button>
                        <button type="submit" name="bulk_delete" class="btn btn-small btn-danger" 
                                onclick="return confirm('Delete selected users?')">
                            Delete Selected
                        </button>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:50px;">
                                <input type="checkbox" id="selectAllCheckbox" onclick="toggleAll(this)">
                            </th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Activity</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" class="user-checkbox">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge" style="background:#28a745;color:white;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="user_type" class="form-select" 
                                                    style="width:auto;padding:0.3rem 0.5rem;font-size:0.85rem;" 
                                                    onchange="if(confirm('Change user type?')) this.form.submit();"
                                                    <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                                <option value="regular" <?= $user['user_type'] == 'regular' ? 'selected' : '' ?>>Regular</option>
                                                <option value="admin" <?= $user['user_type'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                            <input type="hidden" name="change_type" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <div class="user-stats">
                                            ✈️ <?= $user['trip_count'] ?> trips | 
                                            📝 <?= $user['post_count'] ?> posts<br>
                                            ⭐ <?= $user['review_count'] ?> reviews | 
                                            🎁 <?= $user['gifts_sent'] ?> sent
                                        </div>
                                    </td>
                                    <td><?= formatDate($user['join_date']) ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?edit=<?= $user['id'] ?>" class="btn btn-small">Edit</a>
                                            
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" name="reset_password" class="btn btn-small btn-warning"
                                                        onclick="return confirm('Reset password to: password123?')">
                                                    Reset Password
                                                </button>
                                            </form>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?= $user['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?>" 
                                                   class="btn btn-small btn-danger"
                                                   onclick="return confirm('Delete user: <?= htmlspecialchars($user['name']) ?>?\n\nThis will delete:\n- All their trips\n- All their posts\n- All their reviews\n- All their gifts\n\nThis cannot be undone!')">
                                                    Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;color:#666;padding:2rem;">
                                    No users found. <?= $search || $type_filter ? 'Try adjusting your filters.' : '' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $sort_by ? '&sort=' . $sort_by : '' ?><?= $sort_order ? '&order=' . $sort_order : '' ?>">
                        ← Previous
                    </a>
                <?php else: ?>
                    <span class="disabled">← Previous</span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $sort_by ? '&sort=' . $sort_by : '' ?><?= $sort_order ? '&order=' . $sort_order : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $type_filter ? '&type=' . $type_filter : '' ?><?= $sort_by ? '&sort=' . $sort_by : '' ?><?= $sort_order ? '&order=' . $sort_order : '' ?>">
                        Next →
                    </a>
                <?php else: ?>
                    <span class="disabled">Next →</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    