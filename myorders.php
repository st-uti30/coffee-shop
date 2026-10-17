
<?php
session_start();
require_once __DIR__ . "/db.php";

// Customer must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Fetch only this customer's orders
$stmt = $conn->prepare(
    "SELECT id, subtotal, discount, total, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY id DESC"
);

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Brew Haven</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .orders-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .order-card {
            background: white;
            padding: 22px;
            margin: 20px 0;
            border-radius: 12px;
            box-shadow: 0 3px 12px #ddd;
        }

        .order-card h2 {
            color: #603d2a;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background: #f4eadf;
            color: #603d2a;
            font-weight: bold;
        }

        .order-total {
            font-weight: bold;
            color: #603d2a;
        }

        .back-link {
            color: #603d2a;
        }
    </style>
</head>

<body>

<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>

    <nav>
        <a href="index.php">Home</a>
        <a href="cart.php">Cart</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="orders-container">

    <h1>My Orders ☕</h1>
    <p>Track your coffee orders here.</p>

    <p><a class="back-link" href="index.php">← Continue Shopping</a></p>

    <?php if ($result->num_rows === 0): ?>

        <div class="order-card">
            <h2>No orders yet!</h2>
            <p>Your placed orders will appear here.</p>
            <a href="index.php">Explore our menu</a>
        </div>

    <?php else: ?>

        <?php while ($order = $result->fetch_assoc()): ?>

            <div class="order-card">

                <h2>Order #<?php echo (int)$order["id"]; ?></h2>

                <p>
                    <strong>Order Date:</strong>
                    <?php echo htmlspecialchars($order["created_at"] ?? ""); ?>
                </p>

                <p>
                    <strong>Subtotal:</strong>
                    Rp <?php echo number_format((float)$order["subtotal"], 0, ",", "."); ?>
                </p>

                <p>
                    <strong>Discount:</strong>
                    Rp <?php echo number_format((float)$order["discount"], 0, ",", "."); ?>
                </p>

                <p class="order-total">
                    Total: Rp
                    <?php echo number_format((float)$order["total"], 0, ",", "."); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <span class="status">
                        <?php echo htmlspecialchars($order["status"]); ?>
                    </span>
                </p>

            </div>

        <?php endwhile; ?>

    <?php endif; ?>

</main>

</body>
</html>

<?php
$stmt->close();
?>
