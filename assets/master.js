/* =============================================
   MASTER DATA — master.js
   ============================================= */

// ── Tab switching (persists via URL) ──────────
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.tab;

    document.querySelectorAll('.tab').forEach(t => t.classList.remove('tab--active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('tab-panel--active'));

    tab.classList.add('tab--active');
    document.getElementById('tab-' + target).classList.add('tab-panel--active');

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', target);
    window.history.replaceState({}, '', url);
  });
});

// ── Auto dismiss alert ────────────────────────
const alertMsg = document.getElementById('alertMsg');
if (alertMsg) {
  setTimeout(() => alertMsg.style.opacity = '0', 3000);
  setTimeout(() => alertMsg.style.display = 'none', 3500);
}

// ── Modal helpers ─────────────────────────────
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) overlay.classList.remove('show');
  });
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
  }
});

// ── Edit Department ───────────────────────────
function openEditDept(data) {
  console.log(data); // ← add this
  document.getElementById('editDeptId').value = data.id;
  document.getElementById('editDeptCode').value = data.code;
  document.getElementById('editDeptName').value = data.name;
  document.getElementById('editDeptHead').value = data.head_id ?? '';
  document.getElementById('editDeptDesc').value = data.description ?? '';
  document.getElementById('editDeptActive').checked = data.is_active == 1;
  openModal('editDeptModal');
}

// ── Edit Location ─────────────────────────────
function openEditLoc(data) {
  document.getElementById('editLocId').value = data.id;
  document.getElementById('editLocCode').value = data.code;
  document.getElementById('editLocName').value = data.name;
  document.getElementById('editLocDesc').value = data.description ?? '';
  document.getElementById('editLocDept').value = data.department_id ?? '';
  document.getElementById('editLocActive').checked = data.is_active == 1;
  openModal('editLocModal');
}

// ── Edit User ─────────────────────────────────
function openEditUser(data) {
  document.getElementById('editUserId').value = data.id;
  document.getElementById('editUserEmpId').value = data.employee_id;
  document.getElementById('editUserName').value = data.name;
  document.getElementById('editUserEmail').value = data.email;
  document.getElementById('editUserPosition').value = data.position ?? '';
  document.getElementById('editUserRole').value = data.role;
  document.getElementById('editUserActive').checked = data.is_active == 1;
  openModal('editUserModal');
}
