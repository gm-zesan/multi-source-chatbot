/**
 * AI Chat & Spreadsheet Simulator Client Logic
 * Provides full conversational UI, real-time telemetry diagnostics,
 * decision traces, Chart.js visualizations, and session state handling.
 */

// ── LLM Usage & Cost Wizard State ──
let llmUsageHistory = (window.SIMULATOR_CONFIG && window.SIMULATOR_CONFIG.llmUsageHistory) || [];

function getSimulatorConfig() {
    return window.SIMULATOR_CONFIG || {
        routes: {},
        csrfToken: '',
        llmUsageHistory: [],
        storedMessages: []
    };
}

function renderUsageWizard() {
    // Hardcoded Peak Hour Rates & Fixed 130 BDT/$ Exchange Rate
    const inputPricePerM = 0.30;
    const outputPricePerM = 1.20;
    const bdtRate = 130;

    let totalRequests = llmUsageHistory.length;
    let totalInputTokens = 0;
    let totalOutputTokens = 0;
    let totalCost = 0;
    let inputCost = 0;
    let outputCost = 0;

    const tbody = document.getElementById('wizBreakdownTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (totalRequests === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-inbox-line fs-3 opacity-25 d-block mb-1"></i>No requests logged yet.</td></tr>';
    }

    llmUsageHistory.forEach((req, index) => {
        let reqInputTokens = req.prompt_tokens || 0;
        let reqOutputTokens = req.completion_tokens || 0;
        let reqTotalTokens = req.total_tokens || (reqInputTokens + reqOutputTokens);

        totalInputTokens += reqInputTokens;
        totalOutputTokens += reqOutputTokens;

        let reqInputCost = (reqInputTokens / 1000000) * inputPricePerM;
        let reqOutputCost = (reqOutputTokens / 1000000) * outputPricePerM;
        let reqCost = reqInputCost + reqOutputCost;
        let reqCostBdt = reqCost * bdtRate;

        inputCost += reqInputCost;
        outputCost += reqOutputCost;
        totalCost += reqCost;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="ps-3">${index + 1}</td>
            <td><span class="badge bg-secondary-subtle text-secondary border">${req.model || 'deepseek-chat'}</span></td>
            <td class="text-end text-dark fw-medium">${reqInputTokens.toLocaleString()}</td>
            <td class="text-end text-primary fw-medium">${reqOutputTokens.toLocaleString()}</td>
            <td class="text-end text-muted fw-medium">${reqTotalTokens.toLocaleString()}</td>
            <td class="text-end pe-3">
                <span class="fw-bold text-success">$${reqCost.toFixed(6)}</span>
                <span class="badge bg-light text-dark border ms-1" style="font-size: 11px;">৳${reqCostBdt.toFixed(4)}</span>
            </td>
        `;
        tbody.appendChild(tr);
    });

    if (document.getElementById('wizTotalRequests')) document.getElementById('wizTotalRequests').innerText = totalRequests.toLocaleString();
    if (document.getElementById('wizTotalRequests_stat')) document.getElementById('wizTotalRequests_stat').innerText = totalRequests.toLocaleString();
    if (document.getElementById('wizTotalInputTokens')) document.getElementById('wizTotalInputTokens').innerText = totalInputTokens.toLocaleString();
    if (document.getElementById('wizTotalOutputTokens')) document.getElementById('wizTotalOutputTokens').innerText = totalOutputTokens.toLocaleString();

    let avgTokens = totalRequests > 0 ? Math.round((totalInputTokens + totalOutputTokens) / totalRequests) : 0;
    if (document.getElementById('wizAvgTokens')) document.getElementById('wizAvgTokens').innerText = avgTokens.toLocaleString();
    if (document.getElementById('wizAvgTokens_stat')) document.getElementById('wizAvgTokens_stat').innerText = avgTokens.toLocaleString();

    let totalCostBdt = totalCost * bdtRate;
    if (document.getElementById('wizTotalCost')) document.getElementById('wizTotalCost').innerText = `$${totalCost.toFixed(6)}`;
    if (document.getElementById('wizTotalCostBdt')) {
        document.getElementById('wizTotalCostBdt').innerText = `৳${totalCostBdt.toFixed(4)}`;
    }

    if (document.getElementById('wizInputCost')) document.getElementById('wizInputCost').innerText = `$${inputCost.toFixed(6)} (৳${(inputCost * bdtRate).toFixed(4)})`;
    if (document.getElementById('wizOutputCost')) document.getElementById('wizOutputCost').innerText = `$${outputCost.toFixed(6)} (৳${(outputCost * bdtRate).toFixed(4)})`;

    let avgCost = totalRequests > 0 ? (totalCost / totalRequests) : 0;
    let avgCostBdt = avgCost * bdtRate;
    if (document.getElementById('wizAvgCost')) document.getElementById('wizAvgCost').innerHTML = `$${avgCost.toFixed(6)} <span class="ms-1 opacity-75">(৳${avgCostBdt.toFixed(4)})</span>`;
}

function setQuery(text) {
    const input = document.getElementById('userInput');
    if (input) {
        input.value = text;
        input.focus();
    }
}


function updateActiveSpreadsheetUI(activeSpreadsheet) {
    const bar = document.getElementById('activeSpreadsheetBar');
    const nameEl = document.getElementById('activeSpreadsheetName');
    const sheetsEl = document.getElementById('activeSpreadsheetSheets');
    const selectEl = document.getElementById('simulatorSourceSelect');
    const autoBadge = document.getElementById('activeSpreadsheetAutoBadge');

    if (activeSpreadsheet && activeSpreadsheet.file_id) {
        if (nameEl) nameEl.innerText = activeSpreadsheet.filename || activeSpreadsheet.file_id;
        if (sheetsEl) {
            const sheetCount = (activeSpreadsheet.sheets || []).length;
            sheetsEl.innerText = `${sheetCount} ${sheetCount === 1 ? 'sheet' : 'sheets'}`;
        }
        if (autoBadge) {
            if (activeSpreadsheet.auto_resolved) {
                autoBadge.classList.remove('d-none');
            } else {
                autoBadge.classList.add('d-none');
            }
        }
        if (bar) bar.classList.remove('d-none');

        if (selectEl) {
            let exists = false;
            for (let i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].value === activeSpreadsheet.file_id) {
                    selectEl.selectedIndex = i;
                    exists = true;
                    break;
                }
            }
            if (!exists && activeSpreadsheet.filename) {
                const newOpt = document.createElement('option');
                newOpt.value = activeSpreadsheet.file_id;
                newOpt.textContent = `📊 ${activeSpreadsheet.filename} (${(activeSpreadsheet.sheets || []).length} sheets)`;
                selectEl.appendChild(newOpt);
                selectEl.value = activeSpreadsheet.file_id;
            }
        }
    } else {
        if (bar) bar.classList.add('d-none');
        if (autoBadge) autoBadge.classList.add('d-none');
        if (selectEl) selectEl.value = '';
    }
}

async function onSourceSelectionChange(fileId) {
    const config = getSimulatorConfig();
    const selectUrl = (config.routes && config.routes.selectSource) || '/dashboard/simulator/select-source';
    const csrfToken = config.csrfToken || '';

    try {
        const response = await fetch(selectUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({ file_id: fileId || null })
        });

        const data = await response.json();
        if (data.success) {
            updateActiveSpreadsheetUI(data.active_spreadsheet);
            if (data.active_spreadsheet && data.active_spreadsheet.filename) {
                appendMessage(`📌 Focused document set to: **${data.active_spreadsheet.filename}** (${(data.active_spreadsheet.sheets || []).length} sheets, ${data.active_spreadsheet.total_rows || 0} rows)`, 'bot', {
                    route: 'analytics',
                    confidence: 100
                });
            } else {
                appendMessage(`🌐 Context cleared. Questions will automatically resolve across all available workspace documents.`, 'bot', {
                    route: 'analytics',
                    confidence: 100
                });
            }
        }
    } catch (err) {
        console.error("Select source error:", err);
    }
}

async function clearUploadedExcel() {
    if (!confirm("Are you sure you want to remove the active spreadsheet from this simulator session?")) return;

    const config = getSimulatorConfig();
    const clearExcelUrl = (config.routes && config.routes.clearExcel) || '/dashboard/simulator/clear-excel';
    const csrfToken = config.csrfToken || '';

    try {
        const response = await fetch(clearExcelUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            }
        });

        const data = await response.json();
        if (data.success) {
            updateActiveSpreadsheetUI(null);
            appendMessage("🗑️ Active spreadsheet removed. Queries will now auto-resolve across all workspace data sources.", 'bot', {
                route: 'analytics',
                confidence: 100
            });
        }
    } catch (err) {
        console.error("Clear spreadsheet error:", err);
    }
}

function setQueryAndSend(text, sourceId = null) {
    let targetSourceId = sourceId;
    const selectEl = document.getElementById('simulatorSourceSelect');
    if (!targetSourceId && selectEl) {
        // If text explicitly mentions one of our available documents, align the selector
        for (let i = 0; i < selectEl.options.length; i++) {
            const opt = selectEl.options[i];
            if (opt.value && opt.text) {
                const rawName = opt.text.replace(/^[^\w]+/, '').split(' (')[0].trim().toLowerCase();
                if (rawName.length > 2 && text.toLowerCase().includes(rawName)) {
                    targetSourceId = opt.value;
                    break;
                }
            }
        }
    }
    if (targetSourceId && selectEl) {
        selectEl.value = targetSourceId;
        window.currentSelectedSourceId = targetSourceId;
    }
    const input = document.getElementById('userInput');
    if (input) input.value = text;
    const fakeEvent = { preventDefault: () => { } };
    handleSend(fakeEvent);
}

async function handleSend(e) {
    if (e && e.preventDefault) e.preventDefault();

    const input = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    if (!input) return;
    const text = input.value.trim();

    if (!text) return;

    // Target source ID if chosen
    const selectEl = document.getElementById('simulatorSourceSelect');
    const selectedDocId = window.currentSelectedSourceId || (selectEl ? selectEl.value : null);
    window.currentSelectedSourceId = null;

    // Append user message
    appendMessage(text, 'user');
    input.value = '';
    if (sendBtn) sendBtn.disabled = true;

    // Show typing indicator
    const typingId = appendTypingIndicator();

    const config = getSimulatorConfig();
    const sendUrl = (config.routes && config.routes.send) || '/dashboard/simulator/send';
    const csrfToken = config.csrfToken || '';

    try {
        const requestPayload = { message: text };
        if (selectedDocId) {
            requestPayload.file_id = selectedDocId;
        }

        const response = await fetch(sendUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify(requestPayload)
        });

        const data = await response.json();

        // ── Update LLM Usage Wizard (Only if actual paid LLM tokens used) ──
        if (data.decision_trace && data.decision_trace.llm_generation) {
            const llmGen = data.decision_trace.llm_generation;
            const tokens = (llmGen.prompt_tokens || 0) + (llmGen.completion_tokens || 0);
            if (tokens > 0) {
                llmUsageHistory.push(llmGen);
                renderUsageWizard();
            }
        }

        // ── RAW LLM RESPONSE & COMPLETE PAYLOAD DEBUG LOG ──
        console.group("%c🤖 [Conversational AI] Raw Response & Pipeline Telemetry", "background: #2563eb; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;");
        console.log("%cQuery:", "font-weight: bold; color: #0284c7;", text);
        console.log("%cRoute:", "font-weight: bold; color: #16a34a;", data.route);
        console.log("%cRaw LLM Response Payload:", "font-weight: bold; color: #7c3aed;", data.raw_llm_response);
        console.log("%cGenerated Reply Text:", "font-weight: bold; color: #059669;", data.reply);
        console.log("%cMatched FAQ:", "font-weight: bold; color: #d97706;", data.matched_faq);
        console.log("%cConfidence Score:", "font-weight: bold; color: #ea580c;", `${data.confidence}%`);
        console.log("%cComplete JSON Payload:", "font-weight: bold; color: #475569;", data);
        console.groupEnd();

        removeElement(typingId);
        if (sendBtn) sendBtn.disabled = false;

        if (data.success) {
            data.userQuery = text;
            // Sync active spreadsheet if returned
            if (data.active_spreadsheet) {
                updateActiveSpreadsheetUI(data.active_spreadsheet);
            }
            // Append bot reply with route-aware cards
            appendMessage(data.reply, 'bot', data);
            // Update turn decision trace
            recordTurnDecisionTrace(data);
            // Update diagnostic panel
            updateDiagnostics(data);
        } else {
            appendMessage("❌ Error: " + (data.message || "Failed to process message"), 'bot');
        }
    } catch (err) {
        removeElement(typingId);
        if (sendBtn) sendBtn.disabled = false;
        appendMessage("❌ Request failed. Check server logs or connectivity.", 'bot');
        console.error(err);
    }
}

function appendMessage(content, sender, data = null) {
    const container = document.getElementById('chatMessages');
    if (!container) return;
    const div = document.createElement('div');
    div.className = `message-bubble message-${sender}`;

    let headerBadgeHtml = '';
    let extraCardsHtml = '';
    let metaHtml = '';

    if (sender === 'bot' && data) {
        const route = (data.route || 'knowledge').toLowerCase();

        // 1. Route badge in header
        if (route === 'chat') {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill chat"><i class="ri-chat-smile-2-line"></i> Conversational</span></div>`;
        } else if (route === 'knowledge') {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill knowledge"><i class="ri-book-open-line"></i> Grounded KB Answer</span></div>`;
        } else if (route === 'ood') {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill ood"><i class="ri-shield-cross-line"></i> Out-of-Domain Scope</span></div>`;
        } else if (route === 'uncertain') {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill uncertain"><i class="ri-question-line"></i> Clarification Needed</span></div>`;
        } else if (route === 'analytics') {
            const isDocAnalytics = data.active_spreadsheet && data.active_spreadsheet.filename;
            const isAutoResolved = data.active_spreadsheet && data.active_spreadsheet.auto_resolved;
            if (isDocAnalytics) {
                headerBadgeHtml = `
                    <div class="mb-2 d-flex align-items-center gap-2 flex-wrap">
                        <span class="route-pill analytics"><i class="ri-file-excel-2-line"></i> Spreadsheet Virtual DB</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 d-inline-flex align-items-center gap-1" style="font-size: 11.5px;">
                            <i class="ri-file-text-fill"></i> ${escapeHtml(data.active_spreadsheet.filename)}
                            ${isAutoResolved ? '<span class="badge bg-primary text-white ms-1 py-0 px-1" style="font-size: 9.5px; letter-spacing: 0.3px;"><i class="ri-sparkling-fill text-warning"></i> Auto-Detected</span>' : ''}
                        </span>
                    </div>`;
            } else {
                headerBadgeHtml = `<div class="mb-2"><span class="route-pill analytics"><i class="ri-database-2-line"></i> Production MySQL Business DB</span></div>`;
            }
        } else if (route === 'action') {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill action" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a;"><i class="ri-mail-send-line"></i> Seller Notification Action</span></div>`;
        } else if (data.is_handoff) {
            headerBadgeHtml = `<div class="mb-2"><span class="route-pill action"><i class="ri-user-shared-line"></i> Support Specialist Transfer</span></div>`;
        }

        // 2. Interactive Suggestions & Ambiguous Clarification Buttons
        if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
            const isActionConfirm = route === 'action' || content.includes('মেইলটি পাঠাবো?');
            const chipsHtml = data.suggestions.map(s => {
                const sText = typeof s === 'string' ? s : (s.text || s.label || s.title || String(s));
                const sFileId = (typeof s === 'object' && s.source_id) ? s.source_id : '';
                const isYes = sText.toLowerCase().includes('হ্যাঁ') || sText.toLowerCase().includes('yes') || sText.toLowerCase().includes('পাঠান');
                const isNo = sText.toLowerCase().includes('না') || sText.toLowerCase().includes('no') || sText.toLowerCase().includes('বাতিল');
                
                if (isActionConfirm) {
                    if (isYes) {
                        return `<button type="button" class="btn-action-confirm" onclick="setQueryAndSend('হ্যাঁ')"><i class="ri-checkbox-circle-line me-1"></i> ${escapeHtml(sText)}</button>`;
                    }
                    if (isNo) {
                        return `<button type="button" class="btn-action-cancel" onclick="setQueryAndSend('না')"><i class="ri-close-circle-line me-1"></i> ${escapeHtml(sText)}</button>`;
                    }
                }
                
                return `
                    <button type="button" class="suggestion-chip" onclick="setQueryAndSend('${escapeJs(sText)}', '${escapeJs(sFileId)}')">
                        <i class="ri-arrow-right-s-line text-warning"></i> ${escapeHtml(sText)}
                    </button>
                `;
            }).join('');

            const labelHtml = isActionConfirm
                ? '<span class="suggestion-label text-dark fw-bold"><i class="ri-question-answer-line text-primary me-1"></i> অ্যাকশন নিশ্চিত করুন (Click to Respond):</span>'
                : '<span class="suggestion-label"><i class="ri-lightbulb-line text-warning me-1"></i> Did you mean (Click to select):</span>';

            extraCardsHtml += `
                <div class="suggestions-container ${isActionConfirm ? 'action-confirmation-box' : ''}">
                    ${labelHtml}
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        ${chipsHtml}
                    </div>
                </div>
            `;
        }

        // 3. KNOWLEDGE Grounded Citations & Sources
        if (route === 'knowledge' && Array.isArray(data.sources) && data.sources.length > 0) {
            const sourceChips = data.sources.map(src => `
                <span class="source-chip" title="Score: ${src.score}%">
                    <i class="ri-checkbox-circle-fill text-success"></i> ${escapeHtml(src.question)}
                </span>
            `).join('');

            extraCardsHtml += `
                <div class="sources-container">
                    <span class="text-muted small fw-bold"><i class="ri-shield-check-line text-success me-1"></i> Grounded from FAQ:</span>
                    ${sourceChips}
                </div>
            `;
        }

        // 4. ANALYTICS Dynamic Visual Chart & Export Card (Selective & Intelligent Type)
        let chartId = null;
        let parsedAnalytics = null;
        let initialChartType = 'bar';

        if (route === 'analytics') {
            parsedAnalytics = parseAnalyticsData(content);
            const userQ = data.userQuery || data.query || '';
            if (parsedAnalytics && isChartNeeded(userQ, parsedAnalytics)) {
                initialChartType = detectOptimalChartType(userQ, parsedAnalytics);
                chartId = 'chart_' + Math.random().toString(36).substring(2, 9);
                window.activeSimulatorCharts = window.activeSimulatorCharts || {};
                window.activeSimulatorCharts[chartId] = parsedAnalytics;

                extraCardsHtml += `
                    <div class="analytics-chart-card" id="card_${chartId}">
                        <div class="analytics-chart-header">
                            <div class="analytics-chart-title">
                                <i class="ri-pie-chart-2-fill text-primary"></i>
                                <span>Visual Breakdown (${parsedAnalytics.title})</span>
                            </div>
                            <div class="chart-toggle-group">
                                <button type="button" class="chart-toggle-btn ${initialChartType === 'bar' ? 'active' : ''}" onclick="switchChartType('${chartId}', 'bar', this)">Bar</button>
                                <button type="button" class="chart-toggle-btn ${initialChartType === 'doughnut' ? 'active' : ''}" onclick="switchChartType('${chartId}', 'doughnut', this)">Donut</button>
                                <button type="button" class="chart-toggle-btn ${initialChartType === 'line' ? 'active' : ''}" onclick="switchChartType('${chartId}', 'line', this)">Line</button>
                            </div>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="${chartId}"></canvas>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                            <span class="text-muted small" style="font-size: 11px;"><i class="ri-flashlight-line text-warning"></i> Real-time DB Insight</span>
                            <button type="button" class="btn btn-sm btn-light border py-1 px-2" style="font-size: 11px;" onclick="copyReportText(this, '${escapeJs(content)}')">
                                <i class="ri-file-copy-line me-1"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            }
        }

        // 5. 3x UNCERTAIN / Safe Human Handoff Notice Card
        if (data.is_handoff) {
            extraCardsHtml += `
                <div class="handoff-alert-card">
                    <div class="handoff-icon"><i class="ri-customer-service-2-line"></i></div>
                    <div>
                        <strong class="d-block text-dark small" style="font-size:12px;">Human Support Request Registered</strong>
                        <small class="text-muted">A customer support specialist will review your request shortly.</small>
                    </div>
                </div>
            `;
        }

        // Footer metadata
        metaHtml = `
            <div class="message-meta">
                <span>${data.pipeline_diagnostics?.total_time_ms || 0} ms</span>
                <span>Route: <strong>${route.toUpperCase()}</strong></span>
            </div>
        `;
    }

    const formattedBodyHtml = renderFormattedMessage(content);
    div.innerHTML = `${headerBadgeHtml}${formattedBodyHtml}${extraCardsHtml}${metaHtml}`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;

    // Render Chart.js instance after DOM attachment
    if (sender === 'bot' && data && data.route === 'analytics') {
        const parsed = parseAnalyticsData(content);
        const userQ = data.userQuery || data.query || '';
        if (parsed && isChartNeeded(userQ, parsed)) {
            const optimalType = detectOptimalChartType(userQ, parsed);
            const canvasList = div.querySelectorAll('canvas');
            canvasList.forEach(c => {
                initChartJs(c.id, parsed, optimalType);
            });
        }
    }
}

