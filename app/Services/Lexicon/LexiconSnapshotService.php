<?php

declare(strict_types=1);

namespace App\Services\Lexicon;

use App\Models\LexiconDomainEntry;
use App\Models\ConceptPhrasePattern;
use App\Models\ActionIntentMapping;
use App\Models\PolicyIntentMapping;

class LexiconSnapshotService
{
    /**
     * Builds the frozen snapshot contract for the Python LexiconRepository.
     * Merges global (workspace_id=0) and workspace-specific active entries.
     */
    public function buildSnapshot(int $workspaceId): array
    {
        // 1. Fetch active data for global and workspace
        $domainEntries = LexiconDomainEntry::active()
            ->whereIn('workspace_id', [0, $workspaceId])
            ->get();

        $conceptPatterns = ConceptPhrasePattern::active()
            ->whereIn('workspace_id', [0, $workspaceId])
            ->get();

        $actionMappings = ActionIntentMapping::active()
            ->whereIn('workspace_id', [0, $workspaceId])
            ->get();

        $policyMappings = PolicyIntentMapping::active()
            ->whereIn('workspace_id', [0, $workspaceId])
            ->get();

        // 2. Calculate versions
        // Summing the version numbers of all active rows in the scope.
        $globalVersion = 
            $domainEntries->where('workspace_id', 0)->sum('version') +
            $conceptPatterns->where('workspace_id', 0)->sum('version') +
            $actionMappings->where('workspace_id', 0)->sum('version') +
            $policyMappings->where('workspace_id', 0)->sum('version');

        $workspaceVersion = ($workspaceId === 0) ? 0 : (
            $domainEntries->where('workspace_id', $workspaceId)->sum('version') +
            $conceptPatterns->where('workspace_id', $workspaceId)->sum('version') +
            $actionMappings->where('workspace_id', $workspaceId)->sum('version') +
            $policyMappings->where('workspace_id', $workspaceId)->sum('version')
        );

        $snapshotVersion = $globalVersion + $workspaceVersion;

        return [
            'workspace_id'      => $workspaceId,
            'global_version'    => $globalVersion,
            'workspace_version' => $workspaceVersion,
            'snapshot_version'  => $snapshotVersion,
            'domain_entries'    => $this->buildDomainEntries($domainEntries, $workspaceId),
            'concept_patterns'  => $this->buildConceptPatterns($conceptPatterns, $workspaceId),
            'action_mappings'   => $this->buildActionMappings($actionMappings, $workspaceId),
            'policy_mappings'   => $this->buildPolicyMappings($policyMappings, $workspaceId),
        ];
    }

    private function buildDomainEntries($collection, int $workspaceId): array
    {
        $merged = [];

        // Global first
        foreach ($collection->where('workspace_id', 0) as $row) {
            $key = $row->concept_key . '::' . $row->pattern;
            $merged[$key] = $row;
        }

        // Workspace overrides
        if ($workspaceId !== 0) {
            foreach ($collection->where('workspace_id', $workspaceId) as $row) {
                $key = $row->concept_key . '::' . $row->pattern;
                $merged[$key] = $row;
            }
        }

        $formatted = [];
        foreach ($merged as $row) {
            $formatted[$row->concept_key][] = [
                'pattern'   => $row->pattern,
                'expansion' => $row->expansion,
                'language'  => $row->language,
            ];
        }

        return $formatted;
    }

    private function buildConceptPatterns($collection, int $workspaceId): array
    {
        $concepts = [];

        $conceptKeys = $collection->pluck('concept_key')->unique();
        foreach ($conceptKeys as $ck) {
            $concepts[$ck] = [
                'target_doc_type'  => null,
                'positive_phrases' => [],
                'negative_guards'  => [],
            ];
        }

        $metas = $collection->where('pattern_type', 'CONCEPT_META');
        foreach ($conceptKeys as $ck) {
            $wsMeta = $metas->where('workspace_id', $workspaceId)->where('concept_key', $ck)->first();
            $glMeta = $metas->where('workspace_id', 0)->where('concept_key', $ck)->first();
            
            $meta = $wsMeta ?? $glMeta;
            if ($meta) {
                $concepts[$ck]['target_doc_type'] = $meta->target_doc_type;
            }
        }

        foreach ($collection->whereIn('pattern_type', ['POSITIVE', 'NEGATIVE_GUARD']) as $row) {
            $listKey = $row->pattern_type === 'POSITIVE' ? 'positive_phrases' : 'negative_guards';
            $concepts[$row->concept_key][$listKey][] = $row->phrase;
        }

        foreach ($concepts as $ck => $data) {
            $concepts[$ck]['positive_phrases'] = array_values(array_unique($data['positive_phrases']));
            $concepts[$ck]['negative_guards']  = array_values(array_unique($data['negative_guards']));
        }

        return $concepts;
    }

    private function buildActionMappings($collection, int $workspaceId): array
    {
        $merged = [];

        foreach ($collection->where('workspace_id', 0) as $row) {
            $key = $row->intent_name . '::' . $row->action_keyword;
            $merged[$key] = $row;
        }

        if ($workspaceId !== 0) {
            foreach ($collection->where('workspace_id', $workspaceId) as $row) {
                $key = $row->intent_name . '::' . $row->action_keyword;
                $merged[$key] = $row;
            }
        }

        $formatted = [];
        foreach ($merged as $row) {
            $in = $row->intent_name;
            if (!isset($formatted[$in])) {
                $formatted[$in] = [
                    'action_keywords' => [],
                    'target_phrase'   => $row->target_phrase,
                    'penalty_phrase'  => $row->penalty_phrase,
                ];
            }
            $formatted[$in]['action_keywords'][] = $row->action_keyword;
            
            if ($row->workspace_id === $workspaceId && $row->target_phrase) {
                $formatted[$in]['target_phrase'] = $row->target_phrase;
            }
            if ($row->workspace_id === $workspaceId && $row->penalty_phrase) {
                $formatted[$in]['penalty_phrase'] = $row->penalty_phrase;
            }
        }

        foreach ($formatted as $in => $data) {
            $formatted[$in]['action_keywords'] = array_values(array_unique($data['action_keywords']));
        }

        return $formatted;
    }

    private function buildPolicyMappings($collection, int $workspaceId): array
    {
        $merged = [];

        foreach ($collection->where('workspace_id', 0) as $row) {
            $key = $row->policy_name . '::' . $row->cue_phrase;
            $merged[$key] = $row;
        }

        if ($workspaceId !== 0) {
            foreach ($collection->where('workspace_id', $workspaceId) as $row) {
                $key = $row->policy_name . '::' . $row->cue_phrase;
                $merged[$key] = $row;
            }
        }

        $formatted = [];
        foreach ($merged as $row) {
            $pn = $row->policy_name;
            if (!isset($formatted[$pn])) {
                $formatted[$pn] = [
                    'cue_phrases'      => [],
                    'target_doc_types' => [],
                ];
            }
            $formatted[$pn]['cue_phrases'][] = $row->cue_phrase;
            $formatted[$pn]['target_doc_types'][] = $row->target_doc_type;
        }

        foreach ($formatted as $pn => $data) {
            $formatted[$pn]['cue_phrases']      = array_values(array_unique($data['cue_phrases']));
            $formatted[$pn]['target_doc_types'] = array_values(array_unique($data['target_doc_types']));
        }

        return $formatted;
    }
}
