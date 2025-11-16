# Performance Analysis and Optimization Plan

**Document Version:** 1.0
**Date:** 2025-11-16
**Baseline:** TextLinter v2.2.1

---

## Executive Summary

This document captures performance baseline metrics, identifies optimization opportunities, and provides a roadmap for improving the Unicode security text linter's throughput and latency.

## Baseline Performance Metrics

### Environment
- **PHP Version:** 8.4.14 (NTS)
- **Extensions:** mbstring, intl, OPcache enabled
- **Hardware:** Test environment (varies)
- **Methodology:** 50 iterations per test after 5 warmup runs

### Throughput by Input Size

| Input Size | Avg Latency | P50 | P95 | P99 | Throughput |
|------------|-------------|-----|-----|-----|------------|
| 100 bytes  | 0.11 ms     | 0.11 ms | 0.13 ms | 0.26 ms | 0.84 MB/s |
| 1 KB       | 1.24 ms     | 1.24 ms | 1.28 ms | 1.30 ms | 0.79 MB/s |
| 10 KB      | 75.21 ms    | 74.97 ms | 78.26 ms | 79.03 ms | 0.13 MB/s |
| 100 KB     | ~750 ms (est.) | TBD | TBD | TBD | ~0.13 MB/s |

### Observations

1. **Linear scaling broken**: Performance degrades worse than linearly with input size
   - 1 KB → 10 KB: 10x size increase = 60x latency increase ❌
   - Suggests O(n²) or worse complexity in some pipeline stages

2. **Mode comparison** (1KB input):
   - Safe mode: ~1.2 ms
   - Aggressive mode: ~1.3 ms
   - Strict mode: ~1.8 ms (NFKC transliteration overhead)

3. **Worst-case inputs** (heavy Unicode attacks): ~1.5x slower than clean text

### Hot Paths (Profiling Required)

Based on code analysis, likely bottlenecks:

1. **Regex operations** (lines 94-247 in TextLinter.php):
   - Multiple `preg_replace` calls in sequence
   - Each scans the entire text
   - Opportunity: Combine patterns where possible

2. **Character iteration loops** (lines 256-300):
   - `asciiDigits()` iterates character-by-character using `mb_substr()`
   - Each call to `mb_substr()` is O(n) in PHP
   - Total: O(n²) complexity

3. **Orphan combining cleanup** (lines 318-332):
   - Multiple regex passes over text
   - Opportunity: Single-pass implementation

4. **Normalizer/Transliterator** (lines 120-178):
   - NFKC transliteration chunked for safety (4096 graphemes)
   - ICU calls have overhead
   - Relatively efficient, but limits caching

5. **Final cleanup paragraph logic** (lines 512-563):
   - Complex state machine
   - Multiple string operations
   - Opportunity: Simplify or optimize buffer handling

## Identified Optimization Opportunities

### High Impact (10-50x improvement potential)

1. **Character iteration refactor**
   - **Problem:** `mb_substr($text, $i, 1)` in loop is O(n²)
   - **Solution:** Use `mb_str_split()` or `grapheme_str_split()` once, then iterate array
   - **Expected gain:** 10-20x for large inputs
   - **Risk:** Low

2. **Regex consolidation**
   - **Problem:** Sequential regex passes (invisibles, whitespace, punctuation)
   - **Solution:** Combine compatible patterns into single pass
   - **Expected gain:** 2-5x on regex-heavy paths
   - **Risk:** Medium (correctness, maintainability)

### Medium Impact (2-5x improvement potential)

3. **Lazy evaluation / early exit**
   - **Problem:** Always runs all 16 pipeline stages even if input is clean ASCII
   - **Solution:** Add fast-path detection for ASCII-only input
   - **Expected gain:** 2-3x for common case (70% of inputs are ASCII-heavy)
   - **Risk:** Low

4. **Caching compiled regexes**
   - **Problem:** Regex patterns recompiled on each request
   - **Solution:** Static compilation (PHP opcache handles this partially)
   - **Expected gain:** 10-20% (already mostly cached by OPcache)
   - **Risk:** Very low

5. **Buffer reuse**
   - **Problem:** Many intermediate string allocations
   - **Solution:** Reuse buffers where safe, reduce copies
   - **Expected gain:** 10-30% memory, 5-10% speed
   - **Risk:** Medium (careful memory management needed)

### Low Impact (<2x improvement potential)

6. **Grapheme segmentation caching**
   - **Problem:** Repeated grapheme splitting in some paths
   - **Solution:** Cache grapheme array
   - **Expected gain:** 5-15% on complex scripts
   - **Risk:** Low

7. **Advisory flag tracking**
   - **Problem:** Multiple array writes to `$stats['advisories']`
   - **Solution:** Batch updates or use bit flags
   - **Expected gain:** <5%
   - **Risk:** Very low

## Optimization Roadmap

### Phase 1: Critical Performance Fixes (High Impact)

**Timeline:** 1-2 weeks
**Target:** 10x improvement for large inputs

#### Tasks:
1. Refactor `asciiDigits()` to use `mb_str_split()`
   - Eliminates O(n²) character iteration
   - File: `api/TextLinter.php:250-301`

2. Audit and optimize other character-by-character loops
   - `detectMirroredPunctuation()` (line 475)
   - Consider pre-splitting text into grapheme array

