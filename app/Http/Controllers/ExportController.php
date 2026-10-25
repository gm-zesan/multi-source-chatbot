<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesConversationAccess;
use App\Models\Conversation;
use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use AuthorizesConversationAccess;

    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Export a specific conversation to CSV, XLSX, or PDF.
     */
    public function exportConversation(Request $request, Conversation $conversation, string $format = 'pdf'): Response|StreamedResponse
    {
        $this->authorizeConversation($conversation);

        return $this->exportService->exportConversation($conversation, $format);
    }

    /**
     * Export structured analytics data (sent via POST from frontend table/charts).
     */
    public function exportAnalytics(Request $request): Response|StreamedResponse
    {
        $validated = $request->validate([
            'format'  => 'required|string|in:csv,xlsx,pdf',
            'title'   => 'nullable|string|max:200',
            'headers' => 'required|array',
            'rows'    => 'required|array',
            'summary' => 'nullable|array',
        ]);

        $data = [
            'title'   => $validated['title'] ?? 'Business Analytics Report',
            'headers' => $validated['headers'],
            'rows'    => $validated['rows'],
            'summary' => $validated['summary'] ?? [],
        ];

        $filename = 'analytics-report-' . date('Ymd-His');

        return $this->exportService->exportData(
            data: $data,
            format: $validated['format'],
            filename: $filename,
        );
    }
}
