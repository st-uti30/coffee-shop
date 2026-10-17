
<?php
session_start();
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $name === "" ||
        strlen($name) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        strlen($password) < 8 ||
        strlen($password) > 72
    ) {
        $error = "Enter valid details. Password must be at least 8 characters.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $error = "This email is already registered. Please log in.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, 'customer')"
            );
            $stmt->bind_param("sss", $name, $email, $hash);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Registration failed. Please try again.";
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Brew Haven</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>
    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">Cart</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<main class="auth-card">
    <p class="eyebrow">JOIN OUR COFFEE COMMUNITY</p>
    <h1>Create Account</h1>
    <p>Register to unlock your 10% coffee discount!</p>

    <?php if ($error !== ""): ?>
        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label for="name">Full Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            required
        >

        <label for="password">Password (minimum 8 characters)</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            maxlength="72"
            required
        >

        <button type="submit" class="auth-btn">
            Create Account
        </button>
    </form>

    <p class="auth-footer">
        Already registered?
        <a href="login.php">Login here</a>
    </p>
</main>

</body>
</html>
