
<?php

// Load database connection and shared functions
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/includes/functions.php";

$error = "";
$success = "";
$activeTab = "login";

// Redirect users to their dashboards
function dashboardUrl($role)
{
    switch ($role) {
        case "patient":
            return "patient/dashboard.php";

        case "doctor":
            return "doctor/dashboard.php";

        case "admin":
            return "admin/dashboard.php";

        default:
            return "index.php";
    }
}

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle registration and login
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $activeTab = $_POST["action"] ?? "login";

    // Verify CSRF token
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        $error = "Invalid form submission. Please try again.";
    }

    // PATIENT REGISTRATION
    elseif ($activeTab === "register") {

        $name = trim($_POST["full_name"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $phone = trim($_POST["phone"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            $name === "" ||
            $email === "" ||
            $phone === "" ||
            $password === ""
        ) {
            $error = "Please fill in all required fields.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";

        } elseif (strlen($password) < 8) {
            $error = "Password must contain at least 8 characters.";

        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";

        } else {

            try {
                // Check if email already exists
                $stmt = $pdo->prepare(
                    "SELECT id FROM users WHERE email = ?"
                );

                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = "This email is already registered.";

                } else {

                    // Hash password
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Register patient
                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                        (full_name, email, phone, password, role)
                        VALUES (?, ?, ?, ?, 'patient')"
                    );

                    $stmt->execute([
                        $name,
                        $email,
                        $phone,
                        $hashedPassword
                    ]);

                    // Redirect to login after registration
                    redirect("auth.php?registered=1");
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());

                $error = "Registration failed. Please try again.";
            }
        }
    }

    // LOGIN
    elseif ($activeTab === "login") {

        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {
            $error = "Please enter your email and password.";

        } else {

            try {
                // Find user by email
                $stmt = $pdo->prepare(
                    "SELECT id, full_name, email, password, role
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->execute([$email]);

                $user = $stmt->fetch();

                // Verify password
                if (
                    $user &&
                    password_verify($password, $user["password"])
                ) {

                    // Verify doctor account
                    if ($user["role"] === "doctor") {

                        $doctorCheck = $pdo->prepare(
                            "SELECT id FROM doctors WHERE user_id = ?"
                        );

                        $doctorCheck->execute([$user["id"]]);

                        if (!$doctorCheck->fetch()) {
                            $error = "Doctor account is not properly configured.";
                        }
                    }

                    // Login successful
                    if ($error === "") {

                        session_regenerate_id(true);

                        $_SESSION["user_id"] = $user["id"];
                        $_SESSION["full_name"] = $user["full_name"];
                        $_SESSION["email"] = $user["email"];
                        $_SESSION["role"] = $user["role"];

                        redirect(dashboardUrl($user["role"]));
                    }

                } else {
                    $error = "Incorrect email or password.";
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());

                $error = "Login failed. Please try again.";
            }
        }
    }
}

// Registration success message
if (isset($_GET["registered"])) {
    $success = "Registration successful! Please log in.";
    $activeTab = "login";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Moses Healthcare | Login</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: "Century Gothic", CenturyGothic,
                         AppleGothic, sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-sky-50 flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">

        <div class="text-center mb-6">

            <div class="text-4xl mb-2 text-sky-600">✚</div>

            <h1 class="text-2xl font-bold text-sky-700">
                Moses Healthcare
            </h1>

            <p class="text-gray-500 text-sm mt-2">
                Your health, our priority
            </p>

        </div>

        <?php if ($error !== ""): ?>

            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm">
                <?= escape($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($success !== ""): ?>

            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm">
                <?= escape($success) ?>
            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->
        <div id="loginForm"
             class="<?= $activeTab === 'login' ? '' : 'hidden' ?>">

            <h2 class="text-xl font-semibold mb-4 text-gray-800">
                Login to your account
            </h2>

            <form method="POST" action="auth.php" class="space-y-4">

                <input type="hidden" name="action" value="login">

                <input type="hidden" name="csrf_token"
                       value="<?= escape($_SESSION["csrf_token"]) ?>">

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Email address
                    </label>

                    <input type="email" name="email" required
                           autocomplete="email"
                           class="w-full border rounded-lg px-4 py-3
                                  focus:outline-none focus:ring-2
                                  focus:ring-sky-400">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Password
                    </label>

                    <input type="password" name="password" required
                           autocomplete="current-password"
                           class="w-full border rounded-lg px-4 py-3
                                  focus:outline-none focus:ring-2
                                  focus:ring-sky-400">
                </div>

                <button type="submit"
                        class="w-full bg-sky-600 hover:bg-sky-700
                               text-white font-semibold py-3 rounded-lg">
                    Login
                </button>

            </form>

            <p class="text-center text-sm mt-5 text-gray-600">
                Don't have an account?

                <button type="button" onclick="showRegister()"
                        class="text-sky-600 font-semibold">
                    Register here
                </button>
            </p>

        </div>

        <!-- PATIENT REGISTRATION FORM -->
        <div id="registerForm"
             class="<?= $activeTab === 'register' ? '' : 'hidden' ?>">

            <h2 class="text-xl font-semibold mb-4 text-gray-800">
                Patient registration
            </h2>

            <form method="POST" action="auth.php" class="space-y-4">

                <input type="hidden" name="action" value="register">

                <input type="hidden" name="csrf_token"
                       value="<?= escape($_SESSION["csrf_token"]) ?>">

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Full name
                    </label>

                    <input type="text" name="full_name" required
                           maxlength="100" autocomplete="name"
                           class="w-full border rounded-lg px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Email address
                    </label>

                    <input type="email" name="email" required
                           maxlength="100" autocomplete="email"
                           class="w-full border rounded-lg px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Phone number
                    </label>

                    <input type="tel" name="phone" required
                           maxlength="20" autocomplete="tel"
                           class="w-full border rounded-lg px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Password
                    </label>

                    <input type="password" name="password" required
                           minlength="8" autocomplete="new-password"
                           class="w-full border rounded-lg px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Confirm password
                    </label>

                    <input type="password" name="confirm_password" required
                           minlength="8" autocomplete="new-password"
                           class="w-full border rounded-lg px-4 py-3">
                </div>

                <button type="submit"
                        class="w-full bg-sky-600 hover:bg-sky-700
                               text-white font-semibold py-3 rounded-lg">
                    Create Account
                </button>

            </form>

            <p class="text-center text-sm mt-5 text-gray-600">
                Already registered?

                <button type="button" onclick="showLogin()"
                        class="text-sky-600 font-semibold">
                    Login here
                </button>
            </p>

        </div>

    </div>

    <script>
        function showRegister() {
            document.getElementById("loginForm").classList.add("hidden");
            document.getElementById("registerForm").classList.remove("hidden");
        }

        function showLogin() {
            document.getElementById("registerForm").classList.add("hidden");
            document.getElementById("loginForm").classList.remove("hidden");
        }
    </script>

</body>
</html>
