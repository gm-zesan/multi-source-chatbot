<?php

declare(strict_types=1);

namespace App\AI\Routing;

use App\Models\Conversation;

class HybridRouter
{
    public const DEFAULT_CONFIDENCE_THRESHOLD = 0.70;

    public function __construct(
        private readonly float $confidenceThreshold = self::DEFAULT_CONFIDENCE_THRESHOLD,
    ) {}

    /**
     * Route an incoming user query to the appropriate capability.
     */
    public function route(
        string $query,
        ?Conversation $conversation = null,
        ?int $workspaceId = null,
    ): RoutingResult {
        $t_start = microtime(true);
        $cleanQuery = trim($query);

        if ($cleanQuery === '') {
            $latency = round((microtime(true) - $t_start) * 1000, 2);
            return new RoutingResult(
                route: RouteType::CHAT,
                confidence: 1.0,
                intent: 'empty_query',
                signals: [
                    'layer'             => 'layer0_empty',
                    'normalized_query'  => '',
                    'router_latency_ms' => $latency,
                ],
                routerLatencyMs: $latency,
            );
        }

        $normalized = $this->normalizeText($cleanQuery);

        // ── 0. Layer 0: Multi-Turn Pending Action State First ────────────────
        $pendingActionResult = $this->checkPendingActionState($cleanQuery, $normalized, $conversation);
        if ($pendingActionResult !== null) {
            $latency = round((microtime(true) - $t_start) * 1000, 2);
            return new RoutingResult(
                route: $pendingActionResult['route'],
                confidence: $pendingActionResult['confidence'],
                intent: $pendingActionResult['intent'],
                signals: array_merge([
                    'layer'             => 'layer0_pending_action',
                    'normalized_query'  => $normalized,
                    'router_latency_ms' => $latency,
                ], $pendingActionResult['signals']),
                entities: $pendingActionResult['entities'],
                routerLatencyMs: $latency,
            );
        }

        // ── 1. Layer 1: Out-Of-Domain (OOD) Gate ─────────────────────────────
        $oodScore = $this->calculateOodScore($normalized);
        if ($oodScore >= 0.80) {
            $latency = round((microtime(true) - $t_start) * 1000, 2);
            return new RoutingResult(
                route: RouteType::OOD,
                confidence: $oodScore,
                intent: 'ood_negative',
                signals: [
                    'layer'             => 'layer1_ood_gate',
                    'normalized_query'  => $normalized,
                    'ood_score'         => $oodScore,
                    'router_latency_ms' => $latency,
                ],
                entities: [],
                routerLatencyMs: $latency,
            );
        }

        // ── 2. Layer 2: Standalone Pure Chitchat Gate (<= 3 words, pure chitchat) ─
        $pureChatResult = $this->evaluateLayer2PureChat($normalized);
        if ($pureChatResult !== null) {
            $latency = round((microtime(true) - $t_start) * 1000, 2);
            return new RoutingResult(
                route: $pureChatResult['route'],
                confidence: $pureChatResult['confidence'],
                intent: $pureChatResult['intent'],
                signals: array_merge([
                    'layer'             => 'layer2_pure_chat',
                    'normalized_query'  => $normalized,
                    'router_latency_ms' => $latency,
                ], $pureChatResult['signals']),
                entities: [],
                routerLatencyMs: $latency,
            );
        }

        // ── 3. Layer 3: Multilingual Intent Extraction & Disambiguation Engine ─
        $layer3Result = $this->evaluateLayer3Disambiguation($cleanQuery, $normalized, $conversation);
        $latency = round((microtime(true) - $t_start) * 1000, 2);

        $route = $layer3Result['route'];
        $confidence = $layer3Result['confidence'];

        // Safety Gate: If confidence is below threshold and candidate is ACTION, demote to UNCERTAIN
        if ($confidence < $this->confidenceThreshold && $route === RouteType::ACTION) {
            $route = RouteType::UNCERTAIN;
        }

        return new RoutingResult(
            route: $route,
            confidence: $confidence,
            intent: $layer3Result['intent'],
            signals: array_merge([
                'layer'             => 'layer3_multilingual_disambiguation',
                'normalized_query'  => $normalized,
                'router_latency_ms' => $latency,
            ], $layer3Result['signals']),
            entities: $layer3Result['entities'] ?? [],
            routerLatencyMs: $latency,
        );
    }

