<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Tableau de bord';
$activeNav = 'dashboard';
$user = currentUser();
$uid  = $user['id'];

// ---- Handle Create Project ----
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_project') {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $tech  = trim($_POST['technologies'] ?? '');
    $sd    = $_POST['start_date'] ?? '';

    if (!$title) {
        $msg = '<div class="alert alert-danger" data-autohide><i class="fa-solid fa-circle-exclamation"></i> Le titre est obligatoire.</div>';
    } else {
        // Check if student already has an active project
        $exists = $pdo->prepare("SELECT id FROM projects WHERE student_id = ? AND status != 'rejected' LIMIT 1");
        $exists->execute([$uid]);
        if ($exists->fetch()) {
            $msg = '<div class="alert alert-warning" data-autohide><i class="fa-solid fa-triangle-exclamation"></i> Vous avez déjà un projet en cours.</div>';
        } else {
            $ins = $pdo->prepare("INSERT INTO projects (title, description, technologies, start_date, end_date, status, student_id) VALUES (?,?,?,?,NULL,?,?)");
            $ins->execute([$title, $desc, $tech, $sd ?: null, 'pending', $uid]);
            $projId = $pdo->lastInsertId();

            // Insert default tasks
            $defaultTasks = [
                'Cahier des charges', 'Planification du projet',
                'Conception de la base de données', 'Maquettes UI/UX',
                'Codage Front-end', 'Codage Back-end', 'Rapport', 'Présentation'
            ];
            $taskStmt = $pdo->prepare("INSERT INTO tasks (project_id, title, sort_order) VALUES (?,?,?)");
            foreach ($defaultTasks as $i => $t) {
                $taskStmt->execute([$projId, $t, $i+1]);
            }
            $msg = '<div class="alert alert-success" data-autohide><i class="fa-solid fa-circle-check"></i> Projet créé avec succès !</div>';
        }
    }
}

// ---- Handle Dashboard AJAX Poll ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'poll_dashboard') {
    header('Content-Type: application/json');
    
    // Fetch latest project data
    $proj = $pdo->prepare("SELECT id, progress, status FROM projects WHERE student_id=? AND status != 'rejected' ORDER BY created_at DESC LIMIT 1");
    $proj->execute([$uid]);
    $pRow = $proj->fetch();
    $projectId = $pRow ? (int)$pRow['id'] : 0;
    $progress = $pRow ? (int)$pRow['progress'] : 0;
    $status = $pRow ? $pRow['status'] : '';
    
    $health = calculateProjectHealthScore($pdo, $projectId);
    $readiness = calculateProjectReadiness($pdo, $projectId, $uid);
    $risks = detectProjectRisks($pdo, $projectId);
    
    $tasksData = [];
    $completedCount = 0;
    $totalCount = 0;
    if ($projectId) {
        $tStmt = $pdo->prepare("SELECT id, status FROM tasks WHERE project_id=? ORDER BY sort_order");
        $tStmt->execute([$projectId]);
        $tList = $tStmt->fetchAll();
        $totalCount = count($tList);
        foreach ($tList as $tRow) {
            if ($tRow['status'] === 'completed') {
                $completedCount++;
            }
            $tasksData[] = [
                'id' => $tRow['id'],
                'badge' => statusBadge($tRow['status'])
            ];
        }
    }
    
    // Fetch latest rankings
    $rankingsQuery = $pdo->query("
        SELECT 
            u.id AS student_id,
            u.name AS student_name,
            p.title AS project_title,
            COALESCE(p.progress, 0) AS progress
        FROM users u
        LEFT JOIN projects p ON u.id = p.student_id AND p.status != 'rejected'
        WHERE u.role = 'student'
        ORDER BY progress DESC, u.name ASC
    ");
    $rankings = $rankingsQuery->fetchAll();
    
    ob_start();
    $rank = 1;
    foreach ($rankings as $row): 
      $isCurrent = ($row['student_id'] == $uid);
      $highlightStyle = $isCurrent ? 'background: var(--primary-light); font-weight: 600;' : '';
    ?>
    <tr style="<?= $highlightStyle ?>">
      <td style="text-align: center; font-size: 1.1rem;"><?= getRankEmoji($rank) ?></td>
      <td>
        <?= e($row['student_name']) ?>
        <?php if ($isCurrent): ?><span class="badge badge-active" style="margin-left: 8px;">Vous</span><?php endif; ?>
      </td>
      <td style="color: var(--text-secondary);">
        <?= $row['project_title'] ? e($row['project_title']) : '<span class="text-muted">Aucun projet</span>' ?>
      </td>
      <td>
        <div style="display: flex; align-items: center; gap: 10px;">
          <div class="progress-wrap" style="flex: 1; height: 6px;">
            <div class="progress-bar" style="width: <?= (int)$row['progress'] ?>%"></div>
          </div>
          <span style="font-size: .8rem; font-weight: 700; min-width: 35px; text-align: right;"><?= (int)$row['progress'] ?>%</span>
        </div>
      </td>
    </tr>
    <?php 
      $rank++;
    endforeach; 
    $rankingHtml = ob_get_clean();
    
    // Render risks HTML
    ob_start();
    if (empty($risks)): ?>
      <div class="alert alert-success" style="margin-bottom: 0;">
        <i class="fa-solid fa-circle-check"></i> ✅ No risks detected
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($risks as $r): ?>
          <div class="alert alert-<?= $r['color'] ?>" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
            <div>
              <i class="fa-solid fa-circle-exclamation"></i> 
              <strong><?= $r['level'] ?> Task:</strong> <?= e($r['task_title']) ?> — Deadline: <?= $r['deadline'] ?>
            </div>
            <span class="badge badge-<?= $r['color'] ?>"><?= $r['days_remaining'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif;
    $risksHtml = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'progress' => $progress,
        'status_badge' => statusBadge($status),
        'health_score' => $health['score'],
        'health_label' => $health['emoji'] . ' ' . $health['label'],
        'health_class' => $health['class'],
        'readiness_percentage' => $readiness['percentage'],
        'readiness_label' => $readiness['emoji'] . ' ' . $readiness['label'],
        'readiness_class' => $readiness['class'],
        'risks_html' => $risksHtml,
        'tasks' => $tasksData,
        'completed_text' => $completedCount . '/' . $totalCount . ' complétées',
        'ranking_html' => $rankingHtml
    ]);
    exit;
}

