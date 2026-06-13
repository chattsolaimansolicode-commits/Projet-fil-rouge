<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Planification des Soutenances';
$activeNav = 'defense';
$user = currentUser();
$tid  = $user['id'];

$msg = '';

// Create defense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_defense') {
    $projectId  = (int)($_POST['project_id']  ?? 0);
    $studentId  = (int)($_POST['student_id']  ?? 0);
    $date       = $_POST['defense_date']  ?? '';
    $time       = $_POST['defense_time']  ?? '';
    $room       = trim($_POST['room']     ?? '');
    $jury       = trim($_POST['jury_members'] ?? '');
    $notes      = trim($_POST['notes']    ?? '');
    $year       = trim($_POST['academic_year'] ?? '');
    $filiere    = trim($_POST['filiere']  ?? '');

    if (!$projectId || !$studentId || !$date || !$time) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Veuillez remplir tous les champs obligatoires.</div>';
    } else {
        $pdo->prepare("INSERT INTO defenses (project_id, student_id, defense_date, defense_time, room, jury_members, notes, academic_year, filiere) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$projectId, $studentId, $date, $time, $room, $jury, $notes, $year ?: null, $filiere ?: null]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Soutenance planifiée avec succès !</div>';
    }
}

// Update defense status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_defense') {
    $defId  = (int)($_POST['defense_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid  = ['scheduled','completed','cancelled'];
    if ($defId && in_array($status, $valid)) {
        $pdo->prepare("UPDATE defenses SET status=? WHERE id=?")->execute([$status, $defId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Statut mis à jour.</div>';
    }
}

// Delete defense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_defense') {
    $defId = (int)($_POST['defense_id'] ?? 0);
    if ($defId) {
        $pdo->prepare("DELETE FROM defenses WHERE id=?")->execute([$defId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Soutenance supprimée.</div>';
    }
}

// Filters
$fYear    = $_GET['year']    ?? '';
$fFiliere = $_GET['filiere'] ?? '';
$fStatus  = $_GET['status']  ?? '';

$where  = 'WHERE 1=1';
$params = [];
if ($fYear)    { $where .= " AND d.academic_year=?"; $params[] = $fYear; }
if ($fFiliere) { $where .= " AND d.filiere=?";       $params[] = $fFiliere; }
if ($fStatus)  { $where .= " AND d.status=?";        $params[] = $fStatus; }

$defenses = $pdo->prepare("SELECT d.*, u.name AS student_name, u.filiere AS student_filiere, p.title AS project_title FROM defenses d JOIN users u ON d.student_id=u.id JOIN projects p ON d.project_id=p.id $where ORDER BY d.defense_date ASC, d.defense_time ASC");
$defenses->execute($params);
$defenses = $defenses->fetchAll();

// For modal: all active projects with student info
$activeProjects = $pdo->query("SELECT p.id, p.title, p.student_id, p.academic_year, p.filiere, u.name AS student_name FROM projects p JOIN users u ON p.student_id=u.id WHERE p.status IN ('active','pending') ORDER BY u.name")->fetchAll();

$years    = $pdo->query("SELECT DISTINCT academic_year FROM defenses WHERE academic_year IS NOT NULL ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$filieres = $pdo->query("SELECT DISTINCT filiere FROM defenses WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

$mobileNavLinks = [
  ['href' => APP_URL.'/teacher/dashboard.php', 'nav'=>'dashboard', 'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/teacher/projects.php',  'nav'=>'projects',  'icon'=>'📁', 'label'=>'Projets'],
  ['href' => APP_URL.'/teacher/requests.php',  'nav'=>'requests',  'icon'=>'📨', 'label'=>'Demandes'],
  ['href' => APP_URL.'/teacher/grades.php',    'nav'=>'grades',    'icon'=>'⭐', 'label'=>'Notes'],
  ['href' => APP_URL.'/teacher/defense.php',   'nav'=>'defense',   'icon'=>'📅', 'label'=>'Soutenances'],
];
include __DIR__ . '/../includes/layout_teacher.php';
?>

<?= $msg ?>

<div class="page-header">
  <div><h2>Planification des Soutenances</h2><p>Organisez et gérez les dates de soutenance</p></div>
  <button class="btn btn-primary" onclick="openModal('createDefenseModal')">
    <i class="fa-solid fa-plus"></i> Créer une Soutenance
  </button>
</div>

<!-- Filters -->
<div class="filters-bar" style="margin-bottom:20px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%">
    <select name="year" class="filter-select" onchange="this.form.submit()">
      <option value="">Toutes les années</option>
      <?php foreach ($years as $y): ?>
        <option value="<?= e($y) ?>" <?= $fYear===$y?'selected':'' ?>><?= e($y) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="filiere" class="filter-select" onchange="this.form.submit()">
      <option value="">Toutes les filières</option>
      <?php foreach ($filieres as $f): ?>
        <option value="<?= e($f) ?>" <?= $fFiliere===$f?'selected':'' ?>><?= e($f) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select" onchange="this.form.submit()">
      <option value="">Tous les statuts</option>
      <option value="scheduled"  <?= $fStatus==='scheduled'?'selected':'' ?>>Planifiées</option>
      <option value="completed"  <?= $fStatus==='completed'?'selected':'' ?>>Complétées</option>
      <option value="cancelled"  <?= $fStatus==='cancelled'?'selected':'' ?>>Annulées</option>
    </select>
    <?php if ($fYear || $fFiliere || $fStatus): ?>
      <a href="defense.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($defenses)): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">📅</div>
    <h3>Aucune soutenance planifiée</h3>
    <p>Commencez par créer une soutenance.</p>
    <button class="btn btn-primary mt-16" onclick="openModal('createDefenseModal')">
      <i class="fa-solid fa-plus"></i> Créer une Soutenance
    </button>
  </div>
</div></div>
<?php else: ?>
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Étudiant</th>
          <th>Projet</th>
          <th>Filière</th>
          <th>Date</th>
          <th>Heure</th>
          <th>Salle</th>
          <th>Jury</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($defenses as $d): ?>
      <tr>
        <td style="font-weight:600"><?= e($d['student_name']) ?></td>
        <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary);font-size:.83rem"><?= e($d['project_title']) ?></td>
        <td><span class="badge badge-active"><?= e($d['filiere'] ?? $d['student_filiere'] ?? '—') ?></span></td>
        <td style="white-space:nowrap;font-weight:500"><?= date('d/m/Y', strtotime($d['defense_date'])) ?></td>
        <td><?= date('H:i', strtotime($d['defense_time'])) ?></td>
        <td><?= e($d['room'] ?? '—') ?></td>
        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-secondary);font-size:.82rem"><?= e($d['jury_members'] ?? '—') ?></td>
        <td><?= statusBadge($d['status']) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="update_defense">
              <input type="hidden" name="defense_id" value="<?= $d['id'] ?>">
              <input type="hidden" name="status" value="completed">
              <button type="submit" class="btn-icon" data-tooltip="Marquer complétée" <?= $d['status']==='completed'?'disabled':'' ?>>
                <i class="fa-solid fa-check"></i>
              </button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette soutenance ?')">
              <input type="hidden" name="action" value="delete_defense">
              <input type="hidden" name="defense_id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn-icon" style="color:var(--danger)" data-tooltip="Supprimer">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Create Defense Modal -->
<div class="modal-overlay" id="createDefenseModal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--primary);margin-right:8px"></i>Planifier une Soutenance</span>
      <button class="modal-close" onclick="closeModal('createDefenseModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_defense">
      <div class="modal-body">

        <!-- Project/Student selection -->
        <div class="form-group">
          <label class="form-label">Projet <span class="required">*</span></label>
          <select name="project_id" id="defProjectSelect" class="form-control" required onchange="fillStudent(this)">
            <option value="">— Sélectionner un projet —</option>
            <?php foreach ($activeProjects as $p): ?>
              <option value="<?= $p['id'] ?>"
                data-student-id="<?= $p['student_id'] ?>"
                data-student-name="<?= e($p['student_name']) ?>"
                data-year="<?= e($p['academic_year'] ?? '') ?>"
                data-filiere="<?= e($p['filiere'] ?? '') ?>">
                <?= e($p['student_name']) ?> — <?= e(mb_substr($p['title'],0,50)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">La sélection du projet remplit automatiquement l'étudiant.</div>
        </div>

        <input type="hidden" name="student_id" id="defStudentId">

        <!-- Student display (read-only) -->
        <div class="form-group" id="studentDisplay" style="display:none">
          <label class="form-label">Étudiant</label>
          <div style="background:var(--bg);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;font-size:.875rem;font-weight:600;color:var(--primary-dark)" id="studentDisplayText"></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date <span class="required">*</span></label>
            <input type="date" name="defense_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Heure <span class="required">*</span></label>
            <input type="time" name="defense_time" class="form-control" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Salle</label>
            <input type="text" name="room" class="form-control" placeholder="Ex: Salle A101">
          </div>
          <div class="form-group">
            <label class="form-label">Année académique</label>
            <input type="text" name="academic_year" id="defYear" class="form-control" placeholder="Ex: 2024-2025">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Filière</label>
          <input type="text" name="filiere" id="defFiliere" class="form-control" placeholder="Ex: Génie Logiciel">
        </div>

        <div class="form-group">
          <label class="form-label">Membres du Jury</label>
          <input type="text" name="jury_members" class="form-control" placeholder="Ex: Dr. Rachidi, Dr. Amrani, Dr. Benali">
          <div class="form-hint">Séparez les noms par des virgules.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes / Remarques</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Informations complémentaires..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createDefenseModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> Planifier</button>
      </div>
    </form>
  </div>
</div>

<script>
function fillStudent(sel) {
  const opt = sel.options[sel.selectedIndex];
  const studentId   = opt.getAttribute('data-student-id')   || '';
  const studentName = opt.getAttribute('data-student-name') || '';
  const year        = opt.getAttribute('data-year')         || '';
  const filiere     = opt.getAttribute('data-filiere')      || '';

  document.getElementById('defStudentId').value   = studentId;
  document.getElementById('defYear').value         = year;
  document.getElementById('defFiliere').value      = filiere;

  const disp = document.getElementById('studentDisplay');
  const txt  = document.getElementById('studentDisplayText');
  if (studentName) {
    txt.textContent = studentName;
    disp.style.display = 'block';
  } else {
    disp.style.display = 'none';
  }
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
