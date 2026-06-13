<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Gestion des Notes';
$activeNav = 'grades';
$user = currentUser();
$tid  = $user['id'];

$msg = '';

// ---- Filters ----
$filterQ       = trim($_GET['q']       ?? '');
$filterFiliere = $_GET['filiere']      ?? '';
$filterStatus  = $_GET['status']       ?? '';
$filterYear    = $_GET['year']         ?? '';
$activeFilterCount = ($filterQ ? 1 : 0) + ($filterFiliere ? 1 : 0) + ($filterStatus ? 1 : 0) + ($filterYear ? 1 : 0);

// Save grade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_grade') {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $rg = (float)($_POST['report_grade']       ?? 0);
    $pg = (float)($_POST['presentation_grade'] ?? 0);
    $tg = (float)($_POST['technical_grade']    ?? 0);
    $jg = (float)($_POST['jury_grade']         ?? 0);
    $comments = trim($_POST['comments'] ?? '');

    // Weighted final grade
    $final = ($rg * 0.40) + ($pg * 0.25) + ($tg * 0.25) + ($jg * 0.10);

    // Mention
    $mention = match(true) {
        $final >= 16 => 'Très Bien',
        $final >= 14 => 'Bien',
        $final >= 12 => 'Assez Bien',
        $final >= 10 => 'Passable',
        default      => 'Insuffisant',
    };

    // Upsert
    $existing = $pdo->prepare("SELECT id FROM grades WHERE student_id=? AND project_id=?");
    $existing->execute([$studentId, $projectId]);
    if ($existing->fetch()) {
        $pdo->prepare("UPDATE grades SET teacher_id=?, report_grade=?, presentation_grade=?, technical_grade=?, jury_grade=?, final_grade=?, mention=?, comments=?, graded_at=NOW() WHERE student_id=? AND project_id=?")
            ->execute([$tid, $rg, $pg, $tg, $jg, $final, $mention, $comments, $studentId, $projectId]);
    } else {
        $pdo->prepare("INSERT INTO grades (student_id, project_id, teacher_id, report_grade, presentation_grade, technical_grade, jury_grade, final_grade, mention, comments) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$studentId, $projectId, $tid, $rg, $pg, $tg, $jg, $final, $mention, $comments]);
    }

    // Update project status to completed
    $pdo->prepare("UPDATE projects SET status='completed' WHERE id=?")->execute([$projectId]);

    $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Notes enregistrées ! Note finale : '.number_format($final,2).'/20 — Mention : '.$mention.'</div>';
}

// Fetch projects with grades (with filters)
$where  = "WHERE p.status IN ('active','completed')";
$params = [];
if ($filterQ)       { $where .= " AND u.name LIKE ?"; $params[] = "%$filterQ%"; }
if ($filterFiliere) { $where .= " AND u.filiere = ?"; $params[] = $filterFiliere; }
if ($filterYear)    { $where .= " AND u.academic_year = ?"; $params[] = $filterYear; }
if ($filterStatus === 'noted')     { $where .= " AND g.final_grade IS NOT NULL"; }
if ($filterStatus === 'not_noted') { $where .= " AND g.final_grade IS NULL"; }

