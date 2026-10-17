
<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $productId = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        if (!$productId || $productId < 1) {
            http_response_code(400);
            exit("Invalid product.");
        }

        $stmt = $conn->prepare(
            "SELECT id FROM products
             WHERE id = ? AND is_available = 1"
        );
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            http_response_code(404);
            exit("Product not found.");
        }

        $currentQuantity = (int)(
            $_SESSION["cart"][$productId] ?? 0
        );

        if ($currentQuantity >= 20) {
            $message = "Maximum 20 of each product allowed.";
        } else {
            $_SESSION["cart"][$productId] = $currentQuantity + 1;
            header("Location: cart.php");
            exit;
        }
    }

    if ($action === "update") {
        $quantities = $_POST["quantity"] ?? [];

        if (is_array($quantities)) {
            foreach ($_SESSION["cart"] as $id => $oldQuantity) {
                $value = $quantities[$id] ?? $oldQuantity;

                if (is_string($value) && ctype_digit($value)) {
                    $quantity = (int)$value;

                    if ($quantity === 0) {
                        unset($_SESSION["cart"][$id]);
                    } elseif ($quantity <= 20) {
                        $_SESSION["cart"][$id] = $quantity;
                    }
                }
            }
        }

        header("Location: cart.php");
        exit;
    }

    if ($action === "remove") {
        $productId = filter_input(
            INPUT_POST,
            "product_id",
            FILTER_VALIDATE_INT
        );

        if ($productId) {
            unset($_SESSION["cart"][$productId]);
        }

        header("Location: cart.php");
        exit;
    }
}

$cart = $_SESSION["cart"];
$items = [];
$subtotal = 0;

if (!empty($cart)) {
    $ids = array_map("intval", array_keys($cart));
    $ids = array_filter($ids, fn($id) => $id > 0);

    if ($ids) {
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $types = str_repeat("i", count($ids));

        $stmt = $conn->prepare(
            "SELECT id, name, price, image
             FROM products
             WHERE is_available = 1
             AND id IN ($placeholders)"
        );

        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($product = $result->fetch_assoc()) {
            $id = (int)$product["id"];
            $quantity = (int)$cart[$id];

            $product["quantity"] = $quantity;
            $product["line_total"] =
                (float)$product["price"] * $quantity;

            $subtotal += $product["line_total"];
            $items[] = $product;
        }

        $stmt->close();
    }
}

function money($amount) {
    return "Rp " . number_format((float)$amount, 0, ",", ".");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | Brew Haven</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">☕ Brew Haven</a>
    <nav>
        <a href="index.php">Continue Shopping</a>
        <a href="cart.php">Cart</a>
    </nav>
</header>

<main class="cart-section">
    <h1>Your Shopping Cart</h1>

    <?php if ($message !== ""): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p>Your cart is empty. Add some coffee to get started!</p>
        <a href="index.php" class="btn">Browse Coffee</a>
    <?php else: ?>

        <form action="cart.php" method="POST">
            <input type="hidden" name="action" value="update">

            <?php foreach ($items as $item): ?>
                <div class="cart-item">
                    <div>
                        <h3>
                            <?php echo htmlspecialchars($item["name"]); ?>
                        </h3>
                        <p>Price: <?php echo money($item["price"]); ?></p>
                        <p>Line total: <?php echo money($item["line_total"]); ?></p>
                    </div>

                    <label>
                        Quantity
                        <input
                            type="number"
                            name="quantity[<?php echo $item["id"]; ?>]"
                            value="<?php echo $item["quantity"]; ?>"
                            min="0"
                            max="20"
                            required
                        >
                    </label>

                    <button
                        type="submit"
                        class="remove-btn"
                        form="remove-<?php echo $item["id"]; ?>"
                    >Remove</button>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="add-btn">
                Update Cart
            </button>
        </form>

        <?php foreach ($items as $item): ?>
            <form
                id="remove-<?php echo $item["id"]; ?>"
                action="cart.php"
                method="POST"
            >
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id"
                    value="<?php echo $item["id"]; ?>">
            </form>
        <?php endforeach; ?>

        <div class="cart-total">
            <h2>Subtotal: <?php echo money($subtotal); ?></h2>
            <p>Your final discount will be calculated at checkout.</p>
            <a href="checkout.php" class="btn">Proceed to Checkout</a>
        </div>

    <?php endif; ?>
</main>

</body>
</html>
