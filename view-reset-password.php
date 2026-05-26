<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <h1>Reset Password</h1>
            <p class="subtitle">Enter your new password.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if ($is_valid_token): ?>
            <form method="post" action="reset-password?token=<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="password">New Password (min. 8 chars)</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" class="login-button">Reset Password</button>
            </form>
            <?php else: ?>
                <p>Please request a new password reset link.</p>
                <div class="login-links">
                    <a href="forgot-password">Forgot Password</a>
                </div>
            <?php endif; ?>
            
            <div class="login-links">
                <a href="login">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
