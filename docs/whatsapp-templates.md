# WhatsApp Message Templates Specification (Meta Cloud API)

This document contains the complete specification of all WhatsApp message templates required for the Driver Management System. All templates are submitted and approved via the Meta WhatsApp Business Manager before use in production.

- **Language:** Arabic (`ar`)
- **Default Encoding:** UTF-8
- **Opt-out Notice:** Included in onboarding templates per WhatsApp policy ("للإلغاء أرسل: إيقاف").
- **Meta Graph API Version:** Configured via `WHATSAPP_GRAPH_VERSION` in `.env` (defaults to `v25.0`). *Note: Before production deployment, the configured version must be verified against Meta's changelog / currently supported versions, and the app's default version in the Meta App Dashboard must be configured to match.*

---

## 1. Onboarding & Driver Invitation (`driver_invitation`)

* **Category:** `MARKETING` (or `UTILITY` depending on pre-relationship)
* **Name:** `driver_invitation`
* **Language:** `ar`
* **Header:** None
* **Body:**
  ```text
  أهلاً بك يا {{1}}! معك شركة النقل السريع. نتشرف بدعوتك للانضمام إلى شبكة السائقين لدينا وتلقي رحلات عمل يومية ومميزة بعوائد مجزية.

  هل أنت مهتم بالانضمام إلينا والبدء في تلقي الرحلات؟

  (للإلغاء أرسل: إيقاف)
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق (مثال: "كابتن أحمد") أو "كابتن" إذا لم يتوفر الاسم.
* **Buttons:** Quick Reply (أزرار الرد السريع)
  1. `invite_yes`: **نعم، مهتم**
  2. `invite_no`: **لا، شكرًا**

---

## 2. Questionnaire Completion Notice (`questionnaire_completion`)

* **Category:** `UTILITY`
* **Name:** `questionnaire_completion`
* **Language:** `ar`
* **Header:** None
* **Body:**
  ```text
  مرحبًا يا كابتن {{1}}! 
  تم استلام وتأكيد بياناتك بنجاح، وتم تفعيل حسابك كـ "سائق نشط" في شبكتنا.
  ستبدأ في استقبال عروض الرحلات المناسبة لمركبتك وموقعك قريبًا جدًا.

  (للإلغاء أرسل: إيقاف)
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
* **Buttons:** None

---

## 3. Work Order Trip Offer (`order_offer`)

* **Category:** `UTILITY`
* **Name:** `order_offer`
* **Language:** `ar`
* **Header:** `فرصة رحلة جديدة 🚗` (Text)
* **Body:**
  ```text
  مرحبًا يا كابتن {{1}}، تتوفر رحلة جديدة مطابقة لبياناتك:
  - رقم الطلب: {{2}}
  - خط السير: من {{3}} إلى {{4}}
  - موعد الانطلاق: {{5}}
  - المقابل المادي: {{6}} ج.م

  هل ترغب في قبول هذه الرحلة؟ (الأولوية لأسبقية الرد)
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم أمر العمل (مثال: "TRIP-104").
  * `{{3}}`: مكان الانطلاق (مثال: "مدينة نصر").
  * `{{4}}`: وجهة الوصول (مثال: "العين السخنة").
  * `{{5}}`: الموعد والوقت (مثال: "الخميس 8:00 صباحًا").
  * `{{6}}`: القيمة المالية بالجنيه المصري (مثال: "1,200").
* **Buttons:** Quick Reply
  1. `order_accept:{{orderId}}:{{driverId}}`: **أوافق**
  2. `order_decline:{{orderId}}:{{driverId}}`: **أعتذر**

---

## 4. Work Order Trip Confirmation (`order_confirmation`)

* **Category:** `UTILITY`
* **Name:** `order_confirmation`
* **Language:** `ar`
* **Header:** `تم تأكيد حجز الرحلة ✅` (Text)
* **Body:**
  ```text
  مبروك يا كابتن {{1}}! تم تأكيد تكليفك بالرحلة رقم {{2}}.
  
  تفاصيل الالتقاء:
  - الموقع: {{3}}
  - الموعد: {{4}}
  - بيانات المشرف/العميل: {{5}}

  نتمنى لك رحلة موفقة وآمنة. برجاء الالتزام بالحضور قبل الموعد بـ 15 دقيقة.
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
  * `{{3}}`: نقطة التجمع الدقيقة أو الرابط.
  * `{{4}}`: وقت الحضور.
  * `{{5}}`: اسم وهاتف المشرف.
* **Buttons:** Quick Reply
  1. `trip_started`: **بدء التحرك**
  2. `trip_support`: **تواصل مع المشرف**

---

## 5. Backup Driver Notice (`order_backup_notice`)

* **Category:** `UTILITY`
* **Name:** `order_backup_notice`
* **Language:** `ar`
* **Body:**
  ```text
  أهلاً كابتن {{1}}، شكرًا لتجاوبك السريع. تم اكتمال المقاعد الأساسية للرحلة رقم {{2}}، وتم إدراجك كسائق "احتياطي أول".
  
  في حال اعتذار أي سائق أساسي سيتم ترقيتك تلقائيًا وتنبيهك فورًا برسالة تأكيد.
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
* **Buttons:** None

---

## 6. Work Order Filled Notice (`order_filled`)

* **Category:** `UTILITY`
* **Name:** `order_filled`
* **Language:** `ar`
* **Body:**
  ```text
  شكرًا لتجاوبك كابتن {{1}}. نود إعلامك باكتمال العدد المطلوب بالكامل للرحلة رقم {{2}}.
  
  فرص أخرى مميزة ستصلك قريبًا، فكن على استعداد!
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
* **Buttons:** None

---

## 7. Trip Cancellation Notice (`trip_cancellation`)

