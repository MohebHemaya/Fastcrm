# UI Audit: Sidebar Navigation & Form Input System

**Audit Date:** 2026-10-03  
**Status:** Pre-implementation baseline audit (Step 0)  
**Scope:** Frontend Navigation (`AppShell.tsx`), Icon System (`lucide-react` $\rightarrow$ `@heroicons/react`), and Form Inputs across the entire application.

---

## Part A: Sidebar Navigation Audit

### 1. Current Implementation vs. Target Specification

| Feature / Element | Current State (`src/components/layout/AppShell.tsx`) | Target Specification | Variance / Required Change |
|---|---|---|---|
| **Primary Action Button** | ❌ None | "＋ أمر شغل جديد" prominent button at top, full width, `color="primary"`, leading `PlusIcon`, routes to `/work-orders/new` | **ADD**: New primary call-to-action button placed directly below brand area |
| **Grouping Structure** | ❌ Flat list of 10 items without visual categories or headings | **5 Distinct Semantic Groups**: <br>1. **الرئيسية**: لوحة التحكم (`/`)<br>2. **التشغيل اليومي**: أوامر الشغل (`/work-orders`), السائقون (`/drivers`), الاستيراد (`/import`)<br>3. **التواصل**: رسائل واتساب (`/messages`), الأسئلة (`/questions`)<br>4. **التحليلات**: التقارير والتصدير (`/reports`)<br>5. **النظام**: الإعدادات (`/settings`), سجل النشاط (`/activity-logs`), صحة النظام (`/health`) | **REDESIGN**: Add small muted Arabic group headers, proper vertical spacing, and HeroUI `Divider` components |
| **Operational Badges** | ❌ None | **Dynamic real-time counters**: <br>• أوامر الشغل: open slots / filling orders<br>• السائقون: drivers needing review (`needs_review = true`)<br>• الاستيراد: imports ready for review/commit<br>• رسائل واتساب: red badge for failed messages in last 24h<br>• صحة النظام: red dot when scheduler heartbeat is stale or failed jobs exist | **ADD**: Real-time badge indicators fetching from new backend endpoint `/api/v1/sidebar-badges` |
| **Badge Formatting Rules** | N/A | Count $> 99$ displays `"99+"`; count $= 0$ completely hides badge. Collapsed sidebar shows compact notification dot on icon | **IMPLEMENT**: Conditional badge and dot rendering |
| **Icon Library** | `lucide-react` (`LayoutDashboard`, `Users`, `MessageSquare`, `FileSpreadsheet`, `HelpCircle`, `ClipboardList`, `BarChart3`, `Settings`, `History`, `Activity`) | `@heroicons/react` v2 (`/24/outline` for regular, `/24/solid` for active) | **REPLACE**: Swap Lucide with Heroicons and centralize in `src/components/icons.ts` |
| **Desktop Layout** | Fixed width 256px (`w-64`) only | Fixed width (~260px) with **optional collapsed icon-only mode (~72px)** featuring HeroUI `Tooltip` on hover; persisted in `useUiStore` | **ADD**: Collapsed mode toggle and tooltip support |
| **Mobile Drawer** | Custom raw HTML `<aside>` with CSS translate classes and manual backdrop `<div onClick=...>` | Standard HeroUI `Drawer` component (or responsive modal drawer), `placement="right"` for RTL, auto-closes on navigation | **REPLACE**: Migrate mobile navigation to HeroUI `Drawer` |
| **Sidebar Footer** | Static card showing user name & email | User `Dropdown` (تغيير كلمة المرور, تسجيل الخروج) + WhatsApp Provider Mode `Chip` (`Fake` = warning, `Cloud` = success) | **UPDATE**: Integrate provider chip and clean user action triggers |
| **Dashboard Location** | Dashboard metrics & charts reside in `/reports` while `/` is a landing page | Dashboard metrics & charts live at `"/"` (Home); `/reports` reserved for reports and exports; seamless redirect | **MIGRATE**: Re-route dashboard view to `"/"` |

---

## Part B: Form Inputs Audit & Replacement Matrix

### 1. Global Input Inventory & Target HeroUI Wrappers

