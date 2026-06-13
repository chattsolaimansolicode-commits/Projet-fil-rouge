<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Tableau de bord';
$activeNav = 'dashboard';
$user = currentUser();
$tid  = $user['id'];

// Filters
$filterYear     = $_GET['year']     ?? '';
$filterFiliere  = $_GET['filiere']  ?? '';

// Stats
$projCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn();
$reqCount  = $pdo->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
$docCount  = $pdo->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn();
$defCount  = $pdo->query("SELECT COUNT(*) FROM defenses WHERE status='scheduled'")->fetchColumn();

// Recent requests (filtered)
$reqWhere = "WHERE r.status = 'pending'";
$reqParams = [];
if ($filterYear)    { $reqWhere .= " AND u.academic_year = ?"; $reqParams[] = $filterYear; }
if ($filterFiliere) { $reqWhere .= " AND u.filiere = ?"; $reqParams[] = $filterFiliere; }

$reqs = $pdo->prepare("SELECT r.*, u.name AS student_name, u.filiere, u.academic_year FROM requests r JOIN users u ON r.student_id=u.id $reqWhere ORDER BY r.created_at DESC LIMIT 10");
$reqs->execute($reqParams);
$reqs = $reqs->fetchAll();

// Filter options
$years = $pdo->query("SELECT DISTINCT academic_year FROM users WHERE academic_year IS NOT NULL ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$filieres = $pdo->query("SELECT DISTINCT filiere FROM users WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

$mobileNavLinks = [
  ['href' => APP_URL.'/teacher/dashboard.php',  'nav'=>'dashboard',  'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/teacher/projects.php',   'nav'=>'projects',   'icon'=>'📁', 'label'=>'Projets'],
  ['href' => APP_URL.'/teacher/requests.php',   'nav'=>'requests',   'icon'=>'📨', 'label'=>'Demandes'],
  ['href' => APP_URL.'/teacher/documents.php',  'nav'=>'documents',  'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/teacher/defense.php',    'nav'=>'defense',    'icon'=>'📅', 'label'=>'Soutenances'],
];
include __DIR__ . '/../includes/layout_teacher.php';
?>

<!-- Greeting -->
<div class="greeting-section">
  <div class="greeting-text">
    <h1>Bonjour, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p><?= date('l d F Y') ?> · Espace Enseignant</p>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-header"><span class="stat-label">Projets Actifs</span><div class="stat-icon blue"><i class="fa-solid fa-diagram-project"></i></div></div>
    <div class="stat-value"><?= (int)$projCount ?></div><div class="stat-sub">En cours</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header"><span class="stat-label">Demandes</span><div class="stat-icon orange"><i class="fa-solid fa-inbox"></i></div></div>
    <div class="stat-value"><?= (int)$reqCount ?></div><div class="stat-sub">En attente</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header"><span class="stat-label">Documents</span><div class="stat-icon purple"><i class="fa-solid fa-file-lines"></i></div></div>
    <div class="stat-value"><?= (int)$docCount ?></div><div class="stat-sub">À valider</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header"><span class="stat-label">Soutenances</span><div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div></div>
    <div class="stat-value"><?= (int)$defCount ?></div><div class="stat-sub">Planifiées</div>
  </div>
</div>

<!-- Filters (Teacher only) -->
<div class="card mb-16" style="margin-bottom:24px">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-filter" style="color:var(--primary);margin-right:8px"></i>Filtres</span></div>
  <div class="card-body" style="padding:16px 22px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <select name="year" class="filter-select" onchange="this.form.submit()">
        <option value="">Toutes les années</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= e($y) ?>" <?= $filterYear===$y?'selected':'' ?>><?= e($y) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="filiere" class="filter-select" onchange="this.form.submit()">
        <option value="">Toutes les filières</option>
        <?php foreach ($filieres as $f): ?>
          <option value="<?= e($f) ?>" <?= $filterFiliere===$f?'selected':'' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($filterYear || $filterFiliere): ?>
        <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Réinitialiser</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Pending Requests Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-inbox" style="color:var(--primary);margin-right:8px"></i>Demandes en Attente</span>
    <a href="requests.php" class="btn btn-secondary btn-sm">Tout voir</a>
  </div>
  <?php if (empty($reqs)): ?>
  <div class="card-body">
    <div class="empty-state" style="padding:28px">
      <div class="empty-icon" style="font-size:32px">✅</div>
      <h3>Aucune demande en attente</h3>
    </div>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Étudiant</th><th>Filière</th><th>Type</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($reqs as $r): ?>
      <tr>
        <td style="font-weight:600"><?= e($r['student_name']) ?></td>
        <td><span class="badge badge-active"><?= e($r['filiere'] ?? '—') ?></span></td>
        <td><?= e($r['type']) ?></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary)"><?= e($r['message']) ?></td>
        <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <form method="POST" action="requests.php" style="display:inline">
              <input type="hidden" name="action" value="approve"><input type="hidden" name="req_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i></button>
            </form>
            <form method="POST" action="requests.php" style="display:inline">
              <input type="hidden" name="action" value="reject"><input type="hidden" name="req_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-xmark"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
