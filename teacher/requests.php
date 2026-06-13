<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Gestion des Demandes';
$activeNav = 'requests';
$user = currentUser();
$tid  = $user['id'];

$msg = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reqId  = (int)($_POST['req_id'] ?? 0);
    $note   = trim($_POST['teacher_note'] ?? '');

    if ($action === 'approve' && $reqId) {
        $pdo->prepare("UPDATE requests SET status='approved', teacher_id=?, teacher_note=?, updated_at=NOW() WHERE id=?")
            ->execute([$tid, $note, $reqId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Demande approuvée.</div>';
    } elseif ($action === 'reject' && $reqId) {
        $pdo->prepare("UPDATE requests SET status='rejected', teacher_id=?, teacher_note=?, updated_at=NOW() WHERE id=?")
            ->execute([$tid, $note, $reqId]);
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-xmark"></i> Demande rejetée.</div>';
    }
}

// Filters
$fStatus  = $_GET['status']  ?? '';
$search   = trim($_GET['q']  ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($fStatus) { $where .= " AND r.status=?"; $params[] = $fStatus; }
if ($search)  { $where .= " AND (u.name LIKE ? OR r.type LIKE ? OR r.message LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$requests = $pdo->prepare("SELECT r.*, u.name AS student_name, u.filiere, u.academic_year, t.name AS teacher_name FROM requests r JOIN users u ON r.student_id=u.id LEFT JOIN users t ON r.teacher_id=t.id $where ORDER BY r.created_at DESC");
$requests->execute($params);
$requests = $requests->fetchAll();

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
  <div><h2>Gestion des Demandes</h2><p>Approuvez ou rejetez les demandes des étudiants</p></div>
</div>

<!-- Filters -->
<div class="filters-bar">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%">
    <div class="search-input-wrap">
      <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input type="text" name="q" class="form-control" style="padding-left:36px" placeholder="Rechercher étudiant, type..." value="<?= e($search) ?>">
    </div>
    <select name="status" class="filter-select" onchange="this.form.submit()">
      <option value="">Tous les statuts</option>
      <option value="pending"  <?= $fStatus==='pending'?'selected':'' ?>>En attente</option>
      <option value="approved" <?= $fStatus==='approved'?'selected':'' ?>>Approuvées</option>
      <option value="rejected" <?= $fStatus==='rejected'?'selected':'' ?>>Rejetées</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
    <?php if ($fStatus || $search): ?>
      <a href="requests.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (empty($requests)): ?>
  <div class="card-body">
    <div class="empty-state"><div class="empty-icon">✅</div><h3>Aucune demande</h3><p>Toutes les demandes ont été traitées.</p></div>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Étudiant</th>
          <th>Filière</th>
          <th>Type</th>
          <th>Message</th>
          <th>Statut</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td style="font-weight:600"><?= e($r['student_name']) ?></td>
        <td><span class="badge badge-active" style="font-size:.7rem"><?= e($r['filiere'] ?? '—') ?></span></td>
        <td style="font-size:.85rem"><?= e($r['type']) ?></td>
        <td>
          <span style="display:block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary);font-size:.83rem" title="<?= e($r['message']) ?>">
            <?= e($r['message']) ?>
          </span>
          <?php if ($r['teacher_note']): ?>
            <span style="display:block;font-size:.75rem;color:var(--text-muted);margin-top:2px"><i class="fa-solid fa-comment" style="margin-right:3px"></i><?= e(mb_substr($r['teacher_note'],0,50)) ?></span>
          <?php endif; ?>
        </td>
        <td><?= statusBadge($r['status']) ?></td>
        <td style="color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
          <div style="display:flex;gap:6px">
            <button class="btn btn-sm btn-success" onclick="openRespondModal(<?= $r['id'] ?>, 'approve', '<?= e(addslashes($r['student_name'])) ?>')">
              <i class="fa-solid fa-check"></i> Approuver
            </button>
            <button class="btn btn-sm btn-danger" onclick="openRespondModal(<?= $r['id'] ?>, 'reject', '<?= e(addslashes($r['student_name'])) ?>')">
              <i class="fa-solid fa-xmark"></i> Rejeter
            </button>
          </div>
          <?php else: ?>
            <span style="font-size:.8rem;color:var(--text-muted)">Traité</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Respond Modal -->
<div class="modal-overlay" id="respondModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="respondModalTitle">Répondre à la demande</span>
      <button class="modal-close" onclick="closeModal('respondModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" id="respondAction">
      <input type="hidden" name="req_id" id="respondReqId">
      <div class="modal-body">
        <div class="alert alert-info" id="respondInfo" style="margin-bottom:16px"></div>
        <div class="form-group">
          <label class="form-label">Note / Commentaire <span style="font-weight:400;color:var(--text-muted)">(optionnel)</span></label>
          <textarea name="teacher_note" class="form-control" rows="3" placeholder="Expliquez votre décision..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('respondModal')">Annuler</button>
        <button type="submit" class="btn btn-primary" id="respondSubmitBtn">Confirmer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRespondModal(reqId, action, studentName) {
  document.getElementById('respondReqId').value   = reqId;
  document.getElementById('respondAction').value  = action;
  const isApprove = action === 'approve';
  document.getElementById('respondModalTitle').textContent = isApprove ? 'Approuver la demande' : 'Rejeter la demande';
  document.getElementById('respondInfo').textContent = `Étudiant : ${studentName}`;
  const btn = document.getElementById('respondSubmitBtn');
  btn.className = isApprove ? 'btn btn-success' : 'btn btn-danger';
  btn.innerHTML = isApprove ? '<i class="fa-solid fa-check"></i> Approuver' : '<i class="fa-solid fa-xmark"></i> Rejeter';
  openModal('respondModal');
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
