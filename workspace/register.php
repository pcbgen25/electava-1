<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header("Location: " . getDashboardUrl($_SESSION['role']));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    // Public registration only allows vendor accounts — employees provisioned by core admin only
    $role = 'vendor';
    $status = 'inactive'; // Requires admin approval

    // Validate
    if (empty($fullName) || empty($email) || empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Check for existing email/username
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
        if ($check->fetchColumn() > 0) {
            $error = 'An account with this email or username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, full_name, role, status, force_password_change) VALUES (?,?,?,?,?,?,0)");
            $stmt->execute([$email, $username, $hash, $fullName, $role, $status]);
            $newUserId = $pdo->lastInsertId();

            // If vendor, create vendor profile
            if ($role === 'vendor') {
                $pdo->prepare("INSERT INTO vendors (user_id, company_name, contact_person, is_approved) VALUES (?, ?, ?, 0)")->execute([$newUserId, $fullName . "'s Company", $fullName]);
            }

            logAudit($pdo, 'register', 'user', $newUserId, "New $role registration: $email");
            $success = 'Account created successfully! You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Electava Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass { background: rgba(15,23,42,0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.06); }
        .glow-line { background: linear-gradient(90deg, transparent, #10b981, transparent); height: 1px; }
        @keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-12px) rotate(2deg); } }
        .float-anim { animation: float 6s ease-in-out infinite; }
        @keyframes pulse-ring { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(0.95); opacity: 0.5; } }
        .pulse-ring { animation: pulse-ring 3s ease infinite; }
    </style>
</head>
<body class="min-h-screen bg-[#060a13] flex items-center justify-center p-4">
    <!-- Background effects -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-1/4 -left-32 w-96 h-96 bg-emerald-500/5 rounded-full blur-3xl pulse-ring"></div>
        <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-teal-500/5 rounded-full blur-3xl pulse-ring" style="animation-delay:1.5s"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-emerald-900/5 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-6 float-anim">
            <div class="w-16 h-16 mx-auto bg-gradient-to-br from-emerald-500/20 to-teal-600/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 shadow-lg shadow-emerald-500/10 mb-4">
                <i class="fa-solid fa-bolt text-emerald-400 text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Create Account</h1>
            <p class="text-sm text-slate-500 mt-1">Join the Electava Workspace platform</p>
        </div>

        <div class="glow-line w-48 mx-auto mb-6 opacity-50"></div>

        <!-- Register Card -->
        <div class="glass p-7 rounded-2xl shadow-2xl shadow-black/20">
            <?php if ($error): ?>
                <div class="bg-red-500/8 border border-red-500/20 text-red-400 p-3 rounded-xl mb-5 text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-xl mb-5 text-sm flex items-center gap-3">
                    <i class="fa-solid fa-check-circle shrink-0"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="John Doe"
                            class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Email</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@company.com"
                            class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-at absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                        <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="johndoe"
                            class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Account Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="role" value="vendor" checked class="peer sr-only">
                            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-3 text-center peer-checked:border-emerald-500/50 peer-checked:bg-emerald-500/5 transition-all">
                                <i class="fa-solid fa-handshake text-slate-500 peer-checked:text-emerald-400 text-lg mb-1 block"></i>
                                <span class="text-xs text-slate-400 font-medium">Vendor</span>
                                <span class="block text-[10px] text-slate-600 mt-0.5">Sell components</span>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="role" value="employee" class="peer sr-only">
                            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-3 text-center peer-checked:border-emerald-500/50 peer-checked:bg-emerald-500/5 transition-all">
                                <i class="fa-solid fa-user-tie text-slate-500 peer-checked:text-emerald-400 text-lg mb-1 block"></i>
                                <span class="text-xs text-slate-400 font-medium">Employee</span>
                                <span class="block text-[10px] text-slate-600 mt-0.5">Join team</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Password</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                            <input type="password" name="password" required placeholder="••••••••" minlength="8"
                                class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5 uppercase tracking-wider">Confirm</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                            <input type="password" name="confirm_password" required placeholder="••••••••"
                                class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                        </div>
                    </div>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold py-2.5 px-4 rounded-xl transition-all shadow-lg shadow-emerald-600/20 hover:shadow-emerald-500/30 text-sm mt-1">
                    <i class="fa-solid fa-user-plus mr-2"></i>Create Account
                </button>
            </form>
        </div>

        <div class="text-center mt-5">
            <p class="text-sm text-slate-500">Already have an account? <a href="login.php" class="text-emerald-400 hover:text-emerald-300 font-medium transition">Sign In</a></p>
        </div>

        <p class="text-center text-[11px] text-slate-700 mt-4">&copy; <?= date('Y') ?> Electava &mdash; Internal Use Only</p>
    </div>
</body>
</html>
