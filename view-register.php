<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?php echo APP_BASE_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Ninja IoT</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .otp-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 10px;
            font-weight: bold;
        }
        .resend-otp {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }
        .resend-otp a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .resend-otp a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-page-body">
    <div class="login-wrapper">
        <div class="login-container user-login-container">
            <?php if (!isset($show_otp_form) || !$show_otp_form): ?>
                <!-- Registration Form -->
                <h1>Create Account</h1>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php
                    // Helper: keep previously submitted values if the form reloads
                    $firstName     = $_POST['first_name'] ?? '';
                    $lastName      = $_POST['last_name'] ?? '';
                    $email         = $_POST['email'] ?? '';
                    $selectedYear  = $_POST['born_year'] ?? '';
                    $isGoogleSignup = false;

                    if (isset($_SESSION['google_signup_data'])) {
                        $isGoogleSignup = true;
                        $gData = $_SESSION['google_signup_data'];
                        if (empty($firstName)) $firstName = $gData['first_name'];
                        if (empty($lastName)) $lastName = $gData['last_name'];
                        if (empty($email)) $email = $gData['email'];
                    }

                    $currentYear   = (int) date('Y');
                    $minYear       = 1950;
                ?>

                <form method="post" action="register" novalidate>
                    <?php if ($isGoogleSignup): ?>
                        <div class="message success">Please complete your registration details.</div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email (Gmail)</label>
                        <input type="email"
                               name="email"
                               value="<?php echo htmlspecialchars($email); ?>"
                               required
                               pattern=".+@gmail\.com"
                               title="Only Gmail addresses allowed"
                               <?php echo $isGoogleSignup ? 'readonly style="background-color: #eee;"' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label>Born Year</label>
                        <select name="born_year" required>
                            <!-- Placeholder: ensures the dropdown is empty by default -->
                            <option value="" disabled <?php echo $selectedYear === '' ? 'selected' : ''; ?> hidden>
                                Select year
                            </option>
                            <?php for ($year = $currentYear; $year >= $minYear; $year--): ?>
                                <option value="<?php echo $year; ?>" <?php echo ((string)$selectedYear === (string)$year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if (!$isGoogleSignup): ?>
                    <div class="form-group">
                        <label>Password (min. 8 chars)</label>
                        <input type="password" name="password" minlength="8" required>
                    </div>
                    <?php endif; ?>

                    <?php if ($isGoogleSignup): ?>
                        <button type="submit" name="complete_google_signup" class="login-button">Complete Registration</button>
                    <?php else: ?>
                        <button type="submit" name="send_otp" class="login-button">Send OTP</button>
                    <?php endif; ?>
                </form>

                <div class="login-links">
                    <a href="login">Already have an account? Login</a>
                </div>

            <?php else: ?>
                <!-- OTP Verification Form -->
                <h1>Verify Email</h1>
                <p class="subtitle">Enter the 4-digit OTP sent to your email</p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="message success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="register">
                    <div class="form-group">
                        <label>Enter OTP</label>
                        <input type="text" 
                               name="otp" 
                               class="otp-input"
                               maxlength="4" 
                               pattern="[0-9]{4}"
                               placeholder="0000"
                               required 
                               autofocus>
                    </div>

                    <button type="submit" name="verify_otp" class="login-button">Verify & Create Account</button>
                </form>

                <div class="resend-otp">
                    <a href="register" onclick="return confirm('Start registration again?');">Didn't receive OTP? Start over</a>
                    <br><br>
                    <a href="login">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
