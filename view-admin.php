<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-section {
            background-color: var(--admin-surface); padding: 20px; border-radius: var(--border-radius);
            box-shadow: var(--box-shadow); margin-bottom: 25px; border: 1px solid var(--admin-border);
        }
        .form-group-flex {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;
        }
        .form-group-flex label { margin-bottom: 0; margin-right: 10px; }
        .template-link { display: inline-block; margin-left: 15px; font-size: 13px; vertical-align: middle; color: var(--primary-color); }
        .actions-cell a { margin-right: 10px; font-weight: 500; text-decoration: none; }
        .actions-cell a.action-edit { color: var(--primary-darker); }
        .actions-cell a.action-monitor { color: var(--admin-secondary); }
        .actions-cell a.action-delete { color: var(--admin-accent); }
    </style>
</head>
<?php if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true): ?>
<body class="admin-login-page-body">
    <div class="login-wrapper">
        <div class="login-container admin-login-container">
            <div class="app-logo">Ninja IoT Admin</div>
            <h1>Admin Access</h1>
            <p class="subtitle">Manage users and monitor system</p>
            <?php if (!empty($admin_error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($admin_error_message); ?></div>
            <?php endif; ?>
            <form method="post" action="<?php echo get_admin_page_url(); ?>">
                <div class="form-group"><label for="admin_id">Admin ID</label><input type="text" id="admin_id" name="admin_id" required autofocus></div>
                <div class="form-group"><label for="admin_password">Password</label><input type="password" id="admin_password" name="admin_password" required></div>
                <button type="submit" name="admin_login" class="login-button">Login</button>
            </form>
        </div>
    </div>
</body>
<?php else: ?>
<body>
    <div class="top-bar-admin">
        <button class="sidebar-toggle-admin" onclick="AdminApp.UI.toggleSidebar()" title="Toggle Sidebar">☰</button>
        <div class="logo">Admin Panel</div>
        <div class="admin-info">Admin: <strong><?php echo htmlspecialchars(ADMIN_ID); ?></strong></div>
        <div class="time-display-admin" id="admin-time-display">IST: Loading...</div>
    </div>
    <div class="main-container-admin">
        <div class="sidebar-admin">
            <h3>Create New User</h3>
            <?php if (!empty($admin_error_message) && !isset($_POST['update_settings']) && !isset($_POST['bulk_import'])): ?> <p class="message error"><?php echo htmlspecialchars($admin_error_message); ?></p> <?php endif; ?>
            <form method="post" action="<?php echo get_admin_page_url(); ?>">
                <div class="form-group"><label for="fn">First Name:</label><input type="text" id="fn" name="first_name" required></div>
                <div class="form-group"><label for="ln">Last Name:</label><input type="text" id="ln" name="last_name" required></div>
                <div class="form-group"><label for="email">Email (Gmail):</label><input type="email" id="email" name="email" placeholder="user@gmail.com" required></div>
                <div class="form-group"><label for="by">Born Year (YYYY):</label><select id="by" name="born_year" required><option value="">-- Select Year --</option><?php for ($y = intval(date('Y')) - 10; $y >= 1920; $y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?></select></div>
                <button type="submit" name="create_user">Create User</button>
            </form>
            
            <h3 style="margin-top: 25px;">Settings</h3>
            <form method="post" action="<?php echo get_admin_page_url(); ?>">
                 <div class="form-group-flex">
                    <label for="allow_reg">Allow Self-Registration:</label>
                    <input type="checkbox" id="allow_reg" name="allow_self_registration" <?php echo get_app_config('allow_self_registration') ? 'checked' : ''; ?>>
                </div>
                <button type="submit" name="update_settings">Save Settings</button>
            </form>

            <div class="sidebar-actions">
                <a href="index.php?action=download_user_list">Download Users (CSV)</a>
                <a href="<?php echo get_admin_page_url(); ?>?logout_admin=true" class="logout-btn">Logout</a>
            </div>
        </div>
        <div class="sidebar-overlay-admin" onclick="AdminApp.UI.toggleSidebar()"></div>
        <div class="content-admin">
            <?php if (!empty($_SESSION['admin_message'])): ?>
                <p class="message success auto-hide"><?php echo htmlspecialchars($_SESSION['admin_message']); ?></p>
                <?php unset($_SESSION['admin_message']); ?>
            <?php endif; ?>
            <?php if (!empty($admin_success_message)): ?> <p class="message success auto-hide"><?php echo htmlspecialchars($admin_success_message); ?></p> <?php endif; ?>
            <?php if (!empty($admin_error_message)): ?> <p class="message error"><?php echo htmlspecialchars($admin_error_message); ?></p> <?php endif; ?>
            
            <div class="admin-section">
                <h2>Bulk User Import</h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo get_admin_page_url(); ?>">
                    <div class="form-group">
                        <label for="user_csv">Upload CSV File (FirstName,LastName,Email,BornYear,Password):</label>
                        <input type="file" name="user_csv" id="user_csv" accept=".csv" required>
                    </div>
                    <button type="submit" name="bulk_import">Import Users</button>
                    <a href="index.php?action=download_user_template" class="template-link" title="Download a CSV file with the correct headers">Download Template</a>
                </form>
            </div>

            <div class="admin-section">
                <h2>Registered Users List</h2>
                <div class="table-container">
                    <table>
                        <thead><tr><th>UID</th><th>Name</th><th>Email</th><th>Born Year</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php $all_user_details = getAllUsers(); ?>
                            <?php if (empty($all_user_details)): ?> 
                                <tr><td colspan="6" style="text-align:center;">No users registered.</td></tr>
                            <?php else: foreach ($all_user_details as $ud): if (!is_array($ud) || !isset($ud['UID'])) continue; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ud['UID']); ?></td>
                                    <td><?php echo htmlspecialchars(($ud['FirstName'] ?? '') . ' ' . ($ud['LastName'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($ud['Email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($ud['BornYear'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($ud['Status'] ?? 'N/A'); ?></td>
                                    <td class="actions-cell">
                                        <a class="action-monitor" href="monitor-user?uid=<?php echo urlencode($ud['UID']); ?>">Monitor</a>
                                        <a class="action-edit" href="edit-user?uid=<?php echo urlencode($ud['UID']); ?>">Edit</a>
                                        <a class="action-delete" href="<?php echo get_admin_page_url(); ?>?delete_user=<?php echo urlencode($ud['UID']); ?>" onclick="return confirm('DELETE USER\n\nAre you sure you want to delete user \'<?php echo htmlspecialchars(addslashes($ud['UID'])); ?>\' and ALL their associated data?\n\nThis action cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-admin">
        <span>&copy; <?php echo date('Y'); ?> Ninja IoT Admin</span>
        <span id="admin-footer-time-display">IST: ...</span>
    </div>
    <script src="scripts.js"></script>
</body>
<?php endif; ?>
</html>