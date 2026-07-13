<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Sign Up | Crates N' Plates</title>

<!-- GOOGLE SIGN-IN -->
<script src="https://accounts.google.com/gsi/client" async defer></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
</head>

<body class="signup-body">

    <!-- GOOGLE CONFIG -->
    <div id="g_id_onload"
        data-client_id="704090839289-fefs43tnpg5tivrd67hq5te923amgt3g.apps.googleusercontent.com"
        data-callback="handleCredentialResponse">
    </div>

    <div class="signup-container">

        <div class="signup-card">

            <div class="signup-welcome">
                <h1>Join Us</h1>
                <p>
                    Create your account and experience the flavors of
                    Crates N' Plates.
                </p>
            </div>

            <div class="signup-form-side">

                <div class="signup-form-card">

                    <h2 class="signup-brand">
                        CRATES N' PLATES
                    </h2>

                    <h3 class="signup-title">
                        Create Account
                    </h3>

                    <p class="signup-subtitle">
                        Join us today
                    </p>

                    <form method="POST" action="signup_action.php">

                        <div class="signup-group">
                            <label class="signup-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                class="signup-input"
                                placeholder="Enter your name"
                                required>
                        </div>

                        <div class="signup-group">
                            <label class="signup-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="signup-input"
                                placeholder="Enter your email"
                                required>
                        </div>

                        <div class="signup-group">
                            <label class="signup-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="signup-input"
                                placeholder="Enter your password"
                                required>
                        </div>

                        <button
                            type="submit"
                            class="signup-btn">
                            Sign Up
                        </button>

                        <div class="signup-divider">
                            <span>or</span>
                        </div>

                        <div class="signup-google">
                            <div class="g_id_signin"
                                data-type="standard"
                                data-size="large"
                                data-text="signup">
                            </div>
                        </div>

                    </form>

                    <p class="signup-footer">
                        Already have an account?
                        <a href="login.php">Login</a>
                    </p>

                </div>

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