/**
 * Safely and losslessly format Markdown, Bold, Code, Lists, and Paragraphs
 * GUARANTEE: Never drops, skips, or truncates any word, line, or content from LLM response.
 */
function renderFormattedMessage(rawContent) {
    if (rawContent === null || rawContent === undefined) return '';

    // 1. Escape basic HTML entities first to prevent XSS without losing characters
    let text = escapeHtml(String(rawContent));

    // 2. Fenced code blocks (```code```)
    text = text.replace(/```([\s\S]*?)```/g, (match, p1) => {
        return `<pre class="msg-code-block"><code>${p1.trim()}</code></pre>`;
    });

    // 3. Headings (###, ##, #)
    text = text.replace(/^###\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');
    text = text.replace(/^##\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');
    text = text.replace(/^#\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');

    // 4. Horizontal rule / divider (--- or ***)
    text = text.replace(/^---+$|^\*\*\*+$/gm, '<hr class="msg-divider">');

    // 5. Bold text (**bold** or __bold__)
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/__(.*?)__/g, '<strong>$1</strong>');

    // 6. Inline code (`code`)
    text = text.replace(/`([^`\n]+)`/g, '<code class="msg-code">$1</code>');

    // 7. Numbered list items (e.g. 1. Step or 1) Step)
    text = text.replace(/^(\d+)[\.\)]\s+(.*?)$/gm, '<div class="msg-list-item"><span class="num">$1.</span><span class="list-text">$2</span></div>');

    // 8. Bullet list items (e.g. - item, * item, • item)
    text = text.replace(/^[\-\*•]\s+(.*?)$/gm, '<div class="msg-list-item"><span class="bullet">•</span><span class="list-text">$1</span></div>');

    // 8.5 Blockquotes (> Quote)
    text = text.replace(/^&gt;\s+(.*?)$/gm, '<blockquote class="border-start border-3 border-primary ps-2 text-muted small my-1" style="margin-left: 0;">$1</blockquote>');

    // 8.6. Markdown Tables (| Col 1 | Col 2 |)
    text = text.replace(/(?:^\|[^\n]+\|\r?\n(?:\|[^\n]+\|\r?\n?)+)/gm, (tableMatch) => {
        const lines = tableMatch.trim().split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length < 2) return tableMatch;

        let tableHtml = '<div class="table-responsive my-2"><table class="table table-sm table-bordered table-striped mb-0" style="font-size: 12.5px; background: #ffffff;">';
        let isHeader = true;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (/^\|[\s\-:|]+\|$/.test(line)) {
                continue;
            }

            const cells = line.split('|').slice(1, -1).map(c => c.trim());

            if (isHeader) {
                tableHtml += '<thead class="table-light"><tr>';
                cells.forEach(c => {
                    tableHtml += `<th class="py-1 px-2 text-nowrap fw-bold">${c}</th>`;
                });
                tableHtml += '</tr></thead><tbody>';
                isHeader = false;
            } else {
                tableHtml += '<tr>';
                cells.forEach(c => {
                    tableHtml += `<td class="py-1 px-2">${c}</td>`;
                });
                tableHtml += '</tr>';
            }
        }

        tableHtml += '</tbody></table></div>';
        return tableHtml;
    });

    // 9. Paragraphs & Line Breaks (Preserve all content and text)
    const paragraphs = text.split(/\n\n+/);
    const formattedParagraphs = paragraphs.map(p => {
        const trimmed = p.trim();
        if (!trimmed) return '';
        if (trimmed.includes('<div class="msg-list-item"') ||
            trimmed.includes('<div class="msg-heading"') ||
            trimmed.includes('<hr class="msg-divider"') ||
            trimmed.includes('<blockquote') ||
            trimmed.includes('<div class="table-responsive') ||
            trimmed.includes('<pre class="msg-code-block"')) {
            return trimmed.replace(/\n(?=<\/?(div|hr|pre|strong|span|code|blockquote|table|thead|tbody|tr|th|td))/g, '').replace(/\n/g, '<br>');
        }
        return `<div class="msg-paragraph">${trimmed.replace(/\n/g, '<br>')}</div>`;
    });

    return `<div class="msg-content">${formattedParagraphs.join('')}</div>`;
}

function appendTypingIndicator() {
    const container = document.getElementById('chatMessages');
    if (!container) return null;
    const div = document.createElement('div');
    const id = 'typing_' + Date.now();
    div.id = id;
    div.className = 'message-bubble message-bot text-muted small';
    div.innerHTML = `<i class="ri-loader-4-line ri-spin me-1"></i> Processing query through HybridRouter & AI Agent...`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
    return id;
}

function removeElement(id) {
    if (!id) return;
    const el = document.getElementById(id);
    if (el) el.remove();
}

function updateDiagnostics(data) {
    const diag = data.pipeline_diagnostics;
    if (!diag) return;

    // Total time
    const totalTimeEl = document.getElementById('totalTimeBadge');
    if (totalTimeEl) {
        totalTimeEl.textContent = `${diag.total_time_ms} ms`;
        totalTimeEl.className = 'status-badge status-ok';
    }

    // 0. Hybrid Router
    const telemetry = diag.routing_telemetry || {};
    const route = (data.route || 'knowledge').toLowerCase();
    const routerBadge = document.getElementById('routerBadge');
    if (routerBadge) {
        routerBadge.textContent = route.toUpperCase();
        routerBadge.className = `route-pill ${route}`;
    }
    if (document.getElementById('routerRoute')) document.getElementById('routerRoute').textContent = route.toUpperCase();
    if (document.getElementById('routerIntent')) document.getElementById('routerIntent').textContent = telemetry.intent || 'direct_match';
    if (document.getElementById('routerLatency')) document.getElementById('routerLatency').textContent = `${telemetry.router_latency_ms || 0} ms`;
    if (document.getElementById('routerConfidence')) document.getElementById('routerConfidence').textContent = `${Math.round((telemetry.confidence || 1.0) * 100)}%`;

    // 1. CRM
    const crm = diag.crm_extracted;
    const crmBadge = document.getElementById('crmBadge');
    const crmResults = document.getElementById('crmResults');

    if (crm && crm.has_data) {
        if (crmBadge) {
            crmBadge.textContent = crm.db_saved ? `Saved (#${crm.contact_id})` : "Extracted";
            crmBadge.className = "status-badge status-ok";
        }
        let tags = "";
        if (crm.name) tags += `<span class="entity-tag">👤 Name: ${escapeHtml(crm.name)}</span>`;
        (crm.phones || []).forEach(p => tags += `<span class="entity-tag">📞 ${escapeHtml(p)}</span>`);
        (crm.emails || []).forEach(e => tags += `<span class="entity-tag">✉ ${escapeHtml(e)}</span>`);
        if (crm.address) tags += `<span class="entity-tag">📍 Address: ${escapeHtml(crm.address)}</span>`;
        (crm.websites || []).forEach(w => tags += `<span class="entity-tag">🌐 ${escapeHtml(w)}</span>`);
        if (crm.nid) tags += `<span class="entity-tag">🪪 NID: ${escapeHtml(crm.nid)}</span>`;
        if (crmResults) crmResults.innerHTML = tags;
    } else {
        if (crmBadge) {
            crmBadge.textContent = "No Contact Data";
            crmBadge.className = "status-badge status-none";
        }
        if (crmResults) crmResults.innerHTML = `<span class="text-muted small">No contact details found in input.</span>`;
    }

    // 2. Python
    const py = diag.python_service;
    const pyBadge = document.getElementById('pyBadge');
    if (py && py.status === 'ok') {
        if (pyBadge) {
            pyBadge.textContent = `OK (${py.latency_ms} ms)`;
            pyBadge.className = 'status-badge status-ok';
        }
        if (document.getElementById('pyModel')) document.getElementById('pyModel').textContent = py.model;
        if (document.getElementById('pyDims')) document.getElementById('pyDims').textContent = py.dimensions;
        if (document.getElementById('pyVectorSample')) document.getElementById('pyVectorSample').textContent = `Vector Sample: [${(py.vector_sample || []).join(', ')}...]`;
    } else {
        if (pyBadge) {
            pyBadge.textContent = 'BYPASSED / IDLE';
            pyBadge.className = 'status-badge status-none';
        }
        if (document.getElementById('pyVectorSample')) document.getElementById('pyVectorSample').textContent = (route === 'chat' || route === 'ood') ? '0 Embedding calls (Bypassed by HybridRouter)' : 'Idle';
    }

    // 3. Typesense
    const ts = diag.typesense;
    const tsBadge = document.getElementById('tsBadge');
    if (ts && ts.status === 'ok') {
        if (tsBadge) {
            tsBadge.textContent = `OK (${ts.latency_ms} ms)`;
            tsBadge.className = 'status-badge status-ok';
        }
    } else {
        if (tsBadge) {
            tsBadge.textContent = (route === 'chat' || route === 'ood') ? 'BYPASSED' : (ts?.status || 'IDLE').toUpperCase();
            tsBadge.className = (route === 'chat' || route === 'ood') ? 'status-badge status-none' : 'status-badge status-failed';
        }
    }

    if (document.getElementById('tsMatchType')) document.getElementById('tsMatchType').textContent = (data.match_type || 'none').toUpperCase();
    if (document.getElementById('tsFaqId')) document.getElementById('tsFaqId').textContent = data.matched_faq ? `#${data.matched_faq.id.substring(0, 8)}` : 'None';

    // 4. Scores
    const scores = diag.scores;
    const scoreBadge = document.getElementById('scoreBadge');
    if (scores) {
        if (scoreBadge) {
            scoreBadge.textContent = `${scores.final_confidence}%`;
            scoreBadge.className = data.answered ? 'status-badge status-ok' : 'status-badge status-degraded';
        }

        if (document.getElementById('kwScoreText')) document.getElementById('kwScoreText').textContent = `${scores.keyword_score}%`;
        if (document.getElementById('kwBar')) document.getElementById('kwBar').style.width = `${scores.keyword_score}%`;

        if (document.getElementById('semScoreText')) document.getElementById('semScoreText').textContent = `${scores.semantic_score}%`;
        if (document.getElementById('semBar')) document.getElementById('semBar').style.width = `${scores.semantic_score}%`;

        if (document.getElementById('finalScoreText')) document.getElementById('finalScoreText').textContent = `${scores.final_confidence}%`;
        if (document.getElementById('finalBar')) document.getElementById('finalBar').style.width = `${scores.final_confidence}%`;
    }

    // 5. Semantic Answerability Gate
    const gate = diag.answerability_decision;
    const gateBadge = document.getElementById('gateBadge');
    const gateStatusText = document.getElementById('gateStatusText');
    const gateConfidenceText = document.getElementById('gateConfidenceText');
    const gateRuleText = document.getElementById('gateRuleText');
    const gateMarginText = document.getElementById('gateMarginText');
    const gateGroundedCount = document.getElementById('gateGroundedCount');
    const gateGroundedDocs = document.getElementById('gateGroundedDocs');

    if (gate) {
        const status = (gate.status || 'unanswerable').toUpperCase();
        if (gateBadge) {
            gateBadge.textContent = status;
            if (status === 'CONFIDENT') {
                gateBadge.className = 'status-badge status-ok';
            } else if (status === 'AMBIGUOUS') {
                gateBadge.className = 'status-badge status-degraded';
            } else {
                gateBadge.className = 'status-badge status-failed';
            }
        }
        if (gateStatusText) {
            gateStatusText.textContent = status;
            if (status === 'CONFIDENT') {
                gateStatusText.className = 'text-success fw-bold';
            } else if (status === 'AMBIGUOUS') {
                gateStatusText.className = 'text-warning fw-bold';
            } else {
                gateStatusText.className = 'text-danger fw-bold';
            }
        }
        if (gateConfidenceText) gateConfidenceText.textContent = `${Math.round((gate.confidence_score || 0) * 100)}%`;

        const reasons = gate.reasons || {};
        if (gateRuleText) gateRuleText.textContent = reasons.rule || (status === 'CONFIDENT' ? 'evidence_sufficient' : 'none');
        const margin = reasons.margin !== undefined ? reasons.margin.toFixed(4) : 'N/A';
        if (gateMarginText) gateMarginText.textContent = margin;

        if (gateGroundedCount) gateGroundedCount.textContent = gate.grounded_count || 0;

        // Render document badges
        let docHtml = '';
        const sources = data.sources || [];
        if (sources.length > 0) {
            sources.forEach(s => {
                docHtml += `<div class="d-flex justify-content-between align-items-center p-1 px-2 mb-1" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px;">
                                <span class="text-truncate" style="max-width: 220px;"><strong>[${escapeHtml(s.category)}]</strong> ${escapeHtml(s.question)}</span>
                                <span class="badge bg-success-subtle text-success">${s.score}%</span>
                            </div>`;
            });
        } else {
            docHtml = '<span class="text-muted small">Zero ungrounded documents passed to LLM (Safe fallback).</span>';
        }
        if (gateGroundedDocs) gateGroundedDocs.innerHTML = docHtml;
    } else {
        if (gateBadge) {
            gateBadge.textContent = (route === 'chat' || route === 'action') ? 'BYPASSED' : 'IDLE';
            gateBadge.className = 'status-badge status-none';
        }
        if (gateStatusText) gateStatusText.textContent = (route === 'chat' || route === 'action') ? 'Bypassed (Chat/Action)' : 'Idle';
        if (gateConfidenceText) gateConfidenceText.textContent = 'N/A';
        if (gateRuleText) gateRuleText.textContent = 'None';
        if (gateMarginText) gateMarginText.textContent = 'N/A';
        if (gateGroundedCount) gateGroundedCount.textContent = '0';
        if (gateGroundedDocs) gateGroundedDocs.innerHTML = '<span class="text-muted small">No knowledge documents retrieved for this turn.</span>';
    }
}

