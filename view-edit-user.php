<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . get_admin_page_url());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page-body">
    <div class="login-wrapper" style="max-width: 450px;">
        <div class="login-container admin-login-container">
            <h1>Edit User: <?php echo htmlspecialchars($user_to_edit['UID']); ?></h1>
            <p class="subtitle">Update user details below.</p>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form method="post" action="edit-user">
                <input type="hidden" name="uid" value="<?php echo htmlspecialchars($user_to_edit['UID']); ?>">
                
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_to_edit['FirstName'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_to_edit['LastName'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['Email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="born_year">Born Year</label>
                    <input type="number" id="born_year" name="born_year" value="<?php echo htmlspecialchars($user_to_edit['BornYear'] ?? ''); ?>" required>
                </div>
                <hr style="margin: 25px 0; border: 1px solid var(--admin-border);">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                </div>

                <button type="submit" name="update_user" class="login-button">Save Changes</button>
            </form>
            <div class="login-links" style="text-align: center; margin-top: 15px;">
                <a href="<?php echo get_admin_page_url(); ?>">Cancel</a>
            </div>
        </div>
    </div>
</body>
</html>