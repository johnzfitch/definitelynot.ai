/**
 * Cosmic Text Linter v2.2.1
 * Frontend control deck for the retro space console UI.
 * Handles mode toggles, API calls, counters, modal, and toasts.
 */

const inputText = document.getElementById('input-text');
const outputText = document.getElementById('output-text');
const sanitizeBtn = document.getElementById('sanitize-btn');
const copyBtn = document.getElementById('copy-btn');
const inputCount = document.getElementById('input-count');
const outputCount = document.getElementById('output-count');
const statsDisplay = document.getElementById('stats');
const toast = document.getElementById('toast');
const advisoryPanel = document.getElementById('advisory-panel');
const advisoryList = document.getElementById('advisory-list');
const helpBtn = document.getElementById('help-btn');
const helpModal = document.getElementById('help-modal');
const modalClose = document.getElementById('modal-close');
const aboutLink = document.getElementById('about-link');
const colorTrigger = document.getElementById('color-trigger');
const colorModal = document.getElementById('color-modal');
const colorModalClose = document.getElementById('color-modal-close');
const modeButtons = document.querySelectorAll('.mode-btn');
const accentColorPicker = document.getElementById('accent-color-picker');
const accentColorHex = document.getElementById('accent-color-hex');
const accentApply = document.getElementById('accent-apply');
const accentRandom = document.getElementById('accent-random');
const appContainer = document.querySelector('.container');
const API_URL = document.body.dataset.api || 'api/clean.php';
const rootStyle = document.documentElement.style;
const outputModeBtns = document.querySelectorAll('.output-mode-btn');
const diffView = document.getElementById('diff-view');

let isProcessing = false;
let selectedMode = 'safe';
let lastFocused = null;
let toastTimeout;
let outputMode = 'clean';
let lastInputText = '';
let lastOutputText = '';
let comparisonMode = 'char'; // 'char' or 'word'

const hapticRegistry = new WeakSet();

class HapticButton {
    constructor(element) {
        this.element = element;
        this.releaseTimeout = null;
        this.prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches || false;
        this.supportsVibration = typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function';
        this.patterns = this.configurePatterns();
        this.bindHandlers();
    }

    configurePatterns() {
        if (!this.supportsVibration || this.prefersReducedMotion) {
            return { press: null, release: null };
        }

        const ua = navigator.userAgent || '';
        if (/android/i.test(ua)) {
            return { press: [14], release: [10, 8] };
        }
        if (/iphone|ipad|ipod/i.test(ua)) {
            return { press: [6], release: [8] };
        }
        return { press: [10], release: [16] };
    }

    bindHandlers() {
        this.onPress = this.onPress.bind(this);
        this.onRelease = this.onRelease.bind(this);
        this.onCancel = this.onCancel.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);
        this.onKeyUp = this.onKeyUp.bind(this);

        this.element.addEventListener('pointerdown', this.onPress, { passive: true });
        this.element.addEventListener('pointerup', this.onRelease, { passive: true });
        this.element.addEventListener('pointercancel', this.onCancel, { passive: true });
        this.element.addEventListener('pointerleave', this.onCancel, { passive: true });
        this.element.addEventListener('blur', this.onCancel, { passive: true });
        this.element.addEventListener('keydown', this.onKeyDown);
        this.element.addEventListener('keyup', this.onKeyUp);