let turnHistory = [];
let currentTurnIndex = -1;

function recordTurnDecisionTrace(data) {
    const trace = data.decision_trace;
    if (!trace) return;

    turnHistory.push({
        turnNumber: turnHistory.length + 1,
        trace: trace,
        diagnostics: data.pipeline_diagnostics,
        raw: data,
    });

    currentTurnIndex = turnHistory.length - 1;
    renderTurnHistoryPills();
    renderDecisionTrace(trace, turnHistory.length, true);
}

function renderTurnHistoryPills() {
    const nav = document.getElementById('turnHistoryNav');
    if (!nav || turnHistory.length === 0) return;

    nav.innerHTML = turnHistory.map((t, idx) => {
        const isActive = (idx === currentTurnIndex);
        const btnClass = isActive ? 'btn-primary text-white shadow-sm' : 'btn-outline-secondary';
        return `<button type="button" class="btn btn-xs ${btnClass} py-0 px-2" style="font-size: 11px; border-radius: 12px; white-space: nowrap;" onclick="selectTurn(${idx})">
                    Turn #${t.turnNumber}
                </button>`;
    }).join('');
}

function selectTurn(index) {
    if (index >= 0 && index < turnHistory.length) {
        currentTurnIndex = index;
        renderTurnHistoryPills();
        const item = turnHistory[index];
        renderDecisionTrace(item.trace, item.turnNumber, index === turnHistory.length - 1);
        if (item.raw) {
            updateDiagnostics(item.raw);
        }
    }
}

