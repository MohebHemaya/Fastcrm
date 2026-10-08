export interface LocationOption {
  key: string;
  label: string;
}

export const EGYPT_GOVERNORATES: LocationOption[] = [
  { key: 'القاهرة', label: 'القاهرة' },
  { key: 'الجيزة', label: 'الجيزة' },
  { key: 'الإسكندرية', label: 'الإسكندرية' },
  { key: 'الشرقية', label: 'الشرقية' },
  { key: 'القليوبية', label: 'القليوبية' },
  { key: 'الدقهلية', label: 'الدقهلية' },
  { key: 'الغربية', label: 'الغربية' },
  { key: 'المنوفية', label: 'المنوفية' },
  { key: 'البحيرة', label: 'البحيرة' },
  { key: 'دمياط', label: 'دمياط' },
  { key: 'بورسعيد', label: 'بورسعيد' },
  { key: 'الإسماعيلية', label: 'الإسماعيلية' },
  { key: 'السويس', label: 'السويس' },
  { key: 'كفر الشيخ', label: 'كفر الشيخ' },
  { key: 'الفيوم', label: 'الفيوم' },
  { key: 'بني سويف', label: 'بني سويف' },
  { key: 'المنيا', label: 'المنيا' },
  { key: 'أسيوط', label: 'أسيوط' },
  { key: 'سوهاج', label: 'سوهاج' },
  { key: 'قنا', label: 'قنا' },
  { key: 'الأقصر', label: 'الأقصر' },
  { key: 'أسوان', label: 'أسوان' },
  { key: 'البحر الأحمر', label: 'البحر الأحمر' },
  { key: 'مطروح', label: 'مطروح' },
  { key: 'الوادي الجديد', label: 'الوادي الجديد' },
  { key: 'شمال سيناء', label: 'شمال سيناء' },
  { key: 'جنوب سيناء', label: 'جنوب سيناء' },
];

