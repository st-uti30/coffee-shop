
<?php
session_start();
require_once __DIR__ . "/../db.php";

// Only admins can access this page
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    exit("Access denied. Login as admin first.");
}

$message = "";

// Update order status
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $newStatus = $_POST["status"] ?? "";

    $allowedStatuses = [
        "Pending",
        "Preparing",
        "Completed",
        "Cancelled"
    ];

    if (
        $orderId &&
        in_array($newStatus, $allowedStatuses, true)
    ) {
        $stmt = $conn->prepare(
            "UPDATE orders SET status = ? WHERE id = ?"
        );
        $stmt->bind_param("si", $newStatus, $orderId);

        if ($stmt->execute()) {
            $message = "Order #" . $orderId . " status updated successfully!";
        } else {
            $message = "Unable to update order status.";
        }

        $stmt->close();
    } else {
        $message = "Invalid order or status.";
    }
}

// Get all orders
$sql = "SELECT id, user_id, subtotal, discount, total,
               status, created_at
        FROM orders
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load orders.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Orders | Brew Haven</title>
    <link rel="stylesheet" href="../style.css">

    <style>
        body {
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
        }

        .order {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px #ddd;
        }

        .price {
            color: #603d2a;
            font-weight: bold;
        }

        .message {
            padding: 12px;
            background: #e9f7ec;
            border-radius: 6px;
            margin: 15px 0;
        }

        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .status-form select,
        .status-form button {
            padding: 10px;
            border-radius: 5px;
        }

        .status-form button {
            background: #603d2a;
            color: white;
            border: none;
            cursor: pointer;
        }

        a {
            color: #603d2a;
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Customer Orders</h1>

    <p><a href="index.php">← Back to Admin Dashboard</a></p>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <p>No orders yet. Customer orders will appear here.</p>
    <?php endif; ?>

    <?php while ($order = $result->fetch_assoc()): ?>

        <div class="order">

            <h2>Order #<?php echo (int)$order["id"]; ?></h2>

            <p>
                <strong>Customer ID:</strong>
                <?php
                echo $order["user_id"] === null
                    ? "Guest Customer"
                    : (int)$order["user_id"];
                ?>
            </p>

            <p>
                <strong>Date:</strong>
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

            <p class="price">
                <strong>Total:</strong>
                Rp <?php echo number_format((float)$order["total"], 0, ",", "."); ?>
            </p>

            <p>
                <strong>Current Status:</strong>
                <?php echo htmlspecialchars($order["status"]); ?>
            </p>

            <form method="POST" action="orders.php" class="status-form">

                <input
                    type="hidden"
                    name="order_id"
                    value="<?php echo (int)$order["id"]; ?>"
                >

                <label for="status-<?php echo (int)$order["id"]; ?>">
                    Update status:
                </label>

                <select
                    name="status"
                    id="status-<?php echo (int)$order["id"]; ?>"
                    required
                >
                    <?php
                    $statuses = [
                        "Pending",
                        "Preparing",
                        "Completed",
                        "Cancelled"
                    ];
                    ?>

                    <?php foreach ($statuses as $status): ?>
                        <option
                            value="<?php echo $status; ?>"
                            <?php
                            echo strcasecmp($order["status"], $status) === 0
                                ? "selected"
                                : "";
                            ?>
                        >
                            <?php echo $status; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Update Status</button>

            </form>

        </div>

    <?php endwhile; ?>

</div>
</body>
</html>