| Location / File | Current Input / Component | Type & Props | Issues / Inconsistencies | Target HeroUI Component & Wrapper |
|---|---|---|---|---|
| `routes/LoginPage.tsx` (lines 115–136) | Raw `<Input>` | Email & Password, `variant="bordered"` | Physical placeholder `admin@example.com`, manual state, password toggle not standardized | `FormInput` with `startContent={<EnvelopeIcon />} / <LockClosedIcon />}`, Eye/EyeSlash toggle, centralized `ar.ts` labels |
| `components/auth/ChangePasswordDialog.tsx` (lines 80–120) | Raw `<Input>` | Current, new, confirm password | Manual show/hide states, no standardized wrapper | `FormInput` with `type="password"`, Eye toggle, react-hook-form integration |
| `routes/CreateWorkOrderPage.tsx` (Step 1, lines 360–495) | Ad-hoc `<Input>`, `<Textarea>` | `from_location`, `to_location`, `scheduled_at`, `cargo_type`, `pay_amount`, `trips_needed`, `backup_needed`, `notes` | Inconsistent `variant`, manual numeric parsing, datetime string input lacks standardized styling | `FormInput` (`type="text"`, `type="number"`, `type="datetime-local"`), `FormTextarea`, `FormSelect` |
| `routes/CreateWorkOrderPage.tsx` (Step 2, lines 570–670) | Raw `<Select>`, `<Input>` | Question filter selector, operator selector, value multi-select | Hardcoded labels, awkward multi-value tags | `FormSelect`, `FormInput` with clean tags/chips |
| `routes/CreateWorkOrderPage.tsx` (Step 3 & 4, lines 690–870) | Raw `<Input>`, radio/checkboxes | `batch_size`, `batch_interval_minutes`, `min_avg_rating`, `max_no_shows`, `last_trip_within_days` | Raw number inputs without bounds validation indicators | `FormInput` (`type="number"`), `FormSwitch` |
| `routes/DriversPage.tsx` (lines 405–455) | Ad-hoc `<Input>`, `<Select>` | Search box, status filter, group filter | Search box has duplicated debounce timer; Selects use `variant="bordered"` but lack unified wrapper | `SearchInput` (shared debounced component with clearable button & `MagnifyingGlassIcon`), `FormSelect` |
| `features/drivers/components/AddDriverModal.tsx` (lines 145–210) | Raw `<Input>`, `<Select>` | Phone, name, whatsapp group | Phone number lacks explicit `dir="ltr"` / `text-start`, risk of Arabic numeral reversal | `FormInput` with `dir="ltr" text-start`, phone formatting helper |
| `features/drivers/components/ChangeStatusModal.tsx` (lines 125–150) | Raw `<Select>`, `<Textarea>` | Status select, reason textarea | Direct HeroUI usage without RHF controller | `FormSelect`, `FormTextarea` |
| `features/imports/components/ImportUploadStep.tsx` (line 117) | Raw `<input type="file">` | Native browser file input | Raw invisible `<input>` with custom dropzone div | HeroUI `Button`-triggered file selector with styled HeroUI Card dropzone |
| `features/settings/QuestionModal.tsx` (lines 205–340) | Raw `<Input>`, `<Select>`, `<Textarea>` | Question key, type, text, options list | Inconsistent spacing, dynamic options rows use plain inputs | `FormInput`, `FormSelect`, `FormTextarea` |
| `features/settings/SettingsPage.tsx` (lines 440–500) | Raw `<Input>` | Setting values (`reminder_hours`, `timeout_days`, etc.) | Number inputs with text type, manual conversion | `FormInput` with `type="number"` |
| `routes/WorkOrdersListPage.tsx` (lines 265–320) | Raw `<Input>`, `<Select>` | Search, status, date from, date to | Duplicated search logic, date inputs unstyled | `SearchInput`, `FormSelect`, `FormInput` (`type="date"`) |
| `features/workOrders/components/AddDriverModal.tsx` (lines 75–110) | Raw `<Select>` | Driver select, role select | Missing empty state placeholder | `FormSelect` |
| `features/workOrders/components/CancelOrderModal.tsx` (lines 50–70) | Raw `<Textarea>` | Cancellation reason | Inconsistent height and validation message display | `FormTextarea` with required indicator |
| `features/workOrders/components/ChangeOrderStatusModal.tsx` (lines 90–110) | Raw `<Textarea>` | Status change reason | Missing RHF integration | `FormTextarea` |
| `features/workOrders/components/RecordOutcomesModal.tsx` (lines 160–240) | Table-embedded `<Select>`, `<Input>` | Outcome select, rating select, notes input | Compact table rows cramped, uneven input heights | Unified compact `FormSelect` & `FormInput` |
| `features/workOrders/components/RemoveDriverModal.tsx` (lines 75–95) | Raw `<Textarea>` | Removal reason | Direct HeroUI usage | `FormTextarea` |
| `routes/WhatsAppMessagesPage.tsx` (lines 250–290) | Raw `<Input>`, `<Select>` | Search message, status filter, direction filter | Search lacks debounce standardization | `SearchInput`, `FormSelect` |
| `features/whatsapp/components/DriverChatModal.tsx` (lines 330–350) | Raw `<Input>` | Outbound simulation input | Lacks submit button integration | `FormInput` with send button `endContent` |
| `routes/ActivityLogsPage.tsx` (lines 220–265) | Raw `<Select>`, `<Input>` | Action filter, start date, end date | Inconsistent filter bar layout | `FormSelect`, `FormInput` (`type="date"`) |
| `routes/ReportsPage.tsx` (lines 320–350, 600–615) | Raw `<Input>`, `<Select>` | Date range presets, start/end dates, group search | Date range custom inputs cramped | `SearchInput`, `FormSelect`, `FormInput` (`type="date"`) |

