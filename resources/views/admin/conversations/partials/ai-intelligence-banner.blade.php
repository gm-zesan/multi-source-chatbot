<div class="ai-intelligence-banner px-3 sm:px-4 py-2 border-b border-light-border dark:border-dark-border bg-gradient-to-r from-brand-500/5 via-purple-500/5 to-transparent shrink-0">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-2.5 min-w-0 flex-1">
            <span class="w-6 h-6 rounded-lg bg-brand-500/15 text-brand-500 flex items-center justify-center shrink-0 mt-0.5" title="AI Executive Intelligence">
                <i class="ri-sparkling-fill text-xs"></i>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-3xs font-bold uppercase tracking-wider text-brand-600 dark:text-brand-400">AI Executive Summary</span>
                    @php
                        $aiTags = $conversation->getAiTagObjects();
                    @endphp
                    @foreach($aiTags as $tag)
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-3xs font-semibold {{ $tag['badge_classes'] }}">
                            <span>{{ $tag['emoji'] }}</span>
                            <span>{{ $tag['label'] }}</span>
                        </span>
                    @endforeach
                </div>
                <p class="text-xs text-light-text dark:text-dark-text font-normal mt-0.5 leading-snug">
                    {{ $conversation->ai_summary ?: 'Analyzing customer requirements and conversation intent...' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if($conversation->ai_summary_generated_at)
                <span class="text-3xs text-light-muted dark:text-dark-muted hidden md:inline">
                    Updated {{ $conversation->ai_summary_generated_at->diffForHumans() }}
                </span>
            @endif
            <form action="{{ route('conversations.regenerate-intelligence', $conversation->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="app-btn app-btn-secondary !h-6 !px-2 !text-3xs flex items-center gap-1 hover:!border-brand-500 hover:!text-brand-500" title="Re-analyze conversation and update lead tags">
                    <i class="ri-refresh-line text-xs"></i>
                    <span>Re-analyze</span>
                </button>
            </form>
        </div>
    </div>
</div>