        if (!window.PointerEvent) {
            this.element.addEventListener('touchstart', this.onPress, { passive: true });
            this.element.addEventListener('touchend', this.onRelease, { passive: true });
            this.element.addEventListener('touchcancel', this.onCancel, { passive: true });
            this.element.addEventListener('mousedown', this.onPress);
            this.element.addEventListener('mouseup', this.onRelease);
            this.element.addEventListener('mouseleave', this.onCancel);
        }
    }

    shouldVibrate(event) {
        if (!this.patterns.press || !this.patterns.release) return false;
        if (event?.pointerType) {
            return event.pointerType === 'touch' || event.pointerType === 'pen';
        }
        return window.matchMedia?.('(pointer: coarse)')?.matches ?? false;
    }

    vibrate(pattern) {
        if (!pattern || pattern.length === 0) return;
        try {
            navigator.vibrate(pattern);
        } catch (error) {
            console.debug('Vibration blocked', error);
        }
    }

    applyPressedState(isActive) {
        clearTimeout(this.releaseTimeout);
        if (isActive) {
            this.element.classList.add('button-pressed');
            return;
        }

        this.releaseTimeout = setTimeout(() => {
            this.element.classList.remove('button-pressed');
        }, 110);
    }

    onPress(event) {
        if (this.element.disabled) return;
        this.applyPressedState(true);
        if (this.shouldVibrate(event)) {
            this.vibrate(this.patterns.press);
        }
    }

    onRelease(event) {
        if (this.element.disabled) return;
        this.applyPressedState(false);
        if (this.shouldVibrate(event)) {
            this.vibrate(this.patterns.release);
        }
    }

    onCancel() {
        clearTimeout(this.releaseTimeout);
        this.element.classList.remove('button-pressed');
    }

    onKeyDown(event) {
        if (this.element.disabled) return;
        if (event.code === 'Space' || event.code === 'Enter') {
            this.applyPressedState(true);
            if (!this.prefersReducedMotion && this.supportsVibration) {
                this.vibrate(this.patterns.press);
            }
        }
    }

    onKeyUp(event) {
        if (event.code === 'Space' || event.code === 'Enter') {
            this.applyPressedState(false);
            if (!this.prefersReducedMotion && this.supportsVibration) {
                this.vibrate(this.patterns.release);
            }
        }
    }
}

function registerHapticTarget(element) {
    if (!(element instanceof HTMLElement)) return;
    if (element.hasAttribute('data-haptic-disabled') || element.dataset.hapticBound === 'true') {
        return;
    }
    if (hapticRegistry.has(element)) {
        return;
    }
    hapticRegistry.add(element);
    element.dataset.hapticBound = 'true';
    new HapticButton(element);
}

function initHaptics() {
    const selector = 'button, .button, [role="button"], .clickable';
    document.querySelectorAll(selector).forEach(registerHapticTarget);

    if (typeof MutationObserver === 'function') {
        const observer = new MutationObserver(mutations => {
            for (const mutation of mutations) {
                mutation.addedNodes.forEach(node => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }
                    if (node.matches?.(selector)) {
                        registerHapticTarget(node);
                    }
                    node.querySelectorAll?.(selector).forEach(registerHapticTarget);
                });
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
}

function charCount(str) {
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
        const seg = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
        let count = 0;
        for (const _ of seg.segment(str)) {
            count++;
        }
        return count;
    }
    return Array.from(str).length;
}

function byteLength(str) {
    return new TextEncoder().encode(str).length;
}

function formatCounts(str) {
    return `${charCount(str).toLocaleString()} chars • ${byteLength(str).toLocaleString()} bytes`;
}

modeButtons.forEach(button => {
    button.addEventListener('click', () => {
        modeButtons.forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-pressed', 'false');
        });
        button.classList.add('active');
        button.setAttribute('aria-pressed', 'true');
        selectedMode = button.dataset.mode;
    });
});

inputText.addEventListener('input', () => {
    inputCount.textContent = formatCounts(inputText.value);
});

sanitizeBtn.addEventListener('click', async () => {
    if (isProcessing) return;

    const text = inputText.value;
    if (!text.trim()) {
        showToast('INPUT REQUIRED');
        return;
    }

    isProcessing = true;
    sanitizeBtn.classList.add('processing');
    sanitizeBtn.querySelector('.btn-text').textContent = 'PROCESSING...';
    updateStats('PROCESSING SIGNAL...', 'pending');
    advisoryPanel.classList.add('hidden');

    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ text, mode: selectedMode }),
            signal: controller.signal
        });
        clearTimeout(timeout);

        if (!response.ok) {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const errorPayload = await response.json();
                throw new Error(errorPayload.error || `HTTP ${response.status}`);
            }
            const raw = await response.text();
            throw new Error(raw || `HTTP ${response.status}`);
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('Unexpected response from server');
        }

        const result = await response.json();
        outputText.value = result.text;
        outputCount.textContent = formatCounts(result.text || '');
        lastInputText = text;
        lastOutputText = result.text;
        displayStats(result.stats);
        displayAdvisories(result.stats?.advisories || {});
        if (outputMode === 'diff') {
            updateDiffView();
        }
        showToast('TRANSMISSION CLEAN');
    } catch (error) {
        console.error('Sanitization failed:', error);
        showToast(error.name === 'AbortError' ? 'CONNECTION TIMEOUT' : 'TRANSMISSION ERROR');
        updateStats('ERROR: SIGNAL LOST', 'error');
    } finally {
        isProcessing = false;
        sanitizeBtn.classList.remove('processing');
        sanitizeBtn.querySelector('.btn-text').textContent = 'SANITIZE TEXT';
    }
});

