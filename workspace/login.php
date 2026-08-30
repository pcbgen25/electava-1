<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = '';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Generic validation — do not reveal whether login or password is wrong
    if ($login === '' || $password === '') {
        $error = 'Please enter your credentials.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, email, username, password_hash, full_name, role, domain_id, status, force_password_change
             FROM users WHERE (username = ? OR email = ?) LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        // Constant-time comparison even if user not found
        $dummyHash = '$2y$12$invalidhashfortimingprotectiononly.invalidhash';
        $hash = $user ? $user['password_hash'] : $dummyHash;
        $valid = password_verify($password, $hash);

        if (!$valid || !$user) {
            // Generic error — do not say which field is wrong
            $error = 'Invalid credentials. Please try again.';
            if ($user) logLogin($pdo, $user['id'], 'failed');
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is not active. Please contact your administrator.';
            logLogin($pdo, $user['id'], 'failed');
        } else {
            // Regenerate session ID on successful login (prevents session fixation)
            session_regenerate_id(true);

            $_SESSION['user_id']        = (int)$user['id'];
            $_SESSION['username']       = $user['username'];
            $_SESSION['email']          = $user['email'];
            $_SESSION['full_name']      = $user['full_name'];
            $_SESSION['role']           = $user['role'];
            $_SESSION['domain_id']      = $user['domain_id'];
            $_SESSION['allowed_domains'] = [];

            logLogin($pdo, $user['id'], 'success');
            logAudit($pdo, 'login', 'user', $user['id'], 'User logged in');

            // Enforce mandatory password change
            if (!empty($user['force_password_change'])) {
                header('Location: /change_password.php?required=1');
                exit;
            }

            header('Location: ' . getDashboardUrl($user['role']));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Electava Workspace</title>
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
        <div class="text-center mb-8 float-anim">
            <div class="w-20 h-20 mx-auto bg-gradient-to-br from-emerald-500/20 to-teal-600/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 shadow-lg shadow-emerald-500/10 mb-5">
                <i class="fa-solid fa-bolt text-emerald-400 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Electava <span class="text-emerald-400">Workspace</span></h1>
            <p class="text-sm text-slate-500 mt-2">Internal Management Platform</p>
        </div>

        <div class="glow-line w-48 mx-auto mb-8 opacity-50"></div>

        <!-- Login Card -->
        <div class="glass p-8 rounded-2xl shadow-2xl shadow-black/20">
            <?php if ($error): ?>
                <div class="bg-red-500/8 border border-red-500/20 text-red-400 p-3.5 rounded-xl mb-6 text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation shrink-0"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-5">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2 uppercase tracking-wider">Username or Email</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                        <input type="text" name="login" required autocomplete="username" placeholder="Username or email"
                            class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2 uppercase tracking-wider">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm"></i>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••••"
                            class="w-full bg-slate-900/60 border border-slate-700/50 rounded-xl pl-10 pr-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500/50 transition-all text-sm">
                    </div>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-lg shadow-emerald-600/20 hover:shadow-emerald-500/30 text-sm mt-2">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-[11px] text-slate-700 mt-4">&copy; <?= date('Y') ?> Electava &mdash; Internal Use Only</p>
    </div>
</body>
</html>
