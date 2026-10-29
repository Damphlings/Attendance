document.addEventListener('DOMContentLoaded', function () {
    const dashboard = document.querySelector('[data-dashboard]');

    if (!dashboard) {
        return;
    }

    loadDashboardStats();

    // Refresh dashboard data every 60 seconds.
    window.setInterval(loadDashboardStats, 60000);
});

async function loadDashboardStats() {
    const dashboard = document.querySelector('[data-dashboard]');

    if (!dashboard) {
        return;
    }

    try {
        const response = await fetch('api/get_dashboard_stats.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            },
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Unable to load dashboard statistics.');
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(
                data.message || 'Dashboard data could not be loaded.'
            );
        }

        updateDashboardStats(data);
        updateRecentAttendance(data.recent_attendance || []);

        const lastUpdated = document.getElementById('dashboard-last-updated');

        if (lastUpdated) {
            lastUpdated.textContent =
                'Last updated: ' +
                new Date().toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }
    } catch (error) {
        console.error('Dashboard error:', error);

        const errorElement = document.getElementById('dashboard-error');

        if (errorElement) {
            errorElement.textContent =
                'Dashboard data could not be refreshed. Please reload the page.';
            errorElement.hidden = false;
        }
    }
}

function updateDashboardStats(data) {
    setDashboardValue('total-members', data.total_members);
    setDashboardValue('active-members', data.active_members);
    setDashboardValue('total-attendance', data.total_attendance);
    setDashboardValue('today-attendance', data.today_attendance);
    setDashboardValue('today-sessions', data.sessions_today);

    const attendanceRate = Number(data.attendance_rate);

    setDashboardValue(
        'attendance-rate',
        Number.isFinite(attendanceRate)
            ? attendanceRate.toFixed(1) + '%'
            : '0.0%'
    );

    const progressBar = document.getElementById('attendance-progress-bar');

    if (progressBar) {
        const safeRate = Number.isFinite(attendanceRate)
            ? Math.max(0, Math.min(100, attendanceRate))
            : 0;

        progressBar.style.width = safeRate + '%';
        progressBar.setAttribute('aria-valuenow', String(safeRate));
    }
}

function setDashboardValue(id, value) {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    const number = Number(value);

    if (value === null || value === undefined || value === '') {
        element.textContent = '0';
    } else if (Number.isFinite(number)) {
        element.textContent = number.toLocaleString('en-PH');
    } else {
        element.textContent = String(value);
    }
}

function updateRecentAttendance(records) {
    const container = document.getElementById('recent-attendance');

    if (!container) {
        return;
    }

    if (!Array.isArray(records) || records.length === 0) {
        container.replaceChildren();

        const emptyMessage = document.createElement('p');
        emptyMessage.className = 'empty-state';
        emptyMessage.textContent = 'No attendance records available yet.';

        container.appendChild(emptyMessage);
        return;
    }

    const fragment = document.createDocumentFragment();

    records.forEach(function (record) {
        const row = document.createElement('div');
        row.className = 'recent-attendance-item';

        const name = document.createElement('strong');
        name.textContent = record.full_name || 'Unknown member';

        const session = document.createElement('span');
        session.textContent = record.session_name || 'Unknown session';

        const time = document.createElement('small');
        time.textContent = formatDashboardDateTime(record.time_in);

        row.appendChild(name);
        row.appendChild(session);
        row.appendChild(time);

        fragment.appendChild(row);
    });

    container.replaceChildren(fragment);
}

function formatDashboardDateTime(value) {
    if (!value) {
        return '—';
    }

    // SQLite timestamps are commonly returned as YYYY-MM-DD HH:MM:SS.
    // Interpret a timezone-free timestamp as local time to avoid shifting
    // the displayed time unexpectedly.
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Allow the scanner to signal that new attendance was recorded.
document.addEventListener('attendance:recorded', function () {
    loadDashboardStats();
});