copyBtn.addEventListener('click', async () => {
    const text = outputText.value;
    if (!text) {
        showToast('NO DATA TO COPY');
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            showToast('COORDINATES COPIED');
            return;
        }

        const fallback = document.createElement('textarea');
        fallback.value = text;
        fallback.setAttribute('readonly', '');
        fallback.style.position = 'fixed';
        fallback.style.top = '-1000px';
        document.body.appendChild(fallback);
        fallback.select();
        document.execCommand('copy');
        document.body.removeChild(fallback);
        showToast('COORDINATES COPIED');
    } catch (error) {
        console.error('Copy failed:', error);
        showToast('COPY FAILED');
    }
});

function displayStats(stats = {}) {
    const delta = stats.characters_removed ?? 0;
    const deltaSign = delta > 0 ? '-' : delta < 0 ? '+' : '';
    const deltaAbs = Math.abs(delta);
    const statusLabel = (stats.status_label || stats.status || 'CLEAN').toString().toUpperCase();
    const statusState = stats.status_state || (statusLabel.includes('ERROR') ? 'error' : 'complete');

    let html = `
        <div class="stat-item">
            <span class="stat-label">SIGNAL STATUS</span>
            <span class="stat-value" id="status" data-status="${statusState}">${statusLabel}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">MODE</span>
            <span class="stat-value">${(stats.mode || '').toUpperCase()}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">INVISIBLES REMOVED</span>
            <span class="stat-value">${stats.invisibles_removed ?? 0}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">HOMOGLYPHS</span>
            <span class="stat-value">${stats.homoglyphs_normalized ?? 0}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">TOTAL DELTA</span>
            <span class="stat-value">${deltaSign}${deltaAbs.toLocaleString()}</span>
        </div>
    `;

    if ((stats.digits_normalized ?? 0) > 0) {
        html += `
            <div class="stat-item">
                <span class="stat-label">DIGITS NORMALIZED</span>
                <span class="stat-value">${stats.digits_normalized}</span>
            </div>
        `;
    }

    statsDisplay.innerHTML = html;
}

function updateStats(message, status = 'pending') {
    const statusMap = {
        pending: 'processing',
        success: 'complete',
        error: 'error'
    };
    const normalizedStatus = statusMap[status] || 'awaiting';
    statsDisplay.innerHTML = `
        <div class="stat-item">
            <span class="stat-label">SIGNAL STATUS</span>
            <span class="stat-value" id="status" data-status="${normalizedStatus}">${message}</span>
        </div>
    `;
}

function displayAdvisories(advisories = {}) {
    const advisoryMessages = {
        had_bidi_controls: 'BiDi controls detected (Trojan Source risk)',
        had_mixed_scripts: 'Mixed script usage detected',
        had_default_ignorables: 'Invisible characters found',
        had_tag_chars: 'TAG block characters detected',
        had_orphan_combining: 'Orphan combining marks removed',
        confusable_suspected: 'Confusable characters detected',
        had_html_entities: 'HTML entities decoded',
        had_ascii_controls: 'ASCII control characters removed',
        had_noncharacters: 'Noncharacters removed',
        had_private_use: 'Private Use Area characters removed',
        had_mirrored_punctuation: 'Mirrored punctuation in RTL context',
        had_non_ascii_digits: 'Non-ASCII digits normalized'
    };

    const active = Object.entries(advisories)
        .filter(([, value]) => value === true)
        .map(([key]) => advisoryMessages[key])
        .filter(Boolean);

    if (active.length === 0) {
        advisoryPanel.classList.add('hidden');
        advisoryList.innerHTML = '';
        return;
    }

    advisoryList.innerHTML = active
        .map(msg => `
            <div class="advisory-item">
                <span class="advisory-bullet" aria-hidden="true">▸</span>
                <span class="advisory-text">${msg}</span>
            </div>
        `)
        .join('');
    advisoryPanel.classList.remove('hidden');
}

