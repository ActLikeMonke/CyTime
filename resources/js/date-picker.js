const pad = (value) => String(value).padStart(2, '0');
const toDateValue = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const toMonthValue = (year, month) => `${year}-${pad(month + 1)}`;

function parseDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return Number.isNaN(date.getTime()) ? null : date;
}

function parseMonth(value) {
    const match = /^(\d{4})-(\d{2})$/.exec(value ?? '');
    return match ? { year: Number(match[1]), month: Number(match[2]) - 1 } : null;
}

const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

let state = null;

function locale() {
    return document.documentElement.lang || undefined;
}

function isOutOfRange(value) {
    const { input } = state;
    return (input.min && value < input.min) || (input.max && value > input.max);
}

function headerButton(label, onClick, symbol) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ui-date-picker__nav';
    button.setAttribute('aria-label', label);
    button.textContent = symbol;
    button.addEventListener('click', onClick);
    return button;
}

function render(focusSelector = null) {
    const { popover, toggle, type } = state;
    popover.replaceChildren();

    const header = document.createElement('div');
    header.className = 'ui-date-picker__header';
    const title = document.createElement('p');
    title.className = 'ui-date-picker__title';
    title.id = 'ui-date-picker-title';
    title.setAttribute('aria-live', 'polite');
    const previous = headerButton(toggle.dataset.previousLabel, () => shiftView(-1), '‹');
    const next = headerButton(toggle.dataset.nextLabel, () => shiftView(1), '›');
    const { min, max } = state.input;
    const viewKey = type === 'month'
        ? String(state.view.year)
        : toMonthValue(state.view.year, state.view.month);
    const boundKey = (value) => (type === 'month' ? value.slice(0, 4) : value.slice(0, 7));
    previous.disabled = Boolean(min) && viewKey <= boundKey(min);
    next.disabled = Boolean(max) && viewKey >= boundKey(max);
    header.append(previous, title, next);
    popover.append(header);

    if (type === 'month') renderMonths(title);
    else renderDays(title);

    const footer = document.createElement('div');
    footer.className = 'ui-date-picker__footer';
    if (!state.input.required) {
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'ui-date-picker__link';
        clear.textContent = toggle.dataset.clearLabel;
        clear.addEventListener('click', () => choose(''));
        footer.append(clear);
    }
    const now = new Date();
    const currentValue = type === 'month' ? toMonthValue(now.getFullYear(), now.getMonth()) : toDateValue(now);
    const today = document.createElement('button');
    today.type = 'button';
    today.className = 'ui-date-picker__link ui-date-picker__link--end';
    today.textContent = toggle.dataset.todayLabel;
    today.disabled = Boolean(isOutOfRange(currentValue));
    today.addEventListener('click', () => choose(currentValue));
    footer.append(today);
    popover.append(footer);

    const target = popover.querySelector(focusSelector ?? '[tabindex="0"]');
    if (target instanceof HTMLElement) target.focus();
}

function renderDays(title) {
    const { popover, input } = state;
    const { year, month } = state.view;
    title.textContent = new Intl.DateTimeFormat(locale(), { month: 'long', year: 'numeric' }).format(new Date(year, month, 1));

    const grid = document.createElement('table');
    grid.className = 'ui-date-picker__grid';
    grid.setAttribute('role', 'grid');
    grid.setAttribute('aria-labelledby', title.id);

    const weekdayFormat = new Intl.DateTimeFormat(locale(), { weekday: 'short' });
    const weekdayLong = new Intl.DateTimeFormat(locale(), { weekday: 'long' });
    const headRow = document.createElement('tr');
    for (let index = 0; index < 7; index += 1) {
        const day = new Date(2024, 0, 1 + index);
        const cell = document.createElement('th');
        cell.scope = 'col';
        cell.abbr = weekdayLong.format(day);
        cell.textContent = weekdayFormat.format(day).slice(0, 2);
        headRow.append(cell);
    }
    grid.createTHead().append(headRow);

    const first = new Date(year, month, 1);
    const start = addDays(first, -((first.getDay() + 6) % 7));
    const focusValue = state.focus;
    const fullFormat = new Intl.DateTimeFormat(locale(), { dateStyle: 'full' });
    const todayValue = toDateValue(new Date());
    const body = grid.createTBody();

    for (let week = 0; week < 6; week += 1) {
        const row = body.insertRow();
        for (let weekday = 0; weekday < 7; weekday += 1) {
            const date = addDays(start, week * 7 + weekday);
            const value = toDateValue(date);
            const cell = row.insertCell();
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ui-date-picker__day';
            button.dataset.value = value;
            button.textContent = String(date.getDate());
            button.setAttribute('aria-label', fullFormat.format(date));
            button.tabIndex = value === focusValue ? 0 : -1;
            button.classList.toggle('ui-date-picker__day--outside', date.getMonth() !== month);
            button.classList.toggle('ui-date-picker__day--today', value === todayValue);
            if (value === input.value) {
                button.classList.add('ui-date-picker__day--selected');
                button.setAttribute('aria-selected', 'true');
            }
            button.disabled = Boolean(isOutOfRange(value));
            button.addEventListener('click', () => choose(value));
            cell.append(button);
        }
    }

    popover.append(grid);
}

