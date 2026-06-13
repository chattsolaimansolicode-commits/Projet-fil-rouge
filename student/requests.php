<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Mes Demandes';
$activeNav = 'requests';
$user = currentUser();
$uid  = $user['id'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_request') {
    $type    = trim($_POST['type'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (!$type || !$message) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Tous les champs sont requis.</div>';
    } else {
        $pdo->prepare("INSERT INTO requests (student_id, type, message) VALUES (?,?,?)")->execute([$uid, $type, $message]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Demande envoyée !</div>';
    }
}

$requests = $pdo->prepare("SELECT r.*, u.name AS teacher_name FROM requests r LEFT JOIN users u ON r.teacher_id=u.id WHERE r.student_id=? ORDER BY r.created_at DESC");
$requests->execute([$uid]);
$requests = $requests->fetchAll();

$mobileNavLinks = [
  ['href' => APP_URL.'/student/dashboard.php', 'nav'=>'dashboard',  'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/student/projects.php',  'nav'=>'projects',   'icon'=>'📁', 'label'=>'Projet'],
  ['href' => APP_URL.'/student/requests.php',  'nav'=>'requests',   'icon'=>'📨', 'label'=>'Demandes'],
  ['href' => APP_URL.'/student/documents.php', 'nav'=>'documents',  'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/student/grades.php',    'nav'=>'grades',     'icon'=>'⭐', 'label'=>'Notes'],
];
include __DIR__ . '/../includes/layout_student.php';
?>

<?= $msg ?>
<div class="page-header">
  <div><h2>Mes Demandes</h2><p>Soumettez et suivez vos demandes auprès des enseignants</p></div>
  <button class="btn btn-primary" onclick="openModal('createRequestModal')">
    <i class="fa-solid fa-plus"></i> Nouvelle Demande
  </button>
</div>

<?php if (empty($requests)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">📨</div>
    <h3>Aucune demande</h3>
    <p>Soumettez votre première demande en cliquant sur le bouton ci-dessus.</p>
  </div>
</div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Type</th><th>Message</th><th>Statut</th><th>Réponse</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td style="font-weight:600"><?= e($r['type']) ?></td>
        <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-secondary)"><?= e($r['message']) ?></td>
        <td><?= statusBadge($r['status']) ?></td>
        <td style="color:var(--text-muted);font-size:.82rem"><?= $r['teacher_note'] ? e(mb_substr($r['teacher_note'],0,50)) : '—' ?></td>
        <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Create Request Modal -->
<div class="modal-overlay" id="createRequestModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary);margin-right:8px"></i>Nouvelle Demande</span>
      <button class="modal-close" onclick="closeModal('createRequestModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_request">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Type de demande <span class="required">*</span></label>
          <select name="type" class="form-control">
            <option value="">— Sélectionner —</option>
            <option>Changement de sujet</option>
            <option>Changement d'encadrant</option>
            <option>Extension de délai</option>
            <option>Validation de document</option>
            <option>Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" class="form-control" rows="4" placeholder="Décrivez votre demande..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createRequestModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
