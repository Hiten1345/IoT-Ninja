<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <div class="app-logo">Ninja IoT</div>
            <h1>Change Password</h1>
            <p class="subtitle">Please set a new password for your account.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php else: ?>
                <form method="post" action="change-password">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8" autofocus>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    <button type="submit" class="login-button">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
