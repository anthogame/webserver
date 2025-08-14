<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$pending_2fa = false;
$temp_data = null;

if ($_POST) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $code_2fa = isset($_POST['code']) ? sanitize($_POST['code']) : null;
    
    // Données pour l'API
    $auth_data = [
        'email' => $email,
        'password' => $password
    ];
    
    // Ajouter le code 2FA si fourni
    if ($code_2fa) {
        $auth_data['code'] = $code_2fa;
    }
    
    // Appel à l'API AzAuth
    $response = authenticateWithAzAuth($auth_data);
    
    if ($response['success']) {
        $user_data = $response['data'];
        
        // Vérifier si l'utilisateur est banni
        if ($user_data['banned']) {
            $error = 'Votre compte a été suspendu.';
        } else {
            // Connexion réussie - stocker les données en session
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['uuid'] = $user_data['uuid'];
            $_SESSION['email_verified'] = $user_data['email_verified'];
            $_SESSION['money'] = $user_data['money'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['access_token'] = $user_data['access_token'];
            $_SESSION['created_at'] = $user_data['created_at'];
            
            redirect('index.php');
        }
    } else {
        if ($response['reason'] === '2fa') {
            $pending_2fa = true;
            $temp_data = ['email' => $email, 'password' => $password];
            $error = 'Veuillez saisir votre code d\'authentification à deux facteurs.';
        } else {
            $error = $response['message'] ?? 'Erreur de connexion';
        }
    }
}

function authenticateWithAzAuth($data) {
    $url = 'https://rushu.xyz/web/api/auth/authenticate';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        return [
            'success' => false,
            'reason' => 'network_error',
            'message' => 'Erreur de connexion au serveur'
        ];
    }
    
    curl_close($ch);
    
    $decoded_response = json_decode($response, true);
    
    if ($http_code === 200) {
        return [
            'success' => true,
            'data' => $decoded_response
        ];
    } else {
        return [
            'success' => false,
            'reason' => $decoded_response['reason'] ?? 'unknown_error',
            'message' => $decoded_response['message'] ?? 'Erreur inconnue'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-theme="dark">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-dragon"></i>
                </div>
                <h1><?= SITE_NAME ?></h1>
                <p>Connectez-vous à votre compte</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form" id="loginForm">
                <?php if (!$pending_2fa): ?>
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-user"></i>
                            Email ou nom d'utilisateur
                        </label>
                        <input 
                            type="text" 
                            id="email"
                            name="email" 
                            class="form-control" 
                            placeholder="Votre email ou nom d'utilisateur"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="username"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Mot de passe
                        </label>
                        <div class="password-input">
                            <input 
                                type="password" 
                                id="password"
                                name="password" 
                                class="form-control" 
                                placeholder="Votre mot de passe"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Champs cachés pour maintenir les données -->
                    <input type="hidden" name="email" value="<?= htmlspecialchars($temp_data['email']) ?>">
                    <input type="hidden" name="password" value="<?= htmlspecialchars($temp_data['password']) ?>">
                    
                    <div class="form-group">
                        <label for="code">
                            <i class="fas fa-mobile-alt"></i>
                            Code d'authentification à deux facteurs
                        </label>
                        <input 
                            type="text" 
                            id="code"
                            name="code" 
                            class="form-control text-center" 
                            placeholder="000000"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            autocomplete="one-time-code"
                            autofocus
                        >
                        <small class="form-text">Saisissez le code à 6 chiffres de votre application d'authentification</small>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span class="checkmark"></span>
                        Se souvenir de moi
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
                    <span class="btn-text">
                        <?= $pending_2fa ? 'Vérifier le code' : 'Se connecter' ?>
                    </span>
                    <span class="btn-spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
                
                <?php if ($pending_2fa): ?>
                    <button type="button" class="btn btn-secondary" onclick="location.reload()">
                        <i class="fas fa-arrow-left"></i>
                        Retour
                    </button>
                <?php endif; ?>
            </form>
            
            <div class="login-footer">
                <div class="links">
                    <a href="#" onclick="showForgotPassword()">
                        <i class="fas fa-question-circle"></i>
                        Mot de passe oublié ?
                    </a>
                    <a href="#" onclick="showRegister()">
                        <i class="fas fa-user-plus"></i>
                        Créer un compte
                    </a>
                </div>
                
                <div class="theme-toggle">
                    <button type="button" onclick="toggleTheme()" class="theme-btn">
                        <i class="fas fa-moon"></i>
                        <span>Thème sombre</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- CSS spécifique à la page de connexion -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        [data-theme="light"] body {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            background: var(--bg-secondary);
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }
        
        .login-header h1 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            background: var(--bg-accent);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            transform: translateY(-2px);
        }
        
        .password-input {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--accent-color);
        }
        
        .text-center {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            letter-spacing: 0.2rem;
        }
        
        .form-text {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            user-select: none;
        }
        
        .checkbox-label input[type="checkbox"] {
            display: none;
        }
        
        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 0.25rem;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
            content: '\2713';
            position: absolute;
            top: -2px;
            left: 3px;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            width: 100%;
            background: var(--bg-accent);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
            transform: translateY(-2px);
        }
        
        .login-footer {
            margin-top: 2rem;
            text-align: center;
        }
        
        .links {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .links a:hover {
            color: var(--accent-color);
            background: var(--bg-accent);
        }
        
        .theme-toggle {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        
        .theme-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 auto;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .theme-btn:hover {
            color: var(--accent-color);
            background: var(--bg-accent);
        }
        
        /* Animations */
        .login-card {
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-card {
                padding: 2rem 1.5rem;
            }
            
            .links {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
    
    <script>
        // Basculer la visibilité du mot de passe
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
        
        // Gestion du thème
        function toggleTheme() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const themeBtn = document.querySelector('.theme-btn');
            const icon = themeBtn.querySelector('i');
            const text = themeBtn.querySelector('span');
            
            if (newTheme === 'light') {
                icon.className = 'fas fa-sun';
                text.textContent = 'Thème clair';
            } else {
                icon.className = 'fas fa-moon';
                text.textContent = 'Thème sombre';
            }
        }
        
        // Charger le thème sauvegardé
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.body.setAttribute('data-theme', savedTheme);
            
            const themeBtn = document.querySelector('.theme-btn');
            const icon = themeBtn.querySelector('i');
            const text = themeBtn.querySelector('span');
            
            if (savedTheme === 'light') {
                icon.className = 'fas fa-sun';
                text.textContent = 'Thème clair';
            }
        });
        
        // Animation du bouton de connexion
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');
            
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';
            btn.disabled = true;
        });
        
        // Formatage automatique du code 2FA
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 6);
            });
        }
        
        // Fonctions pour les liens (à implémenter selon vos besoins)
        function showForgotPassword() {
            alert('Fonctionnalité de récupération de mot de passe à implémenter');
        }
        
        function showRegister() {
            alert('Fonctionnalité d\'inscription à implémenter');
        }
    </script>
</body>
</html>