# CAS Suit — RBAC Architecture

**Status:** Design approved. Implementation: batches 33.1 → 33.4
**Last revised:** 2026-10-31

---

## 1. The One-Sentence Principle

> CAS Suit owns the security foundation. Individual applications own
> their operational permissions. Administrators receive explicitly
> delegated authority. All permission decisions are enforced centrally.

---

## 2. The Three Layers of Permission

Every permission has three distinct attributes.

| Layer | Question it answers | Who controls it |
|---|---|---|
| Ownership | Which application defines this permission? | Namespace prefix |
| Administration | Which administrator can assign it? | Admin's own scope |
| Enforcement | Can this user perform this action? | Central AuthorizationService |

---

## 3. Permission Namespace Ownership

Every permission slug follows: `{namespace}.{resource}.{action}`

The prefix determines ownership.

| Namespace | Owner | Registrar |
|---|---|---|
| `cas.*` | CAS Core | Migration (protected) |
| `core.*` | CAS Core | Migration (protected) |
| `recruit.*` | Recruit Aid | `Module.php` |
| `hr.*` | HR Aid | `Module.php` |

**Rule:** An application may register permissions only under its own namespace. `cas.*` and `core.*` are protected.

---

## 4. Administrative Scopes

### 4.1 Global CAS Admin
Holds `cas.*` permissions via Super Admin role.

### 4.2 Application Admin (Delegated)
Holds a delegation record in `core_admin_delegations`. Scoped, expiry-bounded, auditable, non-escalating.

### 4.3 Regular User
Holds roles scoped to specific applications.

---

## 5. Permission Evaluation (Enforcement)

The central `AuthorizationService` answers all "can this user do X?".

### 5.2 Precedence (first match wins)

1. User null -> DENY
2. No tenant -> DENY
3. Permission not found -> DENY
4. Module disabled -> DENY
5. Module not assigned -> DENY
6. Policy deny -> DENY
7. Explicit user deny -> DENY
8. Explicit user grant -> ALLOW
9. Role grant (with app assignment) -> ALLOW
10. Super admin bypass (public only) -> ALLOW
11. Default -> DENY

---

## 6. Delegation Model

App admins hold explicit delegation records with allowed/denied lists. Cannot grant permissions they don't hold themselves.

---

## 7. Role Model

Roles are scoped (global / app). System roles are protected. No inheritance in v1.

---

## 8. Permission Registration

Apps register via `getPermissionDefinitions()`. Registration does not auto-activate.

---

## 9. Audit

All RBAC mutations write to `core_rbac_audit_logs`. Append-only.

---

## 10. Threat Model

Privilege escalation paths are closed via namespace checks, self-grant rejection, sensitivity classes, and delegation scope enforcement.

---

## 11. Migration Roadmap

Batch 33.1 (Schema), 33.2 (Service), 33.3 (Delegation), 33.4 (UI).

---

## 12. Non-Goals

- Break-glass access (deferred)
- Role inheritance (deferred)
- Field-level enforcement (v2)

---

## 13. Compatibility

Backward compatible with existing RBAC.
