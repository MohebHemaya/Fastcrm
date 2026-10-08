# UI Redesign: Resolution Verification

Companion to `ui-audit-sidebar-inputs.md`. Every finding from the audit is listed with its resolution.

## Part A: Sidebar

| Finding | Resolution |
|---|---|
| Primary action button missing | Added "أمر شغل جديد" button in `AppShell.tsx` linking to `/work-orders/new`. |
| Flat nav list | 5 semantic groups with muted Arabic headers and dividers. |
| No operational badges | `GET /api/v1/sidebar-badges` (auth:sanctum, cached 30 s). Frontend polls every 60 s, pauses when the tab is hidden, and fails silently. |
| Badge formatting | Counts above 99 show "99+". Zero hides the badge. Collapsed mode shows a dot. |
| lucide-react icons | Fully migrated to `@heroicons/react`, re-exported from `src/components/icons.ts`. Package uninstalled. |
| Fixed-width desktop sidebar | Collapsible icon-only mode with HeroUI Tooltips. State persisted in `useUiStore`. |
| Raw mobile drawer | HeroUI Drawer, `placement="right"`. |
| Static footer | User dropdown (change password, logout) and a WhatsApp provider Chip (Fake = warning, Cloud = success). |
| Dashboard at /reports | Dashboard moved to `/` (`HomePage`). `/reports` is reports and exports only. |

## Part B: Inputs

Every form input listed in the audit now goes through the shared wrappers in `src/components/form/`:
`FormInput`, `FormTextarea`, `FormSelect`, `FormSwitch`, `SearchInput`. The wrappers set `variant="bordered"` and `labelPlacement="outside"`, and phone/code fields use `dir="ltr"`. Dashboard and report date inputs use HeroUI `Input`. The only raw `<input>` left is the file input in `ImportUploadStep`.

## Verification

- Build, Vitest (101), Pest (366), Pint and PHPStan: all pass.
- Playwright: 14 tests; first full run passed.
- No `lucide-react` imports and no physical Tailwind direction classes remain in `frontend/src`.

## Pending

- "After" screenshots in `docs/ui-after/` (13 files, same matrix as `ui-before/`) still need to be captured.
