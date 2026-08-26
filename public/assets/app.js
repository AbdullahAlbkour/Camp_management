document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        window.lucide.createIcons();
    }

    function formatLiveClock(date) {
        const pad = (value) => String(value).padStart(2, '0');
        const year = date.getFullYear();
        const month = pad(date.getMonth() + 1);
        const day = pad(date.getDate());
        const hours = pad(date.getHours());
        const minutes = pad(date.getMinutes());
        return `${hours}:${minutes} ${year}-${month}-${day}`;
    }

    function updateLiveClocks() {
        const now = new Date();
        document.querySelectorAll('[data-live-clock]').forEach((element) => {
            element.textContent = formatLiveClock(now);
        });
    }

    if (document.querySelector('[data-live-clock]')) {
        updateLiveClocks();
        setInterval(updateLiveClocks, 1000);
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
    });

    document.querySelectorAll('.nav a, .logout-form button').forEach((item) => {
        const label = item.querySelector('span')?.textContent?.trim();
        if (label && !item.getAttribute('title')) {
            item.setAttribute('title', label);
        }
    });

    const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');
    const savedSidebarState = localStorage.getItem('sidebarCollapsed');

    function setSidebarCollapsed(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebarCollapse?.setAttribute('aria-expanded', String(!collapsed));
        sidebarCollapse?.setAttribute('title', collapsed ? 'توسيع القائمة' : 'تصغير القائمة');
    }

    if (savedSidebarState === '1') {
        setSidebarCollapsed(true);
    } else {
        setSidebarCollapsed(false);
    }

    sidebarCollapse?.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        setSidebarCollapsed(collapsed);
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    });

    document.querySelectorAll('[data-notification-popover]').forEach((popover) => {
        const trigger = popover.querySelector('[data-notification-trigger]');
        if (!trigger) return;

        function setOpen(open) {
            popover.classList.toggle('open', open);
            trigger.setAttribute('aria-expanded', String(open));
        }

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            setOpen(!popover.classList.contains('open'));
        });

        popover.addEventListener('mouseleave', () => {
            if (!popover.classList.contains('open')) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-notification-popover]')) return;
        document.querySelectorAll('[data-notification-popover].open').forEach((popover) => {
            popover.classList.remove('open');
            popover.querySelector('[data-notification-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-notification-popover].open').forEach((popover) => {
            popover.classList.remove('open');
            popover.querySelector('[data-notification-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    });

    const campSelect = document.querySelector('[data-camp-select]');
    const shelterSelect = document.querySelector('[data-shelter-select]');

    function filterShelters() {
        if (!campSelect || !shelterSelect) return;
        const campId = campSelect.value;
        Array.from(shelterSelect.options).forEach((option) => {
            if (!option.dataset.camp) return;
            option.hidden = campId && option.dataset.camp !== campId;
        });
        const selected = shelterSelect.selectedOptions[0];
        if (selected && selected.hidden) {
            shelterSelect.value = '';
        }
    }

    if (campSelect && shelterSelect) {
        campSelect.addEventListener('change', filterShelters);
        filterShelters();
    }

    document.querySelectorAll('select.js-searchable-select').forEach((select) => {
        if (select.options.length < 9 || select.dataset.enhanced) return;
        select.dataset.enhanced = '1';
        const input = document.createElement('input');
        input.type = 'search';
        input.className = 'select-filter';
        input.placeholder = 'اكتب لتصفية القائمة...';
        select.before(input);
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            Array.from(select.options).forEach((option) => {
                const text = option.textContent.toLowerCase();
                option.hidden = Boolean(term) && !text.includes(term);
            });
        });
    });

    document.querySelectorAll('[data-async-select]').forEach((box) => {
        const input = box.querySelector('input[type="search"]');
        const list = box.querySelector('.async-options');
        const targetName = box.dataset.target;
        const hidden = box.closest('label, form')?.querySelector(`input[type="hidden"][name="${targetName}"]`);
        let timer = null;
        let lastTerm = '';

        function render(items) {
            list.innerHTML = '';
            if (!items.length) {
                list.innerHTML = '<div class="async-empty">لا توجد نتائج مطابقة.</div>';
                return;
            }
            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'async-option';
                button.innerHTML = `<strong>${item.text}</strong><small>${item.meta || ''}</small>`;
                button.addEventListener('click', () => {
                    hidden.value = item.id;
                    input.value = item.text;
                    box.classList.remove('open', 'invalid');
                });
                list.appendChild(button);
            });
        }

        async function search(term) {
            lastTerm = term;
            const url = new URL(box.dataset.asyncSelect, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const items = await response.json();
            if (lastTerm === term) {
                render(items);
                box.classList.add('open');
            }
        }

        input.addEventListener('focus', () => search(input.value.trim()));
        input.addEventListener('input', () => {
            hidden.value = '';
            box.classList.remove('invalid');
            clearTimeout(timer);
            timer = setTimeout(() => search(input.value.trim()), 250);
        });
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('[data-async-select]').forEach((box) => {
            if (!box.contains(event.target)) box.classList.remove('open');
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const requiredAsync = form.querySelectorAll('[data-async-select]');
            for (const box of requiredAsync) {
                const targetName = box.dataset.target;
                const hidden = form.querySelector(`input[type="hidden"][name="${targetName}"][required]`);
                if (hidden && !hidden.value) {
                    event.preventDefault();
                    box.classList.add('invalid');
                    box.querySelector('input[type="search"]').focus();
                    return;
                }
            }
        });
    });

    document.querySelectorAll('[data-count]').forEach((element) => {
        const target = Number(element.dataset.count || 0);
        const duration = 700;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min(1, (now - start) / duration);
            const value = Math.round(target * progress);
            element.textContent = new Intl.NumberFormat('ar').format(value);
            if (progress < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
    });

    document.querySelectorAll('[data-progress]').forEach((bar) => {
        const value = Math.max(0, Math.min(100, Number(bar.dataset.progress || 0)));
        requestAnimationFrame(() => {
            bar.style.width = `${value}%`;
        });
    });

    document.querySelectorAll('[data-dashboard-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.dashboardFilter;
            document.querySelectorAll('[data-dashboard-filter]').forEach((item) => item.classList.toggle('active', item === button));
            document.querySelectorAll('[data-metric-group]').forEach((card) => {
                const visible = filter === 'all' || card.dataset.metricGroup === filter;
                card.style.display = visible ? '' : 'none';
            });
        });
    });

    const chartInstances = new Map();

    function createChart(canvas, requestedType) {
        if (!window.Chart) return;
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const values = JSON.parse(canvas.dataset.values || '[]');
        const type = requestedType || canvas.dataset.chart || 'bar';

        if (chartInstances.has(canvas)) {
            chartInstances.get(canvas).destroy();
        }

        const chart = new window.Chart(canvas, {
            type,
            data: {
                labels,
                datasets: [{
                    label: 'الإجمالي',
                    data: values,
                    backgroundColor: ['#177e72', '#d29a2b', '#4f8bc9', '#c95f46', '#7a8f87', '#237a43'],
                    borderColor: '#177e72',
                    borderWidth: 2,
                    fill: type === 'line',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: type === 'doughnut' },
                    tooltip: {
                        rtl: true,
                        textDirection: 'rtl'
                    }
                },
                scales: type === 'doughnut' ? {} : {
                    y: { beginAtZero: true },
                    x: {
                        ticks: {
                            maxRotation: 0,
                            minRotation: 0
                        }
                    }
                }
            }
        });

        chartInstances.set(canvas, chart);
    }

    function ensureChart(canvas) {
        if (!canvas) return;
        if (!chartInstances.has(canvas)) {
            createChart(canvas);
        }
        requestAnimationFrame(() => chartInstances.get(canvas)?.resize());
    }

    function loadChartJs() {
        if (window.Chart) return Promise.resolve();

        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-chartjs-loader]');
            if (existing) {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', reject, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.defer = true;
            script.dataset.chartjsLoader = '1';
            script.addEventListener('load', resolve, { once: true });
            script.addEventListener('error', reject, { once: true });
            document.head.appendChild(script);
        });
    }

    function bootCanvasCharts() {
        document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
            if (!canvas.closest('[hidden]')) {
                createChart(canvas);
            }
        });

        document.querySelectorAll('[data-dashboard-chart-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                const panel = button.closest('[data-dashboard-chart-tabs]');
                if (!panel) return;

                const target = button.dataset.dashboardChartTab;
                panel.querySelectorAll('[data-dashboard-chart-tab]').forEach((item) => {
                    const active = item === button;
                    item.classList.toggle('active', active);
                    item.setAttribute('aria-selected', String(active));
                });

                panel.querySelectorAll('[data-dashboard-chart-pane]').forEach((pane) => {
                    const active = pane.dataset.dashboardChartPane === target;
                    pane.hidden = !active;
                    pane.classList.toggle('active', active);
                    if (active) {
                        ensureChart(pane.querySelector('canvas[data-chart]'));
                    }
                });
            });
        });

        document.querySelectorAll('[data-chart-type]').forEach((button) => {
            button.addEventListener('click', () => {
                const panel = button.closest('[data-chart-pane], .chart-panel');
                const canvas = panel ? panel.querySelector('canvas[data-chart]') : null;
                if (!canvas) return;

                panel.querySelectorAll('[data-chart-type]').forEach((item) => item.classList.toggle('active', item === button));
                canvas.dataset.chart = button.dataset.chartType;
                createChart(canvas, button.dataset.chartType);
            });
        });
    }

    if (document.querySelector('canvas[data-chart]')) {
        loadChartJs().then(bootCanvasCharts).catch(() => {});
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-global-search]');
    if (!form) return;

    const input = form.querySelector('input[type="search"]');
    const panel = form.querySelector('.global-search-panel');
    const endpoint = form.dataset.globalSearch;
    if (!input || !panel || !endpoint) return;

    let debounce = null;
    let inflight = null;

    function close() {
        panel.hidden = true;
        panel.innerHTML = '';
    }

    function activeLinks() {
        return Array.from(panel.querySelectorAll('a'));
    }

    function moveSelection(step) {
        const links = activeLinks();
        if (links.length === 0) return;

        const current = links.findIndex((link) => link.classList.contains('is-active'));
        const next = (current + step + links.length) % links.length;

        links.forEach((link, index) => link.classList.toggle('is-active', index === next));
        links[next].scrollIntoView({ block: 'nearest' });
    }

    function render(groups) {
        if (!groups.length) {
            panel.innerHTML = '<p class="empty-hint">لا توجد نتائج مطابقة.</p>';
            panel.hidden = false;
            return;
        }

        panel.innerHTML = groups.map((group) => {
            const items = group.items.map((item) => `
                <a href="${item.url}">
                    <strong>${escapeHtml(item.title)}</strong>
                    <em>${escapeHtml(item.meta || '')}</em>
                    <span>${escapeHtml(item.subtitle || '')}</span>
                </a>`).join('');

            return `<div class="group-label">${escapeHtml(group.label)}</div>${items}`;
        }).join('');

        panel.hidden = false;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function query(term) {
        if (inflight) inflight.abort();
        inflight = new AbortController();

        fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: inflight.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((payload) => {
                // A slower earlier request must not overwrite what the user is now typing.
                if (payload.term === input.value.trim()) render(payload.groups || []);
            })
            .catch(() => {});
    }

    input.addEventListener('input', () => {
        const term = input.value.trim();
        window.clearTimeout(debounce);

        if (term.length < 2) {
            close();
            return;
        }

        debounce = window.setTimeout(() => query(term), 220);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            input.blur();
            return;
        }

        if (panel.hidden) return;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            moveSelection(event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.key === 'Enter') {
            const active = panel.querySelector('a.is-active');
            if (active) {
                event.preventDefault();
                window.location.href = active.href;
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!form.contains(event.target)) close();
    });

    // "/" focuses the search box, the way most admin consoles behave.
    document.addEventListener('keydown', (event) => {
        const typingInField = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '');
        if (event.key === '/' && !typingInField) {
            event.preventDefault();
            input.focus();
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    // Advanced filters stay collapsed until asked for, but open automatically
    // when a filter is already applied so nothing in effect is hidden.
    document.querySelectorAll('[data-filter-bar]').forEach((bar) => {
        const toggle = bar.querySelector('[data-filter-toggle]');
        const panel = bar.querySelector('[data-filter-advanced]');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', () => {
            const open = panel.hidden;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        });

        // Changing a select re-runs the search straight away; typed fields wait
        // for Enter or the button so the page does not reload mid-word.
        panel.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', () => bar.submit());
        });
    });
});
