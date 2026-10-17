
<?php
session_start();
require_once __DIR__ . "/db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION["user_id"]);
$error = "";
$done = isset($_GET["done"]) ? (int)$_GET["done"] : 0;

// Load logged-in customer's details from database
$customerName = "";
$customerEmail = "";

if ($isLoggedIn) {
    $userId = (int)$_SESSION["user_id"];

    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        session_destroy();
        exit("User account not found. Please log in again.");
    }

    $customerName = $user["name"];
    $customerEmail = $user["email"];
}

// Load cart and calculate prices from the database
function loadCart($conn) {
    $cart = $_SESSION["cart"] ?? [];
    $items = [];
    $subtotal = 0;

    if (!is_array($cart) || empty($cart)) {
        return [$items, $subtotal];
    }

    $stmt = $conn->prepare(
        "SELECT id, name, price
         FROM products
         WHERE id = ? AND is_available = 1"
    );

    foreach ($cart as $id => $quantity) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

        if (!$id || !$quantity || $quantity < 1 || $quantity > 99) {
            continue;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            continue;
        }

        $lineTotal = (float)$product["price"] * $quantity;
        $subtotal += $lineTotal;

        $items[] = [
            "id" => (int)$product["id"],
            "name" => $product["name"],
            "price" => (float)$product["price"],
            "quantity" => $quantity,
            "line_total" => $lineTotal
        ];
    }

    $stmt->close();

    return [$items, $subtotal];
}

[$items, $subtotal] = loadCart($conn);

$discount = $isLoggedIn ? round($subtotal * 0.10, 2) : 0;
$total = max(0, $subtotal - $discount);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$done) {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $error = "Invalid form token. Refresh the page and try again.";
    } elseif (empty($items)) {
        $error = "Your cart is empty or its products are unavailable.";
    } else {
        $name = $customerName;
        $email = $customerEmail;

        if (!$isLoggedIn) {
            $name = trim($_POST["customer_name"] ?? "");
            $email = trim($_POST["customer_email"] ?? "");

            if (
                $name === "" ||
                strlen($name) > 150 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 254
            ) {
                $error = "Enter a valid name and email address.";
            }
        }

        if ($error === "") {
            // Recalculate the cart immediately before saving
            [$items, $subtotal] = loadCart($conn);

            if (empty($items)) {
                $error = "Your cart is empty or its products are unavailable.";
            } else {
                $discount = $isLoggedIn ? round($subtotal * 0.10, 2) : 0;
                $total = max(0, $subtotal - $discount);

                try {
                    $conn->begin_transaction();

                    $orderUserId = $isLoggedIn
                        ? (int)$_SESSION["user_id"] : null;

                    $status = "Pending";

                    $stmt = $conn->prepare(
                        "INSERT INTO orders
                         (user_id, customer_name, email, subtotal,
                          discount, total, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "issddds",
                        $orderUserId,
                        $name,
                        $email,
                        $subtotal,
                        $discount,
                        $total,
                        $status
                    );

                    $stmt->execute();
                    $orderId = $conn->insert_id;
                    $stmt->close();

                    $itemStmt = $conn->prepare(
                    "INSERT INTO order_items
                    (order_id, product_id, product_name, price, quantity)
                    VALUES (?, ?, ?, ?, ?)"
);
foreach ($cartProducts as $item) {
    $productId = (int)$item["id"];
    $productName = $item["name"];
    $quantity = (int)$item["quantity"];
    $price = (float)$item["price"];

    $itemStmt->bind_param(
        "issdi",
        $orderId,
        $productId,
        $productName,
        $price,
        $quantity
    );

    $itemStmt->execute();
}

                    $itemStmt->close();
                    $conn->commit();

                    unset($_SESSION["cart"]);

                    header("Location: checkout.php?done=" . $orderId);
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = "Order could not be saved. Please try again.";
                }
            }
        }
    }
}

