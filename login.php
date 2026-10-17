
<?php
session_start();
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$error = "";
$registered = isset($_GET["registered"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150 ||
        $password === "" ||
        strlen($password) > 72
    ) {
        $error = "Invalid email or password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Brew Haven</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>
    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">Cart</a>
        <a href="register.php">Register</a>
    </nav>
</header>

<main class="auth-card">
    <p class="eyebrow">WELCOME BACK</p>
    <h1>Login</h1>
    <p>Sign in to enjoy 10% off your order.</p>

    <?php if ($registered): ?>
        <p class="success-message">
            Account created! Please log in.
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            maxlength="72"
            required
        >

        <button type="submit" class="auth-btn">Login</button>
    </form>

    <p class="auth-footer">
        New customer?
        <a href="register.php">Create an account</a>
    </p>
</main>

</body>
</html>