// ---- Fetch Data ----
$project = $pdo->prepare("SELECT p.*, u.name AS supervisor_name FROM projects p LEFT JOIN users u ON p.supervisor_id=u.id WHERE p.student_id=? AND p.status != 'rejected' ORDER BY p.created_at DESC LIMIT 1");
$project->execute([$uid]);
$project = $project->fetch();

$tasks = [];
if ($project) {
    $t = $pdo->prepare("SELECT * FROM tasks WHERE project_id=? ORDER BY sort_order");
    $t->execute([$project['id']]);
    $tasks = $t->fetchAll();
}

$health = calculateProjectHealthScore($pdo, $project ? (int)$project['id'] : 0);
$readiness = calculateProjectReadiness($pdo, $project ? (int)$project['id'] : 0, $uid);
$risks = detectProjectRisks($pdo, $project ? (int)$project['id'] : 0);

$reqCount  = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE student_id=?");
$reqCount->execute([$uid]); $reqCount = $reqCount->fetchColumn();

$docCount = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE student_id=?");
$docCount->execute([$uid]); $docCount = $docCount->fetchColumn();

$grade = $pdo->prepare("SELECT final_grade FROM grades WHERE student_id=? LIMIT 1");
$grade->execute([$uid]); $grade = $grade->fetchColumn();