export const CITIES_BY_GOVERNORATE: Record<string, LocationOption[]> = {
  القاهرة: [
    { key: 'مدينة نصر', label: 'مدينة نصر' },
    { key: 'مصر الجديدة', label: 'مصر الجديدة' },
    { key: 'التجمع الخامس', label: 'التجمع الخامس' },
    { key: 'المعادي', label: 'المعادي' },
    { key: 'شبرا', label: 'شبرا' },
    { key: 'وسط البلد', label: 'وسط البلد' },
    { key: 'حلوان', label: 'حلوان' },
    { key: 'العبور', label: 'العبور' },
    { key: 'مدينة بدر', label: 'مدينة بدر' },
    { key: 'الشروق', label: 'الشروق' },
    { key: 'عين شمس', label: 'عين شمس' },
    { key: 'المرج', label: 'المرج' },
    { key: 'المقطم', label: 'المقطم' },
    { key: 'القطامية', label: 'القطامية' },
    { key: '15 مايو', label: '15 مايو' },
  ],
  الجيزة: [
    { key: '6 أكتوبر', label: '6 أكتوبر' },
    { key: 'الشيخ زايد', label: 'الشيخ زايد' },
    { key: 'الدقي', label: 'الدقي' },
    { key: 'المهندسين', label: 'المهندسين' },
    { key: 'العجوزة', label: 'العجوزة' },
    { key: 'الهرم', label: 'الهرم' },
    { key: 'فيصل', label: 'فيصل' },
    { key: 'الحوامدية', label: 'الحوامدية' },
    { key: 'البدرشين', label: 'البدرشين' },
    { key: 'العياط', label: 'العياط' },
    { key: 'الصف', label: 'الصف' },
    { key: 'أطفيح', label: 'أطفيح' },
    { key: 'أوسيم', label: 'أوسيم' },
    { key: 'كرداسة', label: 'كرداسة' },
  ],
  الإسكندرية: [
    { key: 'ميناء الإسكندرية', label: 'ميناء الإسكندرية' },
    { key: 'ميناء الدخيلة', label: 'ميناء الدخيلة' },
    { key: 'برج العرب', label: 'برج العرب' },
    { key: 'العامرية', label: 'العامرية' },
    { key: 'سموحة', label: 'سموحة' },
    { key: 'سيدي جابر', label: 'سيدي جابر' },
    { key: 'المنتزه', label: 'المنتزه' },
    { key: 'العجمي', label: 'العجمي' },
    { key: 'محرم بك', label: 'محرم بك' },
    { key: 'الرمل', label: 'الرمل' },
    { key: 'المنشية', label: 'المنشية' },
    { key: 'الجمرك', label: 'الجمرك' },
  ],
  الشرقية: [
    { key: 'العاشر من رمضان', label: 'العاشر من رمضان' },
    { key: 'الزقازيق', label: 'الزقازيق' },
    { key: 'بلبيس', label: 'بلبيس' },
    { key: 'منيا القمح', label: 'منيا القمح' },
    { key: 'فاقوس', label: 'فاقوس' },
    { key: 'أبو حماد', label: 'أبو حماد' },
    { key: 'الصالحية الجديدة', label: 'الصالحية الجديدة' },
    { key: 'أبو كبير', label: 'أبو كبير' },
    { key: 'ههيا', label: 'ههيا' },
    { key: 'ديرب نجم', label: 'ديرب نجم' },
    { key: 'الحسينية', label: 'الحسينية' },
    { key: 'كفر صقر', label: 'كفر صقر' },
  ],
  القليوبية: [
    { key: 'بنها', label: 'بنها' },
    { key: 'شبرا الخيمة', label: 'شبرا الخيمة' },
    { key: 'قليوب', label: 'قليوب' },
    { key: 'الخانكة', label: 'الخانكة' },
    { key: 'القناطر الخيرية', label: 'القناطر الخيرية' },
    { key: 'طوخ', label: 'طوخ' },
    { key: 'كفر شكر', label: 'كفر شكر' },
    { key: 'شبين القناطر', label: 'شبين القناطر' },
    { key: 'قها', label: 'قها' },
    { key: 'الخصوص', label: 'الخصوص' },
  ],
  الدقهلية: [
    { key: 'المنصورة', label: 'المنصورة' },
    { key: 'ميت غمر', label: 'ميت غمر' },
    { key: 'السنبلاوين', label: 'السنبلاوين' },
    { key: 'دكرنس', label: 'دكرنس' },
    { key: 'بلقاس', label: 'بلقاس' },
    { key: 'شربين', label: 'شربين' },
    { key: 'طلخا', label: 'طلخا' },
    { key: 'أجا', label: 'أجا' },
    { key: 'المنزلة', label: 'المنزلة' },
    { key: 'نبروه', label: 'نبروه' },
  ],
  الغربية: [
    { key: 'طنطا', label: 'طنطا' },
    { key: 'المحلة الكبرى', label: 'المحلة الكبرى' },
    { key: 'كفر الزيات', label: 'كفر الزيات' },
    { key: 'زفتى', label: 'زفتى' },
    { key: 'السنطة', label: 'السنطة' },
    { key: 'بسيون', label: 'بسيون' },
    { key: 'سمنود', label: 'سمنود' },
    { key: 'قطور', label: 'قطور' },
  ],
  المنوفية: [
    { key: 'شبين الكوم', label: 'شبين الكوم' },
    { key: 'مدينة السادات', label: 'مدينة السادات' },
    { key: 'قويسنا', label: 'قويسنا' },
    { key: 'أشمون', label: 'أشمون' },
    { key: 'منوف', label: 'منوف' },
    { key: 'بركة السبع', label: 'بركة السبع' },
    { key: 'تلا', label: 'تلا' },
    { key: 'الشهداء', label: 'الشهداء' },
    { key: 'الباجور', label: 'الباجور' },
  ],
  البحيرة: [
    { key: 'دمنهور', label: 'دمنهور' },
    { key: 'كفر الدوار', label: 'كفر الدوار' },
    { key: 'إيتاي البارود', label: 'إيتاي البارود' },
    { key: 'كوم حمادة', label: 'كوم حمادة' },
    { key: 'وادي النطرون', label: 'وادي النطرون' },
    { key: 'أبو المطامير', label: 'أبو المطامير' },
    { key: 'حوش عيسى', label: 'حوش عيسى' },
    { key: 'رشيد', label: 'رشيد' },
    { key: 'إدكو', label: 'إدكو' },
    { key: 'الدلنجات', label: 'الدلنجات' },
  ],
  دمياط: [
    { key: 'دمياط', label: 'دمياط' },
    { key: 'ميناء دمياط', label: 'ميناء دمياط' },
    { key: 'دمياط الجديدة', label: 'دمياط الجديدة' },
    { key: 'رأس البر', label: 'رأس البر' },
    { key: 'كفر سعد', label: 'كفر سعد' },
    { key: 'فارسكور', label: 'فارسكور' },
    { key: 'الزرقا', label: 'الزرقا' },
  ],
  بورسعيد: [
    { key: 'بورسعيد', label: 'بورسعيد' },
    { key: 'ميناء غرب بورسعيد', label: 'ميناء غرب بورسعيد' },
    { key: 'ميناء شرق بورسعيد', label: 'ميناء شرق بورسعيد' },
    { key: 'بورفؤاد', label: 'بورفؤاد' },
    { key: 'حي الضواحي', label: 'حي الضواحي' },
    { key: 'حي الزهور', label: 'حي الزهور' },
  ],
  الإسماعيلية: [
    { key: 'الإسماعيلية', label: 'الإسماعيلية' },
    { key: 'فايد', label: 'فايد' },
    { key: 'القنطرة شرق', label: 'القنطرة شرق' },
    { key: 'القنطرة غرب', label: 'القنطرة غرب' },
    { key: 'التل الكبير', label: 'التل الكبير' },
    { key: 'القصاصين', label: 'القصاصين' },
    { key: 'أبو صوير', label: 'أبو صوير' },
  ],
  السويس: [
    { key: 'السويس', label: 'السويس' },
    { key: 'ميناء العين السخنة', label: 'ميناء العين السخنة' },
    { key: 'ميناء بورتوفيق', label: 'ميناء بورتوفيق' },
    { key: 'ميناء الأدبية', label: 'ميناء الأدبية' },
    { key: 'عتاقة', label: 'عتاقة' },
    { key: 'الأربعين', label: 'الأربعين' },
    { key: 'فيصل', label: 'فيصل' },
  ],
  'كفر الشيخ': [
    { key: 'كفر الشيخ', label: 'كفر الشيخ' },
    { key: 'دسوق', label: 'دسوق' },
    { key: 'فوه', label: 'فوه' },
    { key: 'مطوبس', label: 'مطوبس' },
    { key: 'بلطيم', label: 'بلطيم' },
    { key: 'سيدي سالم', label: 'سيدي سالم' },
    { key: 'الحامول', label: 'الحامول' },
    { key: 'بيلا', label: 'بيلا' },
  ],
  الفيوم: [
    { key: 'الفيوم', label: 'الفيوم' },
    { key: 'الفيوم الجديدة', label: 'الفيوم الجديدة' },
    { key: 'سنورس', label: 'سنورس' },
    { key: 'إطسا', label: 'إطسا' },
    { key: 'طامية', label: 'طامية' },
    { key: 'أبشواي', label: 'أبشواي' },
  ],
  'بني سويف': [
    { key: 'بني سويف', label: 'بني سويف' },
    { key: 'بني سويف الجديدة', label: 'بني سويف الجديدة' },
    { key: 'الواسطى', label: 'الواسطى' },
    { key: 'ناصر', label: 'ناصر' },
    { key: 'إهناسيا', label: 'إهناسيا' },
    { key: 'ببا', label: 'ببا' },
    { key: 'الفشن', label: 'الفشن' },
  ],
  المنيا: [
    { key: 'المنيا', label: 'المنيا' },
    { key: 'المنيا الجديدة', label: 'المنيا الجديدة' },
    { key: 'ملوي', label: 'ملوي' },
    { key: 'مغاغة', label: 'مغاغة' },
    { key: 'بني مزار', label: 'بني مزار' },
    { key: 'سمالوط', label: 'سمالوط' },
    { key: 'أبو قرقاص', label: 'أبو قرقاص' },
  ],
  أسيوط: [
    { key: 'أسيوط', label: 'أسيوط' },
    { key: 'أسيوط الجديدة', label: 'أسيوط الجديدة' },
    { key: 'ديروط', label: 'ديروط' },
    { key: 'القوصية', label: 'القوصية' },
    { key: 'منفلوط', label: 'منفلوط' },
    { key: 'أبو تيج', label: 'أبو تيج' },
    { key: 'أبنوب', label: 'أبنوب' },
  ],
  سوهاج: [
    { key: 'سوهاج', label: 'سوهاج' },
    { key: 'سوهاج الجديدة', label: 'سوهاج الجديدة' },
    { key: 'طهطا', label: 'طهطا' },
    { key: 'جرجا', label: 'جرجا' },
    { key: 'أخميم', label: 'أخميم' },
    { key: 'المراغة', label: 'المراغة' },
    { key: 'البلينا', label: 'البلينا' },
  ],
  قنا: [
    { key: 'قنا', label: 'قنا' },
    { key: 'قنا الجديدة', label: 'قنا الجديدة' },
    { key: 'نجع حمادي', label: 'نجع حمادي' },
    { key: 'دشنا', label: 'دشنا' },
    { key: 'قوص', label: 'قوص' },
    { key: 'فرشوط', label: 'فرشوط' },
    { key: 'أبو تشت', label: 'أبو تشت' },
  ],
  الأقصر: [
    { key: 'الأقصر', label: 'الأقصر' },
    { key: 'طيبة الجديدة', label: 'طيبة الجديدة' },
    { key: 'إسنا', label: 'إسنا' },
    { key: 'أرمنت', label: 'أرمنت' },
    { key: 'القرنة', label: 'القرنة' },
  ],
  أسوان: [
    { key: 'أسوان', label: 'أسوان' },
    { key: 'أسوان الجديدة', label: 'أسوان الجديدة' },
    { key: 'إدفو', label: 'إدفو' },
    { key: 'كوم أمبو', label: 'كوم أمبو' },
    { key: 'نصر النوبة', label: 'نصر النوبة' },
    { key: 'أبو سمبل', label: 'أبو سمبل' },
  ],
  'البحر الأحمر': [
    { key: 'الغردقة', label: 'الغردقة' },
    { key: 'ميناء سفاجا', label: 'ميناء سفاجا' },
    { key: 'رأس غارب', label: 'رأس غارب' },
    { key: 'القصير', label: 'القصير' },
    { key: 'مرسى علم', label: 'مرسى علم' },
  ],
  مطروح: [
    { key: 'مرسى مطروح', label: 'مرسى مطروح' },
    { key: 'العلمين الجديدة', label: 'العلمين الجديدة' },
    { key: 'العلمين', label: 'العلمين' },
    { key: 'الحمام', label: 'الحمام' },
    { key: 'الضبعة', label: 'الضبعة' },
    { key: 'سيوة', label: 'سيوة' },
    { key: 'السلوم', label: 'السلوم' },
  ],
  'الوادي الجديد': [
    { key: 'الخارجة', label: 'الخارجة' },
    { key: 'الداخلة', label: 'الداخلة' },
    { key: 'الفرافرة', label: 'الفرافرة' },
    { key: 'باريس', label: 'باريس' },
  ],
  'شمال سيناء': [
    { key: 'العريش', label: 'العريش' },
    { key: 'بئر العبد', label: 'بئر العبد' },
    { key: 'الشيخ زويد', label: 'الشيخ زويد' },
    { key: 'رفح', label: 'رفح' },
  ],
  'جنوب سيناء': [
    { key: 'شرم الشيخ', label: 'شرم الشيخ' },
    { key: 'طور سيناء', label: 'طور سيناء' },
    { key: 'رأس سدر', label: 'رأس سدر' },
    { key: 'نويبع', label: 'نويبع' },
    { key: 'دهب', label: 'دهب' },
    { key: 'طابا', label: 'طابا' },
  ],
};