function renderDecisionTrace(trace, turnNum, isLatest = false) {
    const badge = document.getElementById('traceTurnBadge');
    if (badge) {
        badge.textContent = `Turn #${turnNum}${isLatest ? ' (Latest)' : ''}`;
        badge.className = isLatest ? 'badge bg-primary' : 'badge bg-secondary';
    }

    if (document.getElementById('traceUserQuery')) document.getElementById('traceUserQuery').textContent = `"${trace.query || ''}"`;

    // Route badge
    const routeBadge = document.getElementById('traceRouteBadge');
    const route = (trace.route || 'KNOWLEDGE').toLowerCase();
    if (routeBadge) {
        routeBadge.textContent = `${trace.route} (${trace.route_confidence || 100}%)`;
        routeBadge.className = `route-pill ${route}`;
    }

    // Memory decision
    const memBadge = document.getElementById('traceMemoryBadge');
    const memUsed = (trace.memory_decision === 'USED');
    if (memBadge) {
        memBadge.textContent = trace.memory_decision || 'BYPASSED';
        memBadge.className = memUsed ? 'badge bg-success' : 'badge bg-secondary';
        if (trace.memory_preview) {
            memBadge.title = trace.memory_preview;
        } else {
            memBadge.removeAttribute('title');
        }
    }

    // Contextual signal
    if (document.getElementById('traceContextSignal')) document.getElementById('traceContextSignal').textContent = trace.contextual_signal || 'NONE';

    // Retrieval summary
    const ret = trace.retrieval_summary || {};
    const hits = ret.hits_count || 0;
    const topScore = ret.top_score || 0;
    const docType = ret.top_doc_type ? ` [${ret.top_doc_type}]` : '';
    if (document.getElementById('traceRetrievalSummary')) document.getElementById('traceRetrievalSummary').textContent = `${hits} hits (${topScore}%)${docType}`;

    // Answerability Gate
    const gateBadge = document.getElementById('traceGateBadge');
    const gateStatus = (trace.answerability_status || 'BYPASSED').toUpperCase();
    if (gateBadge) {
        gateBadge.textContent = gateStatus;
        if (gateStatus === 'CONFIDENT') {
            gateBadge.className = 'badge bg-success';
        } else if (gateStatus === 'AMBIGUOUS') {
            gateBadge.className = 'badge bg-warning text-dark';
        } else if (gateStatus === 'UNANSWERABLE') {
            gateBadge.className = 'badge bg-danger';
        } else {
            gateBadge.className = 'badge bg-secondary';
        }
    }

    // Grounded hit count
    if (document.getElementById('traceGroundedCount')) document.getElementById('traceGroundedCount').textContent = `${trace.grounded_hit_count || 0} Docs`;

    // LLM generation
    const llm = trace.llm_generation || {};
    const provider = llm.provider || 'DeepSeek';
    const model = llm.model || 'deepseek-chat';
    const status = llm.status || 'GENERATED';
    if (document.getElementById('traceLlmStatus')) document.getElementById('traceLlmStatus').textContent = `${provider} (${model}) — ${status}`;

    // Latencies
    const lat = trace.latency_breakdown || {};
    const sub = lat.retrieval_sub_stages || {};

    // High-level summary tiles
    if (document.getElementById('latRouter')) document.getElementById('latRouter').textContent = `${lat.router_ms || 0} ms`;
    if (document.getElementById('latRetrieval')) document.getElementById('latRetrieval').textContent = `${lat.knowledge_retrieval_ms ?? lat.retrieval_ms ?? 0} ms`;
    if (document.getElementById('latLlm')) document.getElementById('latLlm').textContent = `${lat.llm_generation_ms ?? lat.llm_ms ?? 0} ms${lat.ttft_ms ? ` [TTFT: ${lat.ttft_ms} ms]` : ''}`;
    if (document.getElementById('latTotal')) document.getElementById('latTotal').textContent = `${lat.total_ms ?? lat.total_e2e_ms ?? 0} ms`;

    // Granular Stage-Level Latency Tree
    if (document.getElementById('latDetailedRouter')) document.getElementById('latDetailedRouter').textContent = `${lat.router_ms || 0} ms`;
    if (document.getElementById('latDetailedContext')) document.getElementById('latDetailedContext').textContent = `${lat.context_resolution_ms || 0} ms`;
    if (document.getElementById('latDetailedMemory')) document.getElementById('latDetailedMemory').textContent = `${lat.memory_retrieval_ms || 0} ms`;
    if (document.getElementById('latDetailedRetrieval')) document.getElementById('latDetailedRetrieval').textContent = `${lat.knowledge_retrieval_ms ?? lat.retrieval_ms ?? 0} ms`;

    if (document.getElementById('latEmbedding')) document.getElementById('latEmbedding').textContent = `${sub.embedding_ms || 0} ms`;
    if (document.getElementById('latTypesense')) document.getElementById('latTypesense').textContent = `${sub.typesense_ms || 0} ms`;
    if (document.getElementById('latReranker')) document.getElementById('latReranker').textContent = `${sub.rerank_ms || 0} ms`;

    if (document.getElementById('latDetailedGate')) document.getElementById('latDetailedGate').textContent = `${lat.answerability_ms || 0} ms`;
    if (document.getElementById('latDetailedLlm')) document.getElementById('latDetailedLlm').textContent = `${lat.llm_generation_ms ?? lat.llm_ms ?? 0} ms${lat.ttft_ms ? ` (TTFT: ${lat.ttft_ms} ms)` : ''}`;
    if (document.getElementById('latDetailedTotal')) document.getElementById('latDetailedTotal').textContent = `${lat.total_e2e_ms ?? lat.total_ms ?? 0} ms`;
}

