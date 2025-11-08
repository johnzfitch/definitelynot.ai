# Cosmic Text Linter Documentation Hub

This directory aggregates guides for contributors, integrators, and operators. Documentation is version-aware so teams can publish multiple variants (e.g., stable vs experimental) by branching from `main`.

## Quick Start Map

| Audience | Start Here | Why |
| --- | --- | --- |
| New contributor | [Project Overview](overview.md) | Understand goals, personas, and how the UI/API/engine fit together. |
| Architect / Lead | [Architecture Guide](architecture.md) | Visualize component boundaries, data contracts, and pipeline passes. |
| Integrator | [API Reference](api.md) | Implement REST clients with knowledge of advisories and error handling. |
| Maintainer | [Implementation Notes](implementation.md) | Dive into PHP/JS internals and release process guidance. |
| Release manager | [Version Branch Playbook](version-branches.md) | Publish separate documentation tracks using git branches. |

## Keeping Docs Current

- Update relevant guides whenever you add an advisory, sanitization pass, or UI affordance.
- Include diagrams (Mermaid or ASCII) for new flows or components.
- Reference code paths directly (e.g., `api/TextLinter.php`, `assets/js/script.js`) to ease navigation.
- Run markdown linting or preview in GitHub to verify formatting.

## Suggested Contributions

- Add usage walkthroughs for new integrations (CI/CD plugins, CMS extensions).
- Document troubleshooting steps when PHP extensions or ICU features are missing.
- Capture performance benchmarks if you optimize the sanitization pipeline.

Treat this directory as the source of truth—any production-facing change should include a documentation update.