// Recent activity from requests & documents
$activity = $pdo->prepare("
  SELECT 'request' AS type, type AS label, created_at FROM requests WHERE student_id=?
  UNION ALL
  SELECT 'document', title, uploaded_at FROM documents WHERE student_id=?
  ORDER BY created_at DESC LIMIT 5
");
$activity->execute([$uid, $uid]);
$activities = $activity->fetchAll();

// Student info
$studentInfo = $pdo->prepare("SELECT * FROM users WHERE id=?");
$studentInfo->execute([$uid]);
$studentInfo = $studentInfo->fetch();

// Fetch student rankings for widget
$rankingsQuery = $pdo->query("
    SELECT 
        u.id AS student_id,
        u.name AS student_name,
        p.title AS project_title,
        COALESCE(p.progress, 0) AS progress
    FROM users u
    LEFT JOIN projects p ON u.id = p.student_id AND p.status != 'rejected'
    WHERE u.role = 'student'
    ORDER BY progress DESC, u.name ASC
");
$rankings = $rankingsQuery->fetchAll();

$mobileNavLinks = [
  ['href' => APP_URL.'/student/dashboard.php',  'nav'=>'dashboard',  'icon'=>'🏠', 'label'=>'Accueil'],
  ['href' => APP_URL.'/student/projects.php',   'nav'=>'projects',   'icon'=>'📁', 'label'=>'Projet'],
  ['href' => APP_URL.'/student/documents.php',  'nav'=>'documents',  'icon'=>'📄', 'label'=>'Docs'],
  ['href' => APP_URL.'/student/grades.php',     'nav'=>'grades',     'icon'=>'⭐', 'label'=>'Notes'],
  ['href' => APP_URL.'/student/defense.php',    'nav'=>'defense',    'icon'=>'📅', 'label'=>'Soutenance'],
];
include __DIR__ . '/../includes/layout_student.php';
?>

<?= $msg ?>

<!-- Greeting -->
<div class="greeting-section">
  <div class="greeting-text">
    <h1>Bonjour, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p><?= date('l d F Y') ?> · <?= $studentInfo['filiere'] ? e($studentInfo['filiere']) . ' · ' : '' ?><?= $studentInfo['academic_year'] ? e($studentInfo['academic_year']) : 'Année universitaire' ?></p>
  </div>
  <button class="btn btn-primary" onclick="openModal('createProjectModal')">
    <i class="fa-solid fa-plus"></i> Créer un Projet
  </button>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Mon Projet</span>
      <div class="stat-icon blue"><i class="fa-solid fa-diagram-project"></i></div>
    </div>
    <div class="stat-value"><?= $project ? 1 : 0 ?></div>
    <div class="stat-sub"><?= $project ? e($project['status']) : 'Aucun projet' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Demandes</span>
      <div class="stat-icon orange"><i class="fa-solid fa-paper-plane"></i></div>
    </div>
    <div class="stat-value"><?= (int)$reqCount ?></div>
    <div class="stat-sub">Soumises</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Documents</span>
      <div class="stat-icon purple"><i class="fa-solid fa-file-lines"></i></div>
    </div>
    <div class="stat-value"><?= (int)$docCount ?></div>
    <div class="stat-sub">Téléversés</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-header">
      <span class="stat-label">Note Finale</span>
      <div class="stat-icon green"><i class="fa-solid fa-star"></i></div>
    </div>
    <div class="stat-value"><?= $grade !== false ? number_format($grade, 1) : '—' ?></div>
    <div class="stat-sub"><?= $grade !== false ? '/20' : 'Pas encore notée' ?></div>
  </div>
</div>

<!-- Ranking Widget -->
<div class="card mb-24">
  <div class="card-header">
    <span class="card-title"><i class="fa-solid fa-trophy" style="color:var(--warning);margin-right:8px"></i>Classement des Étudiants</span>
  </div>
  <div class="table-wrapper">
    <table class="ranking-table">
      <thead>
        <tr>
          <th style="width: 80px; text-align: center;">Rang</th>
          <th>Étudiant</th>
          <th>Projet</th>
          <th style="width: 300px;">Progression</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $rank = 1;
        foreach ($rankings as $row): 
          $isCurrent = ($row['student_id'] == $uid);
          $highlightStyle = $isCurrent ? 'background: var(--primary-light); font-weight: 600;' : '';
        ?>
        <tr style="<?= $highlightStyle ?>">
          <td style="text-align: center; font-size: 1.1rem;">
            <?= getRankEmoji($rank) ?>
          </td>
          <td>
            <?= e($row['student_name']) ?>
            <?php if ($isCurrent): ?>
              <span class="badge badge-active" style="margin-left: 8px;">Vous</span>
            <?php endif; ?>
          </td>
          <td style="color: var(--text-secondary);">
            <?= $row['project_title'] ? e($row['project_title']) : '<span class="text-muted">Aucun projet</span>' ?>
          </td>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <div class="progress-wrap" style="flex: 1; height: 6px;">
                <div class="progress-bar" style="width: <?= (int)$row['progress'] ?>%"></div>
              </div>
              <span style="font-size: .8rem; font-weight: 700; min-width: 35px; text-align: right;">
                <?= (int)$row['progress'] ?>%
              </span>
            </div>
          </td>
        </tr>
        <?php 
          $rank++;
        endforeach; 
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Main Grid -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

  <!-- Left Column -->
  <div style="display:flex;flex-direction:column;gap:24px">

    <?php if ($project): ?>
    <!-- Project Header -->
    <div class="card mb-24" style="background: var(--surface);">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 1rem; font-weight: 700; color: var(--text-primary);"><?= e($project['title']) ?></span>
        <span id="projectStatusBadge"><?= statusBadge($project['status']) ?></span>
      </div>
      <div class="card-body" style="padding: 15px 22px; display: flex; gap: 24px; flex-wrap: wrap; font-size: 0.82rem; color: var(--text-secondary); border-top: 1px solid var(--border-light);">
        <?php if ($project['supervisor_name']): ?>
          <div><i class="fa-solid fa-chalkboard-user" style="color:var(--primary); margin-right: 4px;"></i> Encadrant: <strong><?= e($project['supervisor_name']) ?></strong></div>
        <?php endif; ?>
        <?php if ($project['start_date']): ?>
          <div><i class="fa-regular fa-calendar" style="color:var(--primary); margin-right: 4px;"></i> Début: <strong><?= date('d/m/Y', strtotime($project['start_date'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($project['end_date']): ?>
          <div><i class="fa-regular fa-calendar-check" style="color:var(--primary); margin-right: 4px;"></i> Fin prévue: <strong><?= date('d/m/Y', strtotime($project['end_date'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($project['final_deadline']): ?>
          <div><i class="fa-solid fa-hourglass-half" style="color:var(--danger); margin-right: 4px;"></i> Date limite finale: <strong><?= date('d/m/Y', strtotime($project['final_deadline'])) ?></strong></div>
        <?php endif; ?>
        <?php if ($project['github_url']): ?>
          <div><i class="fa-brands fa-github" style="color:var(--text-primary); margin-right: 4px;"></i> GitHub: <a href="<?= e($project['github_url']) ?>" target="_blank" style="color: var(--primary-dark); font-weight: 600; text-decoration: underline;"><?= e(parse_url($project['github_url'], PHP_URL_PATH) ?? $project['github_url']) ?></a></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Health & Readiness Widgets Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
      <!-- Health Score Widget -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-heart-pulse" style="color:var(--danger);margin-right:8px"></i>Santé du Projet</span></div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between; gap: 15px;">
          <div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary);" id="healthScoreValue"><?= $health['score'] ?>/100</div>
            <div style="font-size: 0.9rem; font-weight: 600; margin-top: 4px;" id="healthScoreLabel" class="<?= $health['class'] ?>"><?= $health['emoji'] ?> <?= $health['label'] ?></div>
          </div>
          <div style="font-size: 0.72rem; color: var(--text-secondary); text-align: right; line-height: 1.5; border-left: 1px solid var(--border-light); padding-left: 10px;">
            Complété: 40pts<br>Délais respectés: 30pts<br>Documents: 20pts<br>Retard: -10pts
          </div>
        </div>
      </div>

      <!-- Readiness Widget -->
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-graduation-cap" style="color:var(--primary);margin-right:8px"></i>Prêt pour la Soutenance</span></div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between; gap: 15px;">
          <div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary);" id="readinessValue"><?= $readiness['percentage'] ?>%</div>
            <div style="font-size: 0.9rem; font-weight: 600; margin-top: 4px;" id="readinessLabel" class="<?= $readiness['class'] ?>"><?= $readiness['emoji'] ?> <?= $readiness['label'] ?></div>
          </div>
          <div style="font-size: 0.72rem; color: var(--text-secondary); text-align: right; line-height: 1.5; border-left: 1px solid var(--border-light); padding-left: 10px;">
            Documents (25%)<br>Tâches (25%)<br>Note assignée (25%)<br>Soutenance (25%)
          </div>
        </div>
      </div>
    </div>

    <!-- Risk Detection Widget -->
    <div class="card" id="riskDetectionCard" style="margin-bottom: 24px;">
      <div class="card-header"><span class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:8px"></i>Détection des Risques</span></div>
      <div class="card-body" id="riskDetectionBody" style="padding: 18px 22px;">
        <?php if (empty($risks)): ?>
          <div class="alert alert-success" style="margin-bottom: 0;">
            <i class="fa-solid fa-circle-check"></i> ✅ No risks detected
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($risks as $r): ?>
              <div class="alert alert-<?= $r['color'] ?>" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div>
                  <i class="fa-solid fa-circle-exclamation"></i> 
                  <strong><?= $r['level'] ?> Task:</strong> <?= e($r['task_title']) ?> — Deadline: <?= $r['deadline'] ?>
                </div>
                <span class="badge badge-<?= $r['color'] ?>"><?= $r['days_remaining'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tasks Table -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px"></i>Tâches du Projet</span>
        <span id="tasksCompletedCount" style="font-size:.8rem;color:var(--text-muted)"><?= count(array_filter($tasks, fn($t)=>$t['status']==='completed')) ?>/<?= count($tasks) ?> complétées</span>
      </div>
      <div class="table-wrapper">
        <table id="tasksTable">
          <thead>
            <tr>
              <th>Tâche</th>
              <th>Statut</th>
              <th>Échéance</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $task): ?>
            <tr data-task="<?= $task['id'] ?>">
              <td style="font-weight:500"><?= e($task['title']) ?></td>
              <td class="badge-cell"><?= statusBadge($task['status']) ?></td>
              <td style="color:var(--text-muted)">
                <?= $task['deadline'] ? date('d/m/Y', strtotime($task['deadline'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon">📁</div>
          <h3>Aucun projet en cours</h3>
          <p>Cliquez sur "Créer un Projet" pour commencer votre PFE.</p>
          <button class="btn btn-primary mt-16" onclick="openModal('createProjectModal')">
            <i class="fa-solid fa-plus"></i> Créer un Projet
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Activity -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);margin-right:8px"></i>Activités Récentes</span>
    </div>
    <div class="card-body">
      <?php if ($activities): ?>
      <div class="timeline">
        <?php foreach ($activities as $act): ?>
        <div class="timeline-item">
          <div class="timeline-dot <?= $act['type']==='document' ? 'purple' : 'orange' ?>"
               style="<?= $act['type']==='document' ? 'background:#EEF2FF;color:#4F46E5' : '' ?>">
            <?= $act['type']==='document' ? '📄' : '📨' ?>
          </div>
          <div class="timeline-content">
            <div class="t-title"><?= e(mb_substr($act['label'], 0, 40)) ?></div>
            <div class="t-time"><?= timeAgo($act['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state" style="padding:28px 0">
        <div class="empty-icon" style="font-size:32px">🕐</div>
        <p>Aucune activité récente</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== Modal: Create Project ===== -->
<div class="modal-overlay" id="createProjectModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fa-solid fa-plus" style="color:var(--primary);margin-right:8px"></i>Créer un Projet</span>
      <button class="modal-close" onclick="closeModal('createProjectModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_project">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Titre du projet <span class="required">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="Ex: Système de gestion...">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Décrivez votre projet..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Technologies utilisées</label>
          <input type="text" name="technologies" class="form-control" placeholder="Ex: PHP, MySQL, Bootstrap, JS">
        </div>
        <div class="form-group">
          <label class="form-label">Date de début</label>
          <input type="date" name="start_date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createProjectModal')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Soumettre</button>
      </div>
    </form>
  </div>
</div>

<script>
// Poll dashboard data every 5 seconds
setInterval(function() {
  fetch('dashboard.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=poll_dashboard'
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Update general progress
      if (typeof updateProgressBar === 'function') {
        updateProgressBar(data.progress);
      }
      
      // Update project status badge
      const projBadge = document.getElementById('projectStatusBadge');
      if (projBadge && data.status_badge) {
        projBadge.innerHTML = data.status_badge;
      }
      
      // Update health score widget
      const healthValue = document.getElementById('healthScoreValue');
      if (healthValue && data.health_score !== undefined) {
        healthValue.textContent = data.health_score + '/100';
      }
      const healthLabel = document.getElementById('healthScoreLabel');
      if (healthLabel && data.health_label !== undefined) {
        healthLabel.innerHTML = data.health_label;
        healthLabel.className = data.health_class || '';
      }
      
      // Update readiness widget
      const readinessValue = document.getElementById('readinessValue');
      if (readinessValue && data.readiness_percentage !== undefined) {
        readinessValue.textContent = data.readiness_percentage + '%';
      }
      const readinessLabel = document.getElementById('readinessLabel');
      if (readinessLabel && data.readiness_label !== undefined) {
        readinessLabel.innerHTML = data.readiness_label;
        readinessLabel.className = data.readiness_class || '';
      }
      
      // Update risks list
      const risksBody = document.getElementById('riskDetectionBody');
      if (risksBody && data.risks_html !== undefined) {
        risksBody.innerHTML = data.risks_html;
      }
      
      // Update completed text count
      const completedText = document.getElementById('tasksCompletedCount');
      if (completedText && data.completed_text) {
        completedText.textContent = data.completed_text;
      }
      
      // Update task table badges
      if (data.tasks) {
        data.tasks.forEach(task => {
          const row = document.querySelector(`tr[data-task="${task.id}"]`);
          if (row) {
            const badgeCell = row.querySelector('.badge-cell');
            if (badgeCell) badgeCell.innerHTML = task.badge;
          }
        });
      }
      
      // Update ranking table
      const rankingBody = document.querySelector('.ranking-table tbody');
      if (rankingBody && data.ranking_html) {
        rankingBody.innerHTML = data.ranking_html;
      }
    }
  })
  .catch(err => console.error('Dashboard polling error:', err));
}, 5000);
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
