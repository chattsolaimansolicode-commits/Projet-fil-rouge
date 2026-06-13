<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Ma Soutenance';
$activeNav = 'defense';
$user = currentUser();
$uid  = $user['id'];

$defense = $pdo->prepare("SELECT d.*, p.title AS project_title FROM defenses d LEFT JOIN projects p ON d.project_id=p.id WHERE d.student_id=? ORDER BY d.defense_date DESC LIMIT 1");
$defense->execute([$uid]);
$defense = $defense->fetch();

$mobileNavLinks = [
  ['href' => APP_URL.'/student/dashboard.php', 'nav'=>'dashboard', 'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/student/projects.php',  'nav'=>'projects',  'icon'=>'📁', 'label'=>'Projet'],
  ['href' => APP_URL.'/student/documents.php', 'nav'=>'documents', 'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/student/grades.php',    'nav'=>'grades',    'icon'=>'⭐', 'label'=>'Notes'],
  ['href' => APP_URL.'/student/defense.php',   'nav'=>'defense',   'icon'=>'📅', 'label'=>'Soutenance'],
];
include __DIR__ . '/../includes/layout_student.php';
?>

<div class="page-header">
  <div><h2>Ma Soutenance</h2><p>Informations sur votre date de soutenance</p></div>
</div>

<?php if (!$defense): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">📅</div>
    <h3>Soutenance non planifiée</h3>
    <p>Votre date de soutenance sera communiquée par votre enseignant.</p>
  </div>
</div></div>
<?php else: ?>
<div class="defense-card">
  <div class="d-header">
    <div class="d-icon">🎓</div>
    <div>
      <div class="d-title"><?= e($defense['project_title'] ?? 'Soutenance PFE') ?></div>
      <div class="d-sub"><?= statusBadge($defense['status']) ?></div>
    </div>
  </div>
  <div class="defense-grid">
    <div class="defense-info-item">
      <div class="d-label"><i class="fa-regular fa-calendar" style="margin-right:5px"></i>Date</div>
      <div class="d-value"><?= date('d/m/Y', strtotime($defense['defense_date'])) ?></div>
    </div>
    <div class="defense-info-item">
      <div class="d-label"><i class="fa-regular fa-clock" style="margin-right:5px"></i>Heure</div>
      <div class="d-value"><?= date('H:i', strtotime($defense['defense_time'])) ?></div>
    </div>
    <div class="defense-info-item">
      <div class="d-label"><i class="fa-solid fa-door-open" style="margin-right:5px"></i>Salle</div>
      <div class="d-value"><?= e($defense['room'] ?? '—') ?></div>
    </div>
    <?php if ($defense['filiere']): ?>
    <div class="defense-info-item">
      <div class="d-label"><i class="fa-solid fa-building-columns" style="margin-right:5px"></i>Filière</div>
      <div class="d-value"><?= e($defense['filiere']) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($defense['jury_members']): ?>
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid #BFDBFE">
    <div class="d-label" style="margin-bottom:10px"><i class="fa-solid fa-users" style="margin-right:5px"></i>Membres du Jury</div>
    <?php foreach (explode(',', $defense['jury_members']) as $m): ?>
      <span style="display:inline-block;background:white;border:1px solid #BFDBFE;border-radius:20px;padding:4px 12px;font-size:.82rem;margin:3px"><?= e(trim($m)) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($defense['notes']): ?>
  <div style="margin-top:16px;background:white;border-radius:var(--radius-sm);padding:14px;border:1px solid #BFDBFE">
    <div class="d-label" style="margin-bottom:6px">Notes</div>
    <p style="font-size:.85rem;color:var(--text-primary)"><?= nl2br(e($defense['notes'])) ?></p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
