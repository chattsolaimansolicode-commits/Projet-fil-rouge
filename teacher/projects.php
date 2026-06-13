<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('teacher');
$pageTitle = 'Gestion des Projets';
$activeNav = 'projects';
$user = currentUser();
$tid  = $user['id'];

// ---- Handle AJAX Project Tasks Fetching (TEACHER) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_project_tasks') {
    header('Content-Type: application/json');
    $projectId = (int)($_POST['project_id'] ?? 0);
    
    // Verify project exists
    $projCheck = $pdo->prepare("SELECT id, title FROM projects WHERE id=?");
    $projCheck->execute([$projectId]);
    $projectRow = $projCheck->fetch();
    
    if ($projectRow) {
        $stmt = $pdo->prepare("
            SELECT t.*, u.name AS validator_name 
            FROM tasks t 
            LEFT JOIN users u ON t.validated_by = u.id 
            WHERE t.project_id = ? 
            ORDER BY t.sort_order
        ");
        $stmt->execute([$projectId]);
        $tasksList = $stmt->fetchAll();
        
        // Format dates
        foreach ($tasksList as &$t) {
            $t['badge_html'] = statusBadge($t['status']);
            $t['deadline_formatted'] = $t['deadline'] ? date('d/m/Y', strtotime($t['deadline'])) : '—';
            $t['validated_at_formatted'] = $t['validated_at'] ? date('d/m/Y H:i', strtotime($t['validated_at'])) : '';
        }
        
        echo json_encode([
            'success' => true,
            'title' => $projectRow['title'],
            'tasks' => $tasksList
        ]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// ---- Handle AJAX Task Status Update (TEACHER) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task_status_teacher') {
    header('Content-Type: application/json');
    $taskId  = (int)($_POST['task_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $comment = $_POST['revision_comment'] ?? '';
    $note    = isset($_POST['task_note']) && $_POST['task_note'] !== '' ? (float)$_POST['task_note'] : null;
    
    $validStatuses = ['not_started', 'in_progress', 'pending_validation', 'revision_necessaire', 'completed'];
    if ($taskId && in_array($status, $validStatuses)) {
        // Find task and project
        $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id=?");
        $stmt->execute([$taskId]);
        $projectId = (int)$stmt->fetchColumn();
        
        if ($projectId) {
            // Clamp note 0-20
            if ($note !== null) {
                $note = max(0, min(20, $note));
            }
            
            if ($status === 'completed') {
                $upd = $pdo->prepare("UPDATE tasks SET status=?, validated_by=?, validated_at=NOW(), revision_comment=NULL, note=? WHERE id=?");
                $upd->execute([$status, $tid, $note, $taskId]);
            } elseif ($status === 'revision_necessaire') {
                $upd = $pdo->prepare("UPDATE tasks SET status=?, validated_by=NULL, validated_at=NULL, revision_comment=?, note=? WHERE id=?");
                $upd->execute([$status, $comment ?: null, $note, $taskId]);
            } else {
                $upd = $pdo->prepare("UPDATE tasks SET status=?, validated_by=NULL, validated_at=NULL, revision_comment=NULL WHERE id=?");
                $upd->execute([$status, $taskId]);
            }
            
            // Recalculate progress
            $newProgress = recalculateProjectProgress($pdo, $projectId);
            
            // Get updated validator details
            $valStmt = $pdo->prepare("SELECT t.validated_at, t.note, u.name AS validator_name FROM tasks t LEFT JOIN users u ON t.validated_by = u.id WHERE t.id=?");
            $valStmt->execute([$taskId]);
            $valData = $valStmt->fetch();
            $validatorName = $valData['validator_name'] ?? '';
            $validatedAtFormatted = $valData['validated_at'] ? date('d/m/Y H:i', strtotime($valData['validated_at'])) : '';
            
            echo json_encode([
                'success'              => true,
                'project_progress'     => $newProgress,
                'badge_html'           => statusBadge($status),
                'validator_name'       => $validatorName,
                'validated_at_formatted' => $validatedAtFormatted,
                'revision_comment'     => $comment,
                'task_note'            => $valData['note'] !== null ? number_format((float)$valData['note'], 2) : null,
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// ---- Handle AJAX Task Deadline Update (TEACHER) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task_deadline') {
    header('Content-Type: application/json');
    $taskId   = (int)($_POST['task_id'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';

    if ($taskId) {
        // Get task title
        $titleStmt = $pdo->prepare("SELECT title FROM tasks WHERE id=?");
        $titleStmt->execute([$taskId]);
        $taskTitle = $titleStmt->fetchColumn();

        if ($taskTitle) {
            $deadlineValue = $deadline ?: null;
            // Apply deadline to ALL tasks with the same title
            $upd = $pdo->prepare("UPDATE tasks SET deadline=? WHERE title=?");
            $upd->execute([$deadlineValue, $taskTitle]);
            $updatedCount = $upd->rowCount();

            echo json_encode([
                'success' => true,
                'updated_count' => $updatedCount,
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

$msg = '';

// Assign supervisor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_supervisor') {
    $projId = (int)($_POST['project_id'] ?? 0);
    $supId  = (int)($_POST['supervisor_id'] ?? 0);
    $pdo->prepare("UPDATE projects SET supervisor_id=? WHERE id=?")->execute([$supId ?: null, $projId]);
    $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Encadrant assigné !</div>';
}

// Update project status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $projId = (int)($_POST['project_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid  = ['pending','active','completed','rejected'];
    if (in_array($status, $valid)) {
        $pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$status, $projId]);
        $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Statut mis à jour.</div>';
    }
}

// Filters
$fYear    = $_GET['year']      ?? '';
$fFiliere = $_GET['filiere']   ?? '';
$fStudent = $_GET['student_id']?? '';
$fStatus  = $_GET['status']    ?? '';
$search   = trim($_GET['q']    ?? '');

$where  = 'WHERE 1=1';
$params = [];
if ($fYear)    { $where .= " AND p.academic_year=?"; $params[] = $fYear; }
if ($fFiliere) { $where .= " AND p.filiere=?";       $params[] = $fFiliere; }
if ($fStudent) { $where .= " AND p.student_id=?";    $params[] = $fStudent; }
if ($fStatus)  { $where .= " AND p.status=?";        $params[] = $fStatus; }
if ($search)   { $where .= " AND (p.title LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$projects = $pdo->prepare("SELECT p.*, u.name AS student_name, u.filiere, u.academic_year, s.name AS supervisor_name FROM projects p JOIN users u ON p.student_id=u.id LEFT JOIN users s ON p.supervisor_id=s.id $where ORDER BY p.created_at DESC");
$projects->execute($params);
$projects = $projects->fetchAll();

// Filter options
$teachers  = $pdo->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name")->fetchAll();
$students  = $pdo->query("SELECT id, name FROM users WHERE role='student' ORDER BY name")->fetchAll();
$years     = $pdo->query("SELECT DISTINCT academic_year FROM projects WHERE academic_year IS NOT NULL ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$filieres  = $pdo->query("SELECT DISTINCT filiere FROM projects WHERE filiere IS NOT NULL ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);

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
  <div><h2>Gestion des Projets</h2><p>Suivez, filtrez et gérez tous les projets PFE</p></div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 22px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <div class="search-input-wrap" style="min-width:200px">
        <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
        <input type="text" name="q" class="form-control" style="padding-left:36px" placeholder="Rechercher..." value="<?= e($search) ?>">
      </div>
      <select name="student_id" class="filter-select">
        <option value="">Tous les étudiants</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $fStudent==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="year" class="filter-select">
        <option value="">Toutes les années</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= e($y) ?>" <?= $fYear===$y?'selected':'' ?>><?= e($y) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="filiere" class="filter-select">
        <option value="">Toutes les filières</option>
        <?php foreach ($filieres as $f): ?>
          <option value="<?= e($f) ?>" <?= $fFiliere===$f?'selected':'' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="filter-select">
        <option value="">Tous les statuts</option>
        <option value="pending"   <?= $fStatus==='pending'?'selected':'' ?>>Pending</option>
        <option value="active"    <?= $fStatus==='active'?'selected':'' ?>>Active</option>
        <option value="completed" <?= $fStatus==='completed'?'selected':'' ?>>Completed</option>
        <option value="rejected"  <?= $fStatus==='rejected'?'selected':'' ?>>Rejected</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrer</button>
      <a href="projects.php" class="btn btn-secondary btn-sm">Réinitialiser</a>
    </form>
  </div>
</div>

<div class="card">
  <?php if (empty($projects)): ?>
  <div class="card-body">
    <div class="empty-state"><div class="empty-icon">📁</div><h3>Aucun projet trouvé</h3></div>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Projet</th><th>Étudiant</th><th>Filière</th><th>Encadrant</th><th>Progression</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
      <tr data-project-row="<?= $p['id'] ?>">
        <td style="font-weight:600;max-width:200px"><?= e(mb_substr($p['title'],0,40)) ?></td>
        <td><?= e($p['student_name']) ?></td>
        <td><span class="badge badge-active"><?= e($p['filiere'] ?? '—') ?></span></td>
        <td>
          <?php if ($p['supervisor_name']): ?>
            <span style="font-size:.82rem;color:var(--text-primary);font-weight:500"><?= e($p['supervisor_name']) ?></span>
          <?php else: ?>
            <span style="color:var(--text-muted);font-size:.8rem">Non assigné</span>
          <?php endif; ?>
        </td>
        <td style="min-width:100px">
          <div style="display:flex;align-items:center;gap:8px">
            <div class="progress-wrap" style="flex:1"><div class="progress-bar" style="width:<?= (int)$p['progress'] ?>%" data-width="<?= (int)$p['progress'] ?>%"></div></div>
            <span class="progress-percentage" style="font-size:.75rem;font-weight:700;color:var(--primary-dark);white-space:nowrap"><?= (int)$p['progress'] ?>%</span>
          </div>
        </td>
        <td><?= statusBadge($p['status']) ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <button class="btn btn-secondary btn-sm" onclick="openTasksModal(<?= $p['id'] ?>, '<?= e(addslashes($p['title'])) ?>')" data-tooltip="Voir les tâches">
              <i class="fa-solid fa-list-check"></i> Voir les tâches
            </button>
            <button class="btn-icon" onclick="openAssignModal(<?= $p['id'] ?>, '<?= e(addslashes($p['supervisor_name'] ?? '')) ?>')" data-tooltip="Assigner encadrant">
              <i class="fa-solid fa-chalkboard-user"></i>
            </button>
            <button class="btn-icon" onclick="openStatusModal(<?= $p['id'] ?>, '<?= $p['status'] ?>')" data-tooltip="Changer statut">
              <i class="fa-solid fa-pen"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Assign Supervisor Modal -->
<div class="modal-overlay" id="assignModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-chalkboard-user" style="color:var(--primary);margin-right:8px"></i>Assigner un Encadrant</span>
      <button class="modal-close" onclick="closeModal('assignModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="assign_supervisor">
      <input type="hidden" name="project_id" id="assignProjectId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Encadrant <span class="required">*</span></label>
          <select name="supervisor_id" id="supervisorSelect" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirmer</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="statusModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-pen" style="color:var(--primary);margin-right:8px"></i>Modifier le Statut</span>
      <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="project_id" id="statusProjectId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nouveau Statut</label>
          <select name="status" id="statusSelect" class="form-control">
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- View Tasks Modal -->
<div class="modal-overlay" id="tasksModal">
  <div class="modal" style="max-width: 800px;">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px"></i>Tâches : <span id="modalProjectTitle" style="font-weight: 600;"></span></span>
      <button class="modal-close" onclick="closeModal('tasksModal')">✕</button>
    </div>
    <div class="modal-body" style="padding-top: 15px;">
      <div id="modalTasksLoading" style="text-align: center; padding: 20px 0;">
        <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 24px; color: var(--primary);"></i> Chargement...
      </div>
      <div id="modalTasksError" style="display: none;" class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i> Erreur lors du chargement des tâches.
      </div>
      
      <div id="modalTasksContent" style="display: none;">
        <div class="table-wrapper">
          <table style="width: 100%;">
            <thead>
              <tr>
                <th>Tâche</th>
                <th style="width: 130px;">Deadline</th>
                <th style="width: 130px;">Statut</th>
                <th style="width: 170px;">Action</th>
                <th style="width: 110px;">Note /20</th>
                <th>Validation / Infos</th>
              </tr>
            </thead>
            <tbody id="modalTasksTableBody">
              <!-- Dynamically populated -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('tasksModal')">Fermer</button>
    </div>
  </div>
</div>

<script>
function openAssignModal(projId, supName) {
  document.getElementById('assignProjectId').value = projId;
  openModal('assignModal');
}
function openStatusModal(projId, status) {
  document.getElementById('statusProjectId').value = projId;
  document.getElementById('statusSelect').value = status;
  openModal('statusModal');
}

let currentProjectId = 0;

function openTasksModal(projId, projTitle) {
  currentProjectId = projId;
  document.getElementById('modalProjectTitle').textContent = projTitle;
  
  // Show loading, hide content/error
  document.getElementById('modalTasksLoading').style.display = 'block';
  document.getElementById('modalTasksError').style.display = 'none';
  document.getElementById('modalTasksContent').style.display = 'none';
  
  openModal('tasksModal');
  
  // Fetch tasks via AJAX
  fetch('projects.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=get_project_tasks&project_id=${projId}`
  })
  .then(res => res.json())
  .then(data => {
    document.getElementById('modalTasksLoading').style.display = 'none';
    if (data.success) {
      const tbody = document.getElementById('modalTasksTableBody');
      tbody.innerHTML = '';
      
      data.tasks.forEach(task => {
        // Main row
        const tr = document.createElement('tr');
        tr.setAttribute('data-modal-task', task.id);
        
        // Info cell
        let infoHtml = '—';
        if (task.status === 'completed') {
          infoHtml = `<div style="font-size:0.75rem; color:var(--text-secondary);">
                        <i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Validé par <strong>${escapeHtml(task.validator_name)}</strong><br>le ${task.validated_at_formatted}
                      </div>`;
        } else if (task.status === 'revision_necessaire') {
          infoHtml = `<div style="font-size:0.75rem; color:var(--danger); font-weight: 500;">
                        <i class="fa-solid fa-circle-exclamation"></i> Révision demandée
                      </div>`;
        }

        const taskNoteVal = (task.note !== null && task.note !== undefined && task.note !== '') ? parseFloat(task.note).toFixed(2) : '';
        
        tr.innerHTML = `
          <td style="font-weight: 500;">${escapeHtml(task.title)}</td>
          <td>
            <input type="date" id="task-deadline-${task.id}" class="form-control" style="font-size:.78rem;padding:5px 8px"
              value="${task.deadline || ''}"
              onchange="saveTaskDeadline(${task.id}, this.value)"
              title="Date limite pour cette tâche">
            <div style="font-size:.65rem;color:var(--text-muted);margin-top:3px;font-style:italic">S'applique à tous les étudiants</div>
          </td>
          <td class="badge-cell">${task.badge_html}</td>
          <td>
            <select class="filter-select" style="font-size:0.78rem; padding:5px 8px; width: 100%;" 
                    data-prev="${task.status}" 
                    onchange="handleTeacherStatusChange(${task.id}, this.value, this)">
              <option value="not_started" ${task.status === 'not_started' ? 'selected' : ''}>Non démarré</option>
              <option value="in_progress" ${task.status === 'in_progress' ? 'selected' : ''}>En cours</option>
              <option value="pending_validation" ${task.status === 'pending_validation' ? 'selected' : ''}>En attente</option>
              <option value="revision_necessaire" ${task.status === 'revision_necessaire' ? 'selected' : ''}>Révision nécessaire</option>
              <option value="completed" ${task.status === 'completed' ? 'selected' : ''}>Validé</option>
            </select>
          </td>
          <td>
            <input type="number" id="task-note-${task.id}" class="form-control" style="width:80px;font-size:.82rem;padding:5px 8px"
              min="0" max="20" step="0.5" placeholder="0-20" value="${taskNoteVal}"
              title="Note pour cette tâche (0-20)">
          </td>
          <td class="info-cell">${infoHtml}</td>
        `;
        tbody.appendChild(tr);
        
        // Comment row for revision_necessaire
        const commentTr = document.createElement('tr');
        commentTr.id = `revision-row-${task.id}`;
        commentTr.style.display = task.status === 'revision_necessaire' ? '' : 'none';
        commentTr.style.background = '#FFF5F5';
        commentTr.innerHTML = `
          <td colspan="6" style="padding: 10px 16px; border-bottom: 1px solid var(--border-light);">
            <div class="form-group" style="margin-bottom: 8px;">
              <label class="form-label" style="font-size: 0.8rem; color: var(--danger);">Commentaire de révision (requis) <span class="required">*</span></label>
              <textarea id="revision-comment-${task.id}" class="form-control" rows="2" placeholder="Expliquez ce qu'il faut modifier...">${escapeHtml(task.revision_comment || '')}</textarea>
            </div>
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
              <button type="button" class="btn btn-secondary btn-sm" onclick="cancelRevision(${task.id})">Annuler</button>
              <button type="button" class="btn btn-danger btn-sm" onclick="submitRevision(${task.id})">Enregistrer révision</button>
            </div>
          </td>
        `;
        tbody.appendChild(commentTr);
      });
      
      document.getElementById('modalTasksContent').style.display = 'block';
    } else {
      document.getElementById('modalTasksError').style.display = 'block';
    }
  })
  .catch(() => {
    document.getElementById('modalTasksLoading').style.display = 'none';
    document.getElementById('modalTasksError').style.display = 'block';
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function handleTeacherStatusChange(taskId, newStatus, selectEl) {
  const prevStatus = selectEl.getAttribute('data-prev');
  
  if (newStatus === 'revision_necessaire') {
    // Show comment input row
    document.getElementById(`revision-row-${taskId}`).style.display = '';
    document.getElementById(`revision-comment-${taskId}`).focus();
  } else {
    // Hide comment input row (if open)
    document.getElementById(`revision-row-${taskId}`).style.display = 'none';
    
    // Save immediately
    saveTeacherTaskStatus(taskId, newStatus, '', selectEl);
  }
}

function cancelRevision(taskId) {
  const commentRow = document.getElementById(`revision-row-${taskId}`);
  commentRow.style.display = 'none';
  
  const tr = document.querySelector(`tr[data-modal-task="${taskId}"]`);
  if (tr) {
    const selectEl = tr.querySelector('select');
    const prevStatus = selectEl.getAttribute('data-prev');
    selectEl.value = prevStatus;
  }
}

function submitRevision(taskId) {
  const comment = document.getElementById(`revision-comment-${taskId}`).value.trim();
  if (!comment) {
    alert('Le commentaire de révision est obligatoire.');
    return;
  }
  
  const tr = document.querySelector(`tr[data-modal-task="${taskId}"]`);
  const selectEl = tr.querySelector('select');
  
  saveTeacherTaskStatus(taskId, 'revision_necessaire', comment, selectEl);
}

function saveTeacherTaskStatus(taskId, status, comment, selectEl) {
  const noteEl = document.getElementById(`task-note-${taskId}`);
  const noteVal = noteEl ? noteEl.value.trim() : '';
  fetch('projects.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=update_task_status_teacher&task_id=${taskId}&status=${status}&revision_comment=${encodeURIComponent(comment)}&task_note=${encodeURIComponent(noteVal)}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Update data-prev
      selectEl.setAttribute('data-prev', status);

      // Update badge in modal row
      const tr = document.querySelector(`tr[data-modal-task="${taskId}"]`);
      if (tr) {
        tr.querySelector('.badge-cell').innerHTML = data.badge_html;

        // Update Info cell
        const infoCell = tr.querySelector('.info-cell');
        if (status === 'completed') {
          infoCell.innerHTML = `<div style="font-size:0.75rem; color:var(--text-secondary);">
                                  <i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Validé par <strong>${escapeHtml(data.validator_name)}</strong><br>le ${data.validated_at_formatted}
                                </div>`;
        } else if (status === 'revision_necessaire') {
          infoCell.innerHTML = `<div style="font-size:0.75rem; color:var(--danger); font-weight: 500;">
                                  <i class="fa-solid fa-circle-exclamation"></i> Révision demandée
                                </div>`;
        } else {
          infoCell.innerHTML = '—';
        }

        // Update note display if returned
        if (data.task_note !== null && data.task_note !== undefined) {
          const noteEl = document.getElementById(`task-note-${taskId}`);
          if (noteEl) noteEl.value = data.task_note;
        }
      }

      // Hide comment row if saved
      if (status !== 'revision_necessaire') {
        const revRow = document.getElementById(`revision-row-${taskId}`);
        if (revRow) revRow.style.display = 'none';
      }

      // Update project progress in main table
      const projectRow = document.querySelector(`tr[data-project-row="${currentProjectId}"]`);
      if (projectRow) {
        const progressBar = projectRow.querySelector('.progress-bar');
        if (progressBar) {
          progressBar.style.width = data.project_progress + '%';
          progressBar.setAttribute('data-width', data.project_progress + '%');
        }
        const progressLabel = projectRow.querySelector('.progress-percentage');
        if (progressLabel) {
          progressLabel.textContent = data.project_progress + '%';
        }
      }
    } else {
      alert('Erreur lors de la mise à jour.');
    }
  })
  .catch(() => {
    alert('Erreur réseau.');
  });
}

function saveTaskDeadline(taskId, deadlineValue) {
  fetch('projects.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=update_task_deadline&task_id='+taskId+'&deadline='+encodeURIComponent(deadlineValue)
  })
  .then(res => res.json())
  .then(data => { if (!data.success) alert('Erreur deadline.'); })
  .catch(() => alert('Erreur réseau.'));
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
