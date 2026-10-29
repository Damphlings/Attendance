document.addEventListener('DOMContentLoaded', function () {
    initializeSidebar();
    initializeFlashMessages();
    initializeConfirmations();
    initializePasswordToggles();
    initializeFormProtection();
});

/**
 * Responsive sidebar toggle.
 * Expects:
 * - .sidebar
 * - .main-area
 * - .sidebar-toggle
 * - optional .sidebar-overlay
 */
function initializeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainArea = document.querySelector('.main-area');
    const toggleButtons = document.querySelectorAll('.sidebar-toggle');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!sidebar) {
        return;
    }

    function openSidebar() {
        sidebar.classList.add('sidebar-open');

        if (overlay) {
            overlay.classList.add('active');
        }

        document.body.classList.add('sidebar-visible');

        toggleButtons.forEach(function (button) {
            button.setAttribute('aria-expanded', 'true');
        });
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');

        if (overlay) {
            overlay.classList.remove('active');
        }

        document.body.classList.remove('sidebar-visible');

        toggleButtons.forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    }

    toggleButtons.forEach(function (button) {
        button.setAttribute('aria-expanded', 'false');

        button.addEventListener('click', function () {
            if (sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    });

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    if (mainArea) {
        mainArea.addEventListener('click', function (event) {
            if (
                window.innerWidth <= 768 &&
                sidebar.classList.contains('sidebar-open') &&
                !sidebar.contains(event.target) &&
                !event.target.closest('.sidebar-toggle')
            ) {
                closeSidebar();
            }
        });
    }
}

/**
 * Automatically dismiss flash messages after a delay.
 * Does not automatically remove error messages.
 */
function initializeFlashMessages() {
    const messages = document.querySelectorAll(
        '.alert-success, .alert-info, .flash-message'
    );

    messages.forEach(function (message) {
        if (
            message.classList.contains('alert-error') ||
            message.classList.contains('alert-danger')
        ) {
            return;
        }

        window.setTimeout(function () {
            message.style.transition = 'opacity 0.3s ease';
            message.style.opacity = '0';

            window.setTimeout(function () {
                message.remove();
            }, 350);
        }, 5000);
    });
}

/**
 * Confirmation dialogs for links and forms.
 *
 * Links:
 *   <a href="delete_member.php?id=1"
 *      data-confirm="Delete this member?">Delete</a>
 *
 * Forms:
 *   <form data-confirm="Save these changes?">
 */
function initializeConfirmations() {
    document.addEventListener('click', function (event) {
        const element = event.target.closest('[data-confirm]');

        if (!element || element.tagName === 'FORM') {
            return;
        }

        const message =
            element.getAttribute('data-confirm') ||
            'Are you sure you want to continue?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form.matches('form[data-confirm]')) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            return;
        }

        const message =
            form.getAttribute('data-confirm') ||
            'Are you sure you want to continue?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
}

/**
 * Password visibility buttons.
 * Expects:
 * <input type="password" id="password">
 * <button type="button" data-toggle-password="#password">
 */
function initializePasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const selector = button.getAttribute('data-toggle-password');
            const input = document.querySelector(selector);

            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';

            button.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );
        });
    });
}

/**
 * Prevent accidental repeated submissions.
 * Only applies to forms explicitly marked data-disable-submit.
 *
 * Do not use this on QR scanning forms that must be submitted repeatedly.
 */
function initializeFormProtection() {
    document.querySelectorAll('form[data-disable-submit]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                return;
            }

            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';

            const submitButton = form.querySelector(
                'button[type="submit"], input[type="submit"]'
            );

            if (submitButton) {
                submitButton.disabled = true;

                if (submitButton.tagName === 'BUTTON') {
                    submitButton.dataset.originalText =
                        submitButton.textContent;

                    submitButton.textContent = 'Please wait...';
                } else {
                    submitButton.dataset.originalValue =
                        submitButton.value;

                    submitButton.value = 'Please wait...';
                }
            }
        });
    });
});

/**
 * Shared helper to display a message inside an element.
 */
function showMessage(element, message, type) {
    if (!element) {
        return;
    }

    const allowedTypes = ['success', 'error', 'warning', 'info'];

    if (!allowedTypes.includes(type)) {
        type = 'info';
    }

    element.textContent = message;
    element.className = 'alert alert-' + type;
    element.hidden = false;
}

/**
 * Shared helper to safely format dates for display.
 * Accepts a valid date string.
 */
function formatDisplayDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Shared helper for displaying time.
 */
function formatDisplayTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleTimeString('en-PH', {
        hour: '2-digit',
        minute: '2-digit'
    });
}