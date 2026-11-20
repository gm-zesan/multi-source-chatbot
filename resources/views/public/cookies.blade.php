@extends('layouts.public')

@section('title', 'Cookie Policy — Entrepreneurs Automation')
@section('meta_description', 'Understand how Entrepreneurs Automation uses cookies and local browser storage to provide secure authentication, theme preferences, and widget sessions.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 mb-4">
                <i class="ri-cookie-line"></i> Browser Storage Disclosure
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-light-text dark:text-dark-text mb-3">
                Cookie Policy
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                Effective Date: November 2026 / Technical Overview
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">1. What are Cookies and Local Storage?</h2>
                <p>
                    Cookies are compact text files placed onto your device by websites that you visit. Alongside HTTP cookies, modern web applications utilize HTML5 LocalStorage and SessionStorage to maintain operational preferences and user session integrity.
                </p>
                <p>
                    Entrepreneurs Automation adheres to a strict minimal-data philosophy: we only employ cookies and client-side storage technologies necessary to deliver secure sessions, maintain theme consistency, and enable live web chat conversations.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">2. Categories of Storage We Use</h2>

                <div class="space-y-4">
                    {{-- Essential --}}
                    <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2 mb-2 font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-shield-keyhole-line text-emerald-500 text-sm"></i>
                            <span>A. Strictly Necessary & Authentication Cookies</span>
                        </div>
                        <p class="text-xs mb-3">
                            These identifiers are strictly required for security, authentication, and CSRF token protection when navigating the website and administrative consoles. Without these tokens, authenticated features cannot function.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <thead>
                                    <tr class="border-b border-light-border dark:border-dark-border text-light-muted dark:text-dark-muted">
                                        <th class="py-2">Identifier</th>
                                        <th class="py-2">Type</th>
                                        <th class="py-2">Purpose</th>
                                        <th class="py-2">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-light-border/40 dark:divide-dark-border/40 text-light-text dark:text-dark-text">
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">XSRF-TOKEN</td>
                                        <td class="py-2">Cookie</td>
                                        <td class="py-2 font-sans">Cross-Site Request Forgery security verification</td>
                                        <td class="py-2">Session</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">ea_session</td>
                                        <td class="py-2">Cookie</td>
                                        <td class="py-2 font-sans">Encrypted administrative session identifier</td>
                                        <td class="py-2">120 Minutes</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">agy_admin_theme</td>
                                        <td class="py-2">LocalStorage</td>
                                        <td class="py-2 font-sans">Remembers Dark / Light theme selection to prevent UI flicker</td>
                                        <td class="py-2">Persistent</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Web Chat Widget --}}
                    <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2 mb-2 font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-chat-3-line text-sky-500 text-sm"></i>
                            <span>B. Customer Web Chat Widget Storage</span>
                        </div>
                        <p class="text-xs mb-2">
                            When customers interact with our embeddable web chat widget on your storefront, client storage is used exclusively to maintain conversation thread continuity across page refreshes.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <tbody class="divide-y divide-light-border/40 dark:divide-dark-border/40 text-light-text dark:text-dark-text">
                                    <tr>
                                        <td class="py-2 text-sky-600 dark:text-sky-400 font-bold w-1/4">ea_chat_session</td>
                                        <td class="py-2 font-sans">Retains active chat conversation UUID so customer messages remain visible on navigation</td>
                                        <td class="py-2 w-1/4">24 Hours</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">3. Third-Party Tracking & Advertising</h2>
                <p>
                    <strong class="text-light-text dark:text-dark-text">We do not employ third-party advertising cookies, cross-site trackers, or data-broker pixels</strong> anywhere on the Entrepreneurs Automation platform. We do not participate in behavioral re-targeting advertising networks.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">4. Managing Your Storage Preferences</h2>
                <p>
                    You can inspect, clear, or block cookies and LocalStorage through your web browser preferences at any time:
                </p>
                <ul class="list-disc pl-6 space-y-1 text-xs">
                    <li><strong class="text-light-text dark:text-dark-text">Google Chrome:</strong> Settings &rarr; Privacy and security &rarr; Third-party cookies &rarr; See all site data and permissions.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Mozilla Firefox:</strong> Settings &rarr; Privacy & Security &rarr; Cookies and Site Data.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Apple Safari:</strong> Preferences &rarr; Privacy &rarr; Manage Website Data.</li>
                </ul>
                <p class="text-xs text-light-muted dark:text-dark-muted">
                    Note: Disabling strictly necessary cookies will prevent successful login authentication to the administrative console.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">5. Contact</h2>
                <p>
                    For technical or compliance questions regarding client storage, contact our engineering team:
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">Entrepreneurs Automation Infrastructure Team</p>
                    <p class="text-light-muted dark:text-dark-muted">Email: infrastructure@entrepreneursautomation.com</p>
                </div>
            </section>
        </div>

        {{-- Footer Link --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('terms') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> Terms of Service
            </a>
            <a href="{{ route('home') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                Return to Homepage &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
