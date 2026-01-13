<?php
session_start();
require_once '../config/database.php';
require_once '../config/currency.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total_products FROM products");
$total_products = $stmt->fetch()['total_products'];

$stmt = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
$total_categories = $stmt->fetch()['total_categories'];

$stmt = $conn->query("SELECT COUNT(*) as total_orders FROM orders");
$total_orders = $stmt->fetch()['total_orders'];

$stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

// Payment settings page is informational; supported methods are COD + PayPal

$title = 'Admin Dashboard - Motoshapi';
$activeAdminPage = 'dashboard';
$mainClass = 'flex-grow-1 py-4 container-xxl';
include 'includes/header.php';
?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <?php if (isset($success)): ?>
                    <div class="col-12">
                        <div class="alert alert-success mb-0"><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="col-12">
                        <div class="alert alert-danger mb-0"><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                <div class="col-12">
                    <h2 class="mb-0">Dashboard</h2>
                    <p class="text-muted">Quick overview of sales performance and system activity.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card text-white bg-primary h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title">Total Products</h5>
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <p class="display-5 fw-bold mb-2"><?php echo $total_products; ?></p>
                    <a href="products.php" class="link-light text-decoration-none">View products <i class="bi bi-arrow-right-short"></i></a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card text-white bg-success h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title">Total Categories</h5>
                        <i class="bi bi-tags fs-4"></i>
                    </div>
                    <p class="display-5 fw-bold mb-2"><?php echo $total_categories; ?></p>
                    <a href="categories.php" class="link-light text-decoration-none">View categories <i class="bi bi-arrow-right-short"></i></a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card text-white bg-warning h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title">Total Orders</h5>
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <p class="display-5 fw-bold mb-2"><?php echo $total_orders; ?></p>
                    <a href="orders.php" class="link-light text-decoration-none">View orders <i class="bi bi-arrow-right-short"></i></a>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card text-white bg-info h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title">Total Users</h5>
                        <i class="bi bi-people fs-4"></i>
                    </div>
                    <p class="display-5 fw-bold mb-2"><?php echo $total_users; ?></p>
                    <a href="users.php" class="link-light text-decoration-none">View users <i class="bi bi-arrow-right-short"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
                                while($order = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td><?php echo format_price($order['total_amount']); ?></td>
                                    <td><span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning text-dark' : 'success'; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $order['transaction_type'] === 'online' ? 'primary' : 'info text-dark'; ?>"><?php echo ucfirst($order['transaction_type']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Low Stock Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5");
                                while($product = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><span class="badge bg-danger"><?php echo $product['stock']; ?></span></td>
                                    <td><a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Update Stock</a></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>