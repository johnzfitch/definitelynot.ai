/**
 * Cosmic Text Linter v2.3.1 - Lintenium Edition
 * Frontend control deck with OverType integration for CLEAN view,
 * diff-match-patch for ORIGINAL/DIFF view, and auto-sanitize pipeline.
 *
 * Architecture:
 * - Left panel: Standard textarea for raw input
 * - Right panel tabs:
 *   - ORIGINAL/DIFF: Custom diff view using diff-match-patch
 *   - CLEAN: OverType markdown preview (read-only)
 *   - REPORT: Detailed security vector breakdown (modal)
 * - Auto-sanitize: 700ms debounced on input/paste
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
const reportBtn = document.getElementById('report-btn');
const reportModal = document.getElementById('report-modal');
const reportModalClose = document.getElementById('report-modal-close');
const reportContent = document.getElementById('report-content');

let isProcessing = false;
let selectedMode = 'safe';
let lastFocused = null;
let toastTimeout;
let outputMode = 'clean'; // Default tab is CLEAN (matches HTML active button)
let lastInputText = '';
let lastOutputText = '';
let lastStats = null;
let comparisonMode = 'char';

// Lintenium OverType & auto-sanitize state
let overtypeEditor = null;
let autoTimer = null;
let lastRequestTime = 0;
const AUTO_DELAY = 700; // ms
const MAX_AUTO_LENGTH = 50000; // chars

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

// ==============================================================
// LINTENIUM INITIALIZATION - OverType for CLEAN view
// ==============================================================

function initializeLintenium() {
  // Initialize OverType for CLEAN view only
  const OT = window.OverType && (window.OverType.default || window.OverType);
  if (OT) {
    try {
      const [editor] = new OT('#output-text', {
        theme: 'cave', // Dark theme matches Cosmic aesthetic
        toolbar: false,
        showStats: false,
        textareaProps: { readonly: true },
        placeholder: 'Cleaned text will appear here...'
      });
      overtypeEditor = editor;

      // Set initial placeholder for CLEAN view
      if (outputMode === 'clean') {
        overtypeEditor.setValue('Process text to see clean output');
      }

      console.log('%c✓ OverType initialized for CLEAN view', 'color:#00d9ff');
    } catch (error) {
      console.error('OverType initialization failed:', error);
    }
  } else {
    console.warn('OverType not loaded');
  }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeLintenium);
} else {
  initializeLintenium();
}

// ==============================================================
// AUTO-SANITIZE PIPELINE - Debounced real-time sanitization
// ==============================================================

function scheduleAutoSanitize() {
  clearTimeout(autoTimer);
  autoTimer = setTimeout(() => {
    const text = inputText.value;

    // Empty state - clear output
    if (!text.trim()) {
      lastInputText = '';
      lastOutputText = '';
      lastStats = null;
      clearStatsAndOutput();
      return;
    }

    // Length safeguard - require manual sanitize for large text
    if (text.length > MAX_AUTO_LENGTH) {
      showToast('Text too large for auto-sanitize. Click SANITIZE button.');
      return;
    }

    // Run sanitization (auto-triggered, not manual)
    runSanitize(text, selectedMode, false);
  }, AUTO_DELAY);
}

function clearStatsAndOutput() {
  updateStats('AWAITING INPUT', 'awaiting');
  advisoryPanel.classList.add('hidden');

  if (outputMode === 'clean' && overtypeEditor) {
    overtypeEditor.setValue('Process text to see clean output');
  } else if (outputMode === 'original') {
    while (diffView.firstChild) {
      diffView.removeChild(diffView.firstChild);
    }
    const placeholder = document.createElement('div');
    placeholder.className = 'diff-placeholder';
    placeholder.textContent = 'Process text to see diff';
    diffView.appendChild(placeholder);
  }

  outputText.value = '';
  outputCount.textContent = formatCounts('');
}

// Shared sanitize function - used by both auto and manual triggers
function runSanitize(text, mode, isManual = false) {
  const requestTime = Date.now();
  lastRequestTime = requestTime;

  // Visual feedback for manual sanitize only
  if (isManual) {
    sanitizeBtn.classList.add('processing');
    sanitizeBtn.querySelector('.btn-text').textContent = 'PROCESSING...';
  }
  updateStats('PROCESSING SIGNAL...', 'pending');
  advisoryPanel.classList.add('hidden');

  fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ text, mode })
  })
  .then(response => {
    if (!response.ok) {
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        return response.json().then(err => {
          throw new Error(err.error || `HTTP ${response.status}`);
        });
      }
      return response.text().then(raw => {
        throw new Error(raw || `HTTP ${response.status}`);
      });
    }

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error('Unexpected response from server');
    }

    return response.json();
  })
  .then(data => {
    // Ignore stale responses
    if (requestTime < lastRequestTime) return;

    // Store results
    lastInputText = text;
    lastOutputText = data.text;
    lastStats = data.stats;

    // Update UI
    outputText.value = data.text;
    outputCount.textContent = formatCounts(data.text || '');
    displayStats(data.stats);
    displayAdvisories(data.stats?.advisories || {});

    // Render based on active tab
    if (outputMode === 'original') {
      updateDiffView();
    } else if (outputMode === 'clean') {
      renderCleanView();
    }

    if (isManual) {
      showToast('TRANSMISSION CLEAN');
    }
  })
  .catch(error => {
    // Ignore stale errors
    if (requestTime < lastRequestTime) return;

    console.error('Sanitization failed:', error);
    showToast('TRANSMISSION ERROR');
    updateStats('ERROR: SIGNAL LOST', 'error');
  })
  .finally(() => {
    if (isManual) {
      isProcessing = false;
      sanitizeBtn.classList.remove('processing');
      sanitizeBtn.querySelector('.btn-text').textContent = 'SANITIZE TEXT';
    }
  });
}

function renderCleanView() {
  if (!overtypeEditor) {
    // Fallback: show textarea, hide OverType container if present
    outputText.value = lastOutputText || '';
    outputText.style.display = '';
    const overtypeContainer = document.querySelector('.overtype-container, .overtype-wrapper, [data-overtype]');
    if (overtypeContainer) overtypeContainer.style.display = 'none';
    return;
  }

  // OverType is available: use it for CLEAN view
  overtypeEditor.setValue(lastOutputText || '');
  overtypeEditor.showPreviewMode(); // Read-only preview with clickable links
}

// Wire auto-sanitize to input events
inputText.addEventListener('input', () => {
  inputCount.textContent = formatCounts(inputText.value);
  scheduleAutoSanitize();
});

// Paste event with immediate length check (before debounce)
inputText.addEventListener('paste', (event) => {
  // Use setTimeout to get the pasted text after it's inserted
  setTimeout(() => {
    const text = inputText.value;
    if (text.length > MAX_AUTO_LENGTH) {
      showToast('Text too large for auto-sanitize. Click SANITIZE button.');
      // Don't schedule auto-sanitize for large pastes
      return;
    }
    scheduleAutoSanitize();
  }, 0);
});

modeButtons.forEach(button => {
  button.addEventListener('click', () => {
    modeButtons.forEach(btn => {
      btn.classList.remove('active');
      btn.setAttribute('aria-pressed', 'false');
    });
    button.classList.add('active');
    button.setAttribute('aria-pressed', 'true');
    selectedMode = button.dataset.mode;
    scheduleAutoSanitize(); // Re-sanitize with new mode
  });
});

// Manual SANITIZE button - calls shared runSanitize function
sanitizeBtn.addEventListener('click', () => {
  if (isProcessing) return;

  const text = inputText.value;
  if (!text.trim()) {
    showToast('INPUT REQUIRED');
    return;
  }

  isProcessing = true;
  runSanitize(text, selectedMode, true);
});

// Copy button - ALWAYS copies sanitized text (lastOutputText)
copyBtn.addEventListener('click', async () => {
  const text = lastOutputText || outputText.value;
  if (!text) {
    showToast('NO DATA TO COPY');
    return;
  }

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      showToast('CLEAN TEXT COPIED');
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
    showToast('CLEAN TEXT COPIED');
  } catch (error) {
    console.error('Copy failed:', error);
    showToast('COPY FAILED');
  }
});

function createStatItem(label, value, statusAttr = null) {
  const item = document.createElement('div');
  item.className = 'stat-item';

  const labelSpan = document.createElement('span');
  labelSpan.className = 'stat-label';
  labelSpan.textContent = label;

  const valueSpan = document.createElement('span');
  valueSpan.className = 'stat-value';
  valueSpan.textContent = value;
  if (statusAttr) {
    valueSpan.id = 'status';
    valueSpan.setAttribute('data-status', statusAttr);
  }

  item.appendChild(labelSpan);
  item.appendChild(valueSpan);
  return item;
}

function displayStats(stats = {}) {
  const delta = stats.characters_removed ?? 0;
  const deltaSign = delta > 0 ? '-' : delta < 0 ? '+' : '';
  const deltaAbs = Math.abs(delta);
  const statusLabel = (stats.status_label || stats.status || 'CLEAN').toString().toUpperCase();
  const statusState = stats.status_state || (statusLabel.includes('ERROR') ? 'error' : 'complete');

  statsDisplay.textContent = '';
  statsDisplay.appendChild(createStatItem('SIGNAL STATUS', statusLabel, statusState));
  statsDisplay.appendChild(createStatItem('MODE', (stats.mode || '').toUpperCase()));
  statsDisplay.appendChild(createStatItem('INVISIBLES REMOVED', String(stats.invisibles_removed ?? 0)));
  statsDisplay.appendChild(createStatItem('HOMOGLYPHS', String(stats.homoglyphs_normalized ?? 0)));
  statsDisplay.appendChild(createStatItem('TOTAL DELTA', `${deltaSign}${deltaAbs.toLocaleString()}`));

  if ((stats.digits_normalized ?? 0) > 0) {
    statsDisplay.appendChild(createStatItem('DIGITS NORMALIZED', String(stats.digits_normalized)));
  }
}

function updateStats(message, status = 'pending') {
  const statusMap = {
    pending: 'processing',
    success: 'complete',
    error: 'error'
  };
  const normalizedStatus = statusMap[status] || 'awaiting';
  statsDisplay.textContent = '';
  statsDisplay.appendChild(createStatItem('SIGNAL STATUS', message, normalizedStatus));
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
    advisoryList.textContent = '';
    return;
  }

  advisoryList.textContent = '';
  active.forEach(msg => {
    const item = document.createElement('div');
    item.className = 'advisory-item';

    const bullet = document.createElement('span');
    bullet.className = 'advisory-bullet';
    bullet.setAttribute('aria-hidden', 'true');
    bullet.textContent = '▸';

    const text = document.createElement('span');
    text.className = 'advisory-text';
    text.textContent = msg;

    item.appendChild(bullet);
    item.appendChild(text);
    advisoryList.appendChild(item);
  });

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
    } else if (!reportModal.classList.contains('hidden')) {
      teardownReportModal();
    }
  }
});

// ==============================================================
// FULL REPORT MODAL - Detailed security vector breakdown
// ==============================================================

function buildReportDom(stats, original, sanitized) {
  const container = document.createElement('div');

  if (!stats) {
    const emptyMsg = document.createElement('p');
    emptyMsg.className = 'report-empty';
    emptyMsg.textContent = 'No sanitization run yet. Process text to generate a security report.';
    container.appendChild(emptyMsg);
    return container;
  }

  const delta = (original?.length || 0) - (sanitized?.length || 0);

  // Advisory labels matching help modal
  const advisoryLabels = {
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

  // Summary section
  const summarySection = document.createElement('div');
  summarySection.className = 'report-section';

  const summaryTitle = document.createElement('h3');
  summaryTitle.textContent = 'Summary';
  summarySection.appendChild(summaryTitle);

  const summaryTable = document.createElement('table');
  summaryTable.className = 'report-table';
  const tbody = document.createElement('tbody');

  const summaryData = [
    ['Characters removed', delta > 0 ? String(delta) : (delta < 0 ? String(delta) : '0')],
    ['Invisibles removed', String(stats.invisibles_removed || 0)],
    ['Homoglyphs normalized', String(stats.homoglyphs_normalized || 0)],
    ['Digits normalized', String(stats.digits_normalized || 0)],
    ['Mode used', (stats.mode || 'safe').toUpperCase()]
  ];

  summaryData.forEach(([label, value]) => {
    const row = document.createElement('tr');
    const th = document.createElement('th');
    th.textContent = label;
    const td = document.createElement('td');
    td.textContent = value;
    row.appendChild(th);
    row.appendChild(td);
    tbody.appendChild(row);
  });

  summaryTable.appendChild(tbody);
  summarySection.appendChild(summaryTable);
  container.appendChild(summarySection);

  // Security vectors section
  const vectorsSection = document.createElement('div');
  vectorsSection.className = 'report-section';

  const vectorsTitle = document.createElement('h3');
  vectorsTitle.textContent = 'Security Vectors Detected';
  vectorsSection.appendChild(vectorsTitle);

  const advisories = stats.advisories || {};
  const activeAdvisories = Object.entries(advisories).filter(([, value]) => value === true);

  if (activeAdvisories.length > 0) {
    const activeList = document.createElement('ul');
    activeList.className = 'report-advisories';

    activeAdvisories.forEach(([key]) => {
      const li = document.createElement('li');
      li.className = 'report-advisory-active';

      const bullet = document.createElement('span');
      bullet.className = 'report-bullet';
      bullet.textContent = '▸';

      const text = document.createTextNode(advisoryLabels[key] || key);

      li.appendChild(bullet);
      li.appendChild(text);
      activeList.appendChild(li);
    });

    vectorsSection.appendChild(activeList);
  } else {
    const note = document.createElement('p');
    note.className = 'report-note';
    note.textContent = 'No security issues detected.';
    vectorsSection.appendChild(note);
  }

  container.appendChild(vectorsSection);

  // Not detected section
  const inactiveAdvisories = Object.entries(advisories).filter(([, value]) => value === false);

  if (inactiveAdvisories.length > 0) {
    const inactiveSection = document.createElement('div');
    inactiveSection.className = 'report-section';

    const inactiveTitle = document.createElement('h3');
    inactiveTitle.textContent = 'Not Detected';
    inactiveSection.appendChild(inactiveTitle);

    const inactiveList = document.createElement('ul');
    inactiveList.className = 'report-advisories';

    inactiveAdvisories.forEach(([key]) => {
      const li = document.createElement('li');
      li.className = 'report-advisory-inactive';

      const bullet = document.createElement('span');
      bullet.className = 'report-bullet';
      bullet.textContent = '○';

      const text = document.createTextNode(advisoryLabels[key] || key);

      li.appendChild(bullet);
      li.appendChild(text);
      inactiveList.appendChild(li);
    });

    inactiveSection.appendChild(inactiveList);
    container.appendChild(inactiveSection);
  }

  return container;
}

function setupReportModal() {
  // Clear and rebuild report content using safe DOM methods
  reportContent.textContent = '';
  const reportDom = buildReportDom(lastStats, lastInputText, lastOutputText);
  reportContent.appendChild(reportDom);

  if (reportModal.classList.contains('hidden')) {
    reportModal.classList.remove('hidden');
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

  const focusables = reportModal.querySelectorAll(
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

  reportModal.addEventListener('keydown', trap);
  reportModal.dataset.trap = 'true';
  reportModal._trapHandler = trap;

  if (first) {
    first.focus();
  }
}

function teardownReportModal() {
  document.body.classList.remove('modal-open');
  if (appContainer) {
    appContainer.removeAttribute('aria-hidden');
    try {
      appContainer.inert = false;
    } catch (error) {
      console.debug('inert unsupported', error);
    }
  }
  if (reportModal._trapHandler) {
    reportModal.removeEventListener('keydown', reportModal._trapHandler);
    delete reportModal._trapHandler;
  }
  reportModal.classList.add('hidden');
  if (lastFocused && typeof lastFocused.focus === 'function') {
    lastFocused.focus();
  }
}

reportBtn.addEventListener('click', () => {
  setupReportModal();
});

reportModalClose.addEventListener('click', () => {
  teardownReportModal();
});

reportModal.addEventListener('click', event => {
  if (event.target === reportModal) {
    teardownReportModal();
  }
});

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

// Output tab switching - ORIGINAL/DIFF vs CLEAN
outputModeBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    const mode = btn.dataset.outputMode;
    if (mode === outputMode) return; // Already active

    // Update active states
    outputModeBtns.forEach(b => {
      const isActive = b === btn;
      b.classList.toggle('active', isActive);
      b.setAttribute('aria-pressed', String(isActive));
    });

    outputMode = mode;

    // Show/hide appropriate views
    if (outputMode === 'original') {
      // Show diff view, hide OverType
      outputText.classList.add('hidden');
      diffView.classList.remove('hidden');
      updateDiffView();
    } else if (outputMode === 'clean') {
      // Show OverType, hide diff view
      outputText.classList.remove('hidden');
      diffView.classList.add('hidden');
      renderCleanView();
    }
  });
});

function computeDiff(original, modified, mode = 'char') {
  if (mode === 'word') {
    return computeWordDiff(original, modified);
  }
  return computeCharDiff(original, modified);
}

function computeCharDiff(original, modified) {
  const diff = [];
  const dp = [];

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
  modeToggle.appendChild(charBtn);

  const wordBtn = document.createElement('button');
  wordBtn.className = `diff-mode-btn ${comparisonMode === 'word' ? 'active' : ''}`;
  wordBtn.dataset.diffMode = 'word';
  wordBtn.textContent = 'word';
  wordBtn.addEventListener('click', () => {
    comparisonMode = 'word';
    updateDiffView();
  });
  modeToggle.appendChild(wordBtn);

  const legend = document.createElement('div');
  legend.className = 'diff-legend';

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

console.log('%cCOSMIC TEXT LINTER v2.3.1', 'color:#00d9ff;font-size:20px;font-weight:bold;');
console.log('%cRemove the invisible. Restore the human.', 'color:#8892b0;font-size:12px;');

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
