{{-- Paid Messaging & Commercial Package Coming Soon Modal --}}
<div class="modal fade" id="paidServiceComingSoonModal" tabindex="-1" aria-labelledby="paidServiceComingSoonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered max-w-lg">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5 sm:p-6">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg border border-amber-500/20 shadow-sm">
                        <i class="ri-flashlight-line"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2" id="paidServiceComingSoonModalLabel">
                            <span>পেইড মেসেজিং সার্ভিস — শীঘ্রই আসছে</span>
                            <span class="app-badge app-badge-warning !text-4xs !py-0.5 !px-1.5 font-mono uppercase font-bold">Coming Soon</span>
                        </h3>
                        <p class="text-3xs text-light-muted dark:text-dark-muted">Paid Messaging Quota, Top-up & Direct Meta Scaling</p>
                    </div>
                </div>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text transition-colors p-1" data-bs-dismiss="modal">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="space-y-4 py-1">
                @php
                    $wsId = Auth::user()?->workspace_id ?: 1;
                    $ws = \App\Models\Workspace::find($wsId);
                    $currentWalletBal = (float) ($ws?->wallet?->balance ?? 50.00);
                    $packages = \App\Services\Billing\WorkspaceWalletService::MESSAGING_PACKAGES;
                @endphp

                {{-- Informational Zero-Cost Guarantee Banner --}}
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs space-y-1">
                    <div class="flex items-center justify-between gap-1.5 font-bold">
                        <span class="flex items-center gap-1.5">
                            <i class="ri-shield-check-fill text-emerald-500 text-base"></i>
                            <span>বর্তমানে আপনার কোনো ক্রেডিট কার্ড চার্জ কাটবে না!</span>
                        </span>
                        <span class="app-badge app-badge-success !text-3xs font-mono">100% Free Protected</span>
                    </div>
                    <p class="text-2xs leading-relaxed text-light-text-secondary dark:text-dark-text-secondary">
                        আমাদের প্ল্যাটফর্মটি শতভাগ মেটার অফিসিয়াল ফ্রি লিমিটের (প্রতি মাসে WhatsApp ১,০০০ ফ্রি কাস্টমার সার্ভিস ও Facebook Messenger ২৪ ঘণ্টা ফ্রি উইন্ডো) আওতায় পরিচালিত হচ্ছে। ১,০০০ কোটা শেষ হলে স্বয়ংক্রিয়ভাবে মেসেজ ব্লক হয়ে অতিরিক্ত চার্জ রোধ করে।
                    </p>
                </div>

                {{-- Active Messaging Packages (Instant Purchase with Wallet) --}}
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <h5 class="text-2xs font-bold text-light-text dark:text-dark-text uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ri-shopping-cart-2-fill text-brand-500"></i>
                            <span>মেসেজিং কোটা প্যাকেজ (Instant Purchase):</span>
                        </h5>
                        <span class="text-3xs text-light-muted dark:text-dark-muted">
                            ওয়ালেট ব্যালেন্স: <strong class="text-brand-600 dark:text-brand-400">৳{{ number_format($currentWalletBal, 2) }} BDT</strong>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        @foreach($packages as $key => $pkg)
                            @php
                                $canAfford = $currentWalletBal >= $pkg['price'];
                            @endphp
                            <div class="p-3 rounded-xl border {{ $key === 'growth' ? 'border-brand-500/40 bg-brand-500/5' : 'border-light-border dark:border-dark-border bg-light-elevated/40 dark:bg-dark-elevated/40' }} flex flex-col justify-between relative">
                                @if($key === 'growth')
                                    <span class="absolute -top-2 right-2 app-badge app-badge-primary !text-4xs !py-0 font-bold uppercase">Popular</span>
                                @endif
                                <div>
                                    <div class="text-xs font-bold text-light-text dark:text-dark-text">{{ $pkg['title'] }}</div>
                                    <div class="text-lg font-extrabold text-light-text dark:text-dark-text mt-1">
                                        ৳{{ number_format($pkg['price'], 0) }} <span class="text-3xs font-normal text-light-muted">BDT</span>
                                    </div>
                                    <div class="text-2xs text-light-muted dark:text-dark-muted mt-0.5">
                                        {{ number_format($pkg['messages']) }} টি মেসেজ
                                    </div>
                                    <p class="text-3xs text-light-text-secondary dark:text-dark-text-secondary mt-1 leading-snug">
                                        {{ $pkg['description'] }}
                                    </p>
                                </div>
                                <div class="mt-3 pt-2 border-t border-light-border dark:border-dark-border">
                                    @if($canAfford)
                                        <form method="POST" action="{{ route('billing.purchase-package') }}">
                                            @csrf
                                            <input type="hidden" name="package" value="{{ $key }}">
                                            <button type="submit" class="app-btn app-btn-primary w-full !h-7 !text-3xs font-bold flex items-center justify-center gap-1 shadow-xs">
                                                <i class="ri-check-line"></i> এখনই কিনুন
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('billing.index') }}" class="app-btn app-btn-secondary w-full !h-7 !text-3xs font-medium flex items-center justify-center gap-1 text-amber-600 dark:text-amber-400">
                                            <i class="ri-wallet-3-line"></i> রিচার্জ করুন
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Feature Roadmap / What is Coming --}}
                <div class="space-y-2.5 pt-1">
                    <h5 class="text-2xs font-bold text-light-text dark:text-dark-text uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ri-sparkling-fill text-amber-500"></i>
                        <span>পেইড সার্ভিসে যেসব সুবিধা অন্তর্ভুক্ত:</span>
                    </h5>
                    <div class="grid grid-cols-1 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl border border-light-border dark:border-dark-border bg-light-elevated/40 dark:bg-dark-elevated/40 flex items-start gap-3">
                            <div class="w-7 h-7 rounded-lg bg-pink-500/10 text-pink-500 flex items-center justify-center shrink-0 text-sm mt-0.5">
                                <i class="ri-smartphone-line"></i>
                            </div>
                            <div>
                                <strong class="text-light-text dark:text-dark-text text-2xs block font-bold">সহজ বিকাশ ও নগদ টপ-আপ (Direct Local Checkout)</strong>
                                <span class="text-3xs text-light-muted dark:text-dark-muted leading-relaxed">কোনো ডুয়েল-কারেন্সি ক্রেডিট কার্ডের ঝামেলা ছাড়াই বিকাশ ও নগদ দিয়ে সরাসরি ওয়ালেট ব্যালেন্স রিচার্জ করার সুবিধা।</span>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl border border-light-border dark:border-dark-border bg-light-elevated/40 dark:bg-dark-elevated/40 flex items-start gap-3">
                            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0 text-sm mt-0.5">
                                <i class="ri-whatsapp-line"></i>
                            </div>
                            <div>
                                <strong class="text-light-text dark:text-dark-text text-2xs block font-bold">১,০০০ কোটা শেষ হলেও আনলিমিটেড AI সেলস</strong>
                                <span class="text-3xs text-light-muted dark:text-dark-muted leading-relaxed">মাসিক ফ্রি ১,০০০ মেসেজ শেষ হলেও প্যাকেজ ক্রয় করে নিরবচ্ছিন্ন অটোমেটেড সেলস ও সাপোর্ট চালু রাখার সুবিধা।</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-light-border dark:border-dark-border flex items-center justify-between gap-2">
                <a href="{{ route('billing.index') }}" class="app-btn app-btn-secondary !h-8 !px-3 text-xs flex items-center gap-1">
                    <i class="ri-wallet-3-line text-brand-500"></i>
                    <span>ওয়ালেট লেজার ও রিচার্জ</span>
                </a>
                <button type="button" class="app-btn app-btn-primary !h-8 !px-4 text-xs shadow-md flex items-center gap-1" data-bs-dismiss="modal">
                    <i class="ri-check-line"></i>
                    <span>বুঝেছি</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.openPaidServiceComingSoonModal = function() {
    const modalEl = document.getElementById('paidServiceComingSoonModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
};
</script>
