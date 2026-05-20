<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Sign Up | Crates N' Plates</title>

<!-- GOOGLE SIGN-IN -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
</head>

<body class="user-auth-body">

<!-- GOOGLE CONFIG -->
<div id="g_id_onload"
     data-client_id="1029428356414-ituvg05tojmq3sva030sab3cdog78fra.apps.googleusercontent.com"
     data-callback="handleCredentialResponse">
</div>

<div class="user-split-container">

    <!-- LEFT FORM -->
    <div class="user-form-side">
        <div class="user-auth-card">

            <h1 class="user-auth-title">Create Account</h1>
            <p class="user-auth-subtitle">Join us today</p>

            <form method="POST" action="signup_action.php">

                <div class="user-form-group">
                    <label class="user-form-label">Full Name</label>
                    <input type="text" name="name" class="user-form-input" placeholder="Enter your name" required>
                </div>

                <div class="user-form-group">
                    <label class="user-form-label">Email Address</label>
                    <input type="email" name="email" class="user-form-input" placeholder="Enter your email" required>
                </div>

                <div class="user-form-group">
                    <label class="user-form-label">Password</label>
                    <input type="password" name="password" class="user-form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="user-btn-auth">
                    Sign Up
                </button>

                <!-- GOOGLE SIGN UP BUTTON -->
                <div style="display:flex; justify-content:center; margin-top:15px;">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-size="large"
                         data-text="signup">
                    </div>
                </div>

            </form>

            <p class="user-auth-footer">
                Already have an account?
                <a href="login.php">Login</a>
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

<!-- GOOGLE CALLBACK SCRIPT -->
<script>
function handleCredentialResponse(response) {

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "google_login.php";

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