---

## Part C: "Before" Visual Evidence

The following visual baseline screenshots have been captured and saved to `docs/ui-before/`:

1. **Sidebar Navigation:**
   - Desktop (1280px): [`docs/ui-before/sidebar-desktop.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/sidebar-desktop.png)
   - Tablet (768px): [`docs/ui-before/sidebar-tablet.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/sidebar-tablet.png)
   - Mobile (375px with open drawer): [`docs/ui-before/sidebar-mobile.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/sidebar-mobile.png)

2. **Form 1: Create Work Order (Worst-looking multi-step form):**
   - Desktop (1280px): [`docs/ui-before/create-order-desktop.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/create-order-desktop.png)
   - Tablet (768px): [`docs/ui-before/create-order-tablet.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/create-order-tablet.png)
   - Mobile (375px): [`docs/ui-before/create-order-mobile.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/create-order-mobile.png)

3. **Form 2: Questions & Settings Form:**
   - Desktop (1280px): [`docs/ui-before/questions-settings-desktop.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/questions-settings-desktop.png)
   - Tablet (768px): [`docs/ui-before/questions-settings-tablet.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/questions-settings-tablet.png)
   - Mobile (375px): [`docs/ui-before/questions-settings-mobile.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/questions-settings-mobile.png)

4. **Form 3: Authentication & Modals:**
   - Login Desktop (1280px): [`docs/ui-before/login-desktop.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/login-desktop.png)
   - Login Tablet (768px): [`docs/ui-before/login-tablet.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/login-tablet.png)
   - Login Mobile (375px): [`docs/ui-before/login-mobile.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/login-mobile.png)
   - Add Driver Modal: [`docs/ui-before/add-driver-modal-desktop.png`](file:///d:/Projects/Web/fast%20crm/docs/ui-before/add-driver-modal-desktop.png)

---

## Part D: Implementation Plan & Standards

1. **Icons (`src/components/icons.ts`):** Re-export all Heroicons v2 (`@heroicons/react/24/outline` and `/24/solid`).
2. **Form Components (`src/components/form/`):**
   - `FormInput`: react-hook-form Controller + HeroUI `Input`, `variant="bordered"`, `labelPlacement="outside"`, error display.
   - `FormTextarea`: react-hook-form Controller + HeroUI `Textarea`.
   - `FormSelect`: react-hook-form Controller + HeroUI `Select`.
   - `FormSwitch`: react-hook-form Controller + HeroUI `Switch`.
   - `SearchInput`: standalone debounced HeroUI `Input` with `MagnifyingGlassIcon` and `isClearable`.
3. **Backend Badge Endpoint:** `GET /api/v1/sidebar-badges` with 30s cache.
4. **AppShell Sidebar:** Compose using HeroUI Button, Tooltip, Badge, Chip, Avatar, Dropdown, Divider, ScrollShadow, Drawer.
5. **Dashboard Migration:** Move Dashboard view from `/reports` to `"/"`.
