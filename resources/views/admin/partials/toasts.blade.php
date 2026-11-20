{{-- Modern Non-Blocking Floating Toast Notification Engine (Sonner / Linear Style) --}}
<div id="agyToastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none" aria-live="polite"></div>

<script>
    (function() {
        const container = document.getElementById('agyToastContainer');

        const icons = {
            success: '<i class="ri-checkbox-circle-fill text-emerald-500 text-base flex-shrink-0"></i>',
            error: '<i class="ri-error-warning-fill text-red-500 text-base flex-shrink-0"></i>',
            warning: '<i class="ri-alert-fill text-amber-500 text-base flex-shrink-0"></i>',
            info: '<i class="ri-information-fill text-brand-500 text-base flex-shrink-0"></i>',
        };

        const borderAccents = {
            success: 'border-l-4 border-l-emerald-500',
            error: 'border-l-4 border-l-red-500',
            warning: 'border-l-4 border-l-amber-500',
            info: 'border-l-4 border-l-brand-500',
        };

        window.toast = function(options) {
            if (!container) return;
            if (typeof options === 'string') {
                options = { message: options, type: 'info' };
            }

            const type = options.type || 'info';
            const title = options.title || (type.charAt(0).toUpperCase() + type.slice(1));
            const message = options.message || options.text || '';
            const duration = options.duration ?? 3500;

            const toastEl = document.createElement('div');
            toastEl.className = `pointer-events-auto flex items-start gap-2.5 p-3 rounded-lg border border-light-border dark:border-dark-border bg-light-surface/95 dark:bg-dark-surface/95 backdrop-blur-md shadow-lg text-xs transition-all duration-300 transform translate-y-3 opacity-0 ${borderAccents[type] || borderAccents.info}`;
            
            toastEl.innerHTML = `
                ${icons[type] || icons.info}
                <div class="flex-1 min-w-0 pr-1">
                    <div class="font-semibold text-light-text dark:text-dark-text leading-tight">${title}</div>
                    ${message ? `<div class="text-2xs text-light-muted dark:text-dark-muted mt-0.5 leading-snug break-words">${message}</div>` : ''}
                </div>
                <button type="button" class="w-5 h-5 rounded flex items-center justify-center text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-sm transition-colors cursor-pointer" aria-label="Close Toast">
                    <i class="ri-close-line"></i>
                </button>
            `;

            const closeBtn = toastEl.querySelector('button');
            const dismiss = () => {
                toastEl.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => {
                    toastEl.remove();
                }, 250);
            };

            closeBtn.addEventListener('click', dismiss);

            container.appendChild(toastEl);

            // Animate in
            requestAnimationFrame(() => {
                toastEl.classList.remove('opacity-0', 'translate-y-3');
            });

            if (duration > 0) {
                setTimeout(dismiss, duration);
            }
        };

        // Helper shortcuts
        window.toast.success = (msg, title = 'Success') => window.toast({ type: 'success', title, message: msg });
        window.toast.error = (msg, title = 'Error') => window.toast({ type: 'error', title, message: msg });
        window.toast.warning = (msg, title = 'Notice') => window.toast({ type: 'warning', title, message: msg });
        window.toast.info = (msg, title = 'Info') => window.toast({ type: 'info', title, message: msg });

        // Seamless SweetAlert 1 Compatibility Shim:
        // Converts legacy blocking swal(...) calls into modern, non-blocking toasts!
        window.swal = function() {
            const args = Array.from(arguments);
            if (typeof args[0] === 'object') {
                const opt = args[0];
                const type = opt.icon || opt.type || 'info';
                window.toast({
                    title: opt.title || 'Notification',
                    message: opt.text || '',
                    type: type === 'warning' ? 'warning' : (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info')),
                });
            } else {
                const title = args[0] || 'Notification';
                const message = args[1] || '';
                const type = args[2] || 'info';
                window.toast({
                    title: title,
                    message: message,
                    type: type === 'warning' ? 'warning' : (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info')),
                });
            }
        };
    })();
</script>
