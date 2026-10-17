<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check whether cart has items
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$cart = $_SESSION['cart'];
$total = 0;
$checkoutItems = [];

foreach ($cart as $productId => $quantity) {
    $productId = (int)$productId;
    $quantity = max(1, (int)$quantity);

    if ($productId < 1) {
        continue;
    }

    $stmt = $conn->prepare(
        "SELECT id, name, price
         FROM products
         WHERE id = ? AND is_available = 1"
    );

    $stmt->bind_param("i", $productId);
    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        continue;
    }

    $price = (float)$product['price'];
    $lineTotal = $price * $quantity;
    $total += $lineTotal;

    $checkoutItems[] = [
        'id' => $productId,
        'name' => $product['name'],
        'price' => $price,
        'quantity' => $quantity
    ];
}

if (empty($checkoutItems)) {
    header("Location: cart.php");
    exit;
}

$cart = $checkoutItems;

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customer === '' || $phone === '' || $address === '') {
        $error = 'Please fill in all fields.';
    } else {
        // Display order confirmation for now.
        // Database order saving can be added after checking your schema.
        $success = true;
        $_SESSION['last_order'] = [
            'customer_name' => $customer,
            'phone' => $phone,
            'address' => $address,
            'total' => $total,
            'items' => $cart
        ];
        unset($_SESSION['cart']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | Brew Haven</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f3ee;
            margin: 0;
            padding: 30px 15px;
            color: #34251e;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 16px #0001;
        }
        input, textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin: 8px 0 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background: #6f4e37;
            color: white;
            border: 0;
            border-radius: 6px;
            padding: 12px 20px;
            cursor: pointer;
        }
        .error { color: #b00020; }
        .success { color: #26733b; }
        a { color: #6f4e37; }
    </style>
</head>
<body>
<div class="container">
    <h1>☕ Brew Haven Checkout</h1>

    <?php if ($success): ?>
        <h2 class="success">Order placed successfully!</h2>
        <p>Thank you, <?= htmlspecialchars($customer) ?>.</p>
        <p>Your order total is:
            <strong>Rp <?= number_format($total, 0, ',', '.') ?></strong>
        </p>
        <p><a href="../index.php">Continue Shopping</a></p>

    <?php else: ?>
        <h2>Order Summary</h2>

        <?php foreach ($cart as $item): ?>
            <?php
            $name = $item['name'] ?? 'Coffee';
            $price = (float)($item['price'] ?? 0);
            $qty = max(1, (int)($item['quantity'] ?? 1));
            ?>
            <p>
                <?= htmlspecialchars($name) ?> × <?= $qty ?>
                — Rp <?= number_format($price * $qty, 0, ',', '.') ?>
            </p>
        <?php endforeach; ?>

        <h3>Total: Rp <?= number_format($total, 0, ',', '.') ?></h3>

        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <h2>Customer Details</h2>
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="customer_name" required>

            <label>Phone Number</label>
            <input type="tel" name="phone" required>

            <label>Delivery Address</label>
            <textarea name="address" rows="3" required></textarea>

            <button type="submit">Place Order</button>
        </form>

        <p><a href="cart.php">← Back to Cart</a></p>
    <?php endif; ?>
</div>
</body>
</html>