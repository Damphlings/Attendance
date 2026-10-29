document.addEventListener('DOMContentLoaded', function () {
    initializeMemberSearch();
    initializeMemberStatusFilter();
    initializeMemberDeleteConfirmation();
});

/**
 * Filters currently rendered member rows by name, code, email, or position.
 *
 * Expected:
 * - #member-search
 * - #members-table
 * - tbody tr[data-member-row]
 */
function initializeMemberSearch() {
    const searchInput = document.getElementById('member-search');
    const table = document.getElementById('members-table');

    if (!searchInput || !table) {
        return;
    }

    searchInput.addEventListener('input', filterMemberRows);

    function filterMemberRows() {
        const query = searchInput.value.trim().toLowerCase();

        table.querySelectorAll('tbody tr[data-member-row]').forEach(function (row) {
            const searchableText = (
                row.dataset.search ||
                row.textContent ||
                ''
            ).toLowerCase();

            const matchesSearch = searchableText.includes(query);

            row.dataset.searchMatch = matchesSearch ? 'true' : 'false';

            applyMemberFilters(row);
        });
    }
}

/**
 * Filters rendered rows by Active/Inactive status.
 *
 * Expected:
 * <select id="member-status-filter">
 *   <option value="">All statuses</option>
 *   <option value="Active">Active</option>
 *   <option value="Inactive">Inactive</option>
 * </select>
 *
 * Rows:
 * <tr data-member-row data-status="Active">
 */
function initializeMemberStatusFilter() {
    const statusFilter = document.getElementById('member-status-filter');

    if (!statusFilter) {
        return;
    }

    statusFilter.addEventListener('change', function () {
        document.querySelectorAll(
            '#members-table tbody tr[data-member-row]'
        ).forEach(function (row) {
            applyMemberFilters(row);
        });
    });
}

function applyMemberFilters(row) {
    const searchInput = document.getElementById('member-search');
    const statusFilter = document.getElementById('member-status-filter');

    const query = searchInput
        ? searchInput.value.trim().toLowerCase()
        : '';

    const selectedStatus = statusFilter
        ? statusFilter.value.trim().toLowerCase()
        : '';

    const searchableText = (
        row.dataset.search ||
        row.textContent ||
        ''
    ).toLowerCase();

    const rowStatus = (
        row.dataset.status ||
        ''
    ).toLowerCase();

    const matchesSearch = searchableText.includes(query);

    const matchesStatus =
        selectedStatus === '' ||
        rowStatus === selectedStatus;

    row.hidden = !(matchesSearch && matchesStatus);
}

/**
 * Confirms deletion before navigating to the deletion endpoint.
 * The endpoint must still validate authorization and CSRF.
 */
function initializeMemberDeleteConfirmation() {
    document.addEventListener('click', function (event) {
        const deleteLink = event.target.closest('[data-delete-member]');

        if (!deleteLink) {
            return;
        }

        const memberName =
            deleteLink.getAttribute('data-member-name') || 'this member';

        const confirmed = window.confirm(
            'Are you sure you want to delete ' + memberName + '?'
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
}