if ($done > 0) {
    $stmt = $conn->prepare(
        "SELECT id, customer_name, subtotal, discount, total, status
         FROM orders WHERE id = ?"
    );
    $stmt->bind_param("i", $done);
    $stmt->execute();
    $completedOrder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Brew Haven</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout {
            max-width: 750px;
            margin: 35px auto;
            padding: 20px;
        }
        .panel {
            background: white;
            padding: 22px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,.07);
        }
        .panel input {
            display: block;
            width: 100%;
            padding: 12px;
            margin: 8px 0 16px;
            box-sizing: border-box;
        }
        .checkout button {
            background: #603d2a;
            color: white;
            padding: 12px 20px;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }
        .line {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 12px 0;
        }
        .message { padding: 12px; border-radius: 5px; }
        .error { background: #fce8e6; color: #a12622; }
        .success { background: #e7f5e8; color: #236b31; }
    </style>
</head>
<body>
<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>
    <nav>
        <a href="index.php">Menu</a>
        <a href="cart.php">Cart</a>
        <?php if ($isLoggedIn): ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<main class="checkout">
    <?php if ($done && !empty($completedOrder)): ?>
        <section class="panel">
            <h1>Order Placed Successfully!</h1>
            <p class="message success">
                Thank you, <?php echo htmlspecialchars($completedOrder["customer_name"]); ?>!
            </p>
            <p>Order number: #<?php echo (int)$completedOrder["id"]; ?></p>
            <p>Subtotal: Rp <?php echo number_format($completedOrder["subtotal"], 0, ",", "."); ?></p>
            <p>Discount: Rp <?php echo number_format($completedOrder["discount"], 0, ",", "."); ?></p>
            <h2>Total: Rp <?php echo number_format($completedOrder["total"], 0, ",", "."); ?></h2>
            <p>Status: <?php echo htmlspecialchars($completedOrder["status"]); ?></p>
            <a href="index.php">Continue Shopping</a>
        </section>
    <?php else: ?>
        <h1>Checkout</h1>

        <?php if ($error !== ""): ?>
            <p class="message error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <section class="panel">
            <h2>Order Summary</h2>

            <?php if (empty($items)): ?>
                <p>Your cart is empty. <a href="index.php">Browse coffee</a></p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="line">
                        <span>
                            <?php echo htmlspecialchars($item["name"]); ?>
                            × <?php echo (int)$item["quantity"]; ?>
                        </span>
                        <span>Rp <?php echo number_format($item["line_total"], 0, ",", "."); ?></span>
                    </div>
                <?php endforeach; ?>

                <hr>
                <div class="line">
                    <span>Subtotal</span>
                    <span>Rp <?php echo number_format($subtotal, 0, ",", "."); ?></span>
                </div>
                <div class="line">
                    <span>Login discount (10%)</span>
                    <span>- Rp <?php echo number_format($discount, 0, ",", "."); ?></span>
                </div>
                <div class="line">
                    <strong>Total</strong>
                    <strong>Rp <?php echo number_format($total, 0, ",", "."); ?></strong>
                </div>

                <?php if (!$isLoggedIn): ?>
                    <p><a href="login.php">Log in</a> to receive a 10% discount.</p>
                <?php else: ?>
                    <p>You're logged in. Your 10% discount has been applied.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if (!empty($items)): ?>
            <section class="panel">
                <h2>Customer Details</h2>

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                    <?php if ($isLoggedIn): ?>
                        <p>Name: <?php echo htmlspecialchars($customerName); ?></p>
                        <p>Email: <?php echo htmlspecialchars($customerEmail); ?></p>
                    <?php else: ?>
                        <label for="customer_name">Full Name</label>
                        <input id="customer_name" name="customer_name"
                               maxlength="150" required>

                        <label for="customer_email">Email Address</label>
                        <input type="email" id="customer_email"
                               name="customer_email" maxlength="254" required>
                    <?php endif; ?>

                    <button type="submit">Place Order</button>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
