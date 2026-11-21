@extends('admin.app')

@section('title')
    Business Profile & Settings
@endsection

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                @if(Auth::user()->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value))
                    <a href="{{ route('workspaces.index') }}" class="app-btn app-btn-secondary !h-6 !px-2 text-2xs">
                        <i class="ri-arrow-left-line"></i> Workspaces
                    </a>
                @endif
                <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <span>Workspace Business Profile</span>
                    <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
                </h1>
            </div>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Configure your business identity, AI chatbot persona, custom sales rules, and customer support channels.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('billing.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-wallet-3-line text-xs"></i> Billing & Wallet
            </a>
            <a href="{{ route('channels.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-share-line text-xs"></i> Channels
            </a>
            <a href="{{ route('simulator.index') }}" class="app-btn app-btn-primary">
                <i class="ri-robot-2-line text-xs"></i> Test in Simulator
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert p-3 rounded border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-checkbox-circle-line text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    <form action="{{ route('workspace.settings.update') }}" method="POST" autocomplete="off">
        @csrf
        @if(Auth::user()->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value))
            <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left Form Column (2 Cols) --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- 1. Business Identity Card --}}
                <div class="app-card overflow-hidden">
                    <div class="app-card-header">
                        <div class="flex items-center gap-2 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-building-line text-brand-500"></i>
                            <span>Business Identity & Currency</span>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="name" class="text-2xs font-semibold text-light-text dark:text-dark-text">Business / Workspace Name <span class="text-red-500">*</span></label>
                                <input type="text" class="app-input w-full @error('name') border-red-500 @enderror" name="name" id="name" value="{{ old('name', $workspace->name) }}" required>
                                @error('name')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="space-y-1">
                                <label for="currency" class="text-2xs font-semibold text-light-text dark:text-dark-text">Default Currency <span class="text-red-500">*</span></label>
                                <select class="app-select w-full @error('currency') border-red-500 @enderror" name="currency" id="currency" required>
                                    <option value="BDT" {{ old('currency', $workspace->currency) === 'BDT' ? 'selected' : '' }}>BDT (৳) - Bangladeshi Taka</option>
                                    <option value="USD" {{ old('currency', $workspace->currency) === 'USD' ? 'selected' : '' }}>USD ($) - US Dollar</option>
                                    <option value="EUR" {{ old('currency', $workspace->currency) === 'EUR' ? 'selected' : '' }}>EUR (€) - Euro</option>
                                    <option value="GBP" {{ old('currency', $workspace->currency) === 'GBP' ? 'selected' : '' }}>GBP (£) - British Pound</option>
                                    <option value="INR" {{ old('currency', $workspace->currency) === 'INR' ? 'selected' : '' }}>INR (₹) - Indian Rupee</option>
                                </select>
                                @error('currency')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="support_phone" class="text-2xs font-semibold text-light-text dark:text-dark-text">Support Helpline Phone</label>
                                <input type="text" class="app-input w-full @error('support_phone') border-red-500 @enderror" name="support_phone" id="support_phone" value="{{ old('support_phone', $workspace->support_phone) }}" placeholder="+8801XXXXXXXXX">
                                <span class="text-2xs text-light-muted dark:text-dark-muted block">Referenced by bot when customer asks for your customer support phone.</span>
                                @error('support_phone')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="space-y-1">
                                <label for="support_email" class="text-2xs font-semibold text-light-text dark:text-dark-text">Support Email Address</label>
                                <input type="email" class="app-input w-full @error('support_email') border-red-500 @enderror" name="support_email" id="support_email" value="{{ old('support_email', $workspace->support_email) }}" placeholder="support@yourbusiness.com">
                                <span class="text-2xs text-light-muted dark:text-dark-muted block">Referenced by bot when escalating inquiries.</span>
                                @error('support_email')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="business_address" class="text-2xs font-semibold text-light-text dark:text-dark-text">Business Physical Store / Office Address</label>
                            <textarea class="app-textarea w-full !h-20 py-2 @error('business_address') border-red-500 @enderror" name="business_address" id="business_address" rows="3" placeholder="Plot/Building, Street, Area, City, Postal Code, Country">{{ old('business_address', $workspace->business_address) }}</textarea>
                            <span class="text-2xs text-light-muted dark:text-dark-muted block">Used by bot when customer asks for store or showroom address.</span>
                            @error('business_address')
                                <div class="text-2xs text-red-500">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- 2. AI Chatbot Personality & Custom Sales Rules --}}
                <div class="app-card overflow-hidden">
                    <div class="app-card-header">
                        <div class="flex items-center gap-2 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-robot-2-line text-brand-500"></i>
                            <span>AI Chatbot Personality & Custom Sales Rules</span>
                            <span class="app-badge app-badge-primary text-3xs uppercase">Omnichannel AI</span>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <p class="text-2xs text-light-muted dark:text-dark-muted">
                            Customize how your AI interacts with customers on Facebook Messenger, WhatsApp, TikTok, and your Storefront Web Widget.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Bot Name --}}
                            <div class="space-y-1">
                                <label for="ai_bot_name" class="text-2xs font-semibold text-light-text dark:text-dark-text">
                                    AI Bot Persona Name
                                </label>
                                <input 
                                    type="text" 
                                    class="app-input w-full" 
                                    name="settings[ai_bot_name]" 
                                    id="ai_bot_name" 
                                    value="{{ old('settings.ai_bot_name', $workspace->getAiBotName()) }}" 
                                    placeholder="e.g. {{ $workspace->name }} AI Assistant"
                                >
                                <span class="text-3xs text-light-muted dark:text-dark-muted block">The name the bot introduces itself with.</span>
                            </div>

                            {{-- Tone & Persona --}}
                            <div class="space-y-1">
                                <label for="ai_persona_tone" class="text-2xs font-semibold text-light-text dark:text-dark-text">
                                    Conversational Tone & Language Style
                                </label>
                                <select class="app-select w-full" name="settings[ai_persona_tone]" id="ai_persona_tone">
                                    <option value="friendly_banglish" {{ old('settings.ai_persona_tone', $workspace->getAiPersonaTone()) === 'friendly_banglish' ? 'selected' : '' }}>
                                        Friendly E-commerce (বাংলা ও বাংলিশ — "জি ভাইয়া/আপু")
                                    </option>
                                    <option value="formal_bengali" {{ old('settings.ai_persona_tone', $workspace->getAiPersonaTone()) === 'formal_bengali' ? 'selected' : '' }}>
                                        Formal Standard Bengali (প্রমিত বাংলা — "সম্মানিত গ্রাহক")
                                    </option>
                                    <option value="balanced" {{ old('settings.ai_persona_tone', $workspace->getAiPersonaTone()) === 'balanced' ? 'selected' : '' }}>
                                        Balanced & Adaptable (ম্যাচিং ল্যাঙ্গুয়েজ ও মার্জিত ভাষা)
                                    </option>
                                    <option value="professional_english" {{ old('settings.ai_persona_tone', $workspace->getAiPersonaTone()) === 'professional_english' ? 'selected' : '' }}>
                                        Professional English (Corporate & International)
                                    </option>
                                </select>
                                <span class="text-3xs text-light-muted dark:text-dark-muted block">Dictates greetings, empathy, and phrasing style.</span>
                            </div>
                        </div>

                        {{-- Custom Sales Rules / System Prompt --}}
                        <div class="space-y-1">
                            <label for="custom_sales_prompt" class="text-2xs font-semibold text-light-text dark:text-dark-text flex items-center justify-between">
                                <span>Custom Business & Delivery Rules (Prompt Directives)</span>
                                <span class="text-brand-500 text-3xs font-mono">Injected into LLM</span>
                            </label>
                            <textarea 
                                class="app-textarea w-full !h-24 py-2 text-xs" 
                                name="settings[custom_sales_prompt]" 
                                id="custom_sales_prompt" 
                                rows="3" 
                                placeholder="যেমন:
