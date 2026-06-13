<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Documents';
$activeNav = 'documents';
$user = currentUser();
$uid  = $user['id'];

// Ensure uploads directory exists
$uploadDir = __DIR__ . "/../uploads/";
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$msg = '';

if (isset($_GET['success'])) {
    $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Document téléversé avec succès !</div>';
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $projId = (int)($_POST['project_id'] ?? 0);

    if (!$title) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Le titre est obligatoire.</div>';
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Erreur lors du téléversement du fichier.</div>';
    } elseif ($_FILES['document_file']['size'] > MAX_FILE_SIZE) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Fichier trop volumineux (max 10MB).</div>';
    } else {
        $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','zip','png','jpg','jpeg'];
        if (!in_array($ext, $allowed)) {
            $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Type de fichier non autorisé.</div>';
        } else {
            $fname = uniqid('doc_') . '.' . $ext;
            move_uploaded_file($_FILES["document_file"]["tmp_name"], $uploadDir . $fname);
            $ins = $pdo->prepare("INSERT INTO documents (project_id, student_id, title, filename, filepath, filetype, filesize) VALUES (?,?,?,?,?,?,?)");
            $ins->execute([
                $projId ?: null, $uid, $title, $_FILES['document_file']['name'],
                'uploads/' . $fname, $ext, $_FILES['document_file']['size']
            ]);
           header('Location: documents.php?success=1');
                         exit;
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_doc') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    $doc = $pdo->prepare("SELECT * FROM documents WHERE id=? AND student_id=?");
    $doc->execute([$docId, $uid]);
    $doc = $doc->fetch();
    if ($doc) {
        @unlink(UPLOAD_PATH . basename($doc['filepath']));
        $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$docId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Document supprimé.</div>';
    }
}

// Fetch documents with feedback
$docs = $pdo->prepare("SELECT d.*, u.name AS feedback_author FROM documents d LEFT JOIN users u ON d.feedback_by=u.id WHERE d.student_id=? ORDER BY d.uploaded_at DESC");
$docs->execute([$uid]);
$docs = $docs->fetchAll();

// Student projects for selector
$projects = $pdo->prepare("SELECT id, title FROM projects WHERE student_id=? AND status != 'rejected'");
$projects->execute([$uid]);
$projects = $projects->fetchAll();

$fileIcons = [
    'pdf'=>'📄','doc'=>'📝','docx'=>'📝','ppt'=>'📊','pptx'=>'📊',
    'zip'=>'📦','png'=>'🖼️','jpg'=>'🖼️','jpeg'=>'🖼️'
];

$mobileNavLinks = [
  ['href' => APP_URL.'/student/dashboard.php', 'nav'=>'dashboard', 'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/student/projects.php',  'nav'=>'projects',  'icon'=>'📁', 'label'=>'Projet'],
  ['href' => APP_URL.'/student/documents.php', 'nav'=>'documents', 'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/student/grades.php',    'nav'=>'grades',    'icon'=>'⭐', 'label'=>'Notes'],
  ['href' => APP_URL.'/student/defense.php',   'nav'=>'defense',   'icon'=>'📅', 'label'=>'Soutenance'],
];
include __DIR__ . '/../includes/layout_student.php';
?>

<?= $msg ?>

<div class="page-header">
  <div>
    <h2>Mes Documents</h2>
    <p>Gérez vos documents et consultez les retours de votre encadrant</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('uploadModal')">
    <i class="fa-solid fa-upload"></i> Téléverser un document
  </button>
</div>

<?php if (empty($docs)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">📂</div>
    <h3>Aucun document</h3>
    <p>Commencez par téléverser vos documents de projet.</p>
    <button class="btn btn-primary mt-16" onclick="openModal('uploadModal')">
      <i class="fa-solid fa-upload"></i> Téléverser
    </button>
  </div>
</div></div>
<?php else: ?>
<div class="docs-grid">
  <?php foreach ($docs as $doc): ?>
  <div class="doc-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
      <div>
        <div class="doc-icon"><?= $fileIcons[$doc['filetype']] ?? '📎' ?></div>
        <div class="doc-title"><?= e($doc['title']) ?></div>
        <div class="doc-meta">
          <?= e($doc['filename']) ?> ·
          <?= $doc['filesize'] ? round($doc['filesize']/1024, 1) . ' KB' : '' ?> ·
          <?= date('d/m/Y', strtotime($doc['uploaded_at'])) ?>
        </div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <?= statusBadge($doc['status']) ?>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:12px">
      <a href="<?= APP_URL . '/' . e($doc['filepath']) ?>" target="_blank" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-eye"></i> Voir
      </a>
      <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce document ?')">
        <input type="hidden" name="action" value="delete_doc">
        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
        <button type="submit" class="btn btn-sm" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA">
          <i class="fa-solid fa-trash"></i>
        </button>
      </form>
    </div>

    <!-- Teacher Feedback -->
    <div class="doc-feedback">
      <div class="feedback-label">
        <i class="fa-solid fa-comment-dots"></i> Feedback Encadrant
        <?php if ($doc['feedback_at']): ?>
          <span style="font-weight:400;color:var(--text-muted);margin-left:auto"><?= date('d/m/Y', strtotime($doc['feedback_at'])) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($doc['feedback']): ?>
        <div class="feedback-text"><?= nl2br(e($doc['feedback'])) ?></div>
        <?php if ($doc['feedback_author']): ?>
          <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px">— <?= e($doc['feedback_author']) ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="no-feedback">No feedback available yet.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-upload" style="color:var(--primary);margin-right:8px"></i>Téléverser un Document</span>
      <button class="modal-close" onclick="closeModal('uploadModal')">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Titre <span class="required">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="Ex: Rapport de stage">
        </div>
        <?php if ($projects): ?>
        <div class="form-group">
          <label class="form-label">Projet associé</label>
          <select name="project_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Fichier <span class="required">*</span></label>
          <input type="file" id="document_file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.png,.jpg,.jpeg">
          <div id="document_file_preview" class="form-hint"></div>
          <div class="form-hint">Formats acceptés : PDF, Word, PowerPoint, ZIP, Images — Max 10MB</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Téléverser</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>