$projects = $pdo->prepare("
    SELECT p.*, u.name AS student_name, u.filiere, u.academic_year,
           g.id AS grade_id, g.report_grade, g.presentation_grade,
           g.technical_grade, g.jury_grade, g.final_grade, g.mention, g.comments,
           p.student_id
    FROM projects p
    JOIN users u ON p.student_id=u.id
    LEFT JOIN grades g ON g.student_id=p.student_id AND g.project_id=p.id
    $where
    ORDER BY u.name
");
$projects->execute($params);
$projects = $projects->fetchAll();

// Pre-fetch tasks-with-notes for each project
$projectTaskNotes = [];
if (!empty($projects)) {
    $projectIds = array_column($projects, 'id');
    $inPlaceholders = implode(',', array_fill(0, count($projectIds), '?'));
    $taskStmt = $pdo->prepare("SELECT project_id, id, title, status, note FROM tasks WHERE project_id IN ($inPlaceholders) AND note IS NOT NULL ORDER BY sort_order");
    $taskStmt->execute($projectIds);
    foreach ($taskStmt->fetchAll() as $t) {
        $projectTaskNotes[$t['project_id']][] = $t;
    }
}

// Fetch filter options
$years    = $pdo->query("SELECT DISTINCT academic_year FROM users WHERE role='student' AND academic_year IS NOT NULL ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$filieres = $pdo->query("SELECT DISTINCT filiere FROM users WHERE role='student' AND filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

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
  <div><h2>Gestion des Notes</h2><p>Évaluez les projets de fin d'études</p></div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 22px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <div style="font-size:.85rem;font-weight:600;color:var(--text-primary);margin-right:4px">
        <i class="fa-solid fa-filter" style="color:var(--primary);margin-right:6px"></i>Filtres
        <?php if ($activeFilterCount > 0): ?>
          <span class="badge badge-active" style="margin-left:6px;font-size:.72rem"><?= $activeFilterCount ?></span>
        <?php endif; ?>
      </div>
      <div class="search-input-wrap" style="min-width:200px">
        <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" name="q" class="form-control" style="padding-left:36px" placeholder="Rechercher un étudiant..." value="<?= e($filterQ) ?>">
      </div>
      <select name="year" class="filter-select">
        <option value="">Toutes les années</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= e($y) ?>" <?= $filterYear===$y?'selected':'' ?>><?= e($y) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="filiere" class="filter-select">
        <option value="">Toutes les filières</option>
        <?php foreach ($filieres as $f): ?>
          <option value="<?= e($f) ?>" <?= $filterFiliere===$f?'selected':'' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="filter-select">
        <option value="">Tous les statuts</option>
        <option value="noted"     <?= $filterStatus==='noted'?'selected':'' ?>>Noté</option>
        <option value="not_noted" <?= $filterStatus==='not_noted'?'selected':'' ?>>Non noté</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrer</button>
      <?php if ($activeFilterCount > 0): ?>
        <a href="grades.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Réinitialiser</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($projects)): ?>
  <div class="card-body">
    <div class="empty-state"><div class="empty-icon">⭐</div><h3>Aucun projet à noter</h3><p>Les projets actifs apparaîtront ici.</p></div>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Étudiant</th>
          <th>Filière</th>
          <th>Projet</th>
          <th>Tâches notées</th>
          <th>Moy. Tâches</th>
          <th>Note Finale</th>
          <th>Mention</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($projects as $p):
        $pTasks = $projectTaskNotes[$p['id']] ?? [];
        $taskAvg = null;
        if (!empty($pTasks)) {
            $taskAvg = round(array_sum(array_column($pTasks, 'note')) / count($pTasks), 2);
        }
        // Count all tasks for this project
        $totalTasksRow = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id=?");
        $totalTasksRow->execute([$p['id']]);
        $totalTasksCount = (int)$totalTasksRow->fetchColumn();
      ?>
      <tr data-grade-row="<?= $p['id'] ?>">
        <td style="font-weight:600"><?= e($p['student_name']) ?></td>
        <td><span class="badge badge-active"><?= e($p['filiere'] ?? '—') ?></span></td>
        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($p['title']) ?>"><?= e($p['title']) ?></td>
        <td>
          <?php if (!empty($pTasks)): ?>
            <button class="btn btn-secondary btn-sm" onclick="toggleTaskNotes(<?= $p['id'] ?>)" id="toggleBtn-<?= $p['id'] ?>">
              <i class="fa-solid fa-list-check"></i> <?= count($pTasks) ?>/<?= $totalTasksCount ?> notées
            </button>
          <?php else: ?>
            <span style="color:var(--text-muted);font-size:.82rem">— <?= $totalTasksCount ?> tâches</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($taskAvg !== null): ?>
            <span style="font-size:.95rem;font-weight:700;color:<?= $taskAvg >= 10 ? 'var(--primary-dark)' : 'var(--danger)' ?>">
              <?= number_format($taskAvg, 2) ?><span style="font-size:.72rem;color:var(--text-muted)">/20</span>
            </span>
          <?php else: ?>
            <span style="color:var(--text-muted)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($p['final_grade'] !== null): ?>
            <span style="font-size:1rem;font-weight:800;color:var(--primary-dark)"><?= number_format($p['final_grade'],2) ?><span style="font-size:.75rem;font-weight:500;color:var(--text-muted)">/20</span></span>
          <?php else: ?>
            <span style="color:var(--text-muted)">—</span>
          <?php endif; ?>
        </td>
        <td><?= $p['mention'] ? '<span class="badge badge-approved">'.e($p['mention']).'</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
        <td>
          <button class="btn btn-primary btn-sm"
            onclick="openGradeModal(<?= $p['id'] ?>, <?= $p['student_id'] ?>, '<?= e(addslashes($p['student_name'])) ?>', '<?= e(addslashes($p['title'])) ?>', <?= $p['report_grade'] ?? 'null' ?>, <?= $p['presentation_grade'] ?? 'null' ?>, <?= $p['technical_grade'] ?? 'null' ?>, <?= $p['jury_grade'] ?? 'null' ?>, '<?= e(addslashes($p['comments'] ?? '')) ?>')">
            <i class="fa-solid fa-star"></i> <?= $p['grade_id'] ? 'Modifier' : 'Noter' ?>
          </button>
        </td>
      </tr>
      <?php if (!empty($pTasks)): ?>
      <tr id="task-notes-panel-<?= $p['id'] ?>" style="display:none">
        <td colspan="8" style="padding:0;background:var(--bg)">
          <div style="padding:14px 24px 14px 36px;border-bottom:2px solid var(--border-light)">
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:10px">
              <i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:6px"></i>Détail des notes par tâche
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
              <?php foreach ($pTasks as $t):
                $noteColor = (float)$t['note'] >= 10 ? 'var(--success)' : 'var(--danger)';
              ?>
              <div style="display:flex;align-items:center;justify-content:space-between;background:white;border:1px solid var(--border-light);border-radius:var(--radius-sm);padding:10px 14px">
                <div>
                  <div style="font-size:.83rem;font-weight:600;color:var(--text-primary)"><?= e($t['title']) ?></div>
                  <div style="margin-top:4px"><?= statusBadge($t['status']) ?></div>
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:<?= $noteColor ?>;white-space:nowrap;margin-left:10px">
                  <?= number_format((float)$t['note'], 2) ?><span style="font-size:.7rem;color:var(--text-muted)">/20</span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php if ($taskAvg !== null): ?>
            <div style="margin-top:12px;padding:10px 14px;background:var(--primary-light);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between">
              <span style="font-size:.83rem;font-weight:600;color:var(--primary-dark)"><i class="fa-solid fa-calculator" style="margin-right:6px"></i>Moyenne des tâches notées</span>
              <span style="font-size:1.1rem;font-weight:800;color:var(--primary-dark)"><?= number_format($taskAvg, 2) ?>/20</span>
            </div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Grade Modal -->
<div class="modal-overlay" id="gradeModal">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-star" style="color:var(--warning);margin-right:8px"></i>Évaluation du Projet</span>
      <button class="modal-close" onclick="closeModal('gradeModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_grade">
      <input type="hidden" name="project_id"  id="gradeProjectId">
      <input type="hidden" name="student_id"  id="gradeStudentId">
      <div class="modal-body">
        <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px;margin-bottom:20px">
          <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted)">Étudiant / Projet</span>
          <div style="font-weight:600;margin-top:4px" id="gradeStudentName"></div>
          <div style="font-size:.83rem;color:var(--text-secondary)" id="gradeProjectTitle"></div>
        </div>

        <!-- Task notes summary in modal -->
        <div id="gradeTaskNotesPanel" style="display:none;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--radius-sm);padding:12px;margin-bottom:16px">
          <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:var(--success);margin-bottom:8px">
            <i class="fa-solid fa-list-check" style="margin-right:5px"></i>Moyenne des notes de tâches
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:.83rem;color:var(--text-secondary)" id="gradeTaskNotesDetail"></span>
            <span style="font-size:1.3rem;font-weight:800;color:var(--success)" id="gradeTaskAvg"></span>
          </div>
        </div>

        <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:12px;margin-bottom:20px;font-size:.82rem;color:var(--primary-dark)">
          <i class="fa-solid fa-circle-info" style="margin-right:6px"></i>
          Coefficients : Rapport 40% · Présentation 25% · Technique 25% · Jury 10%
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Rapport écrit <span style="color:var(--text-muted);font-weight:400">(40%)</span></label>
            <input type="number" name="report_grade" id="g_report" class="form-control" min="0" max="20" step="0.25" placeholder="0–20" oninput="calcFinal()">
          </div>
          <div class="form-group">
            <label class="form-label">Présentation <span style="color:var(--text-muted);font-weight:400">(25%)</span></label>
            <input type="number" name="presentation_grade" id="g_present" class="form-control" min="0" max="20" step="0.25" placeholder="0–20" oninput="calcFinal()">
          </div>
          <div class="form-group">
            <label class="form-label">Réalisation technique <span style="color:var(--text-muted);font-weight:400">(25%)</span></label>
            <input type="number" name="technical_grade" id="g_tech" class="form-control" min="0" max="20" step="0.25" placeholder="0–20" oninput="calcFinal()">
          </div>
          <div class="form-group">
            <label class="form-label">Jury <span style="color:var(--text-muted);font-weight:400">(10%)</span></label>
            <input type="number" name="jury_grade" id="g_jury" class="form-control" min="0" max="20" step="0.25" placeholder="0–20" oninput="calcFinal()">
          </div>
        </div>

        <!-- Live preview -->
        <div style="background:var(--bg);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <span style="font-weight:600;color:var(--text-secondary)">Note Finale Calculée</span>
          <span id="finalPreview" style="font-size:1.4rem;font-weight:800;color:var(--primary-dark)">—</span>
        </div>

        <div class="form-group">
          <label class="form-label">Commentaires</label>
          <textarea name="comments" id="gradeComments" class="form-control" rows="3" placeholder="Observations générales..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('gradeModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
// Map project id -> task note average data (from PHP)
const projectTaskData = <?= json_encode(array_map(function($pid) use ($projectTaskNotes) {
    $tasks = $projectTaskNotes[$pid] ?? [];
    if (empty($tasks)) return null;
    $notes = array_column($tasks, 'note');
    $avg = array_sum($notes) / count($notes);
    return [
        'count' => count($tasks),
        'avg'   => round($avg, 2),
        'tasks' => array_map(fn($t) => ['title'=>$t['title'],'note'=>$t['note']], $tasks)
    ];
}, array_combine(array_column($projects, 'id'), array_column($projects, 'id')))) ?>;

function toggleTaskNotes(projId) {
  const panel  = document.getElementById(`task-notes-panel-${projId}`);
  const btn    = document.getElementById(`toggleBtn-${projId}`);
  const hidden = panel.style.display === 'none';
  panel.style.display = hidden ? '' : 'none';
  btn.innerHTML = hidden
    ? '<i class="fa-solid fa-chevron-up"></i> Masquer'
    : `<i class="fa-solid fa-list-check"></i> ${btn.textContent.trim().split(' ')[1]} notées`;
}

function openGradeModal(projId, studentId, studentName, projectTitle, rg, pg, tg, jg, comments) {
  document.getElementById('gradeProjectId').value    = projId;
  document.getElementById('gradeStudentId').value    = studentId;
  document.getElementById('gradeStudentName').textContent  = studentName;
  document.getElementById('gradeProjectTitle').textContent = projectTitle;

  // Populate existing grades if any
  document.getElementById('g_report').value  = rg !== null ? rg : '';
  document.getElementById('g_present').value = pg !== null ? pg : '';
  document.getElementById('g_tech').value    = tg !== null ? tg : '';
  document.getElementById('g_jury').value    = jg !== null ? jg : '';
  document.getElementById('gradeComments').value = comments || '';
  calcFinal();

  // Show task notes summary if available
  const taskData = projectTaskData[projId];
  const taskPanel = document.getElementById('gradeTaskNotesPanel');
  if (taskData && taskData.count > 0) {
    taskPanel.style.display = '';
    document.getElementById('gradeTaskAvg').textContent = taskData.avg.toFixed(2) + ' / 20';
    document.getElementById('gradeTaskNotesDetail').textContent = `Basé sur ${taskData.count} tâche(s) notée(s)`;
  } else {
    taskPanel.style.display = 'none';
  }

  openModal('gradeModal');
}

function calcFinal() {
  const r = parseFloat(document.getElementById('g_report').value)  || 0;
  const p = parseFloat(document.getElementById('g_present').value) || 0;
  const t = parseFloat(document.getElementById('g_tech').value)    || 0;
  const j = parseFloat(document.getElementById('g_jury').value)    || 0;
  const final = (r * 0.40) + (p * 0.25) + (t * 0.25) + (j * 0.10);
  const el = document.getElementById('finalPreview');
  if (r || p || t || j) {
    el.textContent = final.toFixed(2) + ' / 20';
    el.style.color = final >= 10 ? 'var(--primary-dark)' : 'var(--danger)';
  } else {
    el.textContent = '—';
    el.style.color = 'var(--primary-dark)';
  }
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