function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function setupModalA11y() {
    if (helpModal.classList.contains('hidden')) {
        helpModal.classList.remove('hidden');
    }
    lastFocused = document.activeElement;
    document.body.classList.add('modal-open');
    if (appContainer) {
        appContainer.setAttribute('aria-hidden', 'true');
        try {
            appContainer.inert = true;
        } catch (error) {
            console.debug('inert unsupported', error);
        }
    }

    const focusables = helpModal.querySelectorAll(
        'button, [href], input, textarea, select, details,[tabindex]:not([tabindex="-1"])'
    );
    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    const trap = event => {
        if (event.key !== 'Tab') return;
        if (event.shiftKey) {
            if (document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        } else if (document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    helpModal.addEventListener('keydown', trap);
    helpModal.dataset.trap = 'true';
    helpModal._trapHandler = trap;

    if (first) {
        first.focus();
    }
}

function teardownModalA11y() {
    document.body.classList.remove('modal-open');
    if (appContainer) {
        appContainer.removeAttribute('aria-hidden');
        try {
            appContainer.inert = false;
        } catch (error) {
            console.debug('inert unsupported', error);
        }
    }
    if (helpModal._trapHandler) {
        helpModal.removeEventListener('keydown', helpModal._trapHandler);
        delete helpModal._trapHandler;
    }
    helpModal.classList.add('hidden');
    if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
    }
}

helpBtn.addEventListener('click', () => {
    setupModalA11y();
});

aboutLink.addEventListener('click', event => {
    event.preventDefault();
    setupModalA11y();
});

modalClose.addEventListener('click', () => {
    teardownModalA11y();
});

helpModal.addEventListener('click', event => {
    if (event.target === helpModal) {
        teardownModalA11y();
    }
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        if (!helpModal.classList.contains('hidden')) {
            teardownModalA11y();
        } else if (!colorModal.classList.contains('hidden')) {
            teardownColorModal();
        }
    }
});

/**
 * Color modal management
 */
function setupColorModal() {
    if (colorModal.classList.contains('hidden')) {
        colorModal.classList.remove('hidden');
    }
    lastFocused = document.activeElement;
    document.body.classList.add('modal-open');
    if (appContainer) {
        appContainer.setAttribute('aria-hidden', 'true');
        try {
            appContainer.inert = true;
        } catch (error) {
            console.debug('inert unsupported', error);
        }
    }

    const focusables = colorModal.querySelectorAll(
        'button, [href], input, textarea, select, details,[tabindex]:not([tabindex="-1"])'
    );
    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    const trap = event => {
        if (event.key !== 'Tab') return;
        if (event.shiftKey) {
            if (document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        } else if (document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    colorModal.addEventListener('keydown', trap);
    colorModal.dataset.trap = 'true';
    colorModal._trapHandler = trap;

    if (first) {
        first.focus();
    }
}

function teardownColorModal() {
    document.body.classList.remove('modal-open');
    if (appContainer) {
        appContainer.removeAttribute('aria-hidden');
        try {
            appContainer.inert = false;
        } catch (error) {
            console.debug('inert unsupported', error);
        }
    }
    if (colorModal._trapHandler) {
        colorModal.removeEventListener('keydown', colorModal._trapHandler);
        delete colorModal._trapHandler;
    }
    colorModal.classList.add('hidden');
    if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
    }
}

colorTrigger.addEventListener('click', () => {
    setupColorModal();
});

colorModalClose.addEventListener('click', () => {
    teardownColorModal();
});

colorModal.addEventListener('click', event => {
    if (event.target === colorModal) {
        teardownColorModal();
    }
});

inputText.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        sanitizeBtn.click();
    }
});

outputText.addEventListener('input', () => {
    outputCount.textContent = formatCounts(outputText.value);
});

