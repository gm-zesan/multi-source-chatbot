<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\ActionIntentMapping;
use App\Models\ConceptPhrasePattern;
use App\Models\KnowledgeSearchLog;
use App\Models\LexiconDomainEntry;
use App\Models\PolicyIntentMapping;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LexiconManagementController extends Controller
{
    public function __construct(
        private readonly RetrievalClient $retrievalClient,
    ) {}

    /**
     * Display the Lexicon & Vocabulary Management dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasRole(RoleEnum::SUPERADMIN->value);

        // Resolve active workspace scope
        $availableWorkspaces = $isSuperAdmin ? Workspace::orderBy('name')->get() : collect();
        $selectedWorkspaceId = (int) $request->query('workspace_id', 0);

        if (! $isSuperAdmin && $user && $user->workspace_id) {
            $selectedWorkspaceId = (int) $user->workspace_id;
        }

        // 1. Domain Entries (Synonyms & Deterministic Expansions)
        $domainEntries = LexiconDomainEntry::where('workspace_id', $selectedWorkspaceId)
            ->orderBy('concept_key')
            ->orderBy('pattern')
            ->get();

        // 2. Concept Phrase Patterns (grouped by concept_key)
        $rawConceptPatterns = ConceptPhrasePattern::where('workspace_id', $selectedWorkspaceId)
            ->orderBy('concept_key')
            ->orderBy('pattern_type')
            ->get();

        // 3. Action Intent Mappings
        $actionMappings = ActionIntentMapping::where('workspace_id', $selectedWorkspaceId)
            ->orderBy('intent_name')
            ->orderBy('action_keyword')
            ->get();

        // 4. Policy Intent Mappings
        $policyMappings = PolicyIntentMapping::where('workspace_id', $selectedWorkspaceId)
            ->orderBy('policy_name')
            ->orderBy('cue_phrase')
            ->get();

        // 5. Missed Vocabulary & Unmatched Query Discovery
        $unansweredQuestions = UnansweredQuestion::query()
            ->when($selectedWorkspaceId > 0, fn ($q) => $q->where('workspace_id', $selectedWorkspaceId))
            ->latest('id')
            ->take(20)
            ->get();

        $lowConfidenceSearches = KnowledgeSearchLog::query()
            ->where('final_score', '<', 0.60)
            ->when($selectedWorkspaceId > 0, fn ($q) => $q->where('workspace_id', $selectedWorkspaceId))
            ->latest('id')
            ->take(20)
            ->get();

        // Check health of Python retrieval engine
        $engineHealth = $this->retrievalClient->health();

        return view('admin.lexicons.index', [
            'isSuperAdmin'          => $isSuperAdmin,
            'availableWorkspaces'   => $availableWorkspaces,
            'selectedWorkspaceId'   => $selectedWorkspaceId,
            'domainEntries'         => $domainEntries,
            'rawConceptPatterns'    => $rawConceptPatterns,
            'actionMappings'        => $actionMappings,
            'policyMappings'        => $policyMappings,
            'unansweredQuestions'   => $unansweredQuestions,
            'lowConfidenceSearches' => $lowConfidenceSearches,
            'engineHealth'          => $engineHealth,
        ]);
    }

    // ── Domain Entries CRUD ───────────────────────────────────────────────

    public function storeDomainEntry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|integer',
            'concept_key'  => 'required|string|max:100',
            'pattern'      => 'required|string|max:255',
            'expansion'    => 'required|string|max:255',
            'language'     => 'nullable|string|in:bn,en,code_mixed',
        ]);

        $this->authorizeWorkspace((int) $validated['workspace_id']);

        LexiconDomainEntry::create([
            'workspace_id' => (int) $validated['workspace_id'],
            'concept_key'  => strtoupper(trim($validated['concept_key'])),
            'pattern'      => strtolower(trim($validated['pattern'])),
            'expansion'    => trim($validated['expansion']),
            'language'     => $validated['language'] ?? 'bn',
            'status'       => 'ACTIVE',
            'version'      => 1,
            'activated_by' => Auth::id(),
            'activated_at' => now(),
        ]);

        return back()->with('success', 'Domain entry synonym successfully created.');
    }

    public function updateDomainEntry(Request $request, LexiconDomainEntry $entry): RedirectResponse
    {
        $this->authorizeWorkspace($entry->workspace_id);

        $validated = $request->validate([
            'concept_key' => 'required|string|max:100',
            'pattern'     => 'required|string|max:255',
            'expansion'   => 'required|string|max:255',
            'language'    => 'nullable|string|in:bn,en,code_mixed',
            'status'      => 'required|in:ACTIVE,INACTIVE',
        ]);

        $entry->update([
            'concept_key' => strtoupper(trim($validated['concept_key'])),
            'pattern'     => strtolower(trim($validated['pattern'])),
            'expansion'   => trim($validated['expansion']),
            'language'    => $validated['language'] ?? 'bn',
            'status'      => $validated['status'],
        ]);

        return back()->with('success', 'Domain entry synonym successfully updated.');
    }

    public function deleteDomainEntry(LexiconDomainEntry $entry): RedirectResponse
    {
        $this->authorizeWorkspace($entry->workspace_id);
        $entry->delete();

        return back()->with('success', 'Domain entry synonym successfully removed.');
    }

    // ── Concept Phrase Patterns CRUD ──────────────────────────────────────

    public function storeConceptPattern(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id'    => 'required|integer',
            'concept_key'     => 'required|string|max:100',
            'pattern_type'    => 'required|string|in:CONCEPT_META,POSITIVE,NEGATIVE_GUARD',
            'phrase'          => 'nullable|string|max:255',
            'target_doc_type' => 'nullable|string|max:50',
        ]);

        $this->authorizeWorkspace((int) $validated['workspace_id']);

        ConceptPhrasePattern::create([
            'workspace_id'    => (int) $validated['workspace_id'],
            'concept_key'     => strtoupper(trim($validated['concept_key'])),
            'pattern_type'    => $validated['pattern_type'],
            'phrase'          => $validated['phrase'] ? strtolower(trim($validated['phrase'])) : null,
            'target_doc_type' => $validated['target_doc_type'] ? strtolower(trim($validated['target_doc_type'])) : null,
            'status'          => 'ACTIVE',
            'version'         => 1,
            'activated_by'    => Auth::id(),
            'activated_at'    => now(),
        ]);

        return back()->with('success', 'Concept pattern successfully created.');
    }

    public function updateConceptPattern(Request $request, ConceptPhrasePattern $pattern): RedirectResponse
    {
        $this->authorizeWorkspace($pattern->workspace_id);

        $validated = $request->validate([
            'phrase'          => 'nullable|string|max:255',
            'target_doc_type' => 'nullable|string|max:50',
            'status'          => 'required|in:ACTIVE,INACTIVE',
        ]);

        $pattern->update([
            'phrase'          => $validated['phrase'] ? strtolower(trim($validated['phrase'])) : null,
            'target_doc_type' => $validated['target_doc_type'] ? strtolower(trim($validated['target_doc_type'])) : null,
            'status'          => $validated['status'],
        ]);

        return back()->with('success', 'Concept pattern successfully updated.');
    }

    public function deleteConceptPattern(ConceptPhrasePattern $pattern): RedirectResponse
    {
        $this->authorizeWorkspace($pattern->workspace_id);
        $pattern->delete();

        return back()->with('success', 'Concept pattern successfully removed.');
    }

    // ── Action Intent Mappings CRUD ───────────────────────────────────────

    public function storeActionMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id'   => 'required|integer',
            'intent_name'    => 'required|string|max:100',
            'action_keyword' => 'required|string|max:100',
            'target_phrase'  => 'required|string|max:255',
            'penalty_phrase' => 'nullable|string|max:255',
        ]);

        $this->authorizeWorkspace((int) $validated['workspace_id']);

        ActionIntentMapping::create([
            'workspace_id'   => (int) $validated['workspace_id'],
            'intent_name'    => strtolower(trim($validated['intent_name'])),
            'action_keyword' => strtolower(trim($validated['action_keyword'])),
            'target_phrase'  => strtolower(trim($validated['target_phrase'])),
            'penalty_phrase' => $validated['penalty_phrase'] ? strtolower(trim($validated['penalty_phrase'])) : null,
            'status'         => 'ACTIVE',
            'version'        => 1,
            'activated_by'   => Auth::id(),
            'activated_at'   => now(),
        ]);

        return back()->with('success', 'Action intent mapping successfully created.');
    }

    public function updateActionMapping(Request $request, ActionIntentMapping $mapping): RedirectResponse
    {
        $this->authorizeWorkspace($mapping->workspace_id);

        $validated = $request->validate([
            'action_keyword' => 'required|string|max:100',
            'target_phrase'  => 'required|string|max:255',
            'penalty_phrase' => 'nullable|string|max:255',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        $mapping->update([
            'action_keyword' => strtolower(trim($validated['action_keyword'])),
            'target_phrase'  => strtolower(trim($validated['target_phrase'])),
            'penalty_phrase' => $validated['penalty_phrase'] ? strtolower(trim($validated['penalty_phrase'])) : null,
            'status'         => $validated['status'],
        ]);

        return back()->with('success', 'Action intent mapping successfully updated.');
    }

    public function deleteActionMapping(ActionIntentMapping $mapping): RedirectResponse
    {
        $this->authorizeWorkspace($mapping->workspace_id);
        $mapping->delete();

        return back()->with('success', 'Action intent mapping successfully removed.');
    }

    // ── Policy Intent Mappings CRUD ───────────────────────────────────────

    public function storePolicyMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id'    => 'required|integer',
            'policy_name'     => 'required|string|max:100',
            'cue_phrase'      => 'required|string|max:255',
            'target_doc_type' => 'required|string|max:50',
        ]);

        $this->authorizeWorkspace((int) $validated['workspace_id']);

        PolicyIntentMapping::create([
            'workspace_id'    => (int) $validated['workspace_id'],
            'policy_name'     => strtolower(trim($validated['policy_name'])),
            'cue_phrase'      => strtolower(trim($validated['cue_phrase'])),
            'target_doc_type' => strtolower(trim($validated['target_doc_type'])),
            'status'          => 'ACTIVE',
            'version'         => 1,
            'activated_by'    => Auth::id(),
            'activated_at'    => now(),
        ]);

        return back()->with('success', 'Policy intent mapping successfully created.');
    }

    public function updatePolicyMapping(Request $request, PolicyIntentMapping $mapping): RedirectResponse
    {
        $this->authorizeWorkspace($mapping->workspace_id);

        $validated = $request->validate([
            'cue_phrase'      => 'required|string|max:255',
            'target_doc_type' => 'required|string|max:50',
            'status'          => 'required|in:ACTIVE,INACTIVE',
        ]);

        $mapping->update([
            'cue_phrase'      => strtolower(trim($validated['cue_phrase'])),
            'target_doc_type' => strtolower(trim($validated['target_doc_type'])),
            'status'          => $validated['status'],
        ]);

        return back()->with('success', 'Policy intent mapping successfully updated.');
    }

    public function deletePolicyMapping(PolicyIntentMapping $mapping): RedirectResponse
    {
        $this->authorizeWorkspace($mapping->workspace_id);
        $mapping->delete();

        return back()->with('success', 'Policy intent mapping successfully removed.');
    }

    // ── Manual Sync Action ────────────────────────────────────────────────

    public function syncToEngine(Request $request): JsonResponse|RedirectResponse
    {
        $workspaceId = (int) $request->input('workspace_id', 0);
        $this->authorizeWorkspace($workspaceId);

        $result = $this->retrievalClient->reloadLexicon($workspaceId);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['ok']) {
            return back()->with('success', "AI Engine reloaded successfully! (Snapshot Version: {$result['snapshot_version']})");
        }

        return back()->with('error', "Failed to sync to AI Engine: " . ($result['error'] ?? 'Engine unreachable'));
    }

    // ── Authorization Helper ──────────────────────────────────────────────

    private function authorizeWorkspace(int $workspaceId): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        // Superadmin has access to global (0) and any workspace
        if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        // Non-superadmins cannot modify global (0) or other workspaces
        if ($workspaceId === 0 || (int) $user->workspace_id !== $workspaceId) {
            abort(403, 'Unauthorized access to this workspace lexicon.');
        }
    }
}