async function clearSimulatorChat() {
    const config = getSimulatorConfig();
    const clearUrl = (config.routes && config.routes.clear) || '/dashboard/simulator/clear';
    const csrfToken = config.csrfToken || '';

    try {
        await fetch(clearUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            }
        });
    } catch (e) {
        console.error("Failed to clear chat session:", e);
    }
    window.location.reload();
}

// ── Visual Data Charts & Export Handlers ──
window.chartJsInstances = window.chartJsInstances || {};

function isChartNeeded(query, parsed) {
    if (!parsed || !parsed.dataPoints || parsed.dataPoints.length === 0) return false;
    
    const q = (query || '').toLowerCase();
    const explicitKeywords = [
        'chart', 'graph', 'চার্ট', 'গ্রাফ', 'pie', 'bar', 'line', 'donut', 
        'পাই', 'ডোনাট', 'বার', 'লাইন', 'visual', 'ভিজ্যুয়াল', 'breakdown', 'ব্রেকডাউন'
    ];
    const isExplicit = explicitKeywords.some(kw => q.includes(kw));

    // Show chart if user explicitly requested a chart/graph OR if dataset has 2 or more data points (comparisons / breakdown)
    return isExplicit || parsed.dataPoints.length >= 2;
}

function detectOptimalChartType(query, parsed) {
    const q = (query || '').toLowerCase();

    // 1. Explicit user chart type request has top priority
    if (q.includes('pie') || q.includes('donut') || q.includes('পাই') || q.includes('ডোনাট')) return 'doughnut';
    if (q.includes('bar') || q.includes('বার') || q.includes('কলাম') || q.includes('column')) return 'bar';
    if (q.includes('line') || q.includes('লাইন') || q.includes('গ্রাফ') || q.includes('trend') || q.includes('ট্রেন্ড')) return 'line';

    // 2. Semantic detection based on question & dataset title
    const title = (parsed?.title || '').toLowerCase();
    const combined = q + ' ' + title;

    // Distribution / Share / Methods / Categories -> Donut
    if (combined.includes('method') || combined.includes('মেথড') || combined.includes('share') || combined.includes('ভাগ') || 
        combined.includes('অনুপাত') || combined.includes('ratio') || combined.includes('category') || combined.includes('ক্যাটাগরি') ||
        combined.includes('payment') || combined.includes('পেমেন্ট') || combined.includes('status') || combined.includes('স্ট্যাটাস')) {
        return 'doughnut';
    }

    // Time-series / Trends / Daily / Monthly -> Line
    if (combined.includes('trend') || combined.includes('ট্রেন্ড') || combined.includes('daily') || combined.includes('দৈনিক') || 
        combined.includes('monthly') || combined.includes('মাসিক') || combined.includes('দিন') || combined.includes('days') || 
        combined.includes('date') || combined.includes('তারিখ') || combined.includes('history') || combined.includes('timeline')) {
        return 'line';
    }

    // Default for comparisons, rankings, entity lists -> Bar
    return 'bar';
}

