import type { Question } from '@/types/question';
import type { WorkOrderFilterOperator } from '@/types/workOrder';

export interface CargoTypeOption {
  key: string;
  label: string;
  category?: string;
  requiresRefrigeration?: boolean;
  suggestedVehicleTypes?: string[];
  description?: string;
}

export const CARGO_TYPES: CargoTypeOption[] = [
  {
    key: 'بضائع عامة وجافة',
    label: 'بضائع عامة وجافة',
    category: 'عام',
    description: 'شحنات عادية كرتون، منتجات جافة لا تتطلب تبريد',
  },
  {
    key: 'مواد غذائية مبردة',
    label: 'مواد غذائية مبردة (ألبان، عصائر، خضار مبرد)',
    category: 'غذائي مبرد',
    requiresRefrigeration: true,
    description: 'تتطلب سيارة مجهزة بصندوق تبريد للحفاظ على درجة الحرارة',
  },
  {
    key: 'مجمدات ولحوم وأسماك',
    label: 'مجمدات ولحوم وأسماك مجمدة',
    category: 'تجميد',
    requiresRefrigeration: true,
    description: 'تتطلب سيارة تجميد بدرجات حرارة منخفضة (-18°C فأقل)',
  },
  {
    key: 'أدوية ومستلزمات طبية مبردة',
    label: 'أدوية ومستلزمات طبية (سلسلة تبريد)',
    category: 'طبي',
    requiresRefrigeration: true,
    description: 'منتجات دوائية تتطلب رقابة تبريد صارمة',
  },
  {
    key: 'خضار وفاكهة طازجة',
    label: 'خضار وفاكهة طازجة',
    category: 'زراعي',
    description: 'نقل محاصيل زراعية طازجة من المزارع أو أسواق الجملة',
  },
  {
    key: 'أجهزة كهربائية ومنزلية',
    label: 'أجهزة كهربائية وإلكترونيات',
    category: 'إلكترونيات',
    suggestedVehicleTypes: ['jumbo', 'van', 'truck'],
    description: 'أجهزة حساسة تتطلب صندوق مغلق وحماية من العوامل الجوية',
  },
  {
    key: 'أثاث ومنقولات',
    label: 'أثاث ومفروشات ومنقولات',
    category: 'أثاث',
    suggestedVehicleTypes: ['jumbo', 'van'],
    description: 'نقل منقولات وأثاث مكتبي أو منزلي',
  },
  {
    key: 'مواد بناء وحديد وسيراميك',
    label: 'مواد بناء وسيراميك وحديد',
    category: 'ثقيل',
    suggestedVehicleTypes: ['truck', 'jumbo'],
    description: 'حمولة ثقيلة تتطلب شاحنات نقل ثقيل أو جامبو حمولات عالية',
  },
  {
    key: 'طرود وشحنات تجارة إلكترونية',
    label: 'طرود وشحنات سريعة (E-Commerce)',
    category: 'طرود',
    suggestedVehicleTypes: ['pickup', 'van'],
    description: 'توزيع طرود سريعة وتوصيل للميل الأخير',
  },
  {
    key: 'كيماويات وبويات',
    label: 'كيماويات وبويات ومواد صناعية',
    category: 'صناعي',
    description: 'خامات ومواد صناعية معبأة بأمان',
  },
  {
    key: 'أخرى',
    label: 'أخرى (تحديد نوع آخر يدويًا...)',
    category: 'مخصص',
  },
];

export interface AutoFilterSuggestion {
  question_id: number;
  question_key: string;
  operator: WorkOrderFilterOperator;
  value: any;
  reason: string;
}

/**
 * Returns automated filter recommendations based on the selected cargo type
 * by analyzing available system filter questions.
 */
export function getFilterSuggestionsForCargoType(
  cargoType: string,
  filterQuestions: Question[]
): AutoFilterSuggestion[] {
  if (!cargoType) return [];

  const matchedCargo = CARGO_TYPES.find(
    (c) => c.key === cargoType || c.label === cargoType
  );

  const suggestions: AutoFilterSuggestion[] = [];

  // 1. Check refrigeration requirements
  if (matchedCargo?.requiresRefrigeration) {
    const refrigerationQuestion = filterQuestions.find(
      (q) =>
        q.key === 'has_refrigeration' ||
        q.text_ar.includes('تبريد') ||
        q.text_ar.includes('تجميد')
    );

    if (refrigerationQuestion) {
      // In yes_no or single_choice with yes/true
      const value =
        refrigerationQuestion.type === 'yes_no'
          ? 'yes'
          : refrigerationQuestion.options.find(
              (o) => o.value === 'yes' || o.label_ar.includes('نعم')
            )?.value || 'yes';

      suggestions.push({
        question_id: refrigerationQuestion.id,
        question_key: refrigerationQuestion.key,
        operator: 'eq',
        value,
        reason: `تم اقتراح فلتر (سيارة مجهزة بتبريد) تلقائيًا لأن نوع الحمولة المختارة (${cargoType}) يتطلب تبريد/تجميد.`,
      });
    }
  }

  // 2. Check suggested vehicle types (optional, if single vehicle match preferred)
  if (
    matchedCargo?.suggestedVehicleTypes &&
    matchedCargo.suggestedVehicleTypes.length === 1
  ) {
    const vehicleQuestion = filterQuestions.find(
      (q) => q.key === 'vehicle_type' || q.text_ar.includes('نوع سيارتك')
    );

    if (vehicleQuestion) {
      const targetType = matchedCargo.suggestedVehicleTypes[0];
      const matchingOption = vehicleQuestion.options.find(
        (o) => o.value === targetType
      );
      if (matchingOption) {
        suggestions.push({
          question_id: vehicleQuestion.id,
          question_key: vehicleQuestion.key,
          operator: 'eq',
          value: matchingOption.value,
          reason: `نوع الحمولة (${cargoType}) يتطلب سيارة من نوع (${matchingOption.label_ar}).`,
        });
      }
    }
  }

  return suggestions;
}