    /**
     * Normalize text: lowercase, remove punctuation noise, expand common Banglish/English contractions and typos.
     */
    private function normalizeText(string $query): string
    {
        $text = mb_strtolower(trim($query));

        // Preserve essential characters (letters, numbers, basic Bengali script, and question mark)
        $text = preg_replace('/[^\p{L}\p{M}\p{N}\s\?#]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        $text = trim((string) $text);

        // Normalize common Banglish typos and abbreviations
        $replacements = [
            '/\bchng\b/ui'     => 'change',
            '/\bcncl\b/ui'     => 'cancel',
            '/\bjbe\b/ui'      => 'jabe',
            '/\bbolbn\b/ui'    => 'bolben',
            '/\bbolbe\b/ui'    => 'bolben',
            '/\bkivbe\b/ui'    => 'kivabe',
            '/\bkmne\b/ui'     => 'kemne',
            '/\bkrbo\b/ui'     => 'korbo',
            '/\bpasswrd\b/ui'  => 'password',
            '/\bplz\b/ui'      => 'please',
            '/\bpls\b/ui'      => 'please',
            '/\bthnx\b/ui'     => 'thanks',
            '/\btnx\b/ui'      => 'thanks',
            '/\bthx\b/ui'      => 'thanks',
            '/\bty\b/ui'       => 'thanks',
            '/\bacc\b/ui'      => 'account',
            '/\bacct\b/ui'     => 'account',
            '/\bmsg\b/ui'      => 'message',
            '/\binfo\b/ui'     => 'information',
            '/\bpymnt\b/ui'    => 'payment',
            '/\bordr\b/ui'     => 'order',
            '/\btckt\b/ui'     => 'ticket',
            '/\brfnd\b/ui'     => 'refund',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Check if conversation is currently awaiting user confirmation or follow-up for a pending action.
     *
     * @return ?array{route: RouteType, confidence: float, intent: string, signals: array, entities: array}
     */
    private function checkPendingActionState(string $originalQuery, string $normalized, ?Conversation $conversation): ?array
    {
        $cleaned = trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $normalized));

        // 1. If conversation has an active pending action
        if ($conversation !== null) {
            $pendingAction = $conversation->metadata['pending_action'] ?? null;
            if (is_array($pendingAction) && !empty($pendingAction['action'])) {
                // A. Check if user is providing/restating parameters (e.g. Order ID #1024 or 1024)
                $entities = $this->extractEntities($originalQuery);
                if (!empty($entities['order_id'])) {
                    return [
                        'route'      => RouteType::ACTION,
                        'confidence' => 0.95,
                        'intent'     => $pendingAction['action'],
                        'signals'    => [
                            'has_pending_action' => true,
                            'parameter_provided' => 'order_id',
                        ],
                        'entities'   => array_merge($pendingAction['parameters'] ?? [], $entities),
                    ];
                }

                // B. Positive confirmation signals (English, Bangla, Banglish)
                $confirmExact = [
                    'yes', 'yeah', 'yep', 'confirm', 'sure', 'proceed', 'do it', 'please do', 'ok', 'okay',
                    'yes please', 'yes do it', 'confirm it', 'please confirm', 'sure go ahead',
                    'yes please proceed', 'please proceed', 'proceed please',
                    'হ্যাঁ', 'হ্যা', 'হাঁ', 'হা', 'বাতিল করুন', 'করুন', 'ঠিক আছে', 'করো', 'হ্যাঁ করুন', 'হ্যাঁ বাতিল করুন',
                    'হ্যাঁ করে দিন', 'হুম', 'হ্যাঁ প্লিজ',
                    'ha', 'haa', 'korun', 'koro', 'thik ache', 'yes do it', 'confirm koro', 'confirm korun', 'kore den', 'hum'
                ];

                // C. Negative rejection signals
                $rejectExact = [
                    'no', 'nope', 'stop', 'dont', "don't", 'reject', 'abort', 'nevermind', 'no thanks', 'no need',
                    'bye', 'goodbye', 'cancel',
                    'না', 'দরকার নেই', 'করবেন না', 'থাক', 'বাতিল করার দরকার নাই', 'দরকার নাই', 'লাগবে না',
                    'বিদায়', 'আল্লাহ হাফেজ', 'খোদা হাফেজ',
                    'na', 'baa', 'dorkar nai', 'dorkar nei', 'korben na', 'thak', 'lagbe na', 'bye', 'allah hafez'
                ];

                foreach ($confirmExact as $pattern) {
                    if ($cleaned === $pattern || str_starts_with($cleaned, $pattern . ' ') || str_ends_with($cleaned, ' ' . $pattern)) {
                        return [
                            'route'      => RouteType::ACTION,
                            'confidence' => 0.99,
                            'intent'     => 'action_confirmation',
                            'signals'    => [
                                'has_pending_action' => true,
                                'pending_action'     => $pendingAction['action'],
                                'matched_confirm'    => $pattern,
                            ],
                            'entities'   => $pendingAction['parameters'] ?? [],
                        ];
                    }
                }

                foreach ($rejectExact as $pattern) {
                    // For single keywords like 'cancel', 'abort', 'stop', match strictly exact to avoid colliding with commands
                    if ($pattern === 'cancel' || $pattern === 'abort' || $pattern === 'stop') {
                        if ($cleaned === $pattern) {
                            return [
                                'route'      => RouteType::CHAT,
                                'confidence' => 0.99,
                                'intent'     => 'action_rejection',
                                'signals'    => [
                                    'has_pending_action' => true,
                                    'pending_action'     => $pendingAction['action'],
                                    'matched_reject'     => $pattern,
                                ],
                                'entities'   => $pendingAction['parameters'] ?? [],
                            ];
                        }
                        continue;
                    }

                    if ($cleaned === $pattern || str_starts_with($cleaned, $pattern . ' ') || str_ends_with($cleaned, ' ' . $pattern)) {
                        return [
                            'route'      => RouteType::CHAT,
                            'confidence' => 0.99,
                            'intent'     => 'action_rejection',
                            'signals'    => [
                                'has_pending_action' => true,
                                'pending_action'     => $pendingAction['action'],
                                'matched_reject'     => $pattern,
                            ],
                            'entities'   => $pendingAction['parameters'] ?? [],
                        ];
                    }
                }
            }
        }

        // 2. Standalone Affirmation / Negation without pending action -> CHAT
        $standaloneYesNo = ['yes', 'yeah', 'yep', 'sure', 'ok', 'okay', 'no', 'nope', 'হ্যাঁ', 'হ্যা', 'না', 'ha', 'na'];
        if (in_array($cleaned, $standaloneYesNo, true)) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.95,
                'intent'     => in_array($cleaned, ['no', 'nope', 'না', 'na'], true) ? 'negation' : 'affirmation',
                'signals'    => ['standalone_yes_no' => $cleaned, 'has_pending_action' => false],
                'entities'   => [],
            ];
        }

