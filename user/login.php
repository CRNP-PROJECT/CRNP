<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Login | Crates N' Plates</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/index-s.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
</head>



<body class="user-auth-body">
    <div id="g_id_onload"
     data-client_id="1029428356414-ituvg05tojmq3sva030sab3cdog78fra.apps.googleusercontent.com"
     data-callback="handleCredentialResponse">
</div>

<div class="user-split-container">

    <!-- LEFT FORM -->
    <div class="user-form-side">
        <div class="user-auth-card">

            <h1 class="user-auth-title">Welcome Back</h1>
            <p class="user-auth-subtitle">Sign in to your account</p>

            <form method="POST" action="login_action.php">

                <div class="user-form-group">
                    <label class="user-form-label">Email Address</label>
                    <input type="email" name="email" class="user-form-input" placeholder="Enter your email" required>
                </div>

                <div class="user-form-group">
                    <label class="user-form-label">Password</label>
                    <input type="password" name="password" class="user-form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="user-btn-auth">Login</button>
                     <div style="display: flex; justify-content: center; margin-bottom: 20px;">
            <div class="g_id_signin" data-type="standard"></div>
        </div>

            </form>

            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>  
            <p class="user-auth-footer">
                Don't have an account? <a href="sign_up.php">Sign Up</a>
            </p>

        </div>
    </div>

    <!-- RIGHT VISUAL -->
    <div class="user-visual-side">
        <div class="user-profile-circle">
            <img src="https://cdn-icons-png.flaticon.com/512/4140/4140048.png">
        </div>
    </div>

</div>


<script>
function handleCredentialResponse(response) {

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "google-auth.php"; // adjust path

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "token";
    input.value = response.credential;

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>


</body>
</html>