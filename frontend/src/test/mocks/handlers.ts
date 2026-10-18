import { http, HttpResponse } from 'msw';

let csrfRefreshed = false;
let mockImportStatus = 'ready_for_review';

export const handlers = [
  http.get('/api/v1/health', () => {
    return HttpResponse.json({
      status: 'ok',
      time: '2026-09-30T12:00:00Z',
      db: 'ok',
      data: {
        status: 'ok',
        time: '2026-09-30T12:00:00Z',
        db: 'ok',
      },
      meta: null,
      message: 'النظام يعمل بنجاح.',
    });
  }),

  http.post('/api/v1/broadcasting/auth', () => {
    return HttpResponse.json({
      auth: 'mock-socket-auth-key:mock-signature',
    });
  }),

  http.get('/api/v1/sidebar-badges', () => {
    return HttpResponse.json({
      data: {
        orders_open: 2,
        drivers_needs_review: 1,
        imports_ready: 1,
        failed_messages_24h: 0,
        health_warning: false,
      },
      meta: null,
      message: null,
    });
  }),

  http.get('/api/v1/whatsapp/provider-status', () => {
    return HttpResponse.json({
      data: {
        provider: 'wassenger',
        send_rate_per_minute: 30,
        invite_batch_size: 50,
        free_form_window: 24,
        no_response_days: 3,
      },
      meta: null,
      message: null,
    });
  }),

  http.post('/api/v1/whatsapp/sync-active-inbound', () => {
    return HttpResponse.json({
      data: {
        synced_drivers: 1,
        processed_messages: 0,
      },
      meta: null,
      message: 'تمت مزامنة الرسائل الواردة بنجاح',
    });
  }),

  http.get('/sanctum/csrf-cookie', () => {
    csrfRefreshed = true;
    return new HttpResponse(null, { status: 204 });
  }),

  http.get('/api/v1/auth/me', () => {
    return HttpResponse.json({
      data: {
        id: 1,
        name: 'مدير النظام',
        email: 'admin@example.com',
        is_active: true,
      },
      meta: null,
      message: null,
    });
  }),

  http.post('/api/v1/auth/login', async ({ request }) => {
    const body = (await request.json()) as any;
    if (body.email === 'inactive@example.com') {
      return HttpResponse.json(
        {
          data: null,
          meta: null,
          message: 'الحساب معطل، يرجى التواصل مع الإدارة.',
        },
        { status: 403 }
      );
    }
    if (body.email === 'admin@example.com' && body.password === 'password123') {
      return HttpResponse.json({
        data: {
          id: 1,
          name: 'مدير النظام',
          email: 'admin@example.com',
          is_active: true,
        },
        meta: null,
        message: 'تم تسجيل الدخول بنجاح.',
      });
    }
    return HttpResponse.json(
      {
        data: null,
        meta: {
          errors: {
            email: ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
          },
        },
        message: 'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
        errors: {
          email: ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
        },
      },
      { status: 422 }
    );
  }),

  http.post('/api/v1/auth/logout', () => {
    return HttpResponse.json({
      data: null,
      meta: null,
      message: 'تم تسجيل الخروج بنجاح.',
    });
  }),

  http.patch('/api/v1/auth/password', async ({ request }) => {
    const body = (await request.json()) as any;
    if (body.current_password === 'wrong') {
      return HttpResponse.json(
        {
          data: null,
          meta: {
            errors: {
              current_password: ['كلمة المرور الحالية غير صحيحة.'],
            },
          },
          message: 'البيانات غير صالحة.',
          errors: {
            current_password: ['كلمة المرور الحالية غير صحيحة.'],
          },
        },
        { status: 422 }
      );
    }
    return HttpResponse.json({
      data: null,
      meta: null,
      message: 'تم تغيير كلمة المرور بنجاح.',
    });
  }),

  http.get('/api/v1/activity-logs', () => {
    return HttpResponse.json({
      data: [
        {
          id: 1,
          user: { id: 1, name: 'مدير النظام', email: 'admin@example.com' },
          action: 'login',
          subject_type: null,
          subject_id: null,
          properties: null,
          ip: '127.0.0.1',
          user_agent: 'Mozilla/5.0',
          created_at: '2026-09-30T12:00:00Z',
        },
      ],
      meta: {
        current_page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
      message: null,
    });
  }),

  http.get('/api/v1/test-401', () => {
    return HttpResponse.json(
      {
        data: null,
        meta: null,
        message: 'غير مصرح بالدخول',
      },
      { status: 401 }
    );
  }),

  http.get('/api/v1/test-419', () => {
    if (!csrfRefreshed) {
      return HttpResponse.json(
        {
          data: null,
          meta: null,
          message: 'CSRF token mismatch.',
        },
        { status: 419 }
      );
    }
    return HttpResponse.json({
      data: { retried: true },
      meta: null,
      message: 'نجاح بعد التحديث',
    });
  }),

  http.post('/api/v1/test-422', () => {
    return HttpResponse.json(
      {
        data: null,
        meta: {
          errors: {
            phone: ['رقم الهاتف غير صالح.'],
            email: ['البريد الإلكتروني مطلوب.'],
          },
        },
        message: 'البيانات المدخلة غير صالحة.',
        errors: {
          phone: ['رقم الهاتف غير صالح.'],
          email: ['البريد الإلكتروني مطلوب.'],
        },
      },
      { status: 422 }
    );
  }),

  // Phase 2: WhatsApp Groups
  http.get('/api/v1/whatsapp-groups', () => {
    return HttpResponse.json({
      data: [
        { id: 1, name: 'سائقين القاهرة', drivers_count: 50, created_at: '2026-09-30T10:00:00Z' },
        { id: 2, name: 'سائقين الجيزة', drivers_count: 30, created_at: '2026-09-30T10:00:00Z' },
      ],
      meta: null,
      message: null,
    });
  }),

  // Phase 2: Drivers List
  http.get('/api/v1/drivers', ({ request }) => {
    const url = new URL(request.url);
    const search = url.searchParams.get('search');
    const status = url.searchParams.get('status');

    let list = [
      {
        id: 1,
        phone: '+201012345678',
        name: 'أحمد محمود',
        status: { value: 'active', label: 'نشط' },
        opted_out_at: null,
        notes: 'سائق ممتاز',
        groups: [{ id: 1, name: 'سائقين القاهرة', first_seen_at: '2026-09-30T10:00:00Z' }],
        status_histories: [
          {
            id: 1,
            from_status: null,
            from_status_label: null,
            to_status: 'new',
            to_status_label: 'جديد',
            reason: 'استيراد',
            changed_by: { id: 1, name: 'مدير النظام' },
            created_at: '2026-09-30T10:00:00Z',
          },
          {
            id: 2,
            from_status: 'new',
            from_status_label: 'جديد',
            to_status: 'active',
            to_status_label: 'نشط',
            reason: 'تفعيل السائق',
            changed_by: { id: 1, name: 'مدير النظام' },
            created_at: '2026-09-30T10:05:00Z',
          },
        ],
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T10:05:00Z',
      },
      {
        id: 2,
        phone: '+201198765432',
        name: 'محمد علي',
        status: { value: 'new', label: 'جديد' },
        opted_out_at: null,
        notes: null,
        groups: [{ id: 2, name: 'سائقين الجيزة', first_seen_at: '2026-09-30T10:00:00Z' }],
        status_histories: [],
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T10:00:00Z',
      },
    ];

    if (search) {
      list = list.filter(
        (d) => d.phone.includes(search) || (d.name && d.name.includes(search))
      );
    }
    if (status) {
      list = list.filter((d) => d.status.value === status);
    }

    return HttpResponse.json({
      data: list,
      meta: {
        current_page: 1,
        per_page: 15,
        total: list.length,
        last_page: 1,
      },
      message: null,
    });
  }),

  // Phase 2: Driver Detail
  http.get('/api/v1/drivers/:id', ({ params }) => {
    const id = Number(params.id);
    return HttpResponse.json({
      data: {
        id,
        phone: '+201012345678',
        name: 'أحمد محمود',
        status: { value: 'active', label: 'نشط' },
        opted_out_at: null,
        notes: 'سائق ممتاز',
        groups: [{ id: 1, name: 'سائقين القاهرة', first_seen_at: '2026-09-30T10:00:00Z' }],
        status_histories: [
          {
            id: 1,
            from_status: 'new',
            from_status_label: 'جديد',
            to_status: 'active',
            to_status_label: 'نشط',
            reason: 'تفعيل السائق',
            changed_by: { id: 1, name: 'مدير النظام' },
            created_at: '2026-09-30T10:05:00Z',
          },
        ],
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T10:05:00Z',
      },
      meta: null,
      message: null,
    });
  }),

  // Phase 2: Create Driver
  http.post('/api/v1/drivers', async ({ request }) => {
    const body = (await request.json()) as any;
    if (body.phone === '+201000000000') {
      return HttpResponse.json(
        {
          data: null,
          meta: {
            errors: { phone: ['رقم الهاتف مسجل بالفعل في النظام.'] },
          },
          message: 'رقم الهاتف مسجل بالفعل في النظام.',
          errors: { phone: ['رقم الهاتف مسجل بالفعل في النظام.'] },
        },
        { status: 422 }
      );
    }
    return HttpResponse.json(
      {
        data: {
          id: 10,
          phone: body.phone,
          name: body.name || null,
          status: { value: 'new', label: 'جديد' },
          opted_out_at: null,
          notes: body.notes || null,
          groups: [],
          status_histories: [],
          created_at: '2026-09-30T12:00:00Z',
          updated_at: '2026-09-30T12:00:00Z',
        },
        meta: null,
        message: 'تم إضافة السائق بنجاح.',
      },
      { status: 201 }
    );
  }),

  // Phase 2: Update Driver
  http.patch('/api/v1/drivers/:id', async ({ request, params }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        phone: '+201012345678',
        name: body.name || 'أحمد محمود',
        status: { value: 'active', label: 'نشط' },
        opted_out_at: null,
        notes: body.notes || null,
        groups: [],
        status_histories: [],
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T12:00:00Z',
      },
      meta: null,
      message: 'تم تحديث بيانات السائق بنجاح.',
    });
  }),

  // Phase 2: Change Driver Status
  http.post('/api/v1/drivers/:id/status', async ({ request, params }) => {
    const body = (await request.json()) as any;
    if (body.status === 'trained') {
      return HttpResponse.json(
        {
          data: null,
          meta: null,
          message: 'لا يمكن الانتقال مباشرة من هذه الحالة إلى تم التدريب.',
        },
        { status: 422 }
      );
    }
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        phone: '+201012345678',
        name: 'أحمد محمود',
        status: { value: body.status, label: body.status },
        opted_out_at: body.status === 'opted_out' ? '2026-09-30T12:00:00Z' : null,
        notes: null,
        groups: [],
        status_histories: [],
        created_at: '2026-09-30T10:00:00Z',
        updated_at: '2026-09-30T12:00:00Z',
      },
      meta: null,
      message: 'تم تغيير حالة السائق بنجاح.',
    });
  }),

  // Phase 2: Delete Driver
  http.delete('/api/v1/drivers/:id', () => {
    return HttpResponse.json({
      data: null,
      meta: null,
      message: 'تم حذف السائق بنجاح.',
    });
  }),

  // Phase 2: Upload Import
  http.post('/api/v1/imports', () => {
    return HttpResponse.json(
      {
        data: {
          id: 42,
          original_name: 'test_drivers.xlsx',
          status: 'parsing',
          status_label: 'جاري الفحص',
          user: { id: 1, name: 'مدير النظام' },
          counts: {
            total: 20,
            new: 15,
            duplicate_in_file: 2,
            existing: 1,
            active: 1,
            invalid: 1,
          },
          processed_chunks: 1,
          total_chunks: 1,
          error_message: null,
          created_at: '2026-09-30T12:00:00Z',
          updated_at: '2026-09-30T12:00:00Z',
        },
        meta: null,
        message: 'تم رفع الملف بنجاح وبدء المعالجة.',
      },
      { status: 201 }
    );
  }),

  // Phase 2: Get Import
  http.get('/api/v1/imports/:id', () => {
    return HttpResponse.json({
      data: {
        id: 42,
        original_name: 'test_drivers.xlsx',
        status: mockImportStatus,
        status_label: mockImportStatus === 'completed' ? 'مكتمل' : 'جاهز للمراجعة',
        user: { id: 1, name: 'مدير النظام' },
        counts: {
          total: 20,
          new: 15,
          duplicate_in_file: 2,
          existing: 1,
          active: 1,
          invalid: 1,
        },
        processed_chunks: 1,
        total_chunks: 1,
        error_message: null,
        created_at: '2026-09-30T12:00:00Z',
        updated_at: '2026-09-30T12:00:00Z',
      },
      meta: null,
      message: null,
    });
  }),

  // Phase 2: Get Import Rows
  http.get('/api/v1/imports/:id/rows', ({ request }) => {
    const url = new URL(request.url);
    const classification = url.searchParams.get('classification') || 'new';

    return HttpResponse.json({
      data: [
        {
          id: 101,
          row_number: 2,
          raw_phone: '01012345678',
          normalized_phone: '+201012345678',
          group_name: 'سائقين القاهرة',
          classification,
          classification_label: classification,
          error: classification === 'invalid' ? 'رقم غير صالح' : null,
          driver_id: null,
        },
      ],
      meta: {
        current_page: 1,
        per_page: 15,
        total: 1,
        last_page: 1,
      },
      message: null,
    });
  }),

  // Phase 2: Commit Import
  http.post('/api/v1/imports/:id/commit', () => {
    mockImportStatus = 'completed';
    return HttpResponse.json({
      data: {
        id: 42,
        original_name: 'test_drivers.xlsx',
        status: 'completed',
        status_label: 'مكتمل',
        counts: {
          total: 20,
          new: 15,
          duplicate_in_file: 2,
          existing: 1,
          active: 1,
          invalid: 1,
        },
        processed_chunks: 1,
        total_chunks: 1,
        created_at: '2026-09-30T12:00:00Z',
        updated_at: '2026-09-30T12:05:00Z',
      },
      meta: null,
      message: 'تم حفظ البيانات بنجاح.',
    });
  }),

  // Phase 2: Cancel Import
  http.post('/api/v1/imports/:id/cancel', () => {
    mockImportStatus = 'cancelled';
    return HttpResponse.json({
      data: {
        id: 42,
        original_name: 'test_drivers.xlsx',
        status: 'cancelled',
        status_label: 'ملغي',
        counts: { total: 0, new: 0, duplicate_in_file: 0, existing: 0, active: 0, invalid: 0 },
        processed_chunks: 0,
        total_chunks: 0,
        created_at: '2026-09-30T12:00:00Z',
        updated_at: '2026-09-30T12:01:00Z',
      },
      meta: null,
      message: 'تم إلغاء الاستيراد.',
    });
  }),

  // Phase 4: Questions & Questionnaire
  http.get('/api/v1/questions', () => {
    return HttpResponse.json({
      data: [
        {
          id: 1,
          key: 'vehicle_type',
          type: 'single_choice',
          type_label: 'اختيار من متعدد',
          text_ar: 'ما نوع مركبتك؟',
          version: 1,
          sort_order: 1,
          is_active: true,
          is_required: true,
          is_filter: true,
          options_count: 3,
          answers_count: 12,
          versions_count: 1,
          options: [
            { id: 1, value: 'pickup', label_ar: 'ربع نقل', sort_order: 1 },
            { id: 2, value: 'jumbo', label_ar: 'جامبو', sort_order: 2 },
            { id: 3, value: 'truck', label_ar: 'تريلا', sort_order: 3 },
          ],
        },
        {
          id: 2,
          key: 'has_refrigeration',
          type: 'yes_no',
          type_label: 'نعم / لا',
          text_ar: 'هل السيارة مجهزة بصندوق تبريد؟',
          version: 1,
          sort_order: 2,
          is_active: true,
          is_required: true,
          is_filter: true,
          options_count: 0,
          answers_count: 8,
          versions_count: 1,
          options: [],
        },
      ],
      meta: null,
      message: null,
    });
  }),

  http.post('/api/v1/questions', async ({ request }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json({
      data: {
        id: 3,
        key: body.key,
        type: body.type,
        type_label: 'اختيار',
        text_ar: body.text_ar,
        version: 1,
        sort_order: 3,
        is_active: body.is_active ?? true,
        is_required: body.is_required ?? true,
        is_filter: body.is_filter ?? false,
        options_count: body.options?.length || 0,
        answers_count: 0,
        versions_count: 1,
        options: body.options || [],
      },
      meta: null,
      message: 'تم إضافة السؤال بنجاح',
    });
  }),

  http.patch('/api/v1/questions/:id/toggle-active', ({ params }) => {
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        is_active: false,
      },
      meta: null,
      message: 'تم تغيير حالة السؤال',
    });
  }),

  http.post('/api/v1/questions/reorder', () => {
    return HttpResponse.json({
      data: null,
      meta: null,
      message: 'تم حفظ ترتيب الأسئلة بنجاح',
    });
  }),

  http.post('/api/v1/questions/:id/test-send', async ({ request, params }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json({
      data: {
        provider_message_id: 'ws_poll_' + params.id,
        phone: body?.phone || '01278842350',
      },
      meta: null,
      message: 'تم إرسال السؤال بنجاح كتجربة عبر واتساب (Wassenger)',
    });
  }),

  // Phase 4: Settings
  http.get('/api/v1/settings', () => {
    return HttpResponse.json({
      data: {
        settings: {
          questionnaire_reminder_hours: 24,
          questionnaire_timeout_days: 3,
          send_rate_per_minute: 30,
          invite_batch_size: 50,
          invite_no_response_days: 3,
        },
        definitions: [],
      },
      meta: null,
      message: null,
    });
  }),

  http.put('/api/v1/settings', () => {
    return HttpResponse.json({
      data: null,
      meta: null,
      message: 'تم حفظ الإعدادات بنجاح',
    });
  }),

  // Phase 4: Driver Answers
  http.get('/api/v1/drivers/:id/answers', ({ params }) => {
    return HttpResponse.json({
      data: {
        driver_id: Number(params.id),
        driver_status: 'active',
        driver_status_label: 'شغال معانا',
        needs_review: false,
        review_reason: null,
        next_question: null,
        answers: [
          {
            id: 1,
            question_id: 1,
            question_key: 'vehicle_type',
            question_text: 'ما نوع مركبتك؟',
            version: 1,
            type: 'single_choice',
            type_label: 'اختيار من متعدد',
            display_value: 'ربع نقل',
            value_text: 'ربع نقل',
            value_number: null,
            option_id: 1,
            option_label: 'ربع نقل',
            is_skipped: false,
            answered_at: '2026-10-01T12:00:00Z',
            available_options: [
              { id: 1, value: 'pickup', label_ar: 'ربع نقل' },
              { id: 2, value: 'jumbo', label_ar: 'جامبو' },
            ],
          },
        ],
        history: [],
      },
      meta: null,
      message: null,
    });
  }),

  // Phase 7: Dashboard and Exports
  http.get('/api/v1/dashboard/summary', () => {
    return HttpResponse.json({
      data: {
        drivers_by_status: {
          active: 10,
          new: 5,
          contacted: 2,
          opted_out: 1,
        },
        total_drivers: 18,
        active_drivers_count: 10,
        opted_out_drivers_count: 1,
        orders_this_month: {
          total: 6,
          by_status: {
            completed: 4,
            filled: 1,
            broadcasting: 1,
          },
        },
        offer_acceptance_rate: 75.0,
        avg_time_to_fill_minutes: 35.5,
        monthly_trends: [
          {
            month: '2026-08',
            label: 'أغسطس 2026',
            total_orders: 8,
            completed_orders: 7,
          },
          {
            month: '2026-09',
            label: 'سبتمبر 2026',
            total_orders: 12,
            completed_orders: 10,
          },
          {
            month: '2026-10',
            label: 'أكتوبر 2026',
            total_orders: 6,
            completed_orders: 4,
          },
        ],
        date_range: {
          start_date: null,
          end_date: null,
        },
      },
      meta: null,
      message: null,
    });
  }),

  http.get('/api/v1/dashboard/group-performance', () => {
    return HttpResponse.json({
      data: [
        {
          group_id: 1,
          group_name: 'جروب سائقي الجيزة',
          imported: 50,
          invited: 40,
          interested: 30,
          active: 20,
          declined: 5,
          opted_out: 2,
          conversion_rate: 40.0,
        },
        {
          group_id: 2,
          group_name: 'جروب سائقي القاهرة',
          imported: 80,
          invited: 60,
          interested: 50,
          active: 35,
          declined: 8,
          opted_out: 3,
          conversion_rate: 43.75,
        },
      ],
      meta: null,
      message: null,
    });
  }),

  http.get('/api/v1/exports', () => {
    return HttpResponse.json({
      data: [
        {
          id: 101,
          user_id: 1,
          type: 'drivers',
          params: {},
          status: 'done',
          path: 'exports/drivers_export_101.xlsx',
          error: null,
          expires_at: '2026-10-09T12:00:00Z',
          created_at: '2026-10-02T10:00:00Z',
          updated_at: '2026-10-02T10:01:00Z',
        },
        {
          id: 102,
          user_id: 1,
          type: 'group_performance',
          params: {},
          status: 'queued',
          path: null,
          error: null,
          expires_at: null,
          created_at: '2026-10-02T10:05:00Z',
          updated_at: '2026-10-02T10:05:00Z',
        },
      ],
      meta: null,
      message: null,
    });
  }),

  http.post('/api/v1/exports', async ({ request }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json({
      data: {
        id: 103,
        user_id: 1,
        type: body.type || 'drivers',
        params: body.params || {},
        status: 'queued',
        path: null,
        error: null,
        expires_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
      meta: null,
      message: 'بدأت عملية التصدير في الخلفية بنجاح.',
    });
  }),

  http.get('/api/v1/exports/:id', ({ params }) => {
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        user_id: 1,
        type: 'drivers',
        params: {},
        status: 'done',
        path: `exports/drivers_export_${params.id}.xlsx`,
        error: null,
        expires_at: '2026-10-09T12:00:00Z',
        created_at: '2026-10-02T10:00:00Z',
        updated_at: '2026-10-02T10:01:00Z',
      },
      meta: null,
      message: null,
    });
  }),

  http.get('/api/v1/exports/:id/download', () => {
    const fakeBlob = new Blob(['fake excel content'], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    return new HttpResponse(fakeBlob, {
      status: 200,
      headers: {
        'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition': 'attachment; filename="export.xlsx"',
      },
    });
  }),

  http.get('/api/v1/work-orders/:id/pdf', () => {
    const fakePdfBlob = new Blob(['%PDF-1.4 fake pdf'], {
      type: 'application/pdf',
    });
    return new HttpResponse(fakePdfBlob, {
      status: 200,
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': 'attachment; filename="work_order.pdf"',
      },
    });
  }),

  http.get('/api/v1/drivers/:id/pdf', () => {
    const fakePdfBlob = new Blob(['%PDF-1.4 fake driver pdf'], {
      type: 'application/pdf',
    });
    return new HttpResponse(fakePdfBlob, {
      status: 200,
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': 'attachment; filename="driver.pdf"',
      },
    });
  }),

  http.get('/api/v1/admin/health', () => {
    return HttpResponse.json({
      data: {
        status: 'healthy',
        checked_at: new Date().toISOString(),
        database: {
          status: 'ok',
          driver: 'mysql',
        },
        scheduler: {
          status: 'ok',
          last_heartbeat_at: new Date().toISOString(),
          age_seconds: 45,
          age_minutes: 0.8,
          is_stale: false,
          warning_threshold_minutes: 5,
        },
        queue: {
          backlog_size: 2,
          oldest_job_age_seconds: 15,
          status: 'ok',
        },
        failed_jobs: {
          count: 1,
          recent: [
            {
              id: 99,
              uuid: 'mock-failed-job-uuid-1',
              name: 'App\\Jobs\\SendWhatsAppMessageJob',
              queue: 'default',
              failed_at: new Date().toISOString(),
              exception_preview: 'Meta Graph API 500 Server Error',
            },
          ],
        },
        webhook: {
          last_received_at: new Date().toISOString(),
          age_minutes: 3.5,
        },
        whatsapp: {
          provider: 'wassenger',
          graph_version: 'v25.0',
          failed_messages_24h: 0,
        },
        backups: {
          count: 4,
          last_backup_at: new Date().toISOString(),
        },
      },
      message: 'تم جلب تقرير حالة النظام بنجاح',
    });
  }),

  http.post('/api/v1/admin/health/failed-jobs/:id/retry', () => {
    return HttpResponse.json({
      data: null,
      message: 'تمت إعادة محاولة المهمة بنجاح.',
    });
  }),

  http.delete('/api/v1/admin/health/failed-jobs/:id', () => {
    return HttpResponse.json({
      data: null,
      message: 'تم حذف المهمة الفاشلة بنجاح.',
    });
  }),

  http.post('/api/v1/admin/health/failed-jobs/retry-all', () => {
    return HttpResponse.json({
      data: null,
      message: 'تمت إعادة محاولة جميع المهام الفاشلة.',
    });
  }),

  http.delete('/api/v1/admin/health/failed-jobs/flush', () => {
    return HttpResponse.json({
      data: null,
      message: 'تم حذف جميع المهام الفاشلة.',
    });
  }),

  // Users Management handlers
  http.get('/api/v1/users', () => {
    return HttpResponse.json({
      data: [
        {
          id: 1,
          name: 'المدير العام',
          email: 'admin@fastcrm.local',
          is_active: true,
          last_login_at: '2026-10-04T10:00:00Z',
          created_at: '2026-09-30T12:00:00Z',
        },
        {
          id: 2,
          name: 'مشرف العمليات',
          email: 'operator@fastcrm.local',
          is_active: true,
          last_login_at: '2026-10-03T15:30:00Z',
          created_at: '2026-10-01T09:00:00Z',
        },
        {
          id: 3,
          name: 'مستخدم معطل',
          email: 'inactive@fastcrm.local',
          is_active: false,
          last_login_at: null,
          created_at: '2026-10-02T11:00:00Z',
        },
      ],
      meta: {
        current_page: 1,
        per_page: 15,
        total: 3,
        last_page: 1,
      },
      message: null,
    });
  }),

  http.post('/api/v1/users', async ({ request }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json(
      {
        data: {
          id: 4,
          name: body.name,
          email: body.email,
          is_active: body.is_active ?? true,
          last_login_at: null,
          created_at: new Date().toISOString(),
        },
        message: 'تم إضافة المستخدم بنجاح.',
      },
      { status: 201 }
    );
  }),

  http.put('/api/v1/users/:id', async ({ request, params }) => {
    const body = (await request.json()) as any;
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        name: body.name,
        email: body.email,
        is_active: body.is_active ?? true,
        last_login_at: '2026-10-04T10:00:00Z',
        created_at: '2026-09-30T12:00:00Z',
      },
      message: 'تم تحديث بيانات المستخدم بنجاح.',
    });
  }),

  http.patch('/api/v1/users/:id/toggle-active', ({ params }) => {
    return HttpResponse.json({
      data: {
        id: Number(params.id),
        name: 'المستخدم',
        email: 'user@test.com',
        is_active: false,
        last_login_at: null,
        created_at: '2026-10-01T00:00:00Z',
      },
      message: 'تم تحديث حالة المستخدم بنجاح.',
    });
  }),

  http.delete('/api/v1/users/:id', () => {
    return HttpResponse.json({
      data: null,
      message: 'تم حذف المستخدم بنجاح.',
    });
  }),
];

export const resetMocks = () => {
  csrfRefreshed = false;
  mockImportStatus = 'ready_for_review';
};
