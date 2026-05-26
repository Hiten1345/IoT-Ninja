<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <title>Forgot Password - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <h1>Reset Password</h1>
            <p class="subtitle">Enter your email to receive a reset link.</p>
            <?php if (!empty($success_message)): ?><div class="message success"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>
            <form method="post" action="forgot-password">
                <div class="form-group">
                    <label for="email">Enter your Email Address</label>
                    <input type="email" id="email" name="email" placeholder="example@gmail.com" required autofocus>
                </div>
                <button type="submit" class="btn-login">Send Reset Link</button>
            </form>
            <div class="login-links">
                <a href="login">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>