inputCount.textContent = formatCounts('');
outputCount.textContent = formatCounts('');

initHaptics();

/**
 * Output mode toggle (Clean vs Diff)
 */
outputModeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        outputModeBtns.forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        outputMode = btn.dataset.outputMode;

        if (outputMode === 'diff') {
            outputText.classList.add('hidden');
            diffView.classList.remove('hidden');
            updateDiffView();
        } else {
            outputText.classList.remove('hidden');
            diffView.classList.add('hidden');
        }
    });
});

/**
 * Improved diff algorithm that shows both additions and removals
 * Supports both character-level and word-level comparison
 */
function computeDiff(original, modified, mode = 'char') {
    if (mode === 'word') {
        return computeWordDiff(original, modified);
    }
    return computeCharDiff(original, modified);
}

function computeCharDiff(original, modified) {
    const diff = [];
    const dp = [];

    // Build LCS table
    for (let i = 0; i <= original.length; i++) {
        dp[i] = [];
        for (let j = 0; j <= modified.length; j++) {
            if (i === 0 || j === 0) {
                dp[i][j] = 0;
            } else if (original[i - 1] === modified[j - 1]) {
                dp[i][j] = dp[i - 1][j - 1] + 1;
            } else {
                dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
            }
        }
    }

    // Backtrack to build diff
    let i = original.length;
    let j = modified.length;

    while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && original[i - 1] === modified[j - 1]) {
            diff.unshift({ type: 'unchanged', char: original[i - 1] });
            i--;
            j--;
        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
            diff.unshift({ type: 'added', char: modified[j - 1] });
            j--;
        } else if (i > 0) {
            diff.unshift({ type: 'removed', char: original[i - 1] });
            i--;
        }
    }

    return diff;
}

function computeWordDiff(original, modified) {
    const originalWords = original.split(/(\s+)/);
    const modifiedWords = modified.split(/(\s+)/);
    const diff = [];
    const dp = [];

    // Build LCS table for words
    for (let i = 0; i <= originalWords.length; i++) {
        dp[i] = [];
        for (let j = 0; j <= modifiedWords.length; j++) {
            if (i === 0 || j === 0) {
                dp[i][j] = 0;
            } else if (originalWords[i - 1] === modifiedWords[j - 1]) {
                dp[i][j] = dp[i - 1][j - 1] + 1;
            } else {
                dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
            }
        }
    }

    // Backtrack
    let i = originalWords.length;
    let j = modifiedWords.length;

    while (i > 0 || j > 0) {
        if (i > 0 && j > 0 && originalWords[i - 1] === modifiedWords[j - 1]) {
            diff.unshift({ type: 'unchanged', word: originalWords[i - 1] });
            i--;
            j--;
        } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
            diff.unshift({ type: 'added', word: modifiedWords[j - 1] });
            j--;
        } else if (i > 0) {
            diff.unshift({ type: 'removed', word: originalWords[i - 1] });
            i--;
        }
    }

    return diff;
}

