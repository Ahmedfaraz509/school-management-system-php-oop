/**
 * School Management System - Main Shared Frontend Interactions
 * Handles navigation, modals, search/filtering, toast notifications, and UI states.
 */

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initActiveNavLink();
  initTableSearch();
  initTableFilters();
  initDemoForms();
  initDeleteModals();
  initMessagesUI();
});

/* ==========================================================================
   Sidebar & Navigation
   ========================================================================== */

function initSidebar() {
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.querySelector('.app-sidebar');
  const backdrop = document.querySelector('.sidebar-backdrop');

  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (window.innerWidth >= 992) {
        // Desktop collapse
        document.body.classList.toggle('sidebar-collapsed');
      } else {
        // Mobile / Tablet offcanvas drawer
        sidebar.classList.toggle('show');
        if (backdrop) backdrop.classList.toggle('show');
      }
    });
  }

  if (backdrop && sidebar) {
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
    });
  }

  // Handle window resize
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) {
      if (sidebar) sidebar.classList.remove('show');
      if (backdrop) backdrop.classList.remove('show');
    }
  });
}

function initActiveNavLink() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.php';
  const navLinks = document.querySelectorAll('.sidebar-menu .nav-link');

  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.php')) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

/* ==========================================================================
   Table Search & Filtering
   ========================================================================== */

function initTableSearch() {
  const searchInputs = document.querySelectorAll('.table-search-input');

  searchInputs.forEach((input) => {
    const targetTableSelector = input.getAttribute('data-table') || '.table-custom';
    const table = document.querySelector(targetTableSelector);
    if (!table) return;

    input.addEventListener('input', () => {
      const filterValue = input.value.toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr:not(.empty-row)');

      let matchCount = 0;
      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filterValue)) {
          row.style.display = '';
          matchCount++;
        } else {
          row.style.display = 'none';
        }
      });

      handleEmptyTableNotice(table, matchCount, 'No matching records found.');
    });
  });
}

function initTableFilters() {
  const filterSelects = document.querySelectorAll('.table-filter-select');
  if (!filterSelects.length) return;

  filterSelects.forEach((select) => {
    select.addEventListener('change', applyCombinedFilters);
  });

  const resetBtn = document.querySelector('.btn-reset-filters');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      filterSelects.forEach((select) => (select.value = 'all'));
      const searchInputs = document.querySelectorAll('.table-search-input');
      searchInputs.forEach((inp) => (inp.value = ''));
      applyCombinedFilters();
      showToast('Filters Reset', 'All table filters have been cleared.', 'info');
    });
  }
}

function applyCombinedFilters() {
  const table = document.querySelector('.table-custom');
  if (!table) return;

  const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
  const filterSelects = document.querySelectorAll('.table-filter-select');
  const searchInput = document.querySelector('.table-search-input');
  const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

  let matchCount = 0;

  rows.forEach((row) => {
    let matchesAll = true;

    // Check search value
    if (searchValue && !row.textContent.toLowerCase().includes(searchValue)) {
      matchesAll = false;
    }

    // Check dropdown selects
    filterSelects.forEach((select) => {
      const colIndex = parseInt(select.getAttribute('data-column'), 10);
      const val = select.value.toLowerCase().trim();

      if (val !== 'all' && val !== '') {
        const cells = row.querySelectorAll('td');
        if (cells[colIndex]) {
          const cellText = cells[colIndex].textContent.toLowerCase();
          if (!cellText.includes(val)) {
            matchesAll = false;
          }
        }
      }
    });

    if (matchesAll) {
      row.style.display = '';
      matchCount++;
    } else {
      row.style.display = 'none';
    }
  });

  handleEmptyTableNotice(table, matchCount, 'No records match selected criteria.');
}

function handleEmptyTableNotice(table, count, message) {
  let emptyRow = table.querySelector('.empty-row');
  if (count === 0) {
    if (!emptyRow) {
      const colCount = table.querySelectorAll('thead th').length || 6;
      emptyRow = document.createElement('tr');
      emptyRow.className = 'empty-row';
      emptyRow.innerHTML = `
        <td colspan="${colCount}" class="text-center py-4 text-muted">
          <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary opacity-50"></i>
          <span>${message}</span>
        </td>
      `;
      table.querySelector('tbody').appendChild(emptyRow);
    }
    emptyRow.style.display = '';
  } else if (emptyRow) {
    emptyRow.style.display = 'none';
  }
}

/* ==========================================================================
   Form Handling & Toast Notifications
   ========================================================================== */

