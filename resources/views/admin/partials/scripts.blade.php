{{-- Jquery CDN --}}
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

{{-- SweetAlert CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@if(session('success'))
    <script>
        swal({
            title: "Success!",
            text: "{{ session('success') }}",
            icon: "success",
            button: "OK",
        });
    </script>
@endif

@if(session('error'))
    <script>
        swal({
            title: "Error!",
            text: "{{ session('error') }}",
            icon: "error",
            button: "OK",
        });
    </script>
@endif

{{-- Select 2 --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('.single-select2').length) {
            $('.single-select2').select2();
        }
        if ($('.multiple-select2').length) {
            $('.multiple-select2').select2({
                placeholder: "Select options",
                allowClear: true
            });
        }
    });
</script>

{{-- DatePicker plugin --}}
<script src="https://unpkg.com/gijgo@1.9.14/js/gijgo.min.js" type="text/javascript"></script>
<script>
    if ($('#datepicker').length) {
        $('#datepicker').datepicker({
            format: 'yyyy-mm-dd',
            showOtherMonths: true
        });
    }
</script>

<!-- Common Global CSRF Setup -->
<script>
    var SITEURL = "{{ URL::to('') }}";
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    });
</script>

{{-- =========================================================================
     TAILWIND-NATIVE UI ENGINE (MODAL, DROPDOWN, TABS & THEME CONTROLLER)
     Replaces Bootstrap JS bundle completely with 100% API Compatibility!
     ========================================================================= --}}
