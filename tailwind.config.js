import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                // Semantic Dynamic Theme Variables
                surface: 'var(--color-surface)',
                canvas: 'var(--color-canvas)',
                border: 'var(--color-border)',
                'surface-elevated': 'var(--color-surface-elevated)',
                'surface-subtle': 'var(--color-surface-subtle)',
                'text-primary': 'var(--color-text-primary)',
                'text-secondary': 'var(--color-text-secondary)',
                'text-muted': 'var(--color-text-muted)',

                // Midnight Enterprise Pro Slate / Dark System
                dark: {
                    canvas: '#090D16',
                    surface: '#0F172A',
                    card: '#111827',
                    elevated: '#1E293B',
                    border: '#1E293B',
                    'border-strong': '#334155',
                    muted: '#64748B',
                    text: '#F8FAFC',
                    'text-secondary': '#94A3B8',
                },
                // Light Mode System
                light: {
                    canvas: '#F8FAFC',
                    surface: '#FFFFFF',
                    card: '#FFFFFF',
                    elevated: '#F1F5F9',
                    border: '#E2E8F0',
                    'border-strong': '#CBD5E1',
                    muted: '#64748B',
                    text: '#020617',
                    'text-secondary': '#334155',
                },
                // Primary Accent Brand (Corporate Blue #0284C7)
                brand: {
                    50: '#F0F9FF',
                    100: '#E0F2FE',
                    200: '#BAE6FD',
                    300: '#7DD3FC',
                    400: '#38BDF8',
                    500: '#0EA5E9',
                    600: '#0284C7',
                    700: '#0369A1',
                    800: '#075985',
                    900: '#0C4A6E',
                    DEFAULT: '#0284C7',
                },
                // Telemetry / Status Colors
                telemetry: {
                    emerald: '#10B981',
                    'emerald-dark': '#059669',
                    warning: '#F59E0B',
                    danger: '#EF4444',
                    info: '#0284C7',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            fontSize: {
                '3xs': ['0.6875rem', { lineHeight: '0.95rem' }], // 11px
                '2xs': ['0.75rem', { lineHeight: '1.05rem' }],   // 12px (strictly +1px from original 11px)
                'xs': ['0.8125rem', { lineHeight: '1.15rem' }],   // 13px (strictly +1px from original 12px: sidebar nav & compact text)
                'sm': ['0.875rem', { lineHeight: '1.25rem' }],    // 14px (strictly +1px from original 13px)
                'base': ['0.875rem', { lineHeight: '1.25rem' }],  // 14px (balanced standard base, prevents page bloating)
                'md': ['0.9375rem', { lineHeight: '1.35rem' }],   // 15px
                'lg': ['1.0625rem', { lineHeight: '1.5rem' }],    // 17px
                'xl': ['1.25rem', { lineHeight: '1.75rem' }],     // 20px
                '2xl': ['1.5rem', { lineHeight: '2rem' }],        // 24px
            },
            borderRadius: {
                DEFAULT: '6px',
                sm: '4px',
                md: '6px',
                lg: '8px',
                xl: '10px',
            },
            boxShadow: {
                subtle: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                card: '0 4px 16px -2px rgba(0, 0, 0, 0.25)',
                dropdown: '0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05)',
                modal: '0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.07)',
            },
        },
    },

    plugins: [forms({ strategy: 'class' })],
};
