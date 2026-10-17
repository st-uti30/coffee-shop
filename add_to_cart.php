
<?php
session_start();
require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, "product_id", FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

if (!$quantity || $quantity < 1) {
    $quantity = 1;
}

$quantity = min($quantity, 99);

$stmt = $conn->prepare(
    "SELECT id FROM products WHERE id = ? AND is_available = 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION["cart"]) || !is_array($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$current = (int)($_SESSION["cart"][$id] ?? 0);
$_SESSION["cart"][$id] = min($current + $quantity, 99);

header("Location: cart.php");
exit;
?>
