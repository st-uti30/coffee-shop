
<?php
session_start();
require_once __DIR__ . "/../db.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    http_response_code(403);
    exit("Access denied.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid product ID.");
}

$stmt = $conn->prepare(
    "SELECT name, description, price, is_available
     FROM products WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    exit("Product not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {
        exit("Invalid form token. Please go back and refresh.");
    }

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priceInput = trim($_POST["price"] ?? "");
    $available = isset($_POST["is_available"]) ? 1 : 0;

    if (
        $name === "" ||
        strlen($name) > 150 ||
        strlen($description) > 5000 ||
        !preg_match('/\A[0-9]{1,8}(\.[0-9]{1,2})?\z/', $priceInput) ||
        (float)$priceInput <= 0
    ) {
        $error = "Enter a valid name, description and price.";
    } else {
        $price = (float)$priceInput;

        $stmt = $conn->prepare(
            "UPDATE products
             SET name = ?, description = ?, price = ?, is_available = ?
             WHERE id = ?"
        );
        $stmt->bind_param(
            "ssdii",
            $name,
            $description,
            $price,
            $available,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: index.php?updated=1");
            exit;
        } else {
            $error = "Could not update product.";
            $stmt->close();
        }
    }

    $product["name"] = $name;
    $product["description"] = $description;
    $product["price"] = $priceInput;
    $product["is_available"] = $available;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Coffee | Brew Haven</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        main { max-width: 650px; margin: 40px auto; padding: 20px; }
        form { display: grid; gap: 12px; background: white; padding: 25px; border-radius: 10px; }
        input, textarea { width: 100%; padding: 12px; box-sizing: border-box; }
        button { padding: 12px; background: #603d2a; color: white; border: 0; cursor: pointer; }
        .error { color: #a12622; }
    </style>
</head>
<body>
<main>
    <h1>Edit Coffee</h1>

    <?php if ($error !== ""): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token"
               value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

        <label>Coffee Name</label>
        <input name="name" maxlength="150" required
               value="<?php echo htmlspecialchars($product["name"]); ?>">

        <label>Description</label>
        <textarea name="description" maxlength="5000"><?php
            echo htmlspecialchars($product["description"] ?? "");
        ?></textarea>

        <label>Price (Rp)</label>
        <input type="number" name="price" min="0.01"
               max="99999999.99" step="0.01" required
               value="<?php echo htmlspecialchars((string)$product["price"]); ?>">

        <label>
            <input type="checkbox" name="is_available" value="1"
                <?php echo $product["is_available"] ? "checked" : ""; ?>
                style="width:auto">
            Available for customers
        </label>

        <button type="submit">Save Changes</button>
        <a href="index.php">Back to Dashboard</a>
    </form>
</main>
</body>
</html>
