<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>ملف السائق - {{ $driver->name ?: $driver->phone }}</title>
    <style>
        body {
            font-family: 'amiri', serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #1f2937;
            direction: rtl;
            text-align: right;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .logo-placeholder {
            width: 130px;
            height: 42px;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 13pt;
            text-align: center;
            line-height: 42px;
            border-radius: 4px;
        }

        .profile-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .meta-table td {
            padding: 6px 10px;
            font-size: 9.5pt;
            border: 1px solid #e2e8f0;
        }

        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 20%;
            background-color: #f1f5f9;
        }

        .meta-value {
            color: #0f172a;
            width: 30%;
        }

        .metrics-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .metric-cell {
            width: 20%;
            text-align: center;
            padding: 8px 4px;
            border: 1px solid #cbd5e1;
            background-color: #f0fdf4;
        }

        .metric-number {
            font-size: 16pt;
            font-weight: bold;
            color: #15803d;
            font-family: sans-serif;
            margin-bottom: 2px;
        }

        .metric-title {
            font-size: 8.5pt;
            color: #475569;
            font-weight: bold;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0369a1;
            margin-top: 10px;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #bae6fd;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 9pt;
            padding: 5px 8px;
            text-align: right;
            border: 1px solid #0f172a;
        }

        .data-table td {
            padding: 5px 8px;
            font-size: 9pt;
            border: 1px solid #cbd5e1;
            text-align: right;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .ltr-text {
            direction: ltr !important;
            unicode-bidi: embed !important;
            display: inline-block;
            font-family: sans-serif;
        }

        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
            font-size: 8pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Table -->
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="logo-placeholder">شركة النقل السريع</div>
                <div style="font-size: 8.5pt; color: #64748b; margin-top: 3px;">بطاقة تعريف وبيانات السائق</div>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: middle;">
                <div class="profile-title">{{ $driver->name ?: 'كابتن (بدون اسم)' }}</div>
                <div style="font-size: 10.5pt; font-weight: bold; color: #0284c7; margin-top: 2px;">
                    رقم الهاتف: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $driver->phone }}</span>
                </div>
                <div style="font-size: 8pt; color: #64748b; margin-top: 3px;">
                    تاريخ الطباعة: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $printedAt }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Basic Information -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">الاسم بالكامل</td>
            <td class="meta-value">{{ $driver->name ?: 'غير مسجل' }}</td>
            <td class="meta-label">حالة السائق</td>
            <td class="meta-value"><strong>{{ $driver->status->label() }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">مجموعات الواتساب</td>
            <td class="meta-value">{{ $driver->whatsappGroups->pluck('name')->implode('، ') ?: 'لا توجد' }}</td>
            <td class="meta-label">تاريخ التسجيل</td>
            <td class="meta-value"><span class="ltr-text">{{ $driver->created_at->format('Y-m-d H:i') }}</span></td>
        </tr>
        @if(!empty($driver->notes))
        <tr>
            <td class="meta-label">ملاحظات الإدارة</td>
            <td class="meta-value" colspan="3">{{ $driver->notes }}</td>
        </tr>
        @endif
    </table>

    <!-- Driver Performance Metrics Grid -->
    <div class="section-title">مؤشرات الأداء والالتزام</div>
    <table class="metrics-grid">
        <tr>
            <td class="metric-cell">
                <div class="metric-number">{{ $driver->metrics?->trips_completed ?? 0 }}</div>
                <div class="metric-title">رحلات مكتملة</div>
            </td>
            <td class="metric-cell" style="background-color: #fef2f2;">
                <div class="metric-number" style="color: #b91c1c;">{{ $driver->metrics?->cancellations ?? 0 }}</div>
                <div class="metric-title">اعتذار متأخر</div>
            </td>
            <td class="metric-cell" style="background-color: #fff7ed;">
                <div class="metric-number" style="color: #c2410c;">{{ $driver->metrics?->no_shows ?? 0 }}</div>
                <div class="metric-title">عدم حضور</div>
            </td>
            <td class="metric-cell" style="background-color: #fefce8;">
                <div class="metric-number" style="color: #a16207;">{{ $driver->metrics?->late_count ?? 0 }}</div>
                <div class="metric-title">مرات التأخير</div>
            </td>
            <td class="metric-cell" style="background-color: #eff6ff;">
                <div class="metric-number" style="color: #1d4ed8;">
                    {{ $driver->metrics?->avg_rating ? number_format((float) $driver->metrics->avg_rating, 1) . ' / 5' : '—' }}
                </div>
                <div class="metric-title">متوسط التقييم</div>
            </td>
        </tr>
    </table>

    <!-- Questionnaire Answers -->
    <div class="section-title">إجابات استبيان التسجيل والتأهيل</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50%;">السؤال</th>
                <th style="width: 50%;">إجابة السائق</th>
            </tr>
        </thead>
        <tbody>
            @forelse($questionsWithAnswers as $item)
            <tr>
                <td style="font-weight: bold; color: #334155;">{{ $item['question'] }}</td>
                <td>{{ $item['answer'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="2" style="text-align: center; color: #94a3b8; padding: 10px;">لا توجد إجابات مسجلة بعد.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Recent Trips Activity -->
    <div class="section-title">آخر الرحلات المكلف بها</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">كود الرحلة</th>
                <th style="width: 35%;">خط السير</th>
                <th style="width: 20%; text-align: center;">الموعد</th>
                <th style="width: 20%; text-align: center;">النتيجة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentTrips as $trip)
            <tr>
                <td><span class="ltr-text">{{ $trip->workOrder?->code }}</span></td>
                <td>{{ $trip->workOrder?->from_location }} إلى {{ $trip->workOrder?->to_location }}</td>
                <td style="text-align: center;"><span class="ltr-text">{{ $trip->workOrder?->scheduled_at?->format('Y-m-d') }}</span></td>
                <td style="text-align: center;">
                    {{ $trip->outcome ? $trip->outcome->label() : ($trip->status === \App\Enums\WorkOrderRecipientStatus::CONFIRMED ? 'مؤكد' : $trip->status->label()) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 8px;">لم يشارك السائق في أي رحلات بعد.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        تم استخراج ملف السائق آليًا — وثيقة تشغيلية داخلية لشركة النقل السريع
    </div>

</body>
</html>
