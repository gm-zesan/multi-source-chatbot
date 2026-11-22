@extends('layouts.public')

@section('title', 'Solutions for Commerce & Support — Entrepreneurs Automation')
@section('meta_description', 'Tailored business solutions for e-commerce brands, f-commerce merchants, and multi-location retailers. Reduce manual support and automate inquiries.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-lightbulb-line"></i> Tailored for High-Velocity Modern Commerce • The 3-Agent Solutions
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            ৩ জন এআই কর্মী — আপনার ব্যবসার তিনটি প্রধান বিভাগের দায়িত্বে।
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            সেলস, কুরিয়ার ডিসপ্যাচ ও অ্যাকাউন্টস—যেকোনো ই-কমার্স ও এফ-কমার্স বিজনেসের এই তিনটি প্রধান জটিলতা স্বয়ংক্রিয়ভাবে সমাধান করতে নিয়োগ করুন এআই সহকারী।
        </p>
    </div>

    {{-- 4 Core Solutions --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        {{-- Solution 1: Customer Sales & Support Department --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-customer-service-2-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-brand-500">Agent #1 • Front Office</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Customer Sales & Support Agent</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                মধ্যরাতে কিংবা সেলস ক্যাম্পেইনে কাস্টমারের ইনবক্স মেসেজের উত্তর দিতে দেরি হলে কাস্টমার অন্য পেজে চলে যায়। আমাদের সেলস এজেন্ট এক্সেল স্প্রেডশিট থেকে সঠিক পণ্যের স্পেসিফিকেশন, সাইজ, লাইভ স্টক ও দাম যাচাই করে ০.২ সেকেন্ডে কনভার্ট করে।
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> ২৪/৭ নিরবচ্ছিন্ন সেবা — মধ্যরাত ২টাতেও লাইভ উত্তর</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> বাংলা ও ইংরেজি ভাষার সাবলীল বোঝাপড়া</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> এক্সেল ক্যাটালগ ভিত্তিক ০.০% হ্যালুসিনেশন গ্যারান্টি</li>
            </ul>
        </div>

        {{-- Solution 2: Order Dispatch & Courier Logistics --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-truck-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-amber-500">Agent #2 • Back Office</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Operations & Courier Dispatch Agent</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                ইনবক্স থেকে কাস্টমারের নাম, ফোন নম্বর ও ডেলিভারির ঠিকানা আলাদা করে কুরিয়ার প্যানেলে বসে ম্যানুয়াল টাইপ করার ঝামেলা চিরতরে বন্ধ। সিস্টেম স্বয়ংক্রিয়ভাবে অর্ডার প্রসেস করে Steadfast ও Pathao কুরিয়ারে বুকিং সম্পন্ন করে এবং ডেলিভারি স্ট্যাটাস ট্র্যাক করে।
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> ইনকমপ্লিট লিড রিকভারি — কাস্টমারের ফোন/ঠিকানা মিস হলে স্মরণ করিয়ে দেওয়া</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> Steadfast ও Pathao এ ১-ক্লিক / অটো পার্সেল কনসাইনমেন্ট বুকিং</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> ওয়েবহুকের মাধ্যমে ডেলিভারি ও রিটার্ন স্ট্যাটাস স্বয়ংক্রিয় সিঙ্ক</li>
            </ul>
        </div>

        {{-- Solution 3: Financial Accounting & Inventory Forecasting --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-funds-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-purple-500">Agent #3 • Executive Suite</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Business Owner Intelligence (Virtual CFO)</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                মার্চেন্টের জন্য আলাদা সিএফও বা হিসাবরক্ষকের ভূমিকা পালন করে। পাইকারি ক্রয়মূল্য (COGS) বনাম বিক্রয়মূল্য তুলনা করে রিয়েল-টাইম নেট প্রফিট ক্যালকুলেশন, হাই-ডিমান্ড প্রোডাক্ট ভেলোসিটি এবং আনসোল্ড ডেড স্টক সম্পর্কে ব্যবসায়ীকে চ্যাটেই ব্রিফ করে।
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> পাইকারি খরচ বাদ দিয়ে রিয়েল-টাইম নিট প্রফিট ও মার্জিন বিশ্লেষণ</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> স্টক-আউট প্রিভেনশন এবং ডেড ইনভেন্টরি ক্লিয়ারেন্স অ্যালার্ট</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> প্রাইভেট মোবাইল/ওয়েব কনসোলে সহজ বাংলায় কথোপকথন</li>
            </ul>
        </div>

        {{-- Solution 4: F-Commerce & Omnichannel Social Retailers --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-store-2-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-emerald-500">Unified Architecture</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Omnichannel Autopilot & Human Safety</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Facebook Messenger, WhatsApp, কিংবা ওয়েবসাইট চ্যাট—সব চ্যানেল একই ড্যাশবোর্ডে সংযুক্ত। কোনো বিশেষ কাস্টমারের ক্ষেত্রে প্রয়োজন হলে হিউম্যান মডারেটর তাৎক্ষণিক 'Take Over' বাটনে ক্লিক করে নিজের হাতে কথা চালিয়ে যেতে পারেন।
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> ১-ক্লিক হিউম্যান টেকওভার ও কো-পাইলট মোড</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> অ্যাডমিন কনসোলে ১-ক্লিক কুইক স্টক টগল বোতাম</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> ৯৫%+ মেসেজ হ্যান্ডলিং অটোমেশন ও বিপুল সময় সাশ্রয়</li>
            </ul>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Discuss Your Business Use Case</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
