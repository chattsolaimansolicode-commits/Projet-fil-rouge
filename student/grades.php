<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Mes Notes';
$activeNav = 'grades';
$user = currentUser();
$uid  = $user['id'];

$grade = $pdo->prepare("SELECT g.*, u.name AS teacher_name, p.title AS project_title FROM grades g LEFT JOIN users u ON g.teacher_id=u.id LEFT JOIN projects p ON g.project_id=p.id WHERE g.student_id=? LIMIT 1");
$grade->execute([$uid]);
$grade = $grade->fetch();

// Fetch task notes for the student's project
$taskNotes = [];
$tn = $pdo->prepare("SELECT t.title, t.note, t.validated_at, u.name AS validator_name FROM tasks t LEFT JOIN users u ON t.validated_by = u.id JOIN projects p ON t.project_id = p.id WHERE p.student_id = ? AND t.note IS NOT NULL ORDER BY t.sort_order");
$tn->execute([$uid]);
$taskNotes = $tn->fetchAll();

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
  <div><h2>Mes Notes</h2><p>Résultats de votre projet de fin d'études</p></div>
</div>

<?php if (!$grade): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">⭐</div>
    <h3>Notes non disponibles</h3>
    <p>Vos notes seront publiées après la soutenance.</p>
  </div>
</div></div>
<?php else: ?>

<div class="grade-overview">
  <div class="grade-big">
    <div class="g-label">Note Finale</div>
    <div class="g-value"><?= number_format($grade['final_grade'], 2) ?></div>
    <div class="g-sub">/ 20 · <?= e($grade['project_title'] ?? '') ?></div>
  </div>
  <div class="grade-mention">
    <div class="mention-badge"><?= e($grade['mention'] ?? 'Bien') ?></div>
    <div style="font-size:.82rem;color:var(--text-secondary)">Mention</div>
    <?php if ($grade['teacher_name']): ?>
    <div style="font-size:.78rem;color:var(--text-muted);margin-top:8px">Évalué par <?= e($grade['teacher_name']) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Détail des Notes</span></div>
  <div class="table-wrapper">
    <table class="grade-detail-table">
      <thead><tr><th>Critère</th><th>Coefficient</th><th>Note</th></tr></thead>
      <tbody>
        <tr><td>Rapport écrit</td><td>40%</td><td><?= $grade['report_grade'] !== null ? number_format($grade['report_grade'],2).'/20' : '—' ?></td></tr>
        <tr><td>Présentation orale</td><td>25%</td><td><?= $grade['presentation_grade'] !== null ? number_format($grade['presentation_grade'],2).'/20' : '—' ?></td></tr>
        <tr><td>Réalisation technique</td><td>25%</td><td><?= $grade['technical_grade'] !== null ? number_format($grade['technical_grade'],2).'/20' : '—' ?></td></tr>
        <tr><td>Jury</td><td>10%</td><td><?= $grade['jury_grade'] !== null ? number_format($grade['jury_grade'],2).'/20' : '—' ?></td></tr>
        <tr style="background:var(--primary-light)"><td><strong>Note Finale</strong></td><td><strong>100%</strong></td><td style="color:var(--primary-dark)"><strong><?= number_format($grade['final_grade'],2) ?>/20</strong></td></tr>
      </tbody>
    </table>
  </div>
  <?php if ($grade['comments']): ?>
  <div class="card-body" style="border-top:1px solid var(--border)">
    <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:8px">Commentaires</div>
    <p style="font-size:.88rem;color:var(--text-primary);line-height:1.7"><?= nl2br(e($grade['comments'])) ?></p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Task Notes Section -->
<?php if (!empty($taskNotes)): ?>
<div class="card" style="margin-top:24px">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px"></i>Notes des Tâches</span></div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Tâche</th><th>Note</th><th>Validé par</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($taskNotes as $tn): ?>
        <tr>
          <td style="font-weight:500"><?= e($tn['title']) ?></td>
          <td style="font-weight:600;color:var(--primary-dark)"><?= number_format($tn['note'], 2) ?>/20</td>
          <td><?= e($tn['validator_name'] ?? '—') ?></td>
          <td style="color:var(--text-muted)"><?= $tn['validated_at'] ? date('d/m/Y', strtotime($tn['validated_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-top:24px">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px"></i>Notes des Tâches</span></div>
  <div style="color:var(--text-muted);font-style:italic;padding:16px">
    Aucune note de tâche disponible
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
