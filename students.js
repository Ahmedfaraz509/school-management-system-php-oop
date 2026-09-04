/**
 * Students Directory - Frontend Controller (EduPulse SMS)
 *
 * Talks to api/students.php (EduPulse\Controllers\StudentController).
 * This file was missing entirely, which is why the Students page never
 * loaded any data or responded to Add/Edit/Delete/Search/Filter/Export.
 */
const StudentsApp = (() => {
  const API_URL = 'api/students.php';

  let state = {
    page: 1,
    search: '',
    classId: '',
    gender: '',
    status: '',
    searchDebounce: null
  };

  let csrfToken = null;

  /* ---------------------------------------------------------------- */
  /* Init                                                              */
  /* ---------------------------------------------------------------- */

  function init() {
    csrfToken = document.querySelector('#addStudentForm input[name="csrf_token"]')?.value || '';

    bindToolbar();
    bindAddForm();
    bindEditForm();
    bindDeleteForm();
    bindTableActions();

    loadStudents();
  }

  function bindToolbar() {
    const searchInput = document.getElementById('searchInput');
    const filterClass = document.getElementById('filterClass');
    const filterGender = document.getElementById('filterGender');
    const filterStatus = document.getElementById('filterStatus');
    const resetBtn = document.getElementById('btnResetFilters');
    const exportBtn = document.getElementById('btnExport');

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(state.searchDebounce);
        state.searchDebounce = setTimeout(() => {
          state.search = searchInput.value.trim();
          state.page = 1;
          loadStudents();
        }, 350);
      });
    }

    if (filterClass) {
      filterClass.addEventListener('change', () => {
        state.classId = filterClass.value;
        state.page = 1;
        loadStudents();
      });
    }

    if (filterGender) {
      filterGender.addEventListener('change', () => {
        state.gender = filterGender.value;
        state.page = 1;
        loadStudents();
      });
    }

    if (filterStatus) {
      filterStatus.addEventListener('change', () => {
        state.status = filterStatus.value;
        state.page = 1;
        loadStudents();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        state = { ...state, page: 1, search: '', classId: '', gender: '', status: '' };
        if (searchInput) searchInput.value = '';
        if (filterClass) filterClass.value = '';
        if (filterGender) filterGender.value = '';
        if (filterStatus) filterStatus.value = '';
        loadStudents();
        showToast('Filters Reset', 'All filters have been cleared.', 'info');
      });
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const params = buildQuery({ export: true });
        window.open(`${API_URL}?${params}`, '_blank');
      });
    }
  }

  function bindTableActions() {
    const tbody = document.getElementById('studentsTableBody');
    if (!tbody) return;

    tbody.addEventListener('click', (e) => {
      const viewBtn = e.target.closest('[data-role="view-student"]');
      const editBtn = e.target.closest('[data-role="edit-student"]');
      const deleteBtn = e.target.closest('[data-role="delete-student"]');

      if (viewBtn) {
        openViewModal(viewBtn.getAttribute('data-id'));
      } else if (editBtn) {
        openEditModal(editBtn.getAttribute('data-id'));
      } else if (deleteBtn) {
        openDeleteModal(deleteBtn.getAttribute('data-id'), deleteBtn.getAttribute('data-name'));
      }
    });

    const paginationList = document.getElementById('paginationList');
    if (paginationList) {
      paginationList.addEventListener('click', (e) => {
        const link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        const page = parseInt(link.getAttribute('data-page'), 10);
        if (!Number.isNaN(page) && page !== state.page) {
          state.page = page;
          loadStudents();
        }
      });
    }
  }

  /* ---------------------------------------------------------------- */
  /* Load / render list                                                */
  /* ---------------------------------------------------------------- */

  function buildQuery(extra = {}) {
    const params = new URLSearchParams();
    params.set('action', extra.export ? 'export' : 'index');
    params.set('page', state.page);
    if (state.search) params.set('search', state.search);
    if (state.classId) params.set('class_id', state.classId);
    if (state.gender) params.set('gender', state.gender);
    if (state.status) params.set('status', state.status);
    return params.toString();
  }

  async function loadStudents() {
    const tbody = document.getElementById('studentsTableBody');
    const recordCount = document.getElementById('recordCount');
    const paginationInfo = document.getElementById('paginationInfo');

    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading students...</p>
          </td>
        </tr>
      `;
    }

    try {
      const res = await fetch(`${API_URL}?${buildQuery()}`, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const json = await res.json();

      if (!json.success) {
        renderEmpty(json.message || 'Unable to load students.');
        return;
      }

      renderRows(json.data);
      renderPagination(json.pagination);

      if (recordCount) {
        recordCount.textContent = `${json.pagination.total_records} Student${json.pagination.total_records === 1 ? '' : 's'}`;
      }
      if (paginationInfo) {
        const { current_page, total_pages, total_records, per_page } = json.pagination;
        const start = total_records === 0 ? 0 : (current_page - 1) * per_page + 1;
        const end = Math.min(current_page * per_page, total_records);
        paginationInfo.textContent = `Showing ${start}-${end} of ${total_records} (Page ${current_page} of ${total_pages})`;
      }
    } catch (err) {
      renderEmpty('Something went wrong while loading students. Please try again.');
      showToast('Error', 'Failed to reach the server.', 'danger');
    }
  }

  function statusBadgeClass(status) {
    switch (status) {
      case 'Active':
        return 'badge-subtle-success';
      case 'Pending Doc':
        return 'badge-subtle-warning';
      case 'Inactive':
        return 'badge-subtle-secondary';
      default:
        return 'badge-subtle-secondary';
    }
  }

  function renderRows(students) {
    const tbody = document.getElementById('studentsTableBody');
    if (!tbody) return;

    if (!students || students.length === 0) {
      renderEmpty('No students found. Try adjusting your search or filters.');
      return;
    }

    tbody.innerHTML = students.map((s) => {
      const fullName = escapeHtml(`${s.first_name} ${s.last_name}`);
      const initials = `${(s.first_name || '?')[0] || ''}${(s.last_name || '?')[0] || ''}`.toUpperCase();
      const photo = s.photo_url
        ? `<img src="${escapeHtml(s.photo_url)}" class="rounded-circle" width="36" height="36" alt="${fullName}">`
        : `<div class="avatar-initials rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:0.75rem;font-weight:600;">${initials}</div>`;

      return `
        <tr>
          <td><span class="fw-semibold">${escapeHtml(s.student_uid)}</span></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              ${photo}
              <div>
                <div class="fw-semibold">${fullName}</div>
              </div>
            </div>
          </td>
          <td>${escapeHtml(s.gender || '-')}</td>
          <td>${escapeHtml(s.class_name || 'Unassigned')}</td>
          <td>${escapeHtml(s.email)}</td>
          <td><span class="badge-subtle ${statusBadgeClass(s.status)}">${escapeHtml(s.status)}</span></td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-light" title="View" data-role="view-student" data-id="${s.id}">
              <i class="bi bi-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" title="Edit" data-role="edit-student" data-id="${s.id}">
              <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light text-danger" title="Delete" data-role="delete-student" data-id="${s.id}" data-name="${fullName}">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function renderEmpty(message) {
    const tbody = document.getElementById('studentsTableBody');
    if (!tbody) return;
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center py-4 text-muted">
          <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary opacity-50"></i>
          <span>${escapeHtml(message)}</span>
        </td>
      </tr>
    `;
  }

  function renderPagination(pagination) {
    const list = document.getElementById('paginationList');
    if (!list || !pagination) return;

    const { current_page, total_pages } = pagination;
    let html = '';

    html += pageItem(current_page - 1, '&laquo;', current_page === 1);
    for (let p = 1; p <= total_pages; p++) {
      html += pageItem(p, String(p), false, p === current_page);
    }
    html += pageItem(current_page + 1, '&raquo;', current_page === total_pages);

    list.innerHTML = html;
  }

  function pageItem(page, label, disabled, active = false) {
    return `
      <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
        <a class="page-link" href="#" data-page="${page}">${label}</a>
      </li>
    `;
  }

  /* ---------------------------------------------------------------- */
  /* Add student                                                       */
  /* ---------------------------------------------------------------- */

  function bindAddForm() {
    const form = document.getElementById('addStudentForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearFormErrors(form);

      const submitBtn = document.getElementById('btnAddStudent');
      setBusy(submitBtn, true);

      try {
        const formData = new FormData(form);
        const res = await fetch(`${API_URL}?action=create`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const json = await res.json();

        if (!json.success) {
          if (json.errors) showFormErrors(form, json.errors);
          showToast('Error', json.message || 'Unable to register student.', 'danger');
          return;
        }

        showToast('Success', json.message || 'Student registered successfully!', 'success');
        closeModal('addStudentModal');
        form.reset();
        syncCsrfToken(json.data?.csrf_token);
        state.page = 1;
        loadStudents();
      } catch (err) {
        showToast('Error', 'Something went wrong. Please try again.', 'danger');
      } finally {
        setBusy(submitBtn, false);
      }
    });
  }

  /* ---------------------------------------------------------------- */
  /* Edit student                                                      */
  /* ---------------------------------------------------------------- */

  function bindEditForm() {
    const form = document.getElementById('editStudentForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearFormErrors(form);

      const submitBtn = document.getElementById('btnUpdateStudent');
      setBusy(submitBtn, true);

      try {
        const formData = new FormData(form);
        const res = await fetch(`${API_URL}?action=update`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const json = await res.json();

        if (!json.success) {
          if (json.errors) showFormErrors(form, json.errors);
          showToast('Error', json.message || 'Unable to update student.', 'danger');
          return;
        }

        showToast('Success', json.message || 'Student updated successfully!', 'success');
        closeModal('editStudentModal');
        syncCsrfToken(json.data?.csrf_token);
        loadStudents();
      } catch (err) {
        showToast('Error', 'Something went wrong. Please try again.', 'danger');
      } finally {
        setBusy(submitBtn, false);
      }
    });
  }

  async function openEditModal(id) {
    try {
      const res = await fetch(`${API_URL}?action=get&id=${encodeURIComponent(id)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const json = await res.json();

      if (!json.success) {
        showToast('Error', json.message || 'Unable to load student.', 'danger');
        return;
      }

      const s = json.data.student;
      const form = document.getElementById('editStudentForm');
      if (!form) return;

      form.querySelector('#edit_student_id').value = s.id;
      form.querySelector('#edit_first_name').value = s.first_name || '';
      form.querySelector('#edit_last_name').value = s.last_name || '';
      form.querySelector('#edit_gender').value = s.gender || 'Male';
      form.querySelector('#edit_date_of_birth').value = s.date_of_birth || '';
      form.querySelector('#edit_class_id').value = s.class_id || '';
      form.querySelector('#edit_email').value = s.email || '';
      form.querySelector('#edit_status').value = s.status || 'Active';
      form.querySelector('#edit_admission_date').value = s.admission_date || '';
      form.querySelector('#edit_address').value = s.address || '';
      form.querySelector('input[name="password"]').value = '';

      const preview = document.getElementById('currentPhotoPreview');
      if (preview) {
        preview.innerHTML = s.photo_url
          ? `<img src="${escapeHtml(s.photo_url)}" class="rounded" width="60" height="60" alt="Current photo">`
          : '<span class="text-muted small">No photo uploaded</span>';
      }

      openModal('editStudentModal');
    } catch (err) {
      showToast('Error', 'Unable to load student details.', 'danger');
    }
  }

  /* ---------------------------------------------------------------- */
  /* View student                                                      */
  /* ---------------------------------------------------------------- */

  async function openViewModal(id) {
    openModal('viewStudentModal');

    const nameEl = document.getElementById('view_name');
    const idEl = document.getElementById('view_student_id');
    const statusEl = document.getElementById('view_status_badge');
    const photoEl = document.getElementById('view_photo');
    const detailsEl = document.getElementById('view_details');

    if (nameEl) nameEl.textContent = 'Loading...';
    if (idEl) idEl.textContent = '';
    if (statusEl) statusEl.textContent = '';
    if (detailsEl) detailsEl.innerHTML = '';

    try {
      const res = await fetch(`${API_URL}?action=get&id=${encodeURIComponent(id)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const json = await res.json();

      if (!json.success) {
        showToast('Error', json.message || 'Unable to load student.', 'danger');
        closeModal('viewStudentModal');
        return;
      }

      const s = json.data.student;

      if (nameEl) nameEl.textContent = `${s.first_name} ${s.last_name}`;
      if (idEl) idEl.textContent = s.student_uid;
      if (statusEl) {
        statusEl.textContent = s.status;
        statusEl.className = `badge-subtle ${statusBadgeClass(s.status)}`;
      }
      if (photoEl) photoEl.src = s.photo_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(`${s.first_name} ${s.last_name}`);

      if (detailsEl) {
        const rows = [
          ['Email', s.email],
          ['Gender', s.gender],
          ['Date of Birth', s.date_of_birth],
          ['Class', s.class_name || 'Unassigned'],
          ['Admission Date', s.admission_date],
          ['Address', s.address || '-']
        ];
        detailsEl.innerHTML = rows.map(([label, value]) => `
          <div class="col-6">
            <div class="text-muted">${escapeHtml(label)}</div>
            <div class="fw-semibold">${escapeHtml(String(value ?? '-'))}</div>
          </div>
        `).join('');
      }
    } catch (err) {
      showToast('Error', 'Unable to load student details.', 'danger');
    }
  }

  /* ---------------------------------------------------------------- */
  /* Delete student                                                    */
  /* ---------------------------------------------------------------- */

  function bindDeleteForm() {
    const form = document.getElementById('deleteStudentForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = document.getElementById('btnConfirmDelete');
      setBusy(submitBtn, true);

      try {
        const formData = new FormData(form);
        const res = await fetch(`${API_URL}?action=delete`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const json = await res.json();

        if (!json.success) {
          showToast('Error', json.message || 'Unable to delete student.', 'danger');
          return;
        }

        showToast('Deleted', json.message || 'Student deleted successfully!', 'success');
        closeModal('deleteConfirmModal');
        syncCsrfToken(json.data?.csrf_token);
        loadStudents();
      } catch (err) {
        showToast('Error', 'Something went wrong. Please try again.', 'danger');
      } finally {
        setBusy(submitBtn, false);
      }
    });
  }

  function openDeleteModal(id, name) {
    const form = document.getElementById('deleteStudentForm');
    if (!form) return;
    form.querySelector('#delete_student_id').value = id;
    const nameEl = document.getElementById('deleteStudentName');
    if (nameEl) nameEl.textContent = name || 'this student';
    openModal('deleteConfirmModal');
  }

  /* ---------------------------------------------------------------- */
  /* Helpers                                                            */
  /* ---------------------------------------------------------------- */

  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
  }

  function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const modal = bootstrap.Modal.getInstance(el);
    if (modal) modal.hide();
  }

  function setBusy(btn, busy) {
    if (!btn) return;
    btn.disabled = busy;
    btn.dataset.originalHtml = btn.dataset.originalHtml || btn.innerHTML;
    btn.innerHTML = busy
      ? '<span class="spinner-border spinner-border-sm me-1"></span> Please wait...'
      : btn.dataset.originalHtml;
  }

  function clearFormErrors(form) {
    form.querySelectorAll('.invalid-feedback').forEach((el) => (el.textContent = ''));
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  }

  function showFormErrors(form, errors) {
    Object.entries(errors).forEach(([field, message]) => {
      const input = form.querySelector(`[name="${field}"]`);
      const errorEl = form.querySelector(`#${field}_error, #edit_${field}_error`);
      if (input) input.classList.add('is-invalid');
      if (errorEl) errorEl.textContent = message;
    });
  }

  function syncCsrfToken(newToken) {
    // The server calls Csrf::regenerate() after every successful
    // create/update/delete, so every hidden csrf_token input on the page
    // must be updated or the *next* action will fail with a 403.
    if (!newToken) return;
    csrfToken = newToken;
    document.querySelectorAll('input[name="csrf_token"]').forEach((input) => {
      input.value = newToken;
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  return { init };
})();
