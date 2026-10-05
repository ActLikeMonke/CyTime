const appearanceQuery = window.matchMedia('(prefers-color-scheme: dark)');

function getAppearancePreference() {
    const appearance = localStorage.getItem('appearance');
    return appearance === 'light' || appearance === 'dark' || appearance === 'system' ? appearance : 'system';
}

function applyAppearance(appearance = getAppearancePreference()) {
    const dark = appearance === 'dark' || (appearance === 'system' && appearanceQuery.matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.appearance = appearance;
    document.querySelectorAll('[data-appearance-choice]').forEach((choice) => {
        choice.setAttribute('aria-pressed', String(choice.getAttribute('data-appearance-choice') === appearance));
    });
}

applyAppearance();
appearanceQuery.addEventListener('change', () => {
    if (getAppearancePreference() === 'system') applyAppearance('system');
});

document.addEventListener('livewire:navigated', () => applyAppearance());

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const appearanceChoice = target.closest('[data-appearance-choice]');
    if (appearanceChoice) {
        const appearance = appearanceChoice.getAttribute('data-appearance-choice');
        if (appearance === 'light' || appearance === 'dark' || appearance === 'system') {
            localStorage.setItem('appearance', appearance);
            applyAppearance(appearance);
        }
    }

    const tooltip = target.closest('.ui-tooltip');
    if (tooltip) tooltip.toggleAttribute('data-open');
    else document.querySelectorAll('.ui-tooltip[data-open]').forEach((item) => item.removeAttribute('data-open'));

    const passwordToggle = target.closest('[data-password-toggle]');
    if (passwordToggle) {
        const input = document.getElementById(passwordToggle.getAttribute('aria-controls'));
        if (input instanceof HTMLInputElement) {
            const showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            passwordToggle.setAttribute('aria-label', passwordToggle.getAttribute(showing ? 'data-hide-label' : 'data-show-label'));
        }
    }

    const disabledLink = target.closest('a[aria-disabled="true"]');
    if (disabledLink) event.preventDefault();

    const sidebarToggle = target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
        const isOpen = document.documentElement.classList.toggle('sidebar-open');
        document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    const modalTrigger = target.closest('[data-modal-trigger]');
    if (modalTrigger) {
        const dialog = document.getElementById(modalTrigger.getAttribute('data-modal-trigger'));
        if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
    }

    if (target.closest('[data-modal-close]')) {
        const dialog = target.closest('dialog');
        if (dialog instanceof HTMLDialogElement) dialog.close();
    }

    const dropdownToggle = target.closest('[data-dropdown-toggle]');
    if (dropdownToggle) {
        const dropdown = dropdownToggle.closest('[data-dropdown]');
        const isOpen = dropdownToggle.getAttribute('aria-expanded') === 'true';
        dropdownToggle.setAttribute('aria-expanded', String(!isOpen));
        dropdown?.querySelector('[data-dropdown-panel]')?.toggleAttribute('hidden', isOpen);
    } else {
        document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            if (!dropdown.contains(target)) {
                dropdown.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
                dropdown.querySelector('[data-dropdown-panel]')?.setAttribute('hidden', '');
            }
        });
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.documentElement.classList.remove('sidebar-open');
        document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => toggle.setAttribute('aria-expanded', 'false'));
        document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            dropdown.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
            dropdown.querySelector('[data-dropdown-panel]')?.setAttribute('hidden', '');
        });
        document.querySelectorAll('.ui-tooltip[data-open]').forEach((tooltip) => tooltip.removeAttribute('data-open'));
    }

    if (!(event.target instanceof Element) || !event.target.closest('[data-dropdown]')) return;
    const dropdown = event.target.closest('[data-dropdown]');
    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    const panel = dropdown.querySelector('[data-dropdown-panel]');
    const items = [...(panel?.querySelectorAll('a[href], button:not(:disabled), [role="menuitem"]') ?? [])];
    const currentIndex = items.indexOf(document.activeElement);
    let nextIndex = null;
    if (event.key === 'ArrowDown') nextIndex = (currentIndex + 1) % items.length;
    if (event.key === 'ArrowUp') nextIndex = (currentIndex - 1 + items.length) % items.length;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = items.length - 1;
    if (nextIndex !== null && items.length > 0 && toggle instanceof HTMLElement && panel instanceof HTMLElement) {
        event.preventDefault();
        toggle.setAttribute('aria-expanded', 'true');
        panel.removeAttribute('hidden');
        items[nextIndex].focus();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog[data-show="true"]').forEach((dialog) => {
        if (dialog instanceof HTMLDialogElement && !dialog.matches('[x-data]')) {
            dialog.showModal();
        }
    });
});

document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement) {
        const dialog = event.target;
        const rect = dialog.getBoundingClientRect();
        const inside = event.clientX >= rect.left && event.clientX <= rect.right
            && event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) dialog.close();
    }
    if (event.target instanceof Element && event.target.closest('.app-nav-link')) {
        document.documentElement.classList.remove('sidebar-open');
    }
});

document.addEventListener('livewire:init', () => {
    Livewire.on('toast-show', (event) => {
        const region = document.querySelector('[data-toast-region]');
        if (!region) return;
        const toast = document.createElement('div');
        const type = ['success', 'warning', 'error', 'info'].includes(event.type) ? event.type : 'info';
        toast.className = `ui-toast ui-toast--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.textContent = event.text ?? event.message ?? '';
        region.append(toast);
        window.setTimeout(() => toast.remove(), 5000);
    });
});
