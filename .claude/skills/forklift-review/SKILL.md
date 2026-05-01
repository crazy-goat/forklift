---
name: forklift-review
description: Use when reviewing Forklift GitHub PRs — run parallel stages (code smells, security/leaks, issue/plan compliance, follow-up observations) against the PR diff only, post inline review comments, and iterate until all findings are resolved or explained.
---

# Forklift Review

## Overview

Iterative, multi-stage code review scoped strictly to the PR diff. Four parallel analysis stages produce findings with severity labels. Non-follow-up findings are posted as inline PR review comments. The review repeats until every finding is either fixed or explained by the author.

## Workflow

1. **Fetch PR diff + metadata** — `gh pr diff`, linked issue body, head commit SHA
2. **Run 4 stages in parallel** — each returns `{severity, file, line, title, body}`
3. **Collect findings, assign severity** — BLOCKER/HIGH/MEDIUM/LOW/INFO
4. **Post inline comments** — non-follow-up findings as `gh api` review with inline threads
5. **Author fixes or explains** — push new commits or reply in threads
6. **Re-check unresolved** — re-fetch diff, check only open threads
7. **Still open?** → provide more detailed description per thread, go back to step 5
8. **All resolved** → approve

## Review Stages

Dispatch all four stages in parallel. Each returns structured findings with `{severity, file, line, title, body}`.

### 1. Code Smells & Logic Errors

- Dead code, unreachable branches, redundant conditions
- Incorrect type handling, null safety, edge cases
- Race conditions, ordering dependencies, state mutations
- Overly complex expressions, missing early returns
- Misleading names, inconsistent abstractions

### 2. Security & Leaks

- Unsanitized input, SQL/command injection vectors
- Secrets, tokens, passwords, API keys in code or logs
- PII/email addresses in log statements, error messages, comments
- Missing authorization checks, overly permissive defaults
- Insecure crypto (MD5, SHA1 for passwords, weak random seeds)

### 3. Issue & Plan Compliance

- Does the implementation match the linked issue description?
- Are all acceptance criteria satisfied?
- Does it match any referenced milestone docs in `docs/superpowers/`?
- Missing test coverage for stated requirements?
- Did Rector/lint/static analysis produce unintended side effects?

### 4. Follow-up Observations (info only, no inline comments)

- Performance concerns worth tracking in a separate issue
- Refactoring opportunities outside PR scope
- Documentation gaps or missing inline comments
- Patterns that could be extracted for reuse
- Better idioms available in the target PHP version

**Follow-up findings:** Present to the author in the review summary body only — do NOT post as inline comments. Suggest creating separate GitHub issues if significant.

## Severity Labels

| Level | When to use |
|-------|-------------|
| **BLOCKER** | Security vulnerability, data loss risk, PII leak |
| **HIGH** | Logic bug with user-visible impact, broken acceptance criterion |
| **MEDIUM** | Code smell likely to cause bugs, fragile assumption |
| **LOW** | Style, naming, missing guard for edge case |
| **INFO** | Follow-up only — observation or suggestion |

## Posting Inline Comments

Use `gh api` to create a single review with all non-follow-up findings as inline comments:

```bash
gh api repos/<owner>/<repo>/pulls/<N>/reviews --input - <<'PAYLOAD'
{
  "commit_id": "<head-sha>",
  "event": "COMMENT",
  "body": "<review summary with table, follow-ups listed here>",
  "comments": [
    {
      "path": "src/Foo.php",
      "position": 12,
      "body": "**MEDIUM** — <technical description>\n\n**Why:** <rationale>\n\n**Suggestion:**\n```php\n<fix>\n```"
    }
  ]
}
PAYLOAD
```

**Position** is the 1-indexed line number in the unified diff for that file (first line after the `@@` hunk header is position 1).

Before posting, get the head commit SHA: `gh pr view <N> --json headRefOid -q .headRefOid`

**Comment format:**
- Start with severity: `**BLOCKER**`, `**HIGH**`, `**MEDIUM**`, `**LOW**`
- Describe the problem technically and precisely
- Include "Why" section with the specific risk or violation
- Include "Suggestion" with a concrete code fix (diff or example)
- All in English

## Iteration Rules

### First round
- Run all 4 stages, post all non-follow-up findings

### Subsequent rounds (N+1)
- Re-fetch the PR diff (`gh pr diff <N>`)
- Check ONLY the threads that are still unresolved
- If the author **fixed** the code → mark thread resolved, acknowledge
- If the author **explained** the reasoning and it's valid → resolve, note the explanation
- If the finding is **still present** with no fix or explanation → provide a **more detailed** description:
  - Quote the exact lines still affected
  - Explain the impact more concretely (what breaks, when, under what conditions)
  - Offer an alternative approach if the first suggestion was rejected

### Stopping conditions
- All review threads resolved (fixed or accepted explanation) → **approve**
- Author explicitly dismisses a finding with reasoning → **accept** (the author owns the code)
- Do NOT loop indefinitely — after 3 rounds, escalate unresolved blockers to a summary comment

## Pre-review Steps

1. `gh pr view <N> --json title,url,state,baseRefName,headRefName,number`
2. `gh pr diff <N>` — capture the full unified diff
3. `gh pr view <N> --json body` — extract linked issue number (`Closes #X` or `MxIxx` in title)
4. If an issue reference is found, fetch the issue body for compliance checking:
   ```bash
   gh issue view <issue-number> --json title,body,milestone
   ```

## Review Output Format

### Summary comment (review body)

```markdown
## Review Summary — Round N

| # | Severity | File:Line | Finding |
|---|----------|-----------|---------|
| 1 | MEDIUM | `src/Foo.php:42` | N+1 query in loop |

### Follow-up Observations
- Consider extracting the signal handler setup into a trait (low effort, good payoff)
- The retry logic in `ProcessGroup` could use exponential backoff — file separately

### What's Fine :heavy_check_mark:
- All Rector-applied changes semantically equivalent
- Test coverage maintained at current level
```

### Inline comment (per finding)

```
**MEDIUM** — `cp` overwrites `.git/hooks/pre-push` unconditionally.

**Why:** If a developer has custom pre-push checks, they are silently destroyed on next `composer install`. No backup, no warning, no opt-out.

**Suggestion:**
```bash
if [ -f "$HOOKS_DIR/pre-push" ]; then
  echo "Warning: .git/hooks/pre-push already exists, skipping."
  exit 0
fi
```
```

## Common Mistakes

- **Reviewing files outside the PR diff** — only changed files are in scope
- **Posting follow-ups as inline comments** — keep those in the summary body only
- **Using file line numbers instead of diff positions** — `gh api` expects diff positions, not source line numbers
- **Not re-fetching the diff before subsequent rounds** — the code may have changed
- **Long summaries without a table** — always include the severity table for quick scanning
- **Not fetching the linked issue body** — compliance can't be checked without it