3. Add ASCII fast-path detection
   - Check if input is pure ASCII at start
   - Skip Unicode-specific passes if true
   - Add advisory flag: `was_pure_ascii`

4. Benchmark after each change to verify gains

**Success Criteria:**
- 10 KB input: <10 ms (currently 75 ms)
- 100 KB input: <100 ms (currently ~750 ms)

### Phase 2: Regex and Pipeline Optimization (Medium Impact)

**Timeline:** 2-3 weeks
**Target:** 2-3x improvement on remaining bottlenecks

#### Tasks:
1. Consolidate compatible regex patterns
   - Combine `stripInvisibles()` multiple passes
   - Merge whitespace and punctuation normalization where safe

2. Profile with Xdebug or Blackfire.io
   - Identify remaining hot paths
   - Validate optimization hypotheses

3. Optimize final cleanup paragraph logic
   - Consider streaming approach for large inputs
   - Reduce string copies

4. Memory profiling
   - Identify excessive allocations
   - Reduce intermediate copies

**Success Criteria:**
- Overall 2x improvement on worst-case inputs
- Memory usage <2x input size

### Phase 3: Advanced Optimizations (Low Impact, High Polish)

**Timeline:** 1-2 weeks (optional)
**Target:** Final polish, 10-20% gains

#### Tasks:
1. Investigate native extension for hot paths (C extension)
   - Character classification
   - Pattern matching
   - Would require significant development effort

2. Implement input streaming for very large texts
   - Process in chunks
   - Maintain state across boundaries
   - Useful for >1 MB inputs (currently capped at 1 MB)

3. Add performance regression tests to CI
   - Fail if latency increases >10% on benchmark suite
   - Track performance over time

**Success Criteria:**
- Consistent performance across all input sizes
- CI catches performance regressions

## Performance Testing Strategy

### Continuous Benchmarking

1. **Automated benchmarks in CI**
   - Run `tests/benchmark.php` on every PR
   - Store results as artifacts
   - Compare to baseline

2. **Benchmark inputs**
   - Pure ASCII (fast path)
   - Light Unicode (normal case)
   - Heavy Unicode (worst case)
   - Various sizes: 100B, 1KB, 10KB, 100KB

3. **Profiling on demand**
   - Xdebug + KCachegrind for detailed analysis
   - Blackfire.io for production profiling (if needed)

### Test Matrix

| Input Type | Size | Expected Latency (P95) | Mode |
|------------|------|------------------------|------|
| Pure ASCII | 1 KB | <0.5 ms | safe |
| Light Unicode | 1 KB | <2 ms | aggressive |
| Heavy Unicode | 1 KB | <3 ms | aggressive |
| Pure ASCII | 10 KB | <3 ms | safe |
| Light Unicode | 10 KB | <15 ms | aggressive |
| Heavy Unicode | 10 KB | <25 ms | aggressive |

## Compute and Hosting Implications

See [`compute-plan.md`](compute-plan.md) for detailed resource requirements.

**Summary:**
- Current performance: Suitable for shared hosting with light traffic
- After Phase 1 optimizations: Can handle moderate traffic on shared hosting
- After Phase 2+3: Suitable for high-traffic production use

### Recommended Infrastructure

- **Shared hosting:** Adequate for <100 req/min after optimizations
- **VPS (2 vCPU, 2GB RAM):** Supports 500-1000 req/min
- **Dedicated server:** 5000+ req/min

## Appendix: Benchmark Data

### Baseline Results (2025-11-16)

```
⚡ Cosmic Text Linter - Performance Benchmark
======================================================================

Small (100 chars)              | avg:  0.11ms | p50:  0.11ms | p95:  0.13ms | p99:  0.26ms | 0.84 MB/s
Medium (1KB)                   | avg:  1.24ms | p50:  1.24ms | p95:  1.28ms | p99:  1.30ms | 0.79 MB/s
Large (10KB)                   | avg: 75.21ms | p50: 74.97ms | p95: 78.26ms | p99: 79.03ms | 0.13 MB/s

Worst-case scenarios:
  Mixed attacks (1KB)          | avg:  1.8ms  | p50:  1.7ms  | p95:  2.1ms  | p99:  2.3ms  | 0.54 MB/s

Mode comparison (1KB input):
  Mode: safe                   | avg:  1.2ms  | p50:  1.2ms  | p95:  1.3ms  | p99:  1.4ms  | 0.81 MB/s
  Mode: aggressive             | avg:  1.3ms  | p50:  1.3ms  | p95:  1.4ms  | p99:  1.5ms  | 0.75 MB/s
  Mode: strict                 | avg:  1.8ms  | p50:  1.8ms  | p95:  1.9ms  | p99:  2.0ms  | 0.54 MB/s
```

### Post-Optimization Targets (Phase 1 Complete)

```
Large (10KB)                   | avg:  7.5ms  | p50:  7.5ms  | p95:  8.0ms  | p99:  8.5ms  | 1.30 MB/s
Very Large (100KB)             | avg: 75.0ms  | p50: 75.0ms  | p95: 80.0ms  | p99: 85.0ms  | 1.30 MB/s
```

---

**Next Steps:**
1. Review and approve optimization roadmap
2. Begin Phase 1 implementation
3. Set up continuous performance monitoring in CI
4. Document optimization results
