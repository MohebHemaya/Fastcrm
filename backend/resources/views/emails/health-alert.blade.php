<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنبيه صحة النظام</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            direction: rtl;
            text-align: right;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header {
            border-bottom: 2px solid #ef4444;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .title {
            color: #b91c1c;
            font-size: 20px;
            font-weight: bold;
            margin: 0;
        }
        .warning-list {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .warning-item {
            color: #991b1b;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .warning-item:last-child {
            margin-bottom: 0;
        }
        .metric-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 16px;
        }
        .metric-table th, .metric-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: right;
        }
        .footer {
            margin-top: 24px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">⚠️ تنبيه عاجل: رصد مشاكل في تشغيل النظام</h1>
        </div>

        <p>تم رصد مؤشرات حرجة في نظام إدارة السائقين تتطلب المتابعة الفورية:</p>

        <div class="warning-list">
            @foreach($warnings as $warning)
                <div class="warning-item">• {{ $warning }}</div>
            @endforeach
        </div>

        @if(!empty($healthData))
            <h3>ملخص حالة النظام الحالية:</h3>
            <table class="metric-table">
                <tr>
                    <th>مجدول المهام (Cron):</th>
                    <td>{{ $healthData['scheduler']['status'] ?? 'غير معروف' }} (منذ {{ $healthData['scheduler']['age_minutes'] ?? 0 }} دقيقة)</td>
                </tr>
                <tr>
                    <th>قائمة انتظار المهام:</th>
                    <td>{{ $healthData['queue']['backlog_size'] ?? 0 }} مهمة معلقة</td>
                </tr>
                <tr>
                    <th>المهام الفاشلة:</th>
                    <td>{{ $healthData['failed_jobs']['count'] ?? 0 }} مهمة فاشلة</td>
                </tr>
                <tr>
                    <th>رسائل واتساب فاشلة (24 ساعة):</th>
                    <td>{{ $healthData['whatsapp']['failed_messages_24h'] ?? 0 }} رسالة</td>
                </tr>
            </table>
        @endif

        <div class="footer">
            <p>هذا تنبيه آلي صادر من نظام إدارة السائقين وطلبات العمل.</p>
        </div>
    </div>
</body>
</html>
