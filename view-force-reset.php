<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <h1>Set Your New Password</h1>
            <p class="subtitle">For security, you must set a new password before proceeding.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form method="post" action="force-reset">
                <div class="form-group">
                    <label for="password">New Password (min. 8 chars)</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm New Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <button type="submit" class="login-button">Set Password and Continue</button>
            </form>
        </div>
    </div>
</body>
</html>