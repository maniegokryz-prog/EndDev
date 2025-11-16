<!DOCTYPE html>
<html>
<head>
    <title>OTP System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; }
        h2 { color: #28a745; }
        .result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        label { font-weight: bold; display: block; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🧪 OTP Email System Test</h2>
        
        <?php
        require '../db_connection.php';
        
        echo "<h3>1️⃣ PHPMailer Check</h3>";
        $phpmailerPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($phpmailerPath)) {
            echo "<div class='result success'>✅ PHPMailer found at: " . realpath($phpmailerPath) . "</div>";
            require $phpmailerPath;
            echo "<div class='result success'>✅ PHPMailer loaded successfully</div>";
        } else {
            echo "<div class='result error'>❌ PHPMailer NOT found. Expected at: $phpmailerPath</div>";
        }
        
        echo "<h3>2️⃣ Database Check</h3>";
        $result = $conn->query("SELECT employee_id, email, first_name, last_name, status FROM employees WHERE status = 'active' LIMIT 5");
        
        if ($result->num_rows > 0) {
            echo "<div class='result success'>✅ Found " . $result->num_rows . " active employees</div>";
            echo "<table border='1' cellpadding='10' style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
            echo "<tr><th>Employee ID</th><th>Email</th><th>Name</th></tr>";
            while ($row = $result->fetch_assoc()) {
                $emailStatus = !empty($row['email']) && $row['email'] !== 'N/A' ? '✅' : '❌ No Email';
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['employee_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . " $emailStatus</td>";
                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='result error'>❌ No active employees found</div>";
        }
        
        echo "<h3>3️⃣ SMTP Configuration</h3>";
        echo "<div class='result info'>";
        echo "<strong>SMTP Server:</strong> smtp.ionos.com<br>";
        echo "<strong>Port:</strong> 587<br>";
        echo "<strong>Encryption:</strong> STARTTLS<br>";
        echo "<strong>Username:</strong> accounts@bpcfaceid.com<br>";
        echo "<strong>From:</strong> BPC FaceID Attendance System";
        echo "</div>";
        
        echo "<h3>4️⃣ password_reset_otp Table</h3>";
        $tableCheck = $conn->query("SHOW TABLES LIKE 'password_reset_otp'");
        if ($tableCheck->num_rows > 0) {
            echo "<div class='result success'>✅ Table exists</div>";
            $otpCount = $conn->query("SELECT COUNT(*) as count FROM password_reset_otp")->fetch_assoc();
            echo "<div class='result info'>Records: " . $otpCount['count'] . "</div>";
        } else {
            echo "<div class='result info'>ℹ️ Table will be created automatically on first use</div>";
        }
        ?>
        
        <h3>5️⃣ Test Password Reset</h3>
        <form method="POST" action="">
            <label>Employee ID:</label>
            <input type="text" name="test_emp_id" placeholder="Enter employee ID" required>
            
            <label>Email:</label>
            <input type="email" name="test_email" placeholder="Enter email" required>
            
            <button type="submit" name="test_otp">Send Test OTP</button>
        </form>
        
        <?php
        if (isset($_POST['test_otp'])) {
            echo "<h3>📧 Test Result</h3>";
            
            $test_emp_id = $_POST['test_emp_id'];
            $test_email = $_POST['test_email'];
            
            // Verify employee
            $stmt = $conn->prepare("SELECT id, employee_id, email, first_name, last_name FROM employees WHERE employee_id = ? AND email = ? AND status = 'active'");
            $stmt->bind_param("ss", $test_emp_id, $test_email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo "<div class='result error'>❌ No matching employee found with ID: $test_emp_id and Email: $test_email</div>";
            } else {
                $user = $result->fetch_assoc();
                echo "<div class='result success'>✅ Employee found: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</div>";
                
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                echo "<div class='result info'><strong>Generated OTP:</strong> $otp</div>";
                
                // Try to send email
                if (file_exists($phpmailerPath)) {
                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.ionos.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'accounts@bpcfaceid.com';
                        $mail->Password   = 'Confirmp@ssword123';
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        
                        $mail->setFrom('accounts@bpcfaceid.com', 'BPC FaceID Attendance System');
                        $mail->addAddress($test_email, $user['first_name'] . ' ' . $user['last_name']);
                        
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset OTP - Attendance System';
                        $mail->Body    = "
                            <html>
                            <body style='font-family: Arial, sans-serif;'>
                                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                                    <h2 style='color: #333;'>Password Reset Request</h2>
                                    <p>Hello {$user['first_name']},</p>
                                    <p>You requested to reset your password. Use the OTP code below to continue:</p>
                                    <div style='background-color: #f0f0f0; padding: 15px; text-align: center; margin: 20px 0;'>
                                        <h1 style='color: #28a745; font-size: 32px; letter-spacing: 5px; margin: 0;'>$otp</h1>
                                    </div>
                                    <p><strong>This OTP will expire in 10 minutes.</strong></p>
                                    <p>If you didn't request this, please ignore this email.</p>
                                    <hr style='border: 1px solid #ddd; margin: 20px 0;'>
                                    <p style='color: #999; font-size: 12px;'>Automated Attendance System with Facial Recognition</p>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        $mail->send();
                        echo "<div class='result success'>✅ Email sent successfully to: $test_email</div>";
                        echo "<div class='result info'>📧 Check your email inbox (and spam folder) for the OTP!</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='result error'>❌ Failed to send email: " . $mail->ErrorInfo . "</div>";
                        echo "<div class='result info'>OTP was logged instead. Check error logs.</div>";
                        
                        // Log to project logs folder
                        $logDir = __DIR__ . '/../logs/';
                        if (!file_exists($logDir)) {
                            mkdir($logDir, 0755, true);
                        }
                        $logFile = $logDir . 'otp_errors.log';
                        $logEntry = date('Y-m-d H:i:s') . " - OTP for {$user['first_name']} {$user['last_name']} ($test_email): $otp - Error: {$mail->ErrorInfo}" . PHP_EOL;
                        file_put_contents($logFile, $logEntry, FILE_APPEND);
                        
                        echo "<div class='result info'>📁 Error logged to: " . realpath($logFile) . "</div>";
                    }
                } else {
                    echo "<div class='result error'>❌ PHPMailer not found</div>";
                }
            }
        }
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
            <strong>💡 Note:</strong> If email doesn't send, check:
            <ul>
                <li>Internet connection</li>
                <li>IONOS email credentials are correct</li>
                <li>Error logs: C:\inetpub\wwwroot\EndDev\logs\otp_errors.log</li>
                <li>Email might be in spam folder</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="../login/login.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Go to Login Page</a>
        </div>
    </div>
</body>
</html>
