<?php 

// Direct to the login page
header("Location: login/login.php");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPC Face ID System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #083c34 0%, #0a5347 100%);
        }
        .btn-login {
            padding: 15px 50px;
            font-size: 1.2rem;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="text-center">
        <h1 class="text-white mb-4">Welcome to BPC Face ID System</h1>
        <a href="login/login.php" class="btn btn-light btn-login">Go to Login</a>
    </div>
</body>
</html>