        return null;
    }

    /**
     * Layer 1: Calculate OOD Score based on weather, recipes, politics, sports, external facts, or pure gibberish.
     */
    private function calculateOodScore(string $normalized): float
    {
        $oodPatterns = [
            '/(weather|temperature|forecast|rain|cloudy|আবহাওয়া|আবহাওয়া|তাপমাত্রা|বৃষ্টি|বৃষ্টিপাত)/u',
            '/(recipe|biryani|cook|cake|chocolate|kitchen|dish|রেসিপি|রান্না|বিরিয়ানি|বিরিয়ানি|কেক)/u',
            '/(president|prime minister|election|parliament|government of|রাষ্ট্রপতি|প্রধানমন্ত্রী|সংসদ|নির্বাচন)/u',
            '/(cricket|football|match|score|fifa|world cup|ক্রিকেট|ফুটবল|বিশ্বকাপ)/u',
            '/(hospital|doctor|clinic|pharmacy|ambulance|medical|হাসপাতাল|ডাক্তার|ফার্মেসি|অ্যাম্বুলেন্স)/u',
            '/(stock price|apple stock|nasdaq|crypto|bitcoin|flight to|flights to|শেয়ার বাজার|বিটকয়েন|ফ্লাইট)/u',
            '/(nuclear|submarine|rocket fuel|rocket parts|weapons|explosive|drugs|পারমাণবিক|রকেট)/u',
            '/(poem|write a story|write a song|joke|riddle|কবিতা|গল্প লিখুন|গান লিখুন|কৌতুক)/u',
            '/^(asdf|qwerty|zxcvbnm|ghjk|test123|abcxyz)/u',
        ];

        foreach ($oodPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return 0.95;
            }
        }

        // Gibberish detector (e.g. "asdf ghjk qwerty zxcvbnm")
        $noPunct = preg_replace('/[^\p{L}\s]/u', '', $normalized);
        if (preg_match('/^[a-z\s]{15,}$/u', $noPunct)) {
            $vowelCount = preg_match_all('/[aeiou]/u', $noPunct);
            if ($vowelCount === 0 || ($vowelCount / max(1, strlen(str_replace(' ', '', $noPunct)))) < 0.15) {
                return 0.90;
            }
        }

        return 0.0;
    }

    /**
     * Layer 2: Standalone Pure Chitchat Gate (<= 3 words, pure greeting/thanks/goodbye/liveness).
     *
     * @return ?array{route: RouteType, confidence: float, intent: string, signals: array}
     */
    private function evaluateLayer2PureChat(string $normalized): ?array
    {
        $clean = trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $normalized));
        $words = explode(' ', $clean);
        $wordCount = count($words);

        // Only evaluate pure short chitchat if <= 3 words
        if ($wordCount > 3) {
            return null;
        }

        // Must NOT contain any substantive domain nouns or mutation verbs
        $domainMarkers = [
            'order', 'ticket', 'invoice', 'payment', 'card', 'subscription', 'refund', 'policy', 'pricing', 'plan',
            'cancel', 'change', 'update', 'delete', 'track', 'password', 'login', 'security', 'channel', 'delivery',
            'অর্ডার', 'টিকিট', 'ইনভয়েস', 'পেমেন্ট', 'কার্ড', 'সাবস্ক্রিপশন', 'রিফান্ড', 'পলিসি', 'বাতিল', 'পরিবর্তন',
            'kivabe', 'kothay', 'koto', 'কিভাবে', 'কীভাবে', 'কোথায়', 'কোথায়', 'কত'
        ];

        foreach ($domainMarkers as $dm) {
            if (stripos($clean, $dm) !== false) {
                return null; // Has substantive domain context -> pass to deep disambiguation!
            }
        }

        // 1. Obvious Greetings & Openers
        $greetings = [
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'good day',
            'yo', 'sup', 'wassup', 'howdy', 'hiya', 'hey there', 'hey bro', 'hello bro',
            'assalamu alaikum', 'assalamualaykum', 'salam', 'slaam',
            'হ্যালো', 'হাই', 'হে', 'আসসালামু আলাইকুম', 'সালাম', 'কেমন আছেন', 'কেমন আছো', 'কেমন আছ',
            'শুভ সকাল', 'শুভ অপরাহ্ন', 'শুভ সন্ধ্যা', 'নমস্কার', 'আদাব', 'কি অবস্থা', 'খবর কি', 'সব ভালো তো',
            'kemon achen', 'kemon acho', 'kemon asen', 'shuvo sokal', 'hello vai', 'hi vai', 'vaiya', 'vai',
            'ki obostha', 'ki khobor', 'shob bhalo', 'kemon cholche', 'bhalo achen'
        ];

        if (in_array($clean, $greetings, true)) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.99,
                'intent'     => 'greeting',
                'signals'    => ['matched_exact_greeting' => $clean],
            ];
        }

        // 2. Obvious Presence & Liveness Checks
        $liveness = [
            'are you there', 'are you a bot', 'who are you', 'anyone here', 'is anyone there', 'is anyone online',
            'can you hear me', 'bot or human',
            'কেউ আছেন', 'আপনি কি আছেন', 'কে কথা বলছেন', 'আপনি কি রোবট', 'অনলাইনে কেউ আছেন', 'শুনতে পাচ্ছেন', 'কে আছেন',
            'keu achen', 'apni ki achen', 'ke kotha bolchen', 'apni ki robot', 'online e keu ache', 'shunte pacchen'
        ];

        if (in_array($clean, $liveness, true)) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.99,
                'intent'     => 'liveness_check',
                'signals'    => ['matched_liveness_check' => $clean],
            ];
        }

        // 3. Obvious Gratitude
        $gratitude = [
            'thank you', 'thanks', 'thank you so much', 'thanks a lot', 'appreciate it', 'grateful', 'many thanks',
            'ধন্যবাদ', 'অনেক ধন্যবাদ', 'থ্যাংকস', 'থ্যাংক ইউ', 'অনেক কৃতজ্ঞ', 'উপকার হলো', 'ধন্যবাদ ভাই',
            'dhonnobad', 'onek dhonnobad', 'dhonnobad vai', 'thank u', 'thanks vai', 'onek upokar holo'
        ];

        if (in_array($clean, $gratitude, true)) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.99,
                'intent'     => 'gratitude',
                'signals'    => ['matched_exact_gratitude' => $clean],
            ];
        }

        // 4. Obvious Goodbyes
        $goodbyes = [
            'bye', 'goodbye', 'see you', 'see ya', 'take care', 'tata', 'talk to you later', 'have a nice day',
            'বিদায়', 'বিদায়', 'বাই', 'পরে কথা হবে', 'ভালো থাকবেন', 'আল্লাহ হাফেজ', 'খোদা হাফেজ', 'টাটা',
            'pore kotha hobe', 'bhalo thakben', 'allah hafez', 'khoda hafez', 'tata', 'ajker moto ashi'
        ];

        if (in_array($clean, $goodbyes, true)) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.99,
                'intent'     => 'goodbye',
                'signals'    => ['matched_exact_goodbye' => $clean],
            ];
        }

        return null;
    }

    /**
     * Layer 3: Multilingual Intent Classifier, Feature Extractor, and Disambiguation Engine.
     *
     * @return array{route: RouteType, confidence: float, intent: string, signals: array, entities: array}
     */
    private function evaluateLayer3Disambiguation(string $originalQuery, string $normalized, ?Conversation $conversation): array
    {
        $entities = $this->extractEntities($originalQuery);
        $hasAnyEntity = !empty($entities);

        $hasQuestionMarker = str_contains($originalQuery, '?') || str_contains($originalQuery, '？') || $this->hasInquiryFraming($normalized);
        $hasImperative = $this->hasImperativeFraming($normalized);
        $hasPronounWithoutEntity = $this->hasAmbiguousPronounWithoutEntity($normalized, $hasAnyEntity);
        $isBareAction = $this->isBareActionKeyword($normalized);

        $inquiryScore = $this->calculateInquiryScore($normalized, $hasQuestionMarker);
        $actionScore  = $this->calculateActionScore($normalized, $entities, $hasImperative, $hasQuestionMarker);
        $chatScore    = $this->calculateChatScore($normalized);

        $matchedSignals = [];
        if ($chatScore >= 0.70) $matchedSignals[] = 'conversational_marker';
        if ($inquiryScore >= 0.50) $matchedSignals[] = 'inquiry_marker';
        if ($actionScore >= 0.50) $matchedSignals[] = 'action_marker';
        if ($hasAnyEntity) $matchedSignals[] = 'entity_present';
        if ($hasQuestionMarker) $matchedSignals[] = 'question_framing';
        if ($hasImperative) $matchedSignals[] = 'imperative_framing';

        $signals = [
            'chat_score'            => $chatScore,
            'inquiry_score'         => $inquiryScore,
            'action_score'          => $actionScore,
            'matched_signals'       => $matchedSignals,
            'has_entity'            => $hasAnyEntity,
            'has_question_marker'   => $hasQuestionMarker,
            'has_imperative_marker' => $hasImperative,
            'has_pending_action'    => ($conversation?->metadata['pending_action'] ?? null) !== null,
        ];

        $hasSubstantiveDomain = $this->hasSubstantiveDomainContext($normalized, $entities);
        $hasInquiry = $this->hasInquiryFraming($normalized);

        // ── Rule 0: Pure Conversational Marker without domain or inquiry framing -> CHAT ──────────────────
        // (e.g. "কি অবস্থা আপনার?", "আপনি কি মানুষ নাকি রোবট?", "who are you?", "are you there?", "ke kotha bolchen?")
        if ($chatScore >= 0.70 && !$hasSubstantiveDomain && !$hasInquiry && !$hasAnyEntity && !$hasImperative) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => 0.95,
                'intent'     => 'chitchat',
                'signals'    => array_merge($signals, ['reason' => 'conversational_without_domain_context']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 1: Bare Action Keyword without context (e.g. "cancel", "change", "refund", "hello cancel") ─
        if ($isBareAction) {
            $intentName = $this->determineActionIntent($normalized, $entities);
            return [
                'route'      => RouteType::UNCERTAIN,
                'confidence' => 0.60,
                'intent'     => 'clarify_action_' . $intentName,
                'signals'    => array_merge($signals, ['reason' => 'bare_action_keyword']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 2: Ambiguous Pronoun without antecedent (e.g. "eta cancel kore den", "cancel this") ─────────
        if ($hasPronounWithoutEntity) {
            $intentName = $this->determineActionIntent($normalized, $entities);
            return [
                'route'      => RouteType::UNCERTAIN,
                'confidence' => 0.55,
                'intent'     => 'clarify_action_' . $intentName,
                'signals'    => array_merge($signals, ['reason' => 'ambiguous_pronoun_without_entity']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 3: Soft / Deliberative Statement without explicit command or entity (e.g. "cancel korte chai") ─
        if ($this->isSoftActionDesire($normalized, $hasAnyEntity)) {
            $intentName = $this->determineActionIntent($normalized, $entities);
            return [
                'route'      => RouteType::UNCERTAIN,
                'confidence' => 0.50,
                'intent'     => 'clarify_action_' . $intentName,
                'signals'    => array_merge($signals, ['reason' => 'soft_action_desire_without_entity']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 4: Substantive Knowledge Inquiry (Question / Policy / How-To / "Can I cancel?") ─────────────
        // Critical: Inquiry framing wins over embedded mutation verbs (e.g. "Can you tell me if I can cancel?")
        if ($inquiryScore >= 0.50 && ($inquiryScore >= $actionScore || !$hasImperative)) {
            return [
                'route'      => RouteType::KNOWLEDGE,
                'confidence' => round(max($inquiryScore, 0.90), 2),
                'intent'     => 'knowledge_inquiry',
                'signals'    => array_merge($signals, ['reason' => 'strong_knowledge_inquiry']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 5: Substantive Action Command (Imperative mutation + Domain entity/noun) ────────────────────
        if ($actionScore >= 0.75 && $actionScore > $inquiryScore) {
            $intentName = $this->determineActionIntent($normalized, $entities);
            return [
                'route'      => RouteType::ACTION,
                'confidence' => round($actionScore, 2),
                'intent'     => $intentName,
                'signals'    => array_merge($signals, ['reason' => 'imperative_action_command']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 6: Conversational Chitchat (When no substantive Knowledge or Action intent) ─────────────────
        if ($chatScore >= 0.70 && $inquiryScore < 0.35 && $actionScore < 0.35) {
            return [
                'route'      => RouteType::CHAT,
                'confidence' => round($chatScore, 2),
                'intent'     => 'chitchat',
                'signals'    => array_merge($signals, ['reason' => 'pure_conversational_marker']),
                'entities'   => $entities,
            ];
        }

        // ── Rule 7: Knowledge Default / Safe Read-Only Path ──────────────────────────────────────────────────
        return [
            'route'      => RouteType::KNOWLEDGE,
            'confidence' => 0.85,
            'intent'     => 'knowledge_default',
            'signals'    => array_merge($signals, ['reason' => 'safe_knowledge_fallback']),
            'entities'   => $entities,
        ];
    }

    /**
     * Detect strong inquiry framing across English, Bangla, and Banglish.
     */
    private function hasInquiryFraming(string $text): bool
    {
        $inquiryStarters = [
            'how do i', 'how can i', 'how to', 'where do i', 'where can i', 'what is', 'what are',
            'where is', 'can i', 'could i', 'can you tell me', 'could you tell me', 'is it possible',
            'is it allowed', 'why do', 'when will', 'when can i', 'tell me about', 'explain',
            'steps to', 'guide for', 'let me know', 'please tell me', 'i want to know how',
            'is there a way', 'how is', 'what plans', 'what are the', 'do you offer', 'do you have',
            'do you provide', 'does it', 'is there', 'are there', 'can we', 'how does', 'what do',
            // Bangla
            'কীভাবে', 'কিভাবে', 'কোথায়', 'কোথায়', 'কী কী', 'কি কি', 'কোন কোন', 'নিয়ম কী', 'নিয়ম কি',
            'পলিসি কী', 'পলিসি কি', 'করা যাবে কি', 'করা যাবে', 'করা সম্ভব কি', 'করা সম্ভব',
            'জানা যাবে কি', 'কোথায় পাব', 'কোথায় পাব', 'খরচ কত', 'কত টাকা', 'কত চার্জ', 'চার্জ কত',
            'দাম কত', 'ফি কত', 'কখন', 'কেমন করে', 'আছে কি', 'সুবিধা আছে কি', 'অফার আছে কি',
            'পাওয়া যায় কি', 'পাওয়া যায় কি', 'দেওয়া হয় কি', 'দেওয়া হয় কি',
            'প্ল্যান কি কি', 'ইনভয়েস কোথায়', 'এনক্রিপশন কিভাবে', 'হয়েছে কি', 'হয়েছে কি',
            'করবেন কি', 'জানাবেন কি', 'বলে দিন', 'ব্যাখ্যা করুন', 'পারবো কি', 'পারি কি',
            // Banglish
            'kivabe', 'ki vabe', 'ki bhabe', 'kemne', 'ki ki', 'kora jabe', 'kora jabe ki', 'kora sombhob',
            'policy ki', 'plans ki', 'rules ki', 'kothay pabo', 'dekhte chai', 'janbo kivabe',
            'hoyeche ki', 'hoyeche', 'korbo naki', 'korben naki', 'korben', 'parbo ki',
            'janaben ki', 'bolben ki', 'possible naki', 'koto charge', 'koto taka', 'ache ki', 'offer ache'
        ];

        foreach ($inquiryStarters as $starter) {
            if (stripos($text, $starter) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate Inquiry Score (Information-seeking, How-To, Policy questions).
     */
    private function calculateInquiryScore(string $text, bool $hasQuestionMarker): float
    {
        $score = 0.0;

        if ($this->hasInquiryFraming($text)) {
            $score += 0.70;
        }

        // Domain knowledge topics
        $topics = [
            'policy', 'pricing', 'plans', 'plan', 'features', 'invoice', 'invoices', 'encryption',
            'security', 'multi-channel', 'channels', 'documentation', 'guidance', 'return policy',
            'refund policy', 'shipping', 'payment method', 'password', 'login', 'charge', 'cost', 'fee',
            'trial', 'free trial', 'discount', 'rate limit', 'api key', 'webhook', 'whatsapp', 'telegram',
            'delivery charge', 'হটলাইন', 'হটলাইন নম্বর', 'hotline', 'number', 'ডেলিভারি', 'ডেলিভারি চার্জ',
            'চার্জ', 'খরচ', 'পেমেন্ট', 'ইনভয়েস', 'পাসওয়ার্ড', 'রিফান্ড', 'অ্যাকাউন্ট', 'সিকিউরিটি', 'পলিসি',
            'নিয়ম', 'নিয়ম', 'ট্রায়াল', 'ট্রায়াল', 'ডিসকাউন্ট', 'প্ল্যান', 'টেলিগ্রাম', 'হোয়াটসঅ্যাপ'
        ];

        foreach ($topics as $topic) {
            if (stripos($text, $topic) !== false) {
                $score += 0.30;
                break;
            }
        }

        if ($hasQuestionMarker) {
            $score += 0.20;
        }

        return min(1.0, $score);
    }

    /**
     * Detect imperative action framing (e.g. "please cancel", "cancel kore den", "বাতিল করুন").
     */
    private function hasImperativeFraming(string $text): bool
    {
        $imperativePhrases = [
            // English
            'please cancel', 'cancel my order', 'cancel order', 'cancel the order', 'cancel this', 'cancel it',
            'change my payment method', 'update my card', 'update my phone', 'create a ticket', 'open a ticket',
            'refund my order', 'issue a refund', 'track order', 'track my order', 'delete my account',
            'cancel my subscription',
            // Bangla
            'বাতিল করুন', 'বাতিল করে দিন', 'বাতিল করো', 'ক্যানসেল করুন', 'ক্যানসেল করে দেন', 'পরিবর্তন করে দিন',
            'পরিবর্তন করুন', 'আপডেট করে দিন', 'আপডেট করুন', 'টিকিট তৈরি করুন', 'টিকিট খুলুন', 'রিফান্ড দিন',
            'টাকা ফেরত দিন', 'ট্র্যাক করুন', 'অর্ডারটি বাতিল করুন', 'অর্ডার বাতিল করুন',
            // Banglish
            'cancel kore den', 'cancel korun', 'cancel koren', 'cancel koro', 'change kore den',
            'change korun', 'update kore den', 'update korun', 'ticket khulen', 'ticket create koren',
            'refund den', 'taka ferot den', 'track koren', 'track korun', 'cancel kore dao'
        ];

        foreach ($imperativePhrases as $phrase) {
            if (stripos($text, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate Action Score (Imperative, Mutation commands).
     */
    private function calculateActionScore(string $text, array $entities, bool $hasImperative, bool $hasQuestionMarker): float
    {
        // If the query is an inquiry question without an explicit imperative command + ID, penalize action
        $isClearInquiry = $this->hasInquiryFraming($text) && empty($entities['order_id']);
        if ($isClearInquiry) {
            return 0.0;
        }

        $score = 0.0;

        if ($hasImperative) {
            $score += 0.85;
        }

        // Entity boost (e.g. Order ID #1024 or Ticket ID #501 with mutation keywords)
        if (!empty($entities['order_id']) && (
            stripos($text, 'cancel') !== false ||
            stripos($text, 'বাতিল') !== false ||
            stripos($text, 'track') !== false ||
            stripos($text, 'refund') !== false ||
            stripos($text, 'order') !== false ||
            stripos($text, 'অর্ডার') !== false
        )) {
            $score += 0.35;
        }

        if (!empty($entities['ticket_id']) || stripos($text, 'ticket') !== false || stripos($text, 'টিকিট') !== false) {
            if (stripos($text, 'create') !== false || stripos($text, 'open') !== false || stripos($text, 'raise') !== false || stripos($text, 'তৈরি') !== false || stripos($text, 'খুলুন') !== false) {
                $score += 0.35;
            }
        }

        return min(1.0, $score);
    }

    /**
     * Detect bare standalone action keywords without noun/target context (e.g. "cancel", "change", "refund", "hello cancel").
     */
    private function isBareActionKeyword(string $text): bool
    {
        $clean = trim(preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $text));
        $words = explode(' ', $clean);

        // Filter out conversational greetings / thanks
        $nonChatWords = array_values(array_filter($words, function ($w) {
            return !in_array($w, ['hi', 'hello', 'hey', 'thanks', 'thank', 'you', 'please', 'plz', 'vai', 'ভাই', 'dhonnobad', 'ধন্যবাদ'], true);
        }));

        if (count($nonChatWords) === 1) {
            $single = $nonChatWords[0];
            return in_array($single, [
                'cancel', 'change', 'update', 'refund', 'delete', 'invoice', 'invoices', 'bill',
                'payment', 'payments', 'order', 'orders',
                'বাতিল', 'ক্যানসেল', 'আপডেট', 'পরিবর্তন', 'রিফান্ড', 'ইনভয়েস', 'বিল', 'পেমেন্ট', 'অর্ডার'
            ], true);
        }

        if (count($nonChatWords) === 2) {
            $phrase = implode(' ', $nonChatWords);
            return in_array($phrase, ['order cancel', 'cancel order', 'payment change', 'subscription cancel', 'ticket create'], true);
        }

        return false;
    }

    /**
     * Detect ambiguous pronouns ("this", "it", "eta", "ota", "এটা", "ওটা") without an explicit noun or entity.
     */
    private function hasAmbiguousPronounWithoutEntity(string $text, bool $hasAnyEntity): bool
    {
        if ($hasAnyEntity) {
            return false;
        }

        $domainNouns = [
            'order', 'ticket', 'invoice', 'payment', 'subscription', 'card', 'account',
            'অর্ডার', 'টিকিট', 'ইনভয়েস', 'পেমেন্ট', 'সাবস্ক্রিপশন', 'কার্ড', 'অ্যাকাউন্ট'
        ];

        $hasDomainNoun = false;
        foreach ($domainNouns as $noun) {
            if (stripos($text, $noun) !== false) {
                $hasDomainNoun = true;
                break;
            }
        }

        if ($hasDomainNoun) {
            return false;
        }

        $pronounPatterns = [
            '/\b(cancel|delete|change|update|refund)\s+(this|it|that)\b/ui',
            '/\b(please\s+cancel\s+this|please\s+cancel\s+it)\b/ui',
            '/(eta|ota|eita)\s+(cancel|change|delete|update)/ui',
            '/(cancel|change|delete)\s+(eta|ota|eita)/ui',
            '/(এটা|ওটা)\s+(বাতিল|ক্যানসেল|পরিবর্তন)/ui',
            '/(বাতিল|ক্যানসেল|পরিবর্তন)\s+(এটা|ওটা)/ui',
            '/\b(eta|ota)\s+cancel\s+kore\s+den\b/ui',
            '/\b(eta|ota)\s+change\s+kore\s+den\b/ui',
        ];

        foreach ($pronounPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect soft or uncertain action desire without entity/order ID (e.g. "order ta cancel kora dorkar", "cancel korte chai").
     */
    private function isSoftActionDesire(string $text, bool $hasAnyEntity): bool
    {
        if ($hasAnyEntity) {
            return false;
        }

        $softMarkers = [
            'cancel kora dorkar', 'বাতিল করা দরকার', 'cancel korte chai', 'বাতিল করতে চাই',
            'change korte chai', 'পরিবর্তন করতে চাই', 'i want to cancel', 'i think i want to cancel',
            'maybe cancel this', 'maybe cancel'
        ];

        foreach ($softMarkers as $marker) {
            if (stripos($text, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate Chat Score for conversational pleasantries, presence checks, gratitude, and goodbyes.
     */
    private function calculateChatScore(string $text): float
    {
        $chatCues = [
            // Greetings & Openers
            'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'good day', 'how are you', 'sup', 'yo', 'wassup', 'howdy',
            'হ্যালো', 'হাই', 'হে', 'কেমন আছেন', 'কেমন আছো', 'কেমন আছ', 'সালাম', 'আসসালামু আলাইকুম', 'নমস্কার', 'আদাব', 'কি অবস্থা', 'খবর কি', 'সব ভালো',
            'kemon achen', 'kemon acho', 'kemon asen', 'hello vai', 'hi vai', 'vaiya', 'vai', 'ki obostha', 'ki khobor', 'shob bhalo', 'kemon cholche',
            // Presence, Liveness & Identity
            'are you there', 'who are you', 'are you a bot', 'are you human', 'bot or human', 'is anyone online', 'can you hear me',
            'কেউ আছেন', 'আপনি কি আছেন', 'কে কথা বলছেন', 'আপনি কি রোবট', 'রোবট', 'মানুষ নাকি রোবট', 'অনলাইনে কেউ আছেন', 'শুনতে পাচ্ছেন',
            'keu achen', 'apni ki achen', 'ke kotha bolchen', 'apni ki robot', 'robot', 'online e keu ache', 'shunte pacchen',
            // Gratitude & Appreciation
            'thank you', 'thanks', 'appreciate it', 'grateful', 'many thanks', 'thanks a lot',
            'ধন্যবাদ', 'অনেক ধন্যবাদ', 'থ্যাংকস', 'থ্যাংক ইউ', 'অনেক কৃতজ্ঞ', 'উপকার হলো',
            'dhonnobad', 'onek dhonnobad', 'dhonnobad vai', 'thank u', 'thanks vai', 'onek upokar holo',
            // Goodbyes & Farewells
            'bye', 'goodbye', 'see you', 'take care', 'talk to you later', 'have a good day', 'have a nice day', 'tata',
            'বিদায়', 'বিদায়', 'বাই', 'পরে কথা হবে', 'ভালো থাকবেন', 'আল্লাহ হাফেজ', 'খোদা হাফেজ', 'টাটা',
            'bhalo thakben', 'pore kotha hobe', 'allah hafez', 'khoda hafez', 'tata', 'ajker moto ashi',
            // Compliments & Acknowledgments
            'you are awesome', 'great service', 'great customer service', 'nice talking', 'got it thanks', 'cool thanks',
            'দারুণ লাগলো', 'দারুন লাগলো', 'খুব সুন্দর', 'ভালো লাগলো কথা বলে', 'ঠিক আছে ধন্যবাদ',
            'darun service', 'darun laglo', 'bhalo laglo', 'thik ache dhonnobad', 'shob clear'
        ];

        foreach ($chatCues as $cue) {
            $escaped = preg_quote($cue, '/');
            if (preg_match('/(?:^|\s|[^\p{L}\p{M}\p{N}])' . $escaped . '(?:$|\s|[^\p{L}\p{M}\p{N}])/ui', $text)) {
                return 0.95;
            }
        }

        return 0.0;
    }

    /**
     * Extract structured entities from query (e.g. order IDs, ticket IDs).
     *
     * @return array<string, mixed>
     */
    private function extractEntities(string $query): array
    {
        $entities = [];

        // Extract Order ID: e.g. #1024, order 1024, order #1024, অর্ডার #১০২৪
        if (preg_match('/(?:order|অর্ডার)\s*#?\s*(\d+)/ui', $query, $matches)) {
            $entities['order_id'] = (int) $matches[1];
        } elseif (preg_match('/#(\d+)/u', $query, $matches)) {
            $entities['order_id'] = (int) $matches[1];
        }

        // Extract Ticket ID: e.g. ticket #501, ticket 501
        if (preg_match('/ticket\s*#?\s*(\d+)/ui', $query, $matches)) {
            $entities['ticket_id'] = (int) $matches[1];
        }

        return $entities;
    }

    /**
     * Check if query contains any substantive domain knowledge topics, nouns, or mutation keywords.
     */
    private function hasSubstantiveDomainContext(string $text, array $entities): bool
    {
        if (!empty($entities)) {
            return true;
        }

        $domainTokens = [
            // Domain nouns (EN/BN/Banglish)
            'order', 'ticket', 'invoice', 'invoices', 'payment', 'card', 'subscription', 'refund', 'policy',
            'pricing', 'plans', 'plan', 'feature', 'features', 'encryption', 'encrypted', 'encrypt', 'security', 'channel', 'channels',
            'guidance', 'documentation', 'return', 'shipping', 'password', 'login', 'charge', 'cost', 'fee',
            'delivery', 'hotline', 'account', 'api', 'key', 'data', 'trial', 'free trial', 'discount', 'rate limit', 'webhook', 'whatsapp', 'telegram',
            'অর্ডার', 'টিকিট', 'ইনভয়েস', 'পেমেন্ট', 'কার্ড', 'সাবস্ক্রিপশন', 'রিফান্ড', 'পলিসি', 'নিয়ম', 'নিয়ম',
            'ফিচার', 'এনক্রিপশন', 'সিকিউরিটি', 'চ্যানেল', 'ডকুমেন্টেশন', 'রিটার্ন', 'শিপিং', 'পাসওয়ার্ড', 'লগইন',
            'চার্জ', 'খরচ', 'ডেলিভারি', 'হটলাইন', 'অ্যাকাউন্ট', 'ডাটা', 'তথ্য', 'ট্রায়াল', 'ট্রায়াল', 'ডিসকাউন্ট', 'টেলিগ্রাম', 'হোয়াটসঅ্যাপ',
            // Mutation / Action verbs
            'cancel', 'change', 'update', 'delete', 'track', 'বাতিল', 'ক্যানসেল', 'পরিবর্তন', 'আপডেট', 'ফেরত'
        ];

        foreach ($domainTokens as $token) {
            if (stripos($text, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine specific action intent name.
     */
    private function determineActionIntent(string $text, array $entities): string
    {
        if (stripos($text, 'cancel') !== false || stripos($text, 'বাতিল') !== false || stripos($text, 'ক্যানসেল') !== false) {
            if (stripos($text, 'subscription') !== false || stripos($text, 'সাবস্ক্রিপশন') !== false) {
                return 'cancel_subscription';
            }
            if (stripos($text, 'payment') !== false || stripos($text, 'পেমেন্ট') !== false) {
                return 'cancel_payment';
            }
            return 'cancel_order';
        }

        if (stripos($text, 'ticket') !== false || stripos($text, 'টিকিট') !== false) {
            return 'create_ticket';
        }

        if (stripos($text, 'payment') !== false || stripos($text, 'card') !== false || stripos($text, 'পেমেন্ট') !== false) {
            return 'update_payment_method';
        }

        if (stripos($text, 'subscription') !== false || stripos($text, 'সাবস্ক্রিপশন') !== false) {
            return 'manage_subscription';
        }

        if (stripos($text, 'track') !== false || !empty($entities['order_id'])) {
            return 'get_order';
        }

        return 'generic_action';
    }
}
