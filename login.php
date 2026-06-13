<?php
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/' . $_SESSION['role'] . '/dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'student';
    $remember = isset($_POST['remember']);

    if (!$email || !$password) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            if ($remember) {
                ini_set('session.cookie_lifetime', 604800);
                session_regenerate_id(true);
            }
            redirect(APP_URL . '/' . $user['role'] . '/dashboard.php');
        } else {
            $error = 'Email, mot de passe ou rôle incorrect.';
        }
    }
}

// Stats for display
$studentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$teacherCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$projectCount = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — PFE Manager</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="login-page">
  <!-- Left Panel -->
  <div class="login-left">
    <div class="login-brand">
      <div class="brand-logo">
        <div class="icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
          <div class="brand-name">PFE Manager</div>
          <div class="brand-tagline">Université · Système de gestion des PFE</div>
        </div>
      </div>
      <div class="login-headline">
        <h2>Gérez vos projets de fin d'études avec efficacité</h2>
        <p>Une plateforme moderne qui connecte étudiants et enseignants pour un suivi transparent et efficace des projets de fin d'études.</p>
      </div>
    </div>

    <div class="login-stats">
      <div class="login-stat-card">
        <div class="ls-value"><?= (int)$studentCount ?></div>
        <div class="ls-label"><i class="fa-solid fa-users" style="margin-right:5px"></i>Étudiants</div>
      </div>
      <div class="login-stat-card">
        <div class="ls-value"><?= (int)$projectCount ?></div>
        <div class="ls-label"><i class="fa-solid fa-diagram-project" style="margin-right:5px"></i>Projets</div>
      </div>
      <div class="login-stat-card">
        <div class="ls-value"><?= (int)$teacherCount ?></div>
        <div class="ls-label"><i class="fa-solid fa-chalkboard-user" style="margin-right:5px"></i>Enseignants</div>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="login-right">
    <div class="login-form-header">
      <h3>Bienvenue 👋</h3>
      <p>Connectez-vous à votre espace personnel</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" data-autohide>
        <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
      <div class="alert alert-danger" data-autohide>
        <i class="fa-solid fa-lock"></i> Accès non autorisé.
      </div>
    <?php endif; ?>

    <form method="POST">
      <!-- Role Selector -->
      <div class="form-group">
        <label class="form-label">Je suis</label>
        <div class="role-selector">
          <div class="role-option">
            <input type="radio" name="role" id="role_student" value="student"
              <?= (!isset($_POST['role']) || $_POST['role']==='student') ? 'checked' : '' ?>>
            <label for="role_student" class="role-label">
              <span class="r-icon">🎓</span>
              <span class="r-text">Étudiant</span>
            </label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="role_teacher" value="teacher"
              <?= (isset($_POST['role']) && $_POST['role']==='teacher') ? 'checked' : '' ?>>
            <label for="role_teacher" class="role-label">
              <span class="r-icon">👨‍🏫</span>
              <span class="r-text">Enseignant</span>
            </label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Adresse email <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
          placeholder="votre@email.com" value="<?= e($email) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mot de passe <span class="required">*</span></label>
        <input type="password" id="password" name="password" class="form-control"
          placeholder="••••••••" required>
      </div>

      <div class="form-check">
        <input type="checkbox" id="remember" name="remember">
        <label for="remember">Se souvenir de moi</label>
      </div>

      <button type="submit" class="btn btn-primary btn-login">
        <i class="fa-solid fa-right-to-bracket"></i> Se connecter
      </button>
    </form>

    <p style="margin-top:24px;font-size:.78rem;color:var(--text-muted);text-align:center">
      Compte démo étudiant : <strong>solayman@student.ma</strong> / <strong>password</strong><br>
      Compte démo enseignant : <strong>hamid@teacher.ma</strong> / <strong>password</strong>
    </p>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