export function getCitiesForGovernorate(governorate?: string): LocationOption[] {
  if (!governorate) return [];
  const list = CITIES_BY_GOVERNORATE[governorate] ? [...CITIES_BY_GOVERNORATE[governorate]] : [];
  if (!list.some((c) => c.key === 'أخرى')) {
    list.push({ key: 'أخرى', label: 'مدينة أو منطقة أخرى...' });
  }
  return list;
}

export function formatLocationString(
  governorate?: string,
  city?: string,
  details?: string
): string {
  const parts: string[] = [];
  if (governorate) parts.push(governorate);
  if (city) parts.push(city);
  const base = parts.join(' - ');
  if (details && details.trim()) {
    return base ? `${base} (${details.trim()})` : details.trim();
  }
  return base;
}

export function parseLocationString(fullLocation?: string): {
  governorate?: string;
  city?: string;
  details?: string;
} {
  if (!fullLocation) return {};

  let text = fullLocation.trim();
  let details: string | undefined;

  // Extract (details) if exists at the end
  const matchDetails = text.match(/\((.*?)\)$/);
  if (matchDetails) {
    details = matchDetails[1].trim();
    text = text.replace(/\s*\(.*?\)$/, '').trim();
  }

  // Split by ' - '
  const parts = text.split(' - ').map((p) => p.trim());
  if (parts.length >= 2) {
    return {
      governorate: parts[0],
      city: parts[1],
      details,
    };
  }

  if (parts.length === 1 && parts[0]) {
    // Check if the single part is a governorate
    const isGov = EGYPT_GOVERNORATES.some((g) => g.key === parts[0]);
    if (isGov) {
      return { governorate: parts[0], details };
    }
    return { details: fullLocation };
  }

  return { details };
}
