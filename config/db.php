<?php
// ============================================
// PFE Manager - Database Configuration
// Edit these values to match your XAMPP setup
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty password
define('DB_NAME', 'pfe_manager');
define('DB_CHARSET', 'utf8mb4');

// App settings
define('APP_NAME', 'PFE Manager');
define('APP_URL', 'http://localhost/pfe_manager');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Session lifetime (seconds)
define('SESSION_LIFETIME', 7200);

// ============================================
// PDO Connection
// ============================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    ini_set('default_charset', 'UTF-8');
    mb_internal_encoding('UTF-8');
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
   $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
   $pdo->exec("SET NAMES 'utf8mb4'");
        header('Content-Type: text/html; charset=utf-8');
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}


// ============================================
// Session Start
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_start();
}

// ============================================
// Helper Functions
// ============================================

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . APP_URL . '/login.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['user_id']   ?? 0,
        'name'  => $_SESSION['name']      ?? '',
        'email' => $_SESSION['email']     ?? '',
        'role'  => $_SESSION['role']      ?? '',
    ];
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function statusBadge(string $status): string {
    $map = [
        'not_started'         => ['label' => 'Not Started', 'class' => 'badge-not-started'],
        'in_progress'         => ['label' => 'In Progress', 'class' => 'badge-in-progress'],
        'completed'           => ['label' => 'Completed',   'class' => 'badge-completed'],
        'pending'             => ['label' => 'Pending',     'class' => 'badge-pending'],
        'approved'            => ['label' => 'Approved',    'class' => 'badge-approved'],
        'rejected'            => ['label' => 'Rejected',    'class' => 'badge-rejected'],
        'active'              => ['label' => 'Active',      'class' => 'badge-active'],
        'scheduled'           => ['label' => 'Scheduled',   'class' => 'badge-scheduled'],
        'pending_validation'  => ['label' => 'En attente', 'class' => 'badge-pending-validation'],
        'revision_necessaire' => ['label' => 'Révision nécessaire', 'class' => 'badge-revision-necessaire'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-default'];
    return '<span class="badge ' . $s['class'] . '">' . $s['label'] . '</span>';
}

function recalculateProjectProgress(PDO $pdo, int $projectId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $total = (int)$stmt->fetchColumn();
    
    if ($total === 0) {
        $pct = 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND status = 'completed'");
        $stmt->execute([$projectId]);
        $completed = (int)$stmt->fetchColumn();
        $pct = (int)round(($completed / $total) * 100);
    }
    
    $stmt = $pdo->prepare("UPDATE projects SET progress = ? WHERE id = ?");
    $stmt->execute([$pct, $projectId]);
    
    return $pct;
}

function getRankEmoji(int $rank): string {
    if ($rank === 1) return '🥇';
    if ($rank === 2) return '🥈';
    if ($rank === 3) return '🥉';
    return (string)$rank;
}

function calculateProjectHealthScore(PDO $pdo, int $projectId): array {
    if (!$projectId) {
        return [
            'score' => 0,
            'label' => 'Critical',
            'class' => 'text-danger',
            'badge_class' => 'badge-rejected',
            'emoji' => '🔴'
        ];
    }
    
    // Fetch tasks
    $stmt = $pdo->prepare("SELECT status, deadline, validated_at FROM tasks WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll();
    $totalTasks = count($tasks);
    
    $completed = 0;
    $onTime = 0;
    $hasOverdue = false;
    $today = date('Y-m-d');
    
    foreach ($tasks as $t) {
        if ($t['status'] === 'completed') {
            $completed++;
            // Check deadline respect
            if (empty($t['deadline']) || (!empty($t['validated_at']) && date('Y-m-d', strtotime($t['validated_at'])) <= $t['deadline'])) {
                $onTime++;
            }
        } else {
            // Check if overdue
            if (!empty($t['deadline']) && $t['deadline'] < $today) {
                $hasOverdue = true;
            }
        }
    }
    
    // Points calculation
    $taskPoints = 0;
    $deadlinePoints = 0;
    if ($totalTasks > 0) {
        $taskPoints = ($completed / $totalTasks) * 40;
        $deadlinePoints = ($onTime / $totalTasks) * 30;
    }
    
    // Documents uploaded
    $stmtDoc = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE project_id = ?");
    $stmtDoc->execute([$projectId]);
    $docsCount = (int)$stmtDoc->fetchColumn();
    $docPoints = (min($docsCount, 5) / 5) * 20;
    
    // Delay penalty
    $penalty = $hasOverdue ? -10 : 0;
    
    $score = $taskPoints + $deadlinePoints + $docPoints + $penalty;
    $score = max(0, min(100, round($score)));
    
    if ($score >= 80) {
        $label = 'Excellent';
        $class = 'text-success';
        $badgeClass = 'badge-completed';
        $emoji = '🟢';
    } elseif ($score >= 60) {
        $label = 'Good';
        $class = 'text-primary';
        $badgeClass = 'badge-active';
        $emoji = '🟡';
    } elseif ($score >= 40) {
        $label = 'Needs Improvement';
        $class = 'text-warning';
        $badgeClass = 'badge-pending';
        $emoji = '🟠';
    } else {
        $label = 'Critical';
        $class = 'text-danger';
        $badgeClass = 'badge-rejected';
        $emoji = '🔴';
    }
    
    return [
        'score' => $score,
        'label' => $label,
        'class' => $class,
        'badge_class' => $badgeClass,
        'emoji' => $emoji
    ];
}

function calculateProjectReadiness(PDO $pdo, int $projectId, int $studentId): array {
    if (!$projectId) {
        return [
            'percentage' => 0,
            'label' => 'Not Ready',
            'class' => 'text-danger',
            'emoji' => '🔴'
        ];
    }
    
    // 1. Documents Contribution
    $stmtDoc = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE project_id = ?");
    $stmtDoc->execute([$projectId]);
    $docsCount = (int)$stmtDoc->fetchColumn();
    $docContrib = min($docsCount / 3, 1.0) * 25;
    
    // 2. Tasks Contribution
    $stmtTasks = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ?");
    $stmtTasks->execute([$projectId]);
    $totalTasks = (int)$stmtTasks->fetchColumn();
    
    $taskContrib = 0;
    if ($totalTasks > 0) {
        $stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND status = 'completed'");
        $stmtCompleted->execute([$projectId]);
        $completedTasks = (int)$stmtCompleted->fetchColumn();
        $taskContrib = ($completedTasks / $totalTasks) * 25;
    }
    
    // 3. Grade Contribution
    $stmtGrade = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE project_id = ?");
    $stmtGrade->execute([$projectId]);
    $hasGrade = ((int)$stmtGrade->fetchColumn()) > 0;
    $gradeContrib = $hasGrade ? 25 : 0;
    
    // 4. Defense Contribution
    $stmtDefense = $pdo->prepare("SELECT COUNT(*) FROM defenses WHERE project_id = ?");
    $stmtDefense->execute([$projectId]);
    $hasDefense = ((int)$stmtDefense->fetchColumn()) > 0;
    $defenseContrib = $hasDefense ? 25 : 0;
    
    $readiness = round($docContrib + $taskContrib + $gradeContrib + $defenseContrib);
    
    if ($readiness >= 75) {
        $label = 'Ready for Defense';
        $class = 'text-success';
        $emoji = '🟢';
    } elseif ($readiness >= 50) {
        $label = 'Almost Ready';
        $class = 'text-warning';
        $emoji = '🟡';
    } else {
        $label = 'Not Ready';
        $class = 'text-danger';
        $emoji = '🔴';
    }
    
    return [
        'percentage' => $readiness,
        'label' => $label,
        'class' => $class,
        'emoji' => $emoji
    ];
}

function detectProjectRisks(PDO $pdo, int $projectId): array {
    if (!$projectId) {
        return [];
    }
    
    $stmt = $pdo->prepare("SELECT title, status, deadline FROM tasks WHERE project_id = ? AND deadline IS NOT NULL AND status != 'completed'");
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll();
    
    $risks = [];
    $today = new DateTime(date('Y-m-d'));
    
    foreach ($tasks as $t) {
        $deadline = new DateTime($t['deadline']);
        $diff = $today->diff($deadline);
        $days = (int)$diff->format('%r%a'); // Will be negative if overdue
        
        $level = '';
        $color = '';
        $emoji = '';
        
        if ($t['status'] === 'not_started') {
            if ($days <= 3) {
                $level = 'High Risk';
                $color = 'danger';
                $emoji = '🔴';
            } elseif ($days <= 7) {
                $level = 'Medium Risk';
                $color = 'warning';
                $emoji = '🟠';
            }
        } elseif ($t['status'] === 'in_progress') {
            if ($days <= 2) {
                $level = 'Low Risk';
                $color = 'info';
                $emoji = '🟡';
            }
        }
        
        if ($level) {
            $daysLabel = $days < 0 ? abs($days) . " jours en retard" : ($days == 0 ? "aujourd'hui" : "$days jours restants");
            $risks[] = [
                'task_title' => $t['title'],
                'deadline' => date('d/m/Y', strtotime($t['deadline'])),
                'level' => $level,
                'color' => $color,
                'emoji' => $emoji,
                'days_remaining' => $daysLabel
            ];
        }
    }
    
    // Sort risks by severity: High, then Medium, then Low
    usort($risks, function($a, $b) {
        $map = ['High Risk' => 1, 'Medium Risk' => 2, 'Low Risk' => 3];
        return $map[$a['level']] <=> $map[$b['level']];
    });
    
    return $risks;
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return floor($diff/60) . ' min ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'j ago';
    return date('d/m/Y', $time);
}

function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $init = '';
    foreach (array_slice($parts, 0, 2) as $p) $init .= strtoupper($p[0]);
    return $init;
}
