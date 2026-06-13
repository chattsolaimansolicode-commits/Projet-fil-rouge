<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Validation des Documents';
$activeNav = 'documents';
$user = currentUser();
$tid  = $user['id'];

$msg = '';

// Handle feedback save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_feedback') {
    $docId    = (int)($_POST['doc_id'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    $status   = $_POST['doc_status'] ?? '';
    $valid    = ['pending','approved','rejected'];

    if ($docId) {
        $pdo->prepare("UPDATE documents SET feedback=?, feedback_by=?, feedback_at=NOW(), status=COALESCE(NULLIF(?,''),status) WHERE id=?")
            ->execute([$feedback ?: null, $tid, in_array($status, $valid) ? $status : '', $docId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Feedback enregistré !</div>';
    }
}

// Filters
$fStatus  = $_GET['status']  ?? '';
$fStudent = $_GET['student'] ?? '';
$search   = trim($_GET['q']  ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($fStatus)  { $where .= " AND d.status=?"; $params[] = $fStatus; }
if ($fStudent) { $where .= " AND d.student_id=?"; $params[] = $fStudent; }
if ($search)   { $where .= " AND (d.title LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$docs = $pdo->prepare("SELECT d.*, u.name AS student_name, p.title AS project_title, fb.name AS feedback_author FROM documents d JOIN users u ON d.student_id=u.id LEFT JOIN projects p ON d.project_id=p.id LEFT JOIN users fb ON d.feedback_by=fb.id $where ORDER BY d.uploaded_at DESC");
$docs->execute($params);
$docs = $docs->fetchAll();

$students = $pdo->query("SELECT DISTINCT u.id, u.name FROM users u JOIN documents d ON d.student_id=u.id ORDER BY u.name")->fetchAll();

$fileIcons = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊','zip'=>'📦','png'=>'🖼️','jpg'=>'🖼️','jpeg'=>'🖼️'];

$mobileNavLinks = [
  ['href' => APP_URL.'/teacher/dashboard.php', 'nav'=>'dashboard', 'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/teacher/projects.php',  'nav'=>'projects',  'icon'=>'📁', 'label'=>'Projets'],
  ['href' => APP_URL.'/teacher/requests.php',  'nav'=>'requests',  'icon'=>'📨', 'label'=>'Demandes'],
  ['href' => APP_URL.'/teacher/documents.php', 'nav'=>'documents', 'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/teacher/defense.php',   'nav'=>'defense',   'icon'=>'📅', 'label'=>'Soutenances'],
];
include __DIR__ . '/../includes/layout_teacher.php';
?>

<?= $msg ?>

<div class="page-header">
  <div><h2>Validation des Documents</h2><p>Consultez, validez et commentez les documents soumis</p></div>
</div>

<!-- Filters -->
<div class="filters-bar" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%">
    <div class="search-input-wrap">
      <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input type="text" name="q" class="form-control" style="padding-left:36px" placeholder="Rechercher..." value="<?= e($search) ?>">
    </div>
    <select name="student" class="filter-select" onchange="this.form.submit()">
      <option value="">Tous les étudiants</option>
      <?php foreach ($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $fStudent==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select" onchange="this.form.submit()">
      <option value="">Tous les statuts</option>
      <option value="pending"  <?= $fStatus==='pending'?'selected':'' ?>>En attente</option>
      <option value="approved" <?= $fStatus==='approved'?'selected':'' ?>>Approuvés</option>
      <option value="rejected" <?= $fStatus==='rejected'?'selected':'' ?>>Rejetés</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Filtrer</button>
    <?php if ($fStatus || $fStudent || $search): ?>
      <a href="documents.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($docs)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><div class="empty-icon">📂</div><h3>Aucun document trouvé</h3></div>
</div></div>
<?php else: ?>

<!-- Documents Table -->
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Document</th>
          <th>Étudiant</th>
          <th>Projet</th>
          <th>Type</th>
          <th>Statut</th>
          <th>Feedback</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($docs as $doc): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:20px"><?= $fileIcons[$doc['filetype']] ?? '📎' ?></span>
            <div>
              <div style="font-weight:600;font-size:.87rem"><?= e($doc['title']) ?></div>
              <div style="font-size:.75rem;color:var(--text-muted)"><?= $doc['filesize'] ? round($doc['filesize']/1024,1).' KB' : '' ?></div>
            </div>
          </div>
        </td>
        <td style="font-weight:500"><?= e($doc['student_name']) ?></td>
        <td style="color:var(--text-secondary);font-size:.83rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($doc['project_title'] ?? '—') ?></td>
        <td><span style="background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:2px 8px;font-size:.75rem;font-weight:600;text-transform:uppercase"><?= e(strtoupper($doc['filetype'] ?? '?')) ?></span></td>
        <td><?= statusBadge($doc['status']) ?></td>
        <td>
          <?php if ($doc['feedback']): ?>
            <span style="font-size:.78rem;color:var(--text-secondary);display:block;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($doc['feedback']) ?>">
              <i class="fa-solid fa-comment-dots" style="color:var(--primary);margin-right:4px"></i><?= e($doc['feedback']) ?>
            </span>
          <?php else: ?>
            <span style="font-size:.78rem;color:var(--text-muted);font-style:italic">Aucun feedback</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="<?= APP_URL . '/' . e($doc['filepath']) ?>" target="_blank" class="btn-icon" data-tooltip="Voir le fichier">
              <i class="fa-solid fa-eye"></i>
            </a>
            <button class="btn-icon" onclick="openFeedbackModal(<?= $doc['id'] ?>, '<?= e(addslashes($doc['title'])) ?>', '<?= e(addslashes($doc['feedback'] ?? '')) ?>', '<?= $doc['status'] ?>')" data-tooltip="Feedback">
              <i class="fa-solid fa-comment-dots"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-comment-dots" style="color:var(--primary);margin-right:8px"></i>Feedback Enseignant</span>
      <button class="modal-close" onclick="closeModal('feedbackModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_feedback">
      <input type="hidden" name="doc_id" id="feedbackDocId">
      <div class="modal-body">
        <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px;margin-bottom:18px">
          <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Document</span>
          <div style="font-size:.9rem;font-weight:600;color:var(--text-primary);margin-top:4px" id="feedbackDocTitle"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Statut du document</label>
          <select name="doc_status" id="feedbackStatus" class="form-control">
            <option value="pending">En attente</option>
            <option value="approved">Approuvé</option>
            <option value="rejected">Rejeté</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Feedback / Commentaire</label>
          <textarea name="feedback" id="feedbackText" class="form-control" rows="5"
            placeholder="Écrivez votre retour sur ce document..."></textarea>
          <div class="form-hint">Ce feedback sera visible par l'étudiant.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('feedbackModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openFeedbackModal(docId, title, feedback, status) {
  document.getElementById('feedbackDocId').value   = docId;
  document.getElementById('feedbackDocTitle').textContent = title;
  document.getElementById('feedbackText').value    = feedback;
  document.getElementById('feedbackStatus').value  = status;
  openModal('feedbackModal');
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
