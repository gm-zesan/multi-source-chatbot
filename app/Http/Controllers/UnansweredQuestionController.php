<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permissions\UnansweredQuestionPermission;
use App\Enums\RoleEnum;
use App\Models\UnansweredQuestion;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FAQ\FAQService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UnansweredQuestionController extends Controller
{
    public function __construct(
        private readonly FAQService $faqService,
    ) {}

    /**
     * Display a listing of unanswered questions.
     */
    public function index(Request $request): View|JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);
        $workspaceId = $user->workspace_id;

        $query = UnansweredQuestion::query()
            ->with(['workspace:id,name', 'conversation:id']);

        if (! $isSuperAdmin) {
            $query->where('workspace_id', $workspaceId);
        } elseif ($request->filled('workspace_id')) {
            $query->where('workspace_id', (int) $request->input('workspace_id'));
        }

        $status = $request->input('status');
        if ($status && in_array($status, ['pending', 'resolved', 'ignored'], true)) {
            $query->where('status', $status);
        }

        if ($request->ajax() || $request->wantsJson() || $request->has('draw')) {
            return DataTables::of($query)
                ->addColumn('workspace_name', fn (UnansweredQuestion $q) => $q->workspace?->name ?? 'N/A')
                ->addColumn('occurrence_count_badge', fn (UnansweredQuestion $q) => '<span class="badge bg-secondary font-mono">' . $q->occurrence_count . '</span>')
                ->addColumn('status_badge', function (UnansweredQuestion $q) {
                    return match ($q->status) {
                        'resolved' => '<span class="badge bg-success">Resolved</span>',
                        'ignored'  => '<span class="badge bg-secondary">Ignored</span>',
                        default    => '<span class="badge bg-warning text-dark">Pending</span>',
                    };
                })
                ->addColumn('created_formatted', fn (UnansweredQuestion $q) => $q->created_at?->diffForHumans() ?? 'N/A')
                ->addColumn('actions', function (UnansweredQuestion $q) {
                    return [
                        'id'                => $q->id,
                        'original_question' => $q->original_question,
                        'status'            => $q->status,
                        'notes'             => $q->notes,
                    ];
                })
                ->rawColumns(['occurrence_count_badge', 'status_badge'])
                ->make(true);
        }

        $workspaces = $isSuperAdmin ? Workspace::orderBy('name')->get(['id', 'name']) : collect();

        $stats = [
            'total'    => (clone $query)->count(),
            'pending'  => (clone $query)->where('status', 'pending')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'ignored'  => (clone $query)->where('status', 'ignored')->count(),
        ];

        return view('admin.unanswered-questions.index', [
            'stats'        => $stats,
            'workspaces'   => $workspaces,
            'isSuperAdmin' => $isSuperAdmin,
            'currentWsId'  => $workspaceId,
        ]);
    }

    /**
     * Update the status or notes of an unanswered question.
     */
    public function update(Request $request, UnansweredQuestion $unanswered_question): JsonResponse|RedirectResponse
    {
        $this->authorizeTenantAccess($unanswered_question);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,resolved,ignored'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $unanswered_question->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Question status updated successfully.',
                'question' => $unanswered_question,
            ]);
        }

        return back()->with('success', 'Question status updated successfully.');
    }

    /**
     * Remove an unanswered question.
     */
    public function destroy(Request $request, UnansweredQuestion $unanswered_question): JsonResponse|RedirectResponse
    {
        $this->authorizeTenantAccess($unanswered_question);

        $unanswered_question->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unanswered question deleted successfully.',
            ]);
        }

        return back()->with('success', 'Unanswered question deleted successfully.');
    }

    /**
     * 1-Click Convert an unanswered question into a formal Knowledge Base FAQ.
     */
    public function convertToFaq(Request $request, UnansweredQuestion $unanswered_question): JsonResponse|RedirectResponse
    {
        $this->authorizeTenantAccess($unanswered_question);

        $validated = $request->validate([
            'question'      => ['nullable', 'string', 'max:500'],
            'answer'        => ['required', 'string', 'min:3'],
            'document_type' => ['nullable', 'string', 'max:64'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $faqQuestion = trim((string) ($validated['question'] ?? $unanswered_question->original_question));
        if ($faqQuestion === '') {
            $faqQuestion = $unanswered_question->original_question;
        }

        $faq = $this->faqService->create([
            'workspace_id'  => $unanswered_question->workspace_id,
            'question'      => $faqQuestion,
            'answer'        => $validated['answer'],
            'document_type' => $validated['document_type'] ?? 'faq',
            'is_active'     => $validated['is_active'] ?? true,
        ]);

        $unanswered_question->update([
            'status' => 'resolved',
            'notes'  => trim(($unanswered_question->notes ? $unanswered_question->notes . "\n" : '') . "Converted to FAQ #{$faq->id}"),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unanswered question successfully converted to Knowledge FAQ.',
                'faq_id'  => $faq->id,
            ]);
        }

        return redirect()->route('unanswered-questions.index')
            ->with('success', 'Unanswered question successfully converted to Knowledge FAQ.');
    }

    /**
     * Strict tenant boundary authorization guard.
     */
    private function authorizeTenantAccess(UnansweredQuestion $question): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if ((int) $question->workspace_id !== (int) $user->workspace_id) {
            abort(403, 'Unauthorized access across workspace boundary.');
        }
    }
}
