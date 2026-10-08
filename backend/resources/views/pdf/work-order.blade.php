<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بيان تشغيل رحلة - {{ $order->code }}</title>
    <style>
        body {
            font-family: 'amiri', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1f2937;
            direction: rtl;
            text-align: right;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .logo-placeholder {
            width: 140px;
            height: 48px;
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 14pt;
            text-align: center;
            line-height: 48px;
            border-radius: 4px;
        }

        .order-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .meta-table td {
            padding: 8px 12px;
            font-size: 10pt;
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

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #0369a1;
            margin-top: 14px;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #bae6fd;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 6px 8px;
            text-align: right;
            border: 1px solid #0f172a;
        }

        .data-table td {
            padding: 6px 8px;
            font-size: 9.5pt;
            border: 1px solid #cbd5e1;
            text-align: right;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .badge-confirmed {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .badge-backup {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .notes-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-right: 4px solid #f59e0b;
            padding: 8px 12px;
            font-size: 9.5pt;
            margin-bottom: 16px;
            color: #92400e;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            font-size: 8.5pt;
            color: #64748b;
            text-align: center;
        }

        .ltr-text {
            direction: ltr !important;
            unicode-bidi: embed !important;
            display: inline-block;
            font-family: sans-serif;
        }
    </style>
</head>
<body>

    <!-- Header Table -->
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="logo-placeholder">شركة النقل السريع</div>
                <div style="font-size: 9pt; color: #64748b; margin-top: 4px;">نظام إدارة وتوجيه السائقين</div>
            </td>
            <td style="width: 50%; text-align: left; vertical-align: middle;">
                <div class="order-title">بيان تشغيل رحلة</div>
                <div style="font-size: 11pt; font-weight: bold; color: #0284c7; margin-top: 2px;">
                    رقم الطلب: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->code }}</span>
                </div>
                <div style="font-size: 8.5pt; color: #64748b; margin-top: 4px;">
                    تاريخ الطباعة: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $printedAt }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Order Metadata -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">خط السير (من)</td>
            <td class="meta-value">{{ $order->from_location }}</td>
            <td class="meta-label">خط السير (إلى)</td>
            <td class="meta-value">{{ $order->to_location }}</td>
        </tr>
        <tr>
            <td class="meta-label">موعد التحرك</td>
            <td class="meta-value"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->scheduled_at->format('Y-m-d H:i') }}</span></td>
            <td class="meta-label">حالة الطلب</td>
            <td class="meta-value"><strong>{{ $order->status->label() }}</strong></td>
        </tr>
        <tr>
            <td class="meta-label">الرحلات المطلوبة</td>
            <td class="meta-value"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->trips_needed }}</span> (المؤكد: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->confirmed_count }}</span>)</td>
            <td class="meta-label">الاحتياط المطلوب</td>
            <td class="meta-value"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->backup_needed }}</span> (الحالي: <span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $order->backup_count }}</span>)</td>
        </tr>
        <tr>
            <td class="meta-label">المقابل المادي</td>
            <td class="meta-value">{{ $order->pay_amount ? number_format((float) $order->pay_amount, 2) . ' ج.م' : 'حسب الاتفاق' }}</td>
            <td class="meta-label">نوع الحمولة</td>
            <td class="meta-value">{{ $order->cargo_type ?: 'غير محدد' }}</td>
        </tr>
    </table>

    @if(!empty($order->notes))
    <div class="notes-box">
        <strong>تعليمات وملاحظات الرحلة:</strong> {{ $order->notes }}
    </div>
    @endif

    <!-- Confirmed Drivers Section -->
    <div class="section-title">السائقون المؤكدون للرحلة ({{ count($confirmedRecipients) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%; text-align: center;">#</th>
                <th style="width: 28%;">اسم السائق</th>
                <th style="width: 24%; text-align: center;">رقم الهاتف</th>
                <th style="width: 22%; text-align: center;">وقت التأكيد</th>
                <th style="width: 18%; text-align: center;">الحالة / النتيجة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($confirmedRecipients as $rec)
            <tr>
                <td style="text-align: center; font-weight: bold;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->sequence_number ?? $loop->iteration }}</span></td>
                <td>{{ $rec->driver?->name ?: 'كابتن (غير مسجل)' }}</td>
                <td style="text-align: center;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->driver?->phone }}</span></td>
                <td style="text-align: center;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->responded_at ? $rec->responded_at->format('Y-m-d H:i') : '—' }}</span></td>
                <td style="text-align: center;">
                    @if($rec->outcome)
                        <span class="badge-confirmed">{{ $rec->outcome->label() }}</span>
                    @else
                        <span class="badge-confirmed">مؤكد</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 12px;">لا يوجد سائقون مؤكدون حتى الآن.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Backup Drivers Section -->
    <div class="section-title">السائقون الاحتياط ({{ count($backupRecipients) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%; text-align: center;">#</th>
                <th style="width: 32%;">اسم السائق</th>
                <th style="width: 28%; text-align: center;">رقم الهاتف</th>
                <th style="width: 22%; text-align: center;">وقت الاستجابة</th>
                <th style="width: 10%; text-align: center;">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($backupRecipients as $rec)
            <tr>
                <td style="text-align: center; font-weight: bold;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->sequence_number ?? $loop->iteration }}</span></td>
                <td>{{ $rec->driver?->name ?: 'كابتن (غير مسجل)' }}</td>
                <td style="text-align: center;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->driver?->phone }}</span></td>
                <td style="text-align: center;"><span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">{{ $rec->responded_at ? $rec->responded_at->format('Y-m-d H:i') : '—' }}</span></td>
                <td style="text-align: center;"><span class="badge-backup">احتياط</span></td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 12px;">لا يوجد سائقون في قائمة الاحتياط.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        تم استخراج هذا البيان آليًا عبر نظام إدارة السائقين — صفحة {PAGENO} من {nbpg}
    </div>

</body>
</html>
