---
name: forklift-issue-picker
description: Use when working on Forklift issues in MxIxx format — picking the next issue from open GitHub issues ordered by milestone then issue number, creating feature branches, PRs, handling CI and reviews.
---

# Forklift Issue Picker

## Overview

Issues are labelled `MxIxx` where `Mx` = milestone number and `Ixx` = issue number. The skill has two modes:

**Auto-pick (default):** Automatically selects the open issue with the **lowest milestone number**, then the **lowest issue number** within that milestone.

**List mode:** When called with `list` argument, displays all open issues in a table ordered by milestone then issue number, and lets the user pick which one to work on.

## Workflow

1. **Clean main** → fetch & pull
2. **Pick issue** → lowest milestone, then lowest issue number
3. **Feature branch** → `feature/m<M>i<I>-<slug>`
4. **Implement** → code + tests + `composer lint && composer test`
5. **Push + PR** → `gh pr create` with summary + acceptance checklist + `Closes #N`
6. **CI green?** → no: fix and re-push. yes: use **`/review-pr`** command (dispatches forklift-review as subagent)
7. **Wait for human approval** → check that someone OTHER than the PR author has approved. If not, ask the user to review.
8. **Squash merge** → `gh pr merge --squash`
9. **Loop** → back to clean main

## Steps

### 1. Start from clean main
```bash
git fetch origin
git checkout main
git pull origin main
```

### 2. Pick the next issue

**Auto-pick mode (default):** Picks the lowest milestone + lowest issue number automatically.

```bash
gh issue list --state open --limit 100 --json number,title,milestone --jq '
  sort_by(.milestone.number, .number) | .[0] | {number, title}
'
```

**List mode (`list` argument):** Displays all open issues and lets the user pick.

```bash
gh issue list --state open --limit 100 --json number,title,milestone --jq '
  sort_by(.milestone.number, .number) |
  ["#", "M", "Title"],
  (.[] | [.number, "M\(.milestone.number)", .title]) |
  @tsv
' | column -t -s $'\t'
```

Present the table to the user and ask which issue number to work on. Then proceed with that issue.

### 3. Create feature branch
```bash
git checkout -b feature/m<M>i<I>-<short-kebab-description>
```

### 4. Implement
- Read the issue body and any referenced milestone docs in `docs/superpowers/`
- Write code + unit tests
- Run checks:
  ```bash
  composer lint && composer test
  ```

### 5. Push and create PR
```bash
git push -u origin <branch>

gh pr create \
  --title "M<M>I<I>: <issue title>" \
  --body "## Summary\n\n...\n\nCloses #<N>"
```

### 6. CI and reviews

```bash
gh pr checks --watch
```
- Fix CI failures on the branch
- Once CI is green, dispatch **`/review-pr <PR-number>`** command using the Task tool with `forklift-review` subagent. The subagent runs 4 parallel stages (code smells, security, issue compliance, follow-ups), posts inline PR comments, and iterates until all findings are resolved.
- **Do NOT merge before the review completes** — the review is the gatekeeper

### 7. Wait for human approval (guard)

**Do NOT merge without human review.** The forklift-review skill run by the code author does NOT count as approval — only a review from another person (the user or another contributor) counts.

Before proceeding to merge, verify external approval:
```bash
gh api repos/crazy-goat/forklift/pulls/<N>/reviews --jq '
  [.[] | select(.state == "APPROVED" and .author.login != "crazy-goat[bot]")]
  | if length > 0 then "APPROVED by \(.[0].author.login)" else "NO_EXTERNAL_APPROVAL" end
'
```

If the result is `NO_EXTERNAL_APPROVAL`, stop and ask the user to review the PR. Do NOT merge.

### 8. Merge (after human approval)
```bash
gh pr merge --squash --subject "M<M>I<I>: <title>"
```

### 9. Loop
```bash
git checkout main && git pull origin main
# → back to step 1
```

## `/review-pr` command

Dispatches forklift-review as a subagent for a PR with the given number.

```
/review-pr <PR-number>
```

**Usage:** `Task` tool, `forklift-review` subagent. The PR number MUST be passed in the prompt:
```
Task(
  subagent_type: "forklift-review",
  prompt: "Review PR #<N> using forklift-review skill"
)
```

The subagent loads the forklift-review skill, fetches the PR diff + linked issue via `gh pr view <N>` and `gh pr diff <N>`, runs 4 parallel stages (code smells, security, issue compliance, follow-ups), posts inline PR comments, and iterates up to 3 rounds until all findings are resolved or explained.

## PR conventions

| Field | Format |
|-------|--------|
| Title | `M<M>I<I>: <issue title>` |
| Branch | `feature/m<M>i<I>-<kebab-slug>` |
| Body | Summary table + acceptance criteria checklist + `Closes #N` |
| Merge | Squash, subject same as PR title |

## Common pitfalls

- Forgetting to push the branch before creating PR
- Leaving uncommitted changes when creating PR
- Not watching CI after pushing fixes
- Branch name not matching the issue it solves
- **Merging without external review** — always wait for approval from someone OTHER than the author. Review via `/review-pr` does NOT count as human approval.
- **Checking `gh pr view --json reviews` before merge** — always verify an external person approved before running `gh pr merge`
- **Typos in YAML variable references** — double-check `${{ matrix.key }}` matches the actual key name (singular vs plural)