function updateDiffView() {
    while (diffView.firstChild) {
        diffView.removeChild(diffView.firstChild);
    }

    if (!lastInputText || !lastOutputText) {
        const placeholder = document.createElement('div');
        placeholder.className = 'diff-placeholder';
        placeholder.textContent = 'Process text to see diff';
        diffView.appendChild(placeholder);
        return;
    }

    const diff = computeDiff(lastInputText, lastOutputText, comparisonMode);
    let removedCount = 0;
    let addedCount = 0;

    diff.forEach(item => {
        if (item.type === 'removed') removedCount++;
        if (item.type === 'added') addedCount++;
    });

    // Comparison mode toggle
    const modeToggle = document.createElement('div');
    modeToggle.className = 'diff-mode-toggle';

    const modeLabel = document.createElement('span');
    modeLabel.className = 'diff-mode-label';
    modeLabel.textContent = 'Compare by:';
    modeToggle.appendChild(modeLabel);

    const charBtn = document.createElement('button');
    charBtn.className = `diff-mode-btn ${comparisonMode === 'char' ? 'active' : ''}`;
    charBtn.dataset.diffMode = 'char';
    charBtn.textContent = 'char';
    charBtn.addEventListener('click', () => {
        comparisonMode = 'char';
        updateDiffView();
    });
    registerHapticTarget(charBtn);
    modeToggle.appendChild(charBtn);

    const wordBtn = document.createElement('button');
    wordBtn.className = `diff-mode-btn ${comparisonMode === 'word' ? 'active' : ''}`;
    wordBtn.dataset.diffMode = 'word';
    wordBtn.textContent = 'word';
    wordBtn.addEventListener('click', () => {
        comparisonMode = 'word';
        updateDiffView();
    });
    registerHapticTarget(wordBtn);
    modeToggle.appendChild(wordBtn);

    // Legend
    const legend = document.createElement('div');
    legend.className = 'diff-legend';

    // Removed item
    const removedItem = document.createElement('span');
    removedItem.className = 'diff-legend-item';
    const removedColor = document.createElement('span');
    removedColor.className = 'diff-legend-color diff-legend-removed';
    const removedLabel = document.createElement('span');
    removedLabel.className = 'diff-legend-label';
    removedLabel.textContent = `Removed (${removedCount})`;
    removedItem.appendChild(removedColor);
    removedItem.appendChild(removedLabel);
    legend.appendChild(removedItem);

    // Added item
    const addedItem = document.createElement('span');
    addedItem.className = 'diff-legend-item';
    const addedColor = document.createElement('span');
    addedColor.className = 'diff-legend-color diff-legend-added';
    const addedLabel = document.createElement('span');
    addedLabel.className = 'diff-legend-label';
    addedLabel.textContent = `Added (${addedCount})`;
    addedItem.appendChild(addedColor);
    addedItem.appendChild(addedLabel);
    legend.appendChild(addedItem);

    // Unchanged item
    const unchangedItem = document.createElement('span');
    unchangedItem.className = 'diff-legend-item';
    const unchangedColor = document.createElement('span');
    unchangedColor.className = 'diff-legend-color diff-legend-unchanged';
    const unchangedLabel = document.createElement('span');
    unchangedLabel.className = 'diff-legend-label';
    unchangedLabel.textContent = 'Unchanged';
    unchangedItem.appendChild(unchangedColor);
    unchangedItem.appendChild(unchangedLabel);
    legend.appendChild(unchangedItem);

    const content = document.createElement('div');
    content.className = 'diff-content';

    diff.forEach(item => {
        const span = document.createElement('span');
        const text = item.char || item.word || '';

        if (text === '\n') {
            const marker = document.createElement('span');
            marker.className = 'newline-marker';
            marker.textContent = '↵';
            span.appendChild(marker);
            span.appendChild(document.createTextNode('\n'));
        } else if (text === ' ') {
            const marker = document.createElement('span');
            marker.className = 'space-marker';
            marker.textContent = '·';
            span.appendChild(marker);
        } else {
            span.textContent = text;
        }

        span.className = `diff-${item.type}`;
        span.title = item.type.charAt(0).toUpperCase() + item.type.slice(1);

        content.appendChild(span);
    });

    diffView.appendChild(modeToggle);
    diffView.appendChild(legend);
    diffView.appendChild(content);
}

console.log('%cCOSMIC TEXT LINTER v2.2.1', 'color:#00d9ff;font-size:20px;font-weight:bold;');
console.log('%cRemove the invisible. Restore the human.', 'color:#8892b0;font-size:12px;');

/**
 * Accent control deck
 */
const DEFAULT_ACCENT = '#00d9ff';

function normalizeHex(value) {
    if (!value) return null;
    let hex = value.trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{3}$/.test(hex)) {
        hex = hex.split('').map(ch => ch + ch).join('');
    }
    if (!/^[0-9a-fA-F]{6}$/.test(hex)) {
        return null;
    }
    return `#${hex.toLowerCase()}`;
}

function parseCssColor(value) {
    if (!value) return null;
    const trimmed = value.trim();
    if (trimmed.startsWith('#')) {
        return normalizeHex(trimmed);
    }
    const match = trimmed.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (match) {
        const [r, g, b] = match.slice(1, 4).map(num => Math.max(0, Math.min(255, parseInt(num, 10))));
        return `#${[r, g, b].map(n => n.toString(16).padStart(2, '0')).join('')}`;
    }
    return null;
}

