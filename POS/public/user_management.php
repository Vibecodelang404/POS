<?php
require_once __DIR__ . '/../app/config.php';
requireLogin();
requireAdmin();

$page_title = "Users";

// Handle user actions
$message = '';
$message_type = '';
$csrf_token = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "Security validation failed. Please refresh the page and try again.";
        $message_type = "danger";
    } else {
        if ($action === 'create') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $status = $_POST['status'];
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $email, $hashed_password, $role, $first_name, $last_name, $status);
            
            if ($stmt->execute()) {
                $message = "User created successfully!";
                $message_type = "success";
            } else {
                $message = "Error creating user: " . $conn->error;
                $message_type = "danger";
            }
        }
        
        if ($action === 'update') {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $status = $_POST['status'];

            if ($user_id === (int) $_SESSION['user_id'] && ($role !== 'admin' || $status !== 'active')) {
                $message = "You cannot remove your own admin access or deactivate your current account.";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("UPDATE users SET email=?, role=?, first_name=?, last_name=?, status=? WHERE id=?");
                $stmt->bind_param("sssssi", $email, $role, $first_name, $last_name, $status, $user_id);
                
                if ($stmt->execute()) {
                    $message = "User updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating user: " . $conn->error;
                    $message_type = "danger";
                }
            }
        }
        
        if ($action === 'reset_password') {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'];
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $message = "Password reset successfully!";
                $message_type = "success";
            } else {
                $message = "Error resetting password: " . $conn->error;
                $message_type = "danger";
            }
        }
        
        if ($action === 'delete') {
            $user_id = (int) ($_POST['user_id'] ?? 0);

            if ($user_id === (int) $_SESSION['user_id']) {
                $message = "You cannot delete your current account.";
                $message_type = "warning";
            } else {
                // Check if user has orders
                $check_stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result()->fetch_assoc();
                
                if ($result['order_count'] > 0) {
                    $message = "Cannot delete user with existing orders. Set status to 'inactive' instead.";
                    $message_type = "warning";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                    $stmt->bind_param("i", $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "User deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting user: " . $conn->error;
                        $message_type = "danger";
                    }
                }
            }
        }
    }
}

// Fetch all users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
          (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'completed') as total_sales
          FROM users u WHERE 1=1";
$conditions = [];
$params = [];
$types = '';

if ($search) {
    $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $types .= 'ssss';
}

if ($role_filter) {
    $conditions[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($status_filter) {
    $conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$query .= $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
    SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM users")->fetch_assoc();

ob_start();
?>

<style>
.user-management-grid {
    row-gap: 1rem;
}
.user-stat-note {
    color: var(--muted);
    font-size: 0.86rem;
}
.filter-form-grid {
    row-gap: 1rem;
}
.user-name-cell {
    min-width: 220px;
}
.user-meta {
    color: var(--muted);
    font-size: 0.84rem;
}
.user-table {
    min-width: 1100px;
}
.role-badge-admin {
    background: linear-gradient(135deg, var(--accent), var(--accent-deep)) !important;
}
.section-note {
    color: var(--muted);
    font-size: 0.9rem;
}
</style>

<div class="panel-hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <div class="hero-kicker">Team Coverage</div>
            <h2 class="hero-title">Users</h2>
            <p class="hero-subtitle">Keep the right people active, accountable, and assigned to the work that supports daily business performance.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <button class="btn btn-dark btn-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </button>
        </div>
    </div>
    <div class="summary-strip mt-4">
        <div class="summary-pill">
            <div class="summary-pill-label">Total Accounts</div>
            <div class="summary-pill-value"><?php echo number_format($stats['total_users']); ?></div>
            <div class="metric-note">Current team capacity across admin, staff, and cashier roles</div>
        </div>
        <div class="summary-pill">
            <div class="summary-pill-label">Active Accounts</div>
            <div class="summary-pill-value"><?php echo number_format($stats['active_count']); ?></div>
            <div class="metric-note">People currently available to support operations</div>
        </div>
        <div class="summary-pill">
            <div class="summary-pill-label">Filtered Results</div>
            <div class="summary-pill-value"><?php echo number_format(count($users)); ?></div>
            <div class="metric-note">Accounts matching the team view you are reviewing</div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4 user-management-grid">
    <div class="col-xl-3 col-md-6">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Users</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                    <div class="user-stat-note mt-2">Overall staffing footprint in the system</div>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Admins</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['admin_count']); ?></h3>
                    <div class="user-stat-note mt-2">Decision-makers with full business visibility</div>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Staff</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['staff_count']); ?></h3>
                    <div class="user-stat-note mt-2">Back-office support for stock and store operations</div>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Active Users</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['active_count']); ?></h3>
                    <div class="user-stat-note mt-2">Allowed to access the system</div>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter and Add User Section -->
<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter & Search</h5>
        <span class="section-note"><?php echo count($users); ?> users match the current filters</span>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row filter-form-grid">
            <div class="col-lg-4 col-md-6">
                <input type="text" class="form-control" name="search" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <select class="form-select" name="role_filter">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo $role_filter == 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="cashier" <?php echo $role_filter == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <select class="form-select" name="status_filter">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-danger btn-custom">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <a href="user_management.php" class="btn btn-outline-secondary btn-custom">
                        <i class="fas fa-rotate-left me-2"></i>Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="content-card">
    <div class="card-header">
        <div class="filter-toolbar">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users (<?php echo count($users); ?>)</h5>
            <div class="filter-summary">Use edit for profile changes, key for password reset, and delete only for unused accounts.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered align-middle mb-0 data-table user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="d-none d-lg-table-cell">Orders</th>
                        <th class="d-none d-xl-table-cell">Total Sales</th>
                        <th class="d-none d-lg-table-cell">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10" class="empty-state">No users found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td class="user-name-cell">
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    <div class="user-meta">Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $user['role'] == 'admin' ? 'role-badge-admin' : 
                                            ($user['role'] == 'staff' ? 'bg-primary' : 'bg-info'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell"><?php echo number_format($user['total_orders']); ?></td>
                                <td class="d-none d-xl-table-cell"><?php echo formatCurrency($user['total_sales'] ?? 0); ?></td>
                                <td class="d-none d-lg-table-cell"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="table-actions" role="group" aria-label="User actions">
                                        <button class="btn btn-sm btn-outline-primary" title="Edit user" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" title="Reset password" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Delete user" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrfInput(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <?php echo csrfInput(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="cashier">Cashier</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <?php echo csrfInput(); ?>
                    
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = <?php echo json_encode($csrf_token); ?>;

function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function deleteUser(userId, username) {
    Swal.fire({
        title: 'Delete User?',
        text: `Are you sure you want to delete user "${username}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Add User Form Confirmation
document.addEventListener('DOMContentLoaded', function() {
    const addUserModal = document.getElementById('addUserModal');
    if (addUserModal) {
        const addForm = addUserModal.querySelector('form');
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Create User?',
                text: 'Are you sure you want to create this new user account?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Create',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Edit User Form Confirmation
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        const editForm = editUserModal.querySelector('form');
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Update User?',
                text: 'Are you sure you want to update this user\'s information?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Update',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }

    // Reset Password Form Confirmation
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    if (resetPasswordModal) {
        const resetForm = resetPasswordModal.querySelector('form');
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('reset_username').textContent;
            Swal.fire({
                title: 'Reset Password?',
                text: `Are you sure you want to reset the password for "${username}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Reset',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../app/views/layout.php';
?>


