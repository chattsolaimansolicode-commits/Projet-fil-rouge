<?php
require_once __DIR__ . '/../config/db.php';
requireLogin('student');
$pageTitle = 'Mon Projet';
$activeNav = 'projects';
$user = currentUser();
$uid  = $user['id'];

// ---- Handle Task Status Update (AJAX) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_task') {
    header('Content-Type: application/json');
    $taskId = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $validStatuses = ['not_started', 'in_progress', 'pending_validation'];
    if ($taskId && in_array($status, $validStatuses)) {
        $check = $pdo->prepare("SELECT t.id, t.project_id, t.status AS current_status FROM tasks t JOIN projects p ON t.project_id=p.id WHERE t.id=? AND p.student_id=?");
        $check->execute([$taskId, $uid]);
        $taskData = $check->fetch();
        if ($taskData) {
            $currentStatus = $taskData['current_status'];
            // Student cannot change completed tasks
            if ($currentStatus === 'completed') {
                echo json_encode(['success' => false, 'error' => 'Tâche déjà validée.']);
                exit;
            }
            // Student can set any of the 3 valid statuses from any non-completed status
            $pdo->prepare("UPDATE tasks SET status=? WHERE id=?")->execute([$status, $taskId]);
            $pct = recalculateProjectProgress($pdo, (int)$taskData['project_id']);
            $health = calculateProjectHealthScore($pdo, (int)$taskData['project_id']);
            $readiness = calculateProjectReadiness($pdo, (int)$taskData['project_id'], $uid);
            echo json_encode([
                'success'   => true,
                'progress'  => $pct,
                'badge'     => statusBadge($status),
                'health_score' => $health['score'],
                'health_label' => $health['emoji'] . ' ' . $health['label'],
                'health_class' => $health['class'],
                'readiness_percentage' => $readiness['percentage'],
                'readiness_label' => $readiness['emoji'] . ' ' . $readiness['label'],
                'readiness_class' => $readiness['class'],
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// ---- Handle GitHub URL Update (AJAX) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_github') {
    header('Content-Type: application/json');
    $projId  = (int)($_POST['project_id'] ?? 0);
    $github  = trim($_POST['github_url'] ?? '');
    $own = $pdo->prepare("SELECT id FROM projects WHERE id=? AND student_id=?");
    $own->execute([$projId, $uid]);
    if ($own->fetch()) {
        $pdo->prepare("UPDATE projects SET github_url=? WHERE id=?")->execute([$github ?: null, $projId]);
        echo json_encode(['success' => true, 'github_url' => $github]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// ---- Fetch Data ----
$project = $pdo->prepare("SELECT p.*, u.name AS supervisor_name FROM projects p LEFT JOIN users u ON p.supervisor_id=u.id WHERE p.student_id=? ORDER BY p.created_at DESC LIMIT 1");
$project->execute([$uid]);
$project = $project->fetch();

$tasks = [];
if ($project) {
    $t = $pdo->prepare("SELECT t.*, u.name AS validator_name FROM tasks t LEFT JOIN users u ON t.validated_by=u.id WHERE t.project_id=? ORDER BY t.sort_order");
    $t->execute([$project['id']]);
    $tasks = $t->fetchAll();
}

$health   = calculateProjectHealthScore($pdo, $project ? (int)$project['id'] : 0);
$readiness = calculateProjectReadiness($pdo, $project ? (int)$project['id'] : 0, $uid);
$risks     = detectProjectRisks($pdo, $project ? (int)$project['id'] : 0);

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
  <div><h2>Mon Projet</h2><p>Détails et progression de votre projet de fin d'études</p></div>
  <?php if (!$project): ?>
  <a href="dashboard.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Créer un Projet</a>
  <?php endif; ?>
</div>

<?php if (!$project): ?>
<div class="card"><div class="card-body">
  <div class="empty-state">
    <div class="empty-icon">📁</div>
    <h3>Aucun projet</h3>
    <p>Créez votre projet depuis le tableau de bord.</p>
  </div>
</div></div>
<?php else: ?>

<!-- Project Details Card -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <span class="card-title"><?= e($project['title']) ?></span>
    <?= statusBadge($project['status']) ?>
  </div>
  <div class="card-body">
    <?php if ($project['description']): ?>
    <p style="font-size:.875rem;color:var(--text-secondary);line-height:1.7;margin-bottom:16px"><?= nl2br(e($project['description'])) ?></p>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:16px">
      <?php if ($project['technologies']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px">Technologies</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach (explode(',', $project['technologies']) as $tech): ?>
          <span style="background:var(--primary-light);color:var(--primary-dark);padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:500"><?= e(trim($tech)) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($project['supervisor_name']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px">Encadrant</div>
        <div style="font-size:.875rem;font-weight:600"><?= e($project['supervisor_name']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($project['start_date']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px">Date de début</div>
        <div style="font-size:.875rem"><?= date('d/m/Y', strtotime($project['start_date'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($project['end_date']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px">Date de fin prévue</div>
        <div style="font-size:.875rem"><?= date('d/m/Y', strtotime($project['end_date'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($project['final_deadline']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--danger);margin-bottom:6px">Date limite finale</div>
        <div style="font-size:.875rem;font-weight:600;color:var(--danger)"><?= date('d/m/Y', strtotime($project['final_deadline'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- GitHub URL Section -->
    <div style="border-top:1px solid var(--border-light);padding-top:16px;margin-top:8px">
      <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:8px">
        <i class="fa-brands fa-github" style="margin-right:4px"></i>Repository GitHub
      </div>
      <?php if ($project['github_url']): ?>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap" id="githubDisplay">
        <a href="<?= e($project['github_url']) ?>" target="_blank" style="color:var(--primary-dark);font-weight:600;font-size:.875rem;text-decoration:underline;display:flex;align-items:center;gap:6px">
          <i class="fa-brands fa-github"></i><?= e($project['github_url']) ?>
        </a>
        <button class="btn btn-secondary btn-sm" onclick="showGithubEdit()"><i class="fa-solid fa-pen"></i> Modifier</button>
      </div>
      <?php else: ?>
      <div id="githubDisplay" style="color:var(--text-muted);font-size:.85rem;font-style:italic">Aucun repository GitHub configuré. <button class="btn btn-secondary btn-sm" onclick="showGithubEdit()" style="margin-left:8px"><i class="fa-brands fa-github"></i> Ajouter</button></div>
      <?php endif; ?>
      <div id="githubEdit" style="display:none;margin-top:10px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="url" id="githubInput" class="form-control" style="flex:1;min-width:200px" placeholder="https://github.com/username/repo" value="<?= e($project['github_url'] ?? '') ?>">
          <button class="btn btn-primary btn-sm" onclick="saveGithubUrl(<?= $project['id'] ?>)"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
          <button class="btn btn-secondary btn-sm" onclick="hideGithubEdit()">Annuler</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">
  <div style="display:flex;flex-direction:column;gap:24px">

    <!-- Health & Readiness Widgets -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px">
      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-heart-pulse" style="color:var(--danger);margin-right:8px"></i>Santé du Projet</span></div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:15px">
          <div>
            <div style="font-size:2rem;font-weight:800;color:var(--text-primary)" id="healthScoreValue"><?= $health['score'] ?>/100</div>
            <div style="font-size:.9rem;font-weight:600;margin-top:4px" id="healthScoreLabel" class="<?= $health['class'] ?>"><?= $health['emoji'] ?> <?= $health['label'] ?></div>
          </div>
          <div style="font-size:.7rem;color:var(--text-secondary);text-align:right;line-height:1.6;border-left:1px solid var(--border-light);padding-left:10px">
            Complété: 40pts<br>Délais: 30pts<br>Docs: 20pts<br>Retard: -10pts
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title"><i class="fa-solid fa-graduation-cap" style="color:var(--primary);margin-right:8px"></i>Prêt pour la Soutenance</span></div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:15px">
          <div>
            <div style="font-size:2rem;font-weight:800;color:var(--text-primary)" id="readinessValue"><?= $readiness['percentage'] ?>%</div>
            <div style="font-size:.9rem;font-weight:600;margin-top:4px" id="readinessLabel" class="<?= $readiness['class'] ?>"><?= $readiness['emoji'] ?> <?= $readiness['label'] ?></div>
          </div>
          <div style="font-size:.7rem;color:var(--text-secondary);text-align:right;line-height:1.6;border-left:1px solid var(--border-light);padding-left:10px">
            Documents (25%)<br>Tâches (25%)<br>Note (25%)<br>Soutenance (25%)
          </div>
        </div>
      </div>
    </div>

    <!-- Risk Detection Widget -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:8px"></i>Détection des Risques</span></div>
      <div class="card-body" style="padding:18px 22px">
        <?php if (empty($risks)): ?>
          <div class="alert alert-success" style="margin-bottom:0"><i class="fa-solid fa-circle-check"></i> ✅ Aucun risque détecté</div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($risks as $r): ?>
              <div class="alert alert-<?= $r['color'] ?>" style="margin-bottom:0;display:flex;align-items:center;justify-content:space-between;gap:10px">
                <div>
                  <i class="fa-solid fa-circle-exclamation"></i>
                  <strong><?= $r['level'] ?> :</strong> <?= e($r['task_title']) ?> — Échéance : <?= $r['deadline'] ?>
                </div>
                <span class="badge badge-<?= $r['color'] === 'warning' ? 'pending' : ($r['color'] === 'info' ? 'active' : 'rejected') ?>"><?= $r['days_remaining'] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tasks Table -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px"></i>Tâches</span>
        <span style="font-size:.8rem;color:var(--text-muted)"><?= count(array_filter($tasks, fn($t)=>$t['status']==='completed')) ?>/<?= count($tasks) ?> terminées</span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Tâche</th><th>Statut</th><th>Échéance</th><th>Modifier</th></tr></thead>
          <tbody>
          <?php foreach ($tasks as $task): ?>
          <tr data-task="<?= $task['id'] ?>">
            <td style="font-weight:500"><?= e($task['title']) ?></td>
            <td class="badge-cell"><?= statusBadge($task['status']) ?></td>
            <td style="color:var(--text-muted)"><?= $task['deadline'] ? date('d/m/Y', strtotime($task['deadline'])) : '—' ?></td>
            <td>
              <?php if ($task['status'] === 'completed'): ?>
                <span style="font-size:.78rem;color:var(--success);font-weight:600">
                  <i class="fa-solid fa-lock"></i> Validé
                </span>
              <?php else: ?>
                <select class="filter-select" style="font-size:.78rem;padding:5px 10px"
                  onchange="updateTaskStatus(<?= $task['id'] ?>, this.value)">
                  <option value="not_started" <?= $task['status']==='not_started'?'selected':'' ?>>Non démarré</option>
                  <option value="in_progress" <?= $task['status']==='in_progress'?'selected':'' ?>>En cours</option>
                  <option value="pending_validation" <?= $task['status']==='pending_validation'?'selected':'' ?>>En attente de validation</option>
                </select>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($task['status'] === 'revision_necessaire' && !empty($task['revision_comment'])): ?>
          <tr>
            <td colspan="4" style="background:#FFF5F5;padding:10px 16px;border-bottom:1px solid var(--border-light)">
              <div style="color:var(--danger);font-size:.8rem;font-weight:600;margin-bottom:4px">
                <i class="fa-solid fa-circle-exclamation"></i> Commentaire de révision :
              </div>
              <div style="font-size:.85rem;color:var(--text-primary);padding-left:20px"><?= nl2br(e($task['revision_comment'])) ?></div>
            </td>
          </tr>
          <?php endif; ?>
          <?php if ($task['status'] === 'completed'): ?>
          <tr>
            <td colspan="4" style="background:#F0FDF4;padding:8px 16px;border-bottom:1px solid var(--border-light)">
              <div style="font-size:.78rem;color:var(--success);display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-circle-check"></i>
                Validé par <strong><?= e($task['validator_name'] ?? 'Enseignant') ?></strong>
                <?php if ($task['validated_at']): ?>
                · le <?= date('d/m/Y à H:i', strtotime($task['validated_at'])) ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Interactive Timeline -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fa-solid fa-timeline" style="color:var(--primary);margin-right:8px"></i>Chronologie du Projet</span></div>
      <div class="card-body" style="padding:20px 24px">
        <div style="position:relative;padding-left:28px">
          <!-- vertical line -->
          <div style="position:absolute;left:9px;top:0;bottom:0;width:2px;background:var(--border)"></div>
          <?php foreach ($tasks as $i => $task):
            $icon = match($task['status']) {
              'completed'           => ['✓', '#059669', '#ECFDF5'],
              'in_progress'         => ['⏳', '#D97706', '#FFFBEB'],
              'pending_validation'  => ['⏳', '#D97706', '#FFFBEB'],
              'revision_necessaire' => ['⚠️', '#DC2626', '#FEF2F2'],
              default               => ['⌛', '#94A3B8', '#F1F5F9'],
            };
          ?>
          <div style="position:relative;margin-bottom:<?= ($i < count($tasks)-1) ? '20px' : '12px' ?>">
            <div style="position:absolute;left:-28px;top:2px;width:20px;height:20px;border-radius:50%;background:<?= $icon[2] ?>;display:flex;align-items:center;justify-content:center;font-size:10px;z-index:1">
              <?= $icon[0] ?>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
              <div>
                <div style="font-size:.875rem;font-weight:600;color:<?= $icon[1] ?>"><?= e($task['title']) ?></div>
                <?php if ($task['status'] === 'revision_necessaire' && !empty($task['revision_comment'])): ?>
                <div style="font-size:.75rem;color:var(--danger);margin-top:2px"><i class="fa-solid fa-message" style="margin-right:3px"></i><?= e(mb_substr($task['revision_comment'], 0, 60)) ?>...</div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <?= statusBadge($task['status']) ?>
                <?php if ($task['deadline']): ?>
                <div style="font-size:.72rem;color:var(--text-muted);margin-top:4px"><i class="fa-regular fa-calendar" style="margin-right:3px"></i><?= date('d/m/Y', strtotime($task['deadline'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if ($project['final_deadline']): ?>
          <!-- Final deadline marker -->
          <div style="position:relative;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border)">
            <div style="position:absolute;left:-28px;top:14px;width:20px;height:20px;border-radius:50%;background:#FEF2F2;display:flex;align-items:center;justify-content:center;font-size:10px;z-index:1">🏁</div>
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div style="font-size:.875rem;font-weight:700;color:var(--danger)">Date Limite Finale</div>
              <div style="font-size:.82rem;color:var(--danger);font-weight:600"><?= date('d/m/Y', strtotime($project['final_deadline'])) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Right Column -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Circular Progress -->
    <div class="card">
      <div class="card-header"><span class="card-title">Progression</span></div>
      <div class="card-body" style="text-align:center">
        <div style="position:relative;width:120px;height:120px;margin:0 auto 16px">
          <svg viewBox="0 0 36 36" style="width:120px;height:120px;transform:rotate(-90deg)">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--border)" stroke-width="3"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--primary-dark)" stroke-width="3"
              stroke-dasharray="<?= (int)$project['progress'] ?> 100" stroke-linecap="round" class="progress-circle-text"/>
          </svg>
          <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:var(--primary-dark)">
            <span class="progress-circle-text"><?= (int)$project['progress'] ?>%</span>
          </div>
        </div>
        <div style="font-size:.85rem;color:var(--text-secondary)">Avancement global</div>
        <!-- Linear progress bar also -->
        <div class="progress-wrap" style="margin-top:12px">
          <div class="progress-bar" data-width="<?= (int)$project['progress'] ?>%" style="width:<?= (int)$project['progress'] ?>%"></div>
        </div>
        <div class="progress-label" style="font-size:.82rem;font-weight:700;color:var(--primary-dark);margin-top:6px"><?= (int)$project['progress'] ?>%</div>
      </div>
    </div>

    <!-- Stats mini-card -->
    <div class="card">
      <div class="card-header"><span class="card-title">Résumé</span></div>
      <div class="card-body" style="padding:16px 22px">
        <?php
          $completedCount = count(array_filter($tasks, fn($t)=>$t['status']==='completed'));
          $pendingCount   = count(array_filter($tasks, fn($t)=>$t['status']==='pending_validation'));
          $revisionCount  = count(array_filter($tasks, fn($t)=>$t['status']==='revision_necessaire'));
        ?>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.82rem;color:var(--text-secondary)">✅ Validées</span>
            <strong style="color:var(--success)"><?= $completedCount ?>/<?= count($tasks) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.82rem;color:var(--text-secondary)">⏳ En attente</span>
            <strong style="color:var(--warning)"><?= $pendingCount ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.82rem;color:var(--text-secondary)">⚠️ Révision</span>
            <strong style="color:var(--danger)"><?= $revisionCount ?></strong>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<script>
function showGithubEdit() {
  document.getElementById('githubDisplay').style.display = 'none';
  document.getElementById('githubEdit').style.display = 'block';
  document.getElementById('githubInput').focus();
}
function hideGithubEdit() {
  document.getElementById('githubEdit').style.display = 'none';
  document.getElementById('githubDisplay').style.display = '';
}
function saveGithubUrl(projId) {
  const url = document.getElementById('githubInput').value.trim();
  fetch('projects.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=update_github&project_id=${projId}&github_url=${encodeURIComponent(url)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // Refresh to show updated link
      location.reload();
    } else {
      alert('Erreur lors de la mise à jour.');
    }
  });
}
function updateTaskStatus(taskId, newStatus) {
  fetch('projects.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=update_task&task_id=${taskId}&status=${newStatus}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Erreur lors de la mise à jour.');
      location.reload();
    }
  })
  .catch(() => { alert('Erreur réseau.'); location.reload(); });
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