• ডেলিভারি চার্জ: ঢাকার ভিতরে ৳৮০, ঢাকার বাইরে ৳১৫০।
• ডেলিভারি সময়: ২-৩ কার্যদিবস। ক্যাশ অন ডেলিভারি প্রযোজ্য।
• কোনো পোশাকে সাইজ বা কালার সমস্যা হলে ৩ দিনের মধ্যে এক্সচেঞ্জ সুবিধা রয়েছে।
• কাস্টমার অর্ডার করতে চাইলে তাদের নাম, ফোন নম্বর ও ডেলিভারি ঠিকানা চেয়ে নাও।"
                            >{{ old('settings.custom_sales_prompt', $workspace->getCustomSalesPrompt()) }}</textarea>
                            <span class="text-3xs text-light-muted dark:text-dark-muted block">
                                Add delivery charges, return policies, warranty terms, and discounts that the AI must follow.
                            </span>
                        </div>

                        {{-- Fallback Message --}}
                        <div class="space-y-1">
                            <label for="fallback_message" class="text-2xs font-semibold text-light-text dark:text-dark-text">
                                Fallback Response (When Information is Unknown)
                            </label>
                            <input 
                                type="text" 
                                class="app-input w-full text-xs" 
                                name="settings[fallback_message]" 
                                id="fallback_message" 
                                value="{{ old('settings.fallback_message', $workspace->getFallbackMessage()) }}" 
                                placeholder="দুঃখিত, এই বিষয়টি সম্পর্কে আমার কাছে সঠিক তথ্য নেই। আমাদের সাপোর্ট টিম দ্রুত আপনার সাথে যোগাযোগ করবে।"
                            >
                            <span class="text-3xs text-light-muted dark:text-dark-muted block">Sent when a customer asks a question outside your knowledge base.</span>
                        </div>

                        {{-- Auto Lead Capture Switch --}}
                        <div class="pt-2 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                            <div>
                                <strong class="text-xs font-semibold text-light-text dark:text-dark-text block">
                                    Automated Order & Lead Extraction
                                </strong>
                                <span class="text-3xs text-light-muted dark:text-dark-muted">
                                    When customers provide their Name, Phone & Address in chat, automatically parse and save to your Orders Hub.
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="settings[order_auto_capture_enabled]" value="0">
                                <input 
                                    type="checkbox" 
                                    name="settings[order_auto_capture_enabled]" 
                                    value="1" 
                                    class="sr-only peer"
                                    {{ old('settings.order_auto_capture_enabled', $workspace->isOrderAutoCaptureEnabled()) ? 'checked' : '' }}
                                >
                                <div class="w-9 h-5 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500"></div>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- 3. Operating Hours Card --}}
                <div class="app-card overflow-hidden">
                    <div class="app-card-header">
                        <div class="flex items-center gap-2 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-time-line text-brand-500"></i>
                            <span>Store Operating Hours</span>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3">
                        <p class="text-2xs text-light-muted dark:text-dark-muted">
                            The AI chatbot informs customers whether your sales and support staff are currently available based on these operating hours.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="operating_hours_weekdays" class="text-2xs font-semibold text-light-text dark:text-dark-text">Weekdays Hours</label>
                                <input type="text" class="app-input w-full" name="operating_hours[weekdays]" id="operating_hours_weekdays" value="{{ old('operating_hours.weekdays', $workspace->operating_hours['weekdays'] ?? '09:00 AM - 08:00 PM') }}" placeholder="09:00 AM - 08:00 PM">
                            </div>
                            <div class="space-y-1">
                                <label for="operating_hours_weekend" class="text-2xs font-semibold text-light-text dark:text-dark-text">Weekends / Holidays</label>
                                <input type="text" class="app-input w-full" name="operating_hours[weekend]" id="operating_hours_weekend" value="{{ old('operating_hours.weekend', $workspace->operating_hours['weekend'] ?? '10:00 AM - 06:00 PM') }}" placeholder="10:00 AM - 06:00 PM">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="app-btn app-btn-primary">
                        <i class="ri-save-line"></i> Save Business Settings
                    </button>
                </div>
            </div>

            {{-- Right Info Sidebar (1 Col) --}}
            <div class="space-y-4">
                <div class="app-card overflow-hidden">
                    <div class="app-card-header">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-shield-keyhole-line text-brand-500"></i>
                            <span>Tenant Governance</span>
                        </div>
                    </div>
                    <div class="app-card-body space-y-2.5 text-xs">
                        <div class="pb-2 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted">Workspace ID:</span>
                            <span class="app-badge app-badge-secondary font-mono">#{{ $workspace->id }}</span>
                        </div>
                        <div class="pb-2 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted">Tenant Slug:</span>
                            <code class="text-2xs text-brand-500 font-mono">{{ $workspace->slug }}</code>
                        </div>
                        <div class="pb-2 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted">Prepaid Metered Rate:</span>
                            <span class="font-mono font-semibold text-light-text dark:text-dark-text">৳{{ number_format((float)($workspace->wallet?->rate_per_message ?? 0.40), 2) }} / msg</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted">Tenant Status:</span>
                            @if($workspace->is_active)
                                <span class="app-badge app-badge-success uppercase">Active</span>
                            @else
                                <span class="app-badge app-badge-danger uppercase">Suspended</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- AI Grounding Notice --}}
                <div class="p-3 rounded border border-brand-500/20 bg-brand-500/10 text-2xs space-y-1">
                    <div class="font-semibold text-brand-500 flex items-center gap-1">
                        <i class="ri-information-line"></i>
                        <span>AI Grounding & Context</span>
                    </div>
                    <p class="text-light-text-secondary dark:text-dark-text-secondary">
                        The AI chatbot seamlessly combines these business rules with your FAQs, uploaded documents, spreadsheets, and live store catalogue to formulate precise responses.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