function parseAnalyticsData(text) {
    if (!text || typeof text !== 'string') return null;
    const lines = text.split('\n');
    const dataPoints = [];
    let mainTitle = 'Metrics Breakdown';

    // 1. First attempt: Parse Markdown Table (| Category | Value |)
    const tableLines = lines.map(l => l.trim()).filter(l => l.startsWith('|') && l.endsWith('|'));
    if (tableLines.length >= 2) {
        let headerFound = false;
        let valColIdx = -1;
        let labelColIdx = -1;

        for (let i = 0; i < tableLines.length; i++) {
            const row = tableLines[i];
            if (/^\|[\s\-:|]+\|$/.test(row)) continue; // Divider row

            const cells = row.split('|').slice(1, -1).map(c => c.trim());
            if (!headerFound) {
                headerFound = true;
                // Determine label and value columns
                for (let c = 0; c < cells.length; c++) {
                    const hLower = cells[c].toLowerCase();
                    if (hLower.includes('amount') || hLower.includes('count') || hLower.includes('total') || 
                        hLower.includes('sales') || hLower.includes('collection') || hLower.includes('due') || 
                        hLower.includes('taka') || hLower.includes('টাকা') || hLower.includes('সংখ্যা') || hLower.includes('পরিমাণ')) {
                        valColIdx = c;
                    } else if (labelColIdx === -1) {
                        labelColIdx = c;
                    }
                }
                if (valColIdx === -1 && cells.length >= 2) valColIdx = cells.length - 1;
                if (labelColIdx === -1) labelColIdx = 0;
            } else {
                // Data rows
                const label = cells[labelColIdx] || `Item ${dataPoints.length + 1}`;
                const rawVal = cells[valColIdx] || '';
                const cleanNumStr = rawVal.replace(/[^0-9.-]/g, '');
                const num = parseFloat(cleanNumStr);

                if (!isNaN(num)) {
                    dataPoints.push({
                        label: label,
                        raw: rawVal,
                        value: num,
                    });
                }
            }
        }
    }

    // 2. Second attempt: Bullet points with key-value format
    if (dataPoints.length === 0) {
        const metricRegex = /[-*•]\s*\**([A-Za-z0-9\s_&-]+?)\**:\s*([^\n\r]+)/;
        for (const line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('#') || trimmed.includes('**Business Analytics') || trimmed.includes('Summary') || trimmed.includes('Report')) {
                const cleanTitle = trimmed.replace(/^[#* \-_]+|[#* \-_]+$/g, '');
                if (cleanTitle) mainTitle = cleanTitle;
            }

            const match = trimmed.match(metricRegex);
            if (match) {
                const label = match[1].trim();
                const rawVal = match[2].trim();
                const cleanNumStr = rawVal.replace(/[^0-9.-]/g, '');
                const num = parseFloat(cleanNumStr);

                if (!isNaN(num) && num > 0) {
                    dataPoints.push({
                        label: label,
                        raw: rawVal,
                        value: num,
                    });
                }
            }
        }
    }

    if (dataPoints.length === 0) return null;

    return {
        title: mainTitle,
        dataPoints: dataPoints,
    };
}

function initChartJs(canvasId, parsedData, type = 'bar') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    if (window.chartJsInstances[canvasId]) {
        window.chartJsInstances[canvasId].destroy();
    }

    const labels = parsedData.dataPoints.map(dp => dp.label);
    const dataValues = parsedData.dataPoints.map(dp => dp.value);

    const palette = [
        'rgba(37, 99, 235, 0.85)',
        'rgba(16, 185, 129, 0.85)',
        'rgba(245, 158, 11, 0.85)',
        'rgba(139, 92, 246, 0.85)',
        'rgba(236, 72, 153, 0.85)',
        'rgba(14, 165, 233, 0.85)'
    ];

    const borderPalette = [
        '#1d4ed8',
        '#059669',
        '#d97706',
        '#7c3aed',
        '#db2777',
        '#0284c7'
    ];

    const chartConfig = {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: parsedData.title,
                data: dataValues,
                backgroundColor: type === 'line' ? 'rgba(37, 99, 235, 0.15)' : palette.slice(0, dataValues.length),
                borderColor: type === 'line' ? '#2563eb' : borderPalette.slice(0, dataValues.length),
                borderWidth: 1.5,
                fill: type === 'line',
                tension: 0.35,
                borderRadius: type === 'bar' ? 6 : 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: type === 'doughnut' || type === 'pie',
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: { size: 10 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const orig = parsedData.dataPoints[context.dataIndex]?.raw || context.raw;
                            return ` ${context.label}: ${orig}`;
                        }
                    }
                }
            },
            scales: type === 'doughnut' || type === 'pie' ? {} : {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: { font: { size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    };

    window.chartJsInstances[canvasId] = new Chart(ctx, chartConfig);
}

function switchChartType(chartId, newType, btn) {
    const card = document.getElementById(`card_${chartId}`);
    if (card) {
        card.querySelectorAll('.chart-toggle-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    const parsed = window.activeSimulatorCharts?.[chartId];
    if (parsed) {
        initChartJs(chartId, parsed, newType);
    }
}

async function triggerExport(chartId, format) {
    const parsed = window.activeSimulatorCharts?.[chartId];
    if (!parsed) {
        alert('No structured chart data found to export.');
        return;
    }

    const headers = ['Metric / Label', 'Value (Formatted)'];
    const rows = parsed.dataPoints.map(dp => [dp.label, dp.raw]);
    const summary = {
        'Report Title': parsed.title,
        'Generated By': 'AI Analytics Engine',
        'Export Date': new Date().toLocaleString(),
    };

    const payload = {
        format: format,
        title: parsed.title,
        headers: headers,
        rows: rows,
        summary: summary,
    };

    const config = getSimulatorConfig();
    const exportUrl = (config.routes && config.routes.exportAnalytics) || '/dashboard/export/analytics';
    const csrfToken = config.csrfToken || '';

    try {
        const response = await fetch(exportUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error("Export failed with status " + response.status);
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const ext = format === 'xlsx' ? 'xlsx' : (format === 'pdf' ? 'pdf' : 'csv');
        a.download = `analytics-report-${Date.now()}.${ext}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        console.error("Export error:", err);
        alert("Could not complete export: " + err.message);
    }
}

function copyReportText(btn, text) {
    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(text);
    }

    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="ri-check-line text-success"></i> Copied!';
    setTimeout(() => {
        btn.innerHTML = origHtml;
    }, 2000);
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escapeJs(str) {
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// ── Global Window Exports for Inline HTML Event Listeners ──
window.renderUsageWizard = renderUsageWizard;
window.setQuery = setQuery;
window.updateActiveSpreadsheetUI = updateActiveSpreadsheetUI;
window.onSourceSelectionChange = onSourceSelectionChange;
window.clearUploadedExcel = clearUploadedExcel;
window.setQueryAndSend = setQueryAndSend;
window.handleSend = handleSend;
window.appendMessage = appendMessage;
window.appendTypingIndicator = appendTypingIndicator;
window.removeElement = removeElement;
window.updateDiagnostics = updateDiagnostics;
window.recordTurnDecisionTrace = recordTurnDecisionTrace;
window.renderTurnHistoryPills = renderTurnHistoryPills;
window.selectTurn = selectTurn;
window.renderDecisionTrace = renderDecisionTrace;
window.clearSimulatorChat = clearSimulatorChat;
window.isChartNeeded = isChartNeeded;
window.detectOptimalChartType = detectOptimalChartType;
window.parseAnalyticsData = parseAnalyticsData;
window.initChartJs = initChartJs;
window.switchChartType = switchChartType;
window.triggerExport = triggerExport;
window.copyReportText = copyReportText;
window.escapeHtml = escapeHtml;
window.escapeJs = escapeJs;
window.renderFormattedMessage = renderFormattedMessage;

// ── Page Initialization & History Re-hydration ──
window.addEventListener('load', function () {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Restore Usage History
    if (llmUsageHistory.length > 0) {
        renderUsageWizard();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const config = getSimulatorConfig();
    const serverStoredMessages = config.storedMessages || [];

    if (Array.isArray(serverStoredMessages) && serverStoredMessages.length > 0) {
        let lastUserQuery = '';
        serverStoredMessages.forEach(msg => {
            if (msg.direction === 'inbound') {
                lastUserQuery = msg.body;
                appendMessage(msg.body, 'user');
            } else {
                appendMessage(msg.body, 'bot', {
                    route: msg.route || 'knowledge',
                    is_handoff: msg.is_handoff,
                    suggestions: msg.suggestions || [],
                    sources: msg.sources || [],
                    userQuery: lastUserQuery,
                    query: lastUserQuery,
                    pipeline_diagnostics: msg.pipeline_diagnostics || { total_time_ms: 0 },
                });
            }
        });
    }
});
