# Version Branch Playbook

Cosmic Text Linter maintains documentation variants via dedicated git branches. Follow this checklist when publishing or updating docs so stakeholders can consume the right guidance.

## Branch Strategy

| Branch | Purpose | Contents |
| --- | --- | --- |
| `docs/stable` | Matches the production release. | Polished guides that mirror the shipped sanitizer behavior. |
| `docs/next` | Tracks upcoming changes on `main`. | Previews experimental advisories, UI tweaks, or pipeline additions. |

> Both branches start from `main`. Keep their history linear by rebasing on the latest release before publishing.

## Creating or Refreshing Branches

```bash
git checkout main
git pull origin main

git checkout -B docs/stable
# Update docs for production release
# commit changes
git push origin docs/stable --force-with-lease

git checkout main

git checkout -B docs/next
# Document upcoming features
git push origin docs/next --force-with-lease
```

- Use `--force-with-lease` to avoid overwriting teammate work unintentionally.
- Tag documentation releases (e.g., `docs-v2.3.0`) so downstream sites can pin content.

## Removing Deprecated PRs

When a documentation PR becomes obsolete:

1. Close the PR on your hosting platform (GitHub, GitLab, etc.).
2. Delete the remote branch if it is no longer needed: `git push origin --delete docs/<name>`.
3. Reference this playbook in the closing comment so reviewers know the docs moved to versioned branches.

## Maintaining Parity

- After merging feature work into `main`, update `docs/next` immediately.
- Once the feature ships, cherry-pick or merge documentation updates into `docs/stable`.
- Keep `docs/overview.md` and `docs/api.md` in sync across branches to avoid confusing integrators.

## Automation Ideas

- Use CI to publish static documentation for each branch (e.g., GitHub Pages `docs/stable` → `/docs`, `docs/next` → `/docs/next`).
- Add a badge to the root `README.md` linking to both documentation branches.

Following this playbook ensures your team can iterate quickly without confusing partners who rely on stable documentation.