function renderMonths(title) {
    const { popover, input } = state;
    const { year } = state.view;
    title.textContent = String(year);
    const grid = document.createElement('div');
    grid.className = 'ui-date-picker__months';
    grid.setAttribute('role', 'group');
    grid.setAttribute('aria-labelledby', title.id);
    const monthFormat = new Intl.DateTimeFormat(locale(), { month: 'short' });
    const monthLong = new Intl.DateTimeFormat(locale(), { month: 'long', year: 'numeric' });
    const now = new Date();

    for (let month = 0; month < 12; month += 1) {
        const value = toMonthValue(year, month);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ui-date-picker__day ui-date-picker__month';
        button.dataset.value = value;
        button.textContent = monthFormat.format(new Date(year, month, 1));
        button.setAttribute('aria-label', monthLong.format(new Date(year, month, 1)));
        button.tabIndex = value === state.focus ? 0 : -1;
        button.classList.toggle('ui-date-picker__day--today', year === now.getFullYear() && month === now.getMonth());
        if (value === input.value) {
            button.classList.add('ui-date-picker__day--selected');
            button.setAttribute('aria-pressed', 'true');
        }
        button.disabled = Boolean(isOutOfRange(value));
        button.addEventListener('click', () => choose(value));
        grid.append(button);
    }

    popover.append(grid);
}

function setFocus(value) {
    const { min, max } = state.input;
    if (min && value < min) value = min;
    if (max && value > max) value = max;
    state.focus = value;
    if (state.type === 'month') {
        state.view = { year: parseMonth(value).year };
    } else {
        const date = parseDate(value);
        state.view = { year: date.getFullYear(), month: date.getMonth() };
    }
    render(`[data-value="${value}"]`);
}

function shiftView(direction) {
    if (state.type === 'month') {
        const current = parseMonth(state.focus);
        setFocus(toMonthValue(current.year + direction, current.month));
        return;
    }
    const current = parseDate(state.focus);
    const target = new Date(current.getFullYear(), current.getMonth() + direction, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
    setFocus(toDateValue(new Date(target.getFullYear(), target.getMonth(), Math.min(current.getDate(), lastDay))));
}

function choose(value) {
    const { input, toggle } = state;
    if (value !== '' && isOutOfRange(value)) return;
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    close();
    toggle.focus();
}

function position() {
    if (!state) return;
    const { popover, input } = state;
    const anchor = input.getBoundingClientRect();
    const gap = 4;
    const width = popover.offsetWidth;
    const height = popover.offsetHeight;
    const left = Math.max(8, Math.min(anchor.right - width, window.innerWidth - width - 8));
    const below = anchor.bottom + gap;
    const top = below + height > window.innerHeight && anchor.top - gap - height > 0 ? anchor.top - gap - height : below;
    popover.style.left = `${left}px`;
    popover.style.top = `${top}px`;
}

function open(toggle) {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly) return;
    close();

    const type = toggle.dataset.datePickerToggle === 'month' ? 'month' : 'date';
    const now = new Date();
    let focus = input.value;
    if (type === 'month' && !parseMonth(focus)) focus = toMonthValue(now.getFullYear(), now.getMonth());
    if (type === 'date' && !parseDate(focus)) focus = toDateValue(now);
    if (input.max && focus > input.max) focus = input.max;
    if (input.min && focus < input.min) focus = input.min;

    const popover = document.createElement('div');
    popover.className = 'ui-date-picker';
    popover.setAttribute('role', 'dialog');
    popover.setAttribute('aria-label', toggle.getAttribute('aria-label'));
    (input.closest('dialog') ?? document.body).append(popover);

    state = { input, toggle, type, popover, focus, view: {} };
    toggle.setAttribute('aria-expanded', 'true');
    popover.addEventListener('keydown', onKeydown);
    setFocus(focus);
    position();
}

function close() {
    if (!state) return;
    state.popover.remove();
    state.toggle.setAttribute('aria-expanded', 'false');
    state = null;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        const { toggle } = state;
        close();
        toggle.focus();
        return;
    }

    const value = event.target instanceof HTMLElement ? event.target.dataset.value : null;
    if (!value) return;

    let next = null;
    if (state.type === 'month') {
        const { year, month } = parseMonth(value);
        const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3, PageUp: -12, PageDown: 12 };
        if (event.key in steps) {
            const index = year * 12 + month + steps[event.key];
            next = toMonthValue(Math.floor(index / 12), index % 12);
        }
    } else {
        const date = parseDate(value);
        const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        if (event.key in steps) next = toDateValue(addDays(date, steps[event.key]));
        if (event.key === 'Home') next = toDateValue(addDays(date, -((date.getDay() + 6) % 7)));
        if (event.key === 'End') next = toDateValue(addDays(date, 6 - ((date.getDay() + 6) % 7)));
        if (event.key === 'PageUp' || event.key === 'PageDown') {
            event.preventDefault();
            state.focus = value;
            shiftView(event.key === 'PageUp' ? -1 : 1);
            return;
        }
    }

    if (next) {
        event.preventDefault();
        setFocus(next);
    }
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const toggle = target.closest('[data-date-picker-toggle]');
    if (toggle) {
        if (state?.toggle === toggle) close();
        else open(toggle);
        return;
    }

    if (state && target.isConnected && !state.popover.contains(target)) close();
});

document.addEventListener('focusin', (event) => {
    if (state && event.target instanceof Node && !state.popover.contains(event.target) && event.target !== state.toggle) close();
});

window.addEventListener('resize', position);
window.addEventListener('scroll', position, true);
document.addEventListener('livewire:navigating', close);
