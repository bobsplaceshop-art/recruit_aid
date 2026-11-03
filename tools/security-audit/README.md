# Recruit Aid Security Auditor

## Purpose

A repeatable, project-wide static security analysis tool for Recruit Aid. It analyses PHP source, tenant-related SQL, route registrations, controller authorization patterns and polymorphic ownership patterns. It consolidates findings into one report.

The auditor is diagnostic. It does not replace runtime testing or penetration testing.

## Run the audit

From the project root, execute:

`php tools/security-audit/run.php`

The runner executes all regression suites and analysis stages, validates the generated reports, and stops if a stage fails.

## Pipeline

1. AST, route and controller authorization regression tests.
2. Tenant SQL regression tests.
3. Polymorphic ownership regression tests.
4. Tenant and source audit.
5. Route audit.
6. Controller authorization audit.
7. Polymorphic ownership audit.
8. Findings consolidation.

## Reports

All reports are written to `tools/security-audit/reports/`.

| Report | Contents |
|---|---|
| `security-audit.json` | Source inventory and tenant SQL findings |
| `route-audit.json` | Route registrations, duplicates and middleware findings |
| `controller-auth-audit.json` | Controller and route authorization observations |
| `polymorphic-ownership-audit.json` | Polymorphic ownership and file-link findings |
| `verified-findings.json` | Manually maintained findings register |
| `consolidated-security-audit.json` | Combined findings and observations |

The consolidated report is the primary report for reviewing results.

## Finding statuses

| Status | Meaning |
|---|---|
| `VERIFIED` | Manually investigated finding recorded as verified |
| `VERIFIED_STATIC_DEFECT` | Deterministic static defect; runtime impact may still need validation |
| `REVIEW` | Investigation signal, not automatically a confirmed vulnerability |
| `COVERAGE_GAP` | A code path or behaviour the auditor could not sufficiently resolve |

Severity, confidence and status describe different aspects of a finding. Consider them together.

## Latest recorded baseline

The last successful full run reported:

- 26 regression tests passed.
- 213 PHP files scanned by the base audit.
- 0 file scan failures.
- 163 route registrations discovered.
- 159 effective routes evaluated.
- 1,290 controller methods analysed.
- 152 consolidated findings: 5 `VERIFIED`, 6 `VERIFIED_STATIC_DEFECT`, 138 `REVIEW` and 3 `COVERAGE_GAP`.

These figures are a dated baseline, not a guarantee that all security issues have been identified or resolved. Counts may change as the project and audit rules evolve.

## Limitations

This is static analysis based on PHP parsing, recognised patterns and heuristics.

- Tenant SQL analysis may not fully understand dynamic SQL, complex subqueries, heredoc or nowdoc strings, or unusual alias patterns.
- Route analysis may not resolve dynamic paths, route groups, inherited middleware or unsupported registration patterns.
- Controller authorization analysis does not fully trace inherited checks, indirect authorization or every service-level decision.
- Polymorphic ownership analysis detects recognised patterns, not every possible ownership relationship.
- A missing explicit permission or tenant predicate is not automatically proof of a vulnerability. Public routes, global operations and upstream ownership checks require context.
- Static analysis does not execute application workflows, test live database permissions or establish exploitability.

Treat findings as evidence-led investigation items. Do not change application code solely because a heuristic flagged a pattern.

## Operating rules

- Run from the project root.
- Review the consolidated report after each run.
- Preserve regression tests when changing analyzer logic.
- Add regression cases for confirmed false positives and newly detected patterns.
- Keep the verified findings register evidence-based.
- Preserve previous reports separately when historical comparisons are needed.
- Audit execution must not modify Recruit Aid application source files or application data.

## Scope and safety

The auditor reads project files and writes its own reports under `tools/security-audit/`. Its intended operation is read-only with respect to the application.