* **Category:** `UTILITY`
* **Name:** `trip_cancellation`
* **Language:** `ar`
* **Header:** `تنبيه إلغاء رحلة ⚠️` (Text)
* **Body:**
  ```text
  عناية الكابتن {{1}}،
  نحيطك علمًا بأنه تم إلغاء الرحلة رقم {{2}} المقررة بتاريخ {{3}}، وذلك بسبب: {{4}}.
  
  نعتذر عن أي إزعاج، وستكون لك الأولوية في الرحلات القادمة.
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
  * `{{3}}`: تاريخ وموعد الرحلة.
  * `{{4}}`: سبب الإلغاء (مثال: "إلغاء من طرف العميل").
* **Buttons:** None

---

## 8. Backup Driver Promotion (`backup_promoted`)

* **Category:** `UTILITY`
* **Name:** `backup_promoted`
* **Language:** `ar`
* **Header:** `ترقية رحلة هامة 🚀` (Text)
* **Body:**
  ```text
  كابتن {{1}}، نظرًا لاعتذار أحد السائقين، تم ترقيتك الآن لتكون السائق الأساسي للرحلة رقم {{2}} المتجهة إلى {{3}} في موعد {{4}}.
  
  برجاء تأكيد جاهزيتك للتحرك فورًا.
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
  * `{{3}}`: الوجهة.
  * `{{4}}`: الموعد.
* **Buttons:** Quick Reply
  1. `confirm_backup`: **جاهز للتحرك**
  2. `decline_backup`: **اعتذار**

---

## 9. Trip Reminder Notice (`trip_reminder`)

* **Category:** `UTILITY`
* **Name:** `trip_reminder`
* **Language:** `ar`
* **Body:**
  ```text
  تذكير بموعد رحلتك غدًا:
  - كابتن: {{1}}
  - الرحلة رقم: {{2}}
  - الموعد: {{3}}
  - نقطة التجمع: {{4}}
  
  نرجو التأكد من جاهزية المركبة ونظافتها والوصول قبل الموعد بـ 15 دقيقة على الأقل. نتمنى لك رحلة آمنة.
  ```
* **Placeholders:**
  * `{{1}}`: اسم السائق.
  * `{{2}}`: رقم الرحلة.
  * `{{3}}`: الموعد المحدد.
  * `{{4}}`: نقطة التجمع.
* **Buttons:** Quick Reply
  1. `reminder_ok`: **أنا جاهز ومستعد**

---

## 10. Inbound Opt-Out Keywords & Processing Rules

The system processes inbound driver messages and quick reply taps through `App\Services\InboundMessageRouter`. To balance user autonomy with preventing accidental opt-outs caused by natural conversational Arabic, keywords are partitioned into **Standard Keywords** and **Exact-Only Keywords**.

### 10.1 Standard Opt-Out Keywords
Standard keywords trigger an opt-out when they constitute the entire message or appear as the first word of the message.

| Keyword (Arabic / English) | Normalized Form | Matching Rule |
| :--- | :--- | :--- |
| `إيقاف` / `ايقاف` | `ايقاف` | Whole message or first word (`إيقاف`, `ايقاف الرسائل`) |
| `اوقف` | `اوقف` | Whole message or first word |
| `إلغاء` / `الغاء` | `الغاء` | Whole message or first word |
| `لا اريد` / `لا أريد` | `لا اريد` | Whole message or prefix phrase |
| `stop` / `STOP` | `stop` | Whole message or first word (case-insensitive) |
| `unsubscribe` | `unsubscribe` | Whole message or first word (case-insensitive) |

### 10.2 Exact-Only Opt-Out Keywords
Exact-only keywords are everyday Arabic words frequently used in conversational contexts (such as discussing breakdowns, end of shifts, stops, or departures). To prevent false positives, these keywords **strictly trigger opt-out only when the trimmed and normalized message contains solely that single word**.

| Keyword | Why Exact-Only? (Conversational Conflict Avoided) | Matches Opt-Out? | Does NOT Opt-Out (False Positive Protected) |
| :--- | :--- | :--- | :--- |
| `وقف` | Common root for waiting/parking ("الشغل وقف النهارده", "عربيتي واقفة في الورشة") | `وقف` (standalone) | "الشغل وقف النهارده", "عربيتي واقفة" |
| `انهاء` / `إنهاء` | Common word for finishing duties ("مش هقدر انهي الشغل", "معاد إنهاء الرحلة") | `انهاء`, `إنهاء` (standalone) | "مش هقدر انهي الشغل بدري" |
| `قف` | Common imperative / transit stop ("المحطة عند إشارة قف") | `قف` (standalone) | "المحطة عند إشارة قف", "أنا واقف عند قف" |
| `خروج` | Common departure / exit term ("خروج السيارة من الجراج بعد قليل") | `خروج` (standalone) | "خروج السيارة من الجراج بعد قليل" |

### 10.3 Quick Reply Button Payloads
The following button payload IDs immediately trigger opt-out regardless of message body text:
- `opt_out`
- `stop`
- `unsubscribe`

### 10.4 Lifecycle Behavior on Opt-Out
1. **Status Transition:** Driver status changes to `opted_out` via `DriverStatusService::transition()`.
2. **Timestamp:** `opted_out_at` timestamp is set to `now()`.
3. **Audit Log:** An audit log event (`driver.status_changed`) is recorded.
4. **Outbound Messaging Lock:** `WhatsAppMessageSender` strictly rejects any further outbound template or session messages to this driver with a `DriverOptedOutException`.
5. **Confirmation Dispatch:** Exactly **one** final confirmation text message is dispatched acknowledging the opt-out ("تم إلغاء اشتراكك بنجاح ولن تتلقى أي رسائل أخرى...").