function initDemoForms() {
  const forms = document.querySelectorAll('form[data-demo-form="true"], .demo-form');

  forms.forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();

      // Check HTML5 validity
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const formName = form.getAttribute('data-entity-name') || 'Record';
      const actionType = form.getAttribute('data-action-type') || 'saved';

      // Close modal if form is in modal
      const modalEl = form.closest('.modal');
      if (modalEl) {
        const modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.hide();
      }

      // Reset form
      form.reset();

      // Show toast
      showToast(
        'Success',
        `${formName} has been ${actionType} successfully (Demo)`,
        'success'
      );
    });
  });
}

function showToast(title, message, type = 'success') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }

  const toastId = 'toast_' + Date.now();
  let bgHeader = 'bg-primary text-white';
  let icon = 'bi-check-circle-fill text-success';

  if (type === 'success') {
    bgHeader = 'bg-success text-white';
    icon = 'bi-check-circle-fill';
  } else if (type === 'danger') {
    bgHeader = 'bg-danger text-white';
    icon = 'bi-exclamation-triangle-fill';
  } else if (type === 'info') {
    bgHeader = 'bg-info text-white';
    icon = 'bi-info-circle-fill';
  } else if (type === 'warning') {
    bgHeader = 'bg-warning text-dark';
    icon = 'bi-exclamation-circle-fill';
  }

  const toastHTML = `
    <div id="${toastId}" class="toast align-items-center mb-2 shadow" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header ${bgHeader}">
        <i class="bi ${icon} me-2"></i>
        <strong class="me-auto">${title}</strong>
        <small>Just now</small>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body bg-white text-dark">
        ${message}
      </div>
    </div>
  `;

  container.insertAdjacentHTML('beforeend', toastHTML);
  const toastElement = document.getElementById(toastId);
  const bsToast = new bootstrap.Toast(toastElement, { delay: 4000 });
  bsToast.show();

  toastElement.addEventListener('hidden.bs.toast', () => {
    toastElement.remove();
  });
}

/* ==========================================================================
   Delete Confirmation Modal Logic
   ========================================================================== */

let rowToDelete = null;

function initDeleteModals() {
  // Delegate click for delete action buttons
  document.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('.btn-action-delete, [data-action="delete"]');
    if (!deleteBtn) return;

    e.preventDefault();
    rowToDelete = deleteBtn.closest('tr') || deleteBtn.closest('.card');
    const itemName = deleteBtn.getAttribute('data-item-name') || 'this item';

    const deleteModalEl = document.getElementById('deleteConfirmModal');
    if (deleteModalEl) {
      const deleteTextEl = deleteModalEl.querySelector('.delete-item-label');
      if (deleteTextEl) deleteTextEl.textContent = `"${itemName}"`;
      const modalInstance = new bootstrap.Modal(deleteModalEl);
      modalInstance.show();
    } else {
      // Fallback
      if (confirm(`Are you sure you want to delete ${itemName}?`)) {
        if (rowToDelete) rowToDelete.remove();
        showToast('Deleted', 'Record removed successfully (Demo)', 'danger');
      }
    }
  });

  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', () => {
      if (rowToDelete) {
        rowToDelete.remove();
        rowToDelete = null;
      }
      const deleteModalEl = document.getElementById('deleteConfirmModal');
      if (deleteModalEl) {
        const modalInstance = bootstrap.Modal.getInstance(deleteModalEl);
        if (modalInstance) modalInstance.hide();
      }
      showToast('Deleted', 'Record deleted successfully (Demo)', 'danger');
    });
  }
}

/* ==========================================================================
   Messages UI Interactive Handler
   ========================================================================== */

function initMessagesUI() {
  const messageItems = document.querySelectorAll('.message-item');
  const previewSender = document.getElementById('previewSender');
  const previewSubject = document.getElementById('previewSubject');
  const previewTime = document.getElementById('previewTime');
  const previewBody = document.getElementById('previewBody');
  const previewEmail = document.getElementById('previewEmail');

  if (!messageItems.length) return;

  messageItems.forEach((item) => {
    item.addEventListener('click', () => {
      messageItems.forEach((m) => m.classList.remove('active'));
      item.classList.add('active');
      item.classList.remove('unread');

      const sender = item.getAttribute('data-sender');
      const subject = item.getAttribute('data-subject');
      const time = item.getAttribute('data-time');
      const body = item.getAttribute('data-body');
      const email = item.getAttribute('data-email');

      if (previewSender) previewSender.textContent = sender;
      if (previewSubject) previewSubject.textContent = subject;
      if (previewTime) previewTime.textContent = time;
      if (previewBody) previewBody.textContent = body;
      if (previewEmail) previewEmail.textContent = email;
    });
  });
}

/* ==========================================================================
   Export / Print Utilities
   ========================================================================== */

function exportTableDemo(type = 'CSV') {
  showToast('Export Started', `Generating and downloading ${type} file... (Demo)`, 'info');
}

function printSectionDemo(sectionId) {
  window.print();
}
