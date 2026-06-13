/* ============================================
   PFE Manager - Main JavaScript
   ============================================ */

// ---- Sidebar Toggle (Mobile) ----
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar) sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('show');
}

// ---- Modal Helpers ----
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});

// Close modal on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.show').forEach(m => {
      m.classList.remove('show');
      document.body.style.overflow = '';
    });
  }
});

// ---- Alert Auto-dismiss ----
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.alert[data-autohide]').forEach(function(alert) {
    setTimeout(function() {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity .4s ease';
      setTimeout(() => alert.remove(), 400);
    }, 4000);
  });
});

// ---- Active Nav Link ----
document.addEventListener('DOMContentLoaded', function() {
  const current = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href && current.endsWith(href.split('/').pop())) {
      link.classList.add('active');
    }
  });
});

// ---- Task Status Update (AJAX) ----
function updateTaskStatus(taskId, newStatus) {
  const row = document.querySelector(`tr[data-task="${taskId}"]`);

  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=update_task&task_id=${taskId}&status=${newStatus}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Update badge
      if (row) {
        const badgeCell = row.querySelector('.badge-cell');
        if (badgeCell) badgeCell.innerHTML = data.badge;
      }
      // Update progress
      if (data.progress !== undefined) updateProgressBar(data.progress);
    } else {
      alert('Erreur lors de la mise à jour.');
    }
  })
  .catch(() => {
    alert('Erreur réseau — vérifiez la connexion.');
  });
}

function updateProgressBar(pct) {
  // Progress bar (dashboard)
  const bar = document.querySelector('.progress-bar');
  if (bar) bar.style.width = pct + '%';

  // Progress label text
  const label = document.querySelector('.progress-label');
  if (label) label.textContent = pct + '%';

  // Circular SVG progress (projects page)
  const circle = document.querySelector('circle[stroke="var(--primary-dark)"]');
  if (circle) circle.setAttribute('stroke-dasharray', pct + ' 100');

  // Circular text
  const circleText = document.querySelector('.progress-circle-text');
  if (circleText) circleText.textContent = pct + '%';
}

// ---- File Upload Preview ----
function setupFileInput(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const preview = document.getElementById(inputId + '_preview');
    if (preview) {
      preview.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
      preview.style.color = 'var(--primary-dark)';
    }
  });
}

// ---- Confirm Delete ----
function confirmDelete(message, form) {
  if (confirm(message || 'Êtes-vous sûr ?')) form.submit();
}

// ---- Simple Search Filter (client-side) ----
function setupSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ---- Format file size ----
function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

// ---- Init on DOM ready ----
document.addEventListener('DOMContentLoaded', function() {
  // Setup file inputs
  ['document_file', 'attachment'].forEach(setupFileInput);

  // Animate stat cards on load
  document.querySelectorAll('.stat-card').forEach((card, i) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(16px)';
    setTimeout(() => {
      card.style.transition = 'opacity .4s ease, transform .4s ease';
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, i * 80);
  });

  // Animate progress bars
  document.querySelectorAll('.progress-bar').forEach(bar => {
    const target = bar.getAttribute('data-width') || bar.style.width;
    bar.style.width = '0';
    setTimeout(() => { bar.style.width = target; }, 300);
  });

  // Tooltip (simple title-based)
  document.querySelectorAll('[data-tooltip]').forEach(el => {
    el.title = el.getAttribute('data-tooltip');
  });
});