<script>
    // --- 1. Pure Tailwind Modal Manager ---
    window.showTailwindModal = function(modalEl) {
        if (typeof modalEl === 'string') {
            modalEl = document.querySelector(modalEl);
        }
        if (!modalEl) return;

        // Ensure backdrop exists
        var backdrop = document.getElementById('tailwindModalBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'tailwindModalBackdrop';
            backdrop.className = 'tailwind-modal-backdrop opacity-0 transition-opacity duration-200';
            document.body.appendChild(backdrop);
        }

        // Show backdrop with fade
        backdrop.style.display = 'block';
        setTimeout(() => { backdrop.classList.remove('opacity-0'); backdrop.classList.add('opacity-100'); }, 10);

        // Position and show modal
        modalEl.style.display = 'flex';
        modalEl.classList.remove('hidden');
        modalEl.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        setTimeout(() => {
            modalEl.classList.add('show');
            var dialog = modalEl.querySelector('.modal-dialog');
            if (dialog) {
                dialog.style.transform = 'scale(1) translateY(0)';
                dialog.style.opacity = '1';
            }
        }, 10);

        // Dispatch shown event
        modalEl.dispatchEvent(new Event('shown.bs.modal', { bubbles: true }));
    };

    window.hideTailwindModal = function(modalEl) {
        if (typeof modalEl === 'string') {
            modalEl = document.querySelector(modalEl);
        }
        if (!modalEl) return;

        var dialog = modalEl.querySelector('.modal-dialog');
        if (dialog) {
            dialog.style.transform = 'scale(0.96) translateY(-10px)';
            dialog.style.opacity = '0';
        }
        modalEl.classList.remove('show');

        setTimeout(() => {
            modalEl.style.display = 'none';
            modalEl.classList.add('hidden');
            modalEl.setAttribute('aria-hidden', 'true');

            // Hide backdrop if no other active modal
            var anyOpen = document.querySelectorAll('.modal.show, .tailwind-modal.show');
            if (anyOpen.length === 0) {
                var backdrop = document.getElementById('tailwindModalBackdrop');
                if (backdrop) {
                    backdrop.classList.remove('opacity-100');
                    backdrop.classList.add('opacity-0');
                    setTimeout(() => { backdrop.style.display = 'none'; }, 200);
                }
                document.body.style.overflow = '';
            }
        }, 150);

        modalEl.dispatchEvent(new Event('hidden.bs.modal', { bubbles: true }));
    };

    // jQuery $.fn.modal adapter for seamless existing JS compatibility
    if (window.jQuery) {
        window.jQuery.fn.modal = function(action) {
            return this.each(function() {
                if (action === 'show') {
                    window.showTailwindModal(this);
                } else if (action === 'hide') {
                    window.hideTailwindModal(this);
                }
            });
        };
    }

    // bootstrap.Modal API compatibility shim
    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = function(el) {
        this.el = typeof el === 'string' ? document.querySelector(el) : el;
    };
    window.bootstrap.Modal.prototype.show = function() {
        window.showTailwindModal(this.el);
    };
    window.bootstrap.Modal.prototype.hide = function() {
        window.hideTailwindModal(this.el);
    };
    window.bootstrap.Modal.getInstance = function(el) {
        return new window.bootstrap.Modal(el);
    };
    window.bootstrap.Modal.getOrCreateInstance = function(el) {
        return new window.bootstrap.Modal(el);
    };

    // bootstrap.Tab API compatibility shim
    window.bootstrap.Tab = function(el) {
        this.el = typeof el === 'string' ? document.querySelector(el) : el;
    };
    window.bootstrap.Tab.prototype.show = function() {
        if (!this.el) return;
        var targetSelector = this.el.getAttribute('data-bs-target') || this.el.getAttribute('href');
        if (!targetSelector) return;
        var targetEl = document.querySelector(targetSelector);
        if (!targetEl) return;
        var container = targetEl.parentElement;
        if (container) {
            container.querySelectorAll('.tab-pane').forEach(function(p) {
                p.classList.remove('show', 'active');
                p.style.display = 'none';
            });
        }
        targetEl.classList.add('show', 'active');
        targetEl.style.display = 'block';

        var nav = this.el.closest('ul, nav, .nav, .tabs');
        if (nav) {
            nav.querySelectorAll('.nav-link, button').forEach(function(b) {
                b.classList.remove('active');
            });
        }
        this.el.classList.add('active');
    };
    window.bootstrap.Tab.getInstance = function(el) {
        return new window.bootstrap.Tab(el);
    };
    window.bootstrap.Tab.getOrCreateInstance = function(el) {
        return new window.bootstrap.Tab(el);
    };

    // Modal Trigger Delegation
    document.addEventListener('click', function(e) {
        // Open Modal
        var trigger = e.target.closest('[data-bs-toggle="modal"], [data-modal-target]');
        if (trigger) {
            e.preventDefault();
            var targetSelector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-modal-target') || trigger.getAttribute('href');
            if (targetSelector && targetSelector.startsWith('#')) {
                window.showTailwindModal(targetSelector);
            }
            return;
        }

        // Close Modal
        var dismiss = e.target.closest('[data-bs-dismiss="modal"], [data-modal-close]');
        if (dismiss) {
            e.preventDefault();
            var modal = dismiss.closest('.modal, .tailwind-modal');
            if (modal) {
                window.hideTailwindModal(modal);
            }
            return;
        }

        // Backdrop click to close (unless data-bs-backdrop="static")
        if (e.target.classList.contains('modal') || e.target.classList.contains('tailwind-modal')) {
            if (e.target.getAttribute('data-bs-backdrop') !== 'static') {
                window.hideTailwindModal(e.target);
            }
        }

        // Dismiss Alert
        var alertDismiss = e.target.closest('[data-bs-dismiss="alert"]');
        if (alertDismiss) {
            e.preventDefault();
            var alertEl = alertDismiss.closest('.alert');
            if (alertEl) {
                alertEl.style.opacity = '0';
                setTimeout(() => alertEl.remove(), 200);
            }
        }

        // Tabs / Pills Toggle
        var tabToggle = e.target.closest('[data-bs-toggle="pill"], [data-bs-toggle="tab"]');
        if (tabToggle) {
            e.preventDefault();
            if (window.bootstrap.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(tabToggle).show();
            }
            return;
        }

        // Dropdown toggle
        var dropToggle = e.target.closest('[data-bs-toggle="dropdown"], [data-dropdown-toggle]');
        if (dropToggle) {
            e.preventDefault();
            e.stopPropagation();
            var menu = dropToggle.nextElementSibling || dropToggle.parentElement.querySelector('.dropdown-menu, .app-dropdown-menu');
            if (menu) {
                var isShown = menu.classList.contains('show') || !menu.classList.contains('hidden');
                document.querySelectorAll('.dropdown-menu.show, .app-dropdown-menu:not(.hidden)').forEach(m => {
                    m.classList.remove('show');
                    m.classList.add('hidden');
                });
                if (!isShown) {
                    menu.classList.add('show');
                    menu.classList.remove('hidden');
                }
            }
            return;
        }

        // Close dropdowns on outside click
        if (!e.target.closest('.dropdown-menu, .app-dropdown-menu')) {
            document.querySelectorAll('.dropdown-menu.show, .app-dropdown-menu:not(.hidden)').forEach(m => {
                m.classList.remove('show');
                m.classList.add('hidden');
            });
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var openModals = document.querySelectorAll('.modal.show, .tailwind-modal.show');
            if (openModals.length > 0) {
                var topModal = openModals[openModals.length - 1];
                if (topModal.getAttribute('data-bs-backdrop') !== 'static') {
                    window.hideTailwindModal(topModal);
                }
            }
        }
    });

    // --- 2. Global Dark / Light Theme Controller ---
    function updateThemeToggleUI(theme) {
        var icon = document.getElementById('themeToggleIcon');
        var btn = document.getElementById('themeToggleBtn');
        if (!icon) return;
        if (theme === 'dark') {
            icon.className = 'ri-sun-line text-amber-400 text-sm';
            if (btn) btn.title = 'Switch to Light Mode';
        } else {
            icon.className = 'ri-moon-line text-slate-500 text-sm';
            if (btn) btn.title = 'Switch to Dark Mode';
        }
    }

    function toggleAdminTheme() {
        var currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        if (newTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        try {
            localStorage.setItem('agy_admin_theme', newTheme);
        } catch (e) {}

        updateThemeToggleUI(newTheme);
        window.dispatchEvent(new CustomEvent('agy-theme-changed', { detail: { theme: newTheme } }));
    }

    document.addEventListener('DOMContentLoaded', function() {
        var activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        if (activeTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        updateThemeToggleUI(activeTheme);

        // Omnisearch keyboard shortcut (⌘K or Ctrl+K)
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                var searchInput = document.getElementById('globalOmnisearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });

        // Sidebar toggle controller with persistence & mobile support
        var sidebarToggle = document.getElementById('sidebarToggleBtn') || document.getElementById('btn');
        var sidebar = document.getElementById('adminSidebar') || document.querySelector('.sidebar');
        var sidebarBackdrop = document.getElementById('sidebarBackdrop');

        window.toggleSidebar = function(force) {
            if (!sidebar) return;
            var isDesktop = window.innerWidth >= 1024;
            if (isDesktop) {
                var willCollapse = (typeof force === 'boolean') ? !force : !sidebar.classList.contains('collapsed');
                sidebar.classList.toggle('collapsed', willCollapse);
                try {
                    localStorage.setItem('agy_sidebar_collapsed', willCollapse ? 'true' : 'false');
                } catch(e) {}
            } else {
                var willOpen = (typeof force === 'boolean') ? force : !sidebar.classList.contains('active');
                sidebar.classList.toggle('active', willOpen);
                sidebar.classList.toggle('open', willOpen);
                if (sidebarBackdrop) {
                    if (willOpen) {
                        sidebarBackdrop.classList.remove('hidden');
                    } else {
                        sidebarBackdrop.classList.add('hidden');
                    }
                }
            }
        };

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                window.toggleSidebar();
            });
        }

        // Restore desktop collapse preference on load
        try {
            if (window.innerWidth >= 1024 && localStorage.getItem('agy_sidebar_collapsed') === 'true') {
                if (sidebar) sidebar.classList.add('collapsed');
            }
        } catch(e) {}
    });
</script>