function hexToHsl(hex) {
    const value = normalizeHex(hex);
    if (!value) return null;
    const bigint = parseInt(value.slice(1), 16);
    const r = ((bigint >> 16) & 255) / 255;
    const g = ((bigint >> 8) & 255) / 255;
    const b = (bigint & 255) / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;
    const delta = max - min;

    if (delta !== 0) {
        s = l > 0.5 ? delta / (2 - max - min) : delta / (max + min);
        switch (max) {
            case r:
                h = (g - b) / delta + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / delta + 2;
                break;
            default:
                h = (r - g) / delta + 4;
        }
        h /= 6;
    }

    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}

function hslToHex(h, s, l) {
    const sat = Math.max(0, Math.min(100, s)) / 100;
    const light = Math.max(0, Math.min(100, l)) / 100;
    const chroma = (1 - Math.abs(2 * light - 1)) * sat;
    const x = chroma * (1 - Math.abs(((h / 60) % 2) - 1));
    const m = light - chroma / 2;
    let r = 0, g = 0, b = 0;

    if (h >= 0 && h < 60) [r, g, b] = [chroma, x, 0];
    else if (h < 120) [r, g, b] = [x, chroma, 0];
    else if (h < 180) [r, g, b] = [0, chroma, x];
    else if (h < 240) [r, g, b] = [0, x, chroma];
    else if (h < 300) [r, g, b] = [x, 0, chroma];
    else [r, g, b] = [chroma, 0, x];

    const toHex = val => Math.round((val + m) * 255).toString(16).padStart(2, '0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
}

function applyAccent(hex, options = {}) {
    const { silent = false } = options;
    const normalized = normalizeHex(hex);
    if (!normalized) {
        if (!silent) {
            showToast('INVALID HEX SIGNAL');
        }
        return false;
    }

    const hsl = hexToHsl(normalized) || { h: 190, s: 100, l: 50 };
    const secondary = hslToHex((hsl.h + 320) % 360, Math.min(100, hsl.s + 10), Math.min(75, hsl.l + 10));
    const dark = hslToHex(hsl.h, Math.min(100, hsl.s + 5), Math.max(15, hsl.l - 35));

    rootStyle.setProperty('--color-accent', normalized);
    rootStyle.setProperty('--color-accent-secondary', secondary);
    rootStyle.setProperty('--color-accent-dark', dark);

    if (accentColorPicker) {
        accentColorPicker.value = normalized;
    }
    if (accentColorHex) {
        accentColorHex.value = normalized.replace('#', '').toUpperCase();
    }

    if (!silent) {
        showToast('NEON VECTOR UPDATED');
    }
    return true;
}

function randomAccent() {
    const hue = Math.floor(Math.random() * 360);
    const saturation = 70 + Math.floor(Math.random() * 25);
    const lightness = 45 + Math.floor(Math.random() * 15);
    return hslToHex(hue, saturation, lightness);
}

const currentAccent = parseCssColor(getComputedStyle(document.documentElement).getPropertyValue('--color-accent')) || DEFAULT_ACCENT;
applyAccent(currentAccent, { silent: true });

if (accentColorPicker) {
    accentColorPicker.addEventListener('input', event => {
        applyAccent(event.target.value, { silent: true });
    });
    accentColorPicker.addEventListener('change', event => {
        applyAccent(event.target.value);
    });
}

if (accentColorHex) {
    accentColorHex.addEventListener('input', event => {
        event.target.value = event.target.value.replace(/[^0-9a-fA-F]/g, '').slice(0, 6).toUpperCase();
    });
    accentColorHex.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            applyAccent(accentColorHex.value);
        }
    });
    accentColorHex.addEventListener('blur', () => {
        if (accentColorHex.value.length === 6) {
            applyAccent(accentColorHex.value);
        }
    });
}

if (accentApply) {
    accentApply.addEventListener('click', () => {
        applyAccent(accentColorHex?.value || currentAccent);
    });
}

if (accentRandom) {
    accentRandom.addEventListener('click', () => {
        const hex = randomAccent();
        applyAccent(hex);
    });
}
