        </div>
    </main>

    <script>
        // Active sidebar highlighting
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.documentElement;
            const path = window.location.pathname;

            // Dashboard hrefs that must use EXACT match only
            const exactMatchHrefs = ['/core_admin/', '/admin/', '/employee/', '/vendor/'];

            document.querySelectorAll('.sidebar-item').forEach(item => {
                const href = item.getAttribute('href');
                if (!href) return;

                const normalizedHref = href.replace(/\/$/, '');
                const normalizedPath = path.replace(/\/$/, '');

                let isActive = false;
                if (exactMatchHrefs.includes(href)) {
                    // Dashboard: exact match only
                    isActive = normalizedPath === normalizedHref || path === href;
                } else {
                    // Other links: match exact or sub-paths
                    isActive = normalizedPath === normalizedHref || path === href ||
                        (normalizedHref !== '' && path.startsWith(normalizedHref + '/'));
                }

                if (isActive) {
                    item.classList.add('active');
                }
            });

            const themeToggleButton = document.getElementById('themeToggleButton');
            const themeToggleIcon = document.getElementById('themeToggleIcon');

            const applyWorkspaceTheme = (theme, persist = true) => {
                const nextTheme = theme === 'light' ? 'light' : 'dark';
                root.setAttribute('data-theme', nextTheme);

                if (persist) {
                    try {
                        localStorage.setItem('workspace-theme', nextTheme);
                    } catch (error) {}
                }

                if (themeToggleButton) {
                    const isLight = nextTheme === 'light';
                    themeToggleButton.setAttribute('aria-pressed', String(isLight));
                    themeToggleButton.setAttribute('title', isLight ? 'Switch to dark mode' : 'Switch to light mode');
                    themeToggleButton.setAttribute('aria-label', isLight ? 'Switch to dark mode' : 'Switch to light mode');
                }

                if (themeToggleIcon) {
                    themeToggleIcon.className = `fa-solid ${nextTheme === 'light' ? 'fa-sun' : 'fa-moon'} text-base`;
                }
            };

            applyWorkspaceTheme(root.getAttribute('data-theme') || 'dark', false);

            if (themeToggleButton) {
                themeToggleButton.addEventListener('click', () => {
                    const currentTheme = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
                    applyWorkspaceTheme(currentTheme === 'light' ? 'dark' : 'light');
                });
            }

            if (window.matchMedia) {
                const colorScheme = window.matchMedia('(prefers-color-scheme: light)');
                const handleSchemeChange = (event) => {
                    try {
                        if (localStorage.getItem('workspace-theme')) {
                            return;
                        }
                    } catch (error) {}
                    applyWorkspaceTheme(event.matches ? 'light' : 'dark', false);
                };

                if (typeof colorScheme.addEventListener === 'function') {
                    colorScheme.addEventListener('change', handleSchemeChange);
                } else if (typeof colorScheme.addListener === 'function') {
                    colorScheme.addListener(handleSchemeChange);
                }
            }
        });

        // Toast notification system
        function showToast(message, type = 'success') {
            const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
            const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            const isLightTheme = document.documentElement.getAttribute('data-theme') === 'light';
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-2xl text-sm font-medium';
            toast.style.cssText = `background:${isLightTheme ? 'rgba(255,255,255,0.96)' : 'rgba(15,23,42,0.95)'};color:${isLightTheme ? '#0f172a' : '#f8fafc'};border:1px solid ${colors[type]}40;backdrop-filter:blur(12px);animation:fadeIn 0.3s ease`;
            toast.innerHTML = `<i class="fa-solid ${icons[type]}" style="color:${colors[type]}"></i><span>${message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        // Confirm delete modal
        function confirmAction(message, callback) {
            if (confirm(message)) callback();
        }

        // Fetch helper
        async function apiCall(url, method = 'GET', data = null) {
            const opts = { method, headers: { 'Content-Type': 'application/json' } };
            if (data) opts.body = JSON.stringify(data);
            const res = await fetch(url, opts);
            return res.json();
        }
    </script>
</body>
</html>
