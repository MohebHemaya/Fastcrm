import { describe, it, expect } from 'vitest';
import { questionService } from '@/services/questionService';
import { settingsService } from '@/services/settingsService';

describe('questionService & settingsService', () => {
  it('fetches questions successfully', async () => {
    const questions = await questionService.getQuestions();
    expect(questions).toHaveLength(2);
    expect(questions[0].key).toBe('vehicle_type');
    expect(questions[0].options_count).toBe(3);
  });

  it('creates question with options', async () => {
    const created = await questionService.createQuestion({
      key: 'city_pref',
      type: 'single_choice',
      text_ar: 'المدينة المفضلة؟',
      is_required: true,
      is_filter: true,
      is_active: true,
      options: [
        { value: 'cairo', label_ar: 'القاهرة' },
        { value: 'giza', label_ar: 'الجيزة' },
      ],
    });

    expect(created.key).toBe('city_pref');
    expect(created.text_ar).toBe('المدينة المفضلة؟');
  });

  it('toggles question active state', async () => {
    const res = await questionService.toggleActive(1);
    expect(res.id).toBe(1);
    expect(res.is_active).toBe(false);
  });

  it('reorders questions successfully', async () => {
    await expect(
      questionService.reorderQuestions([
        { id: 1, sort_order: 2 },
        { id: 2, sort_order: 1 },
      ])
    ).resolves.not.toThrow();
  });

  it('sends test WhatsApp question poll successfully', async () => {
    const res = await questionService.testSend(1, '01278842350');
    expect(res.provider_message_id).toBe('ws_poll_1');
    expect(res.phone).toBe('01278842350');
  });

  it('fetches and updates settings successfully', async () => {
    const data = await settingsService.getSettings();
    expect(data.settings.questionnaire_reminder_hours).toBe(24);

    await expect(
      settingsService.updateSettings({
        questionnaire_reminder_hours: 48,
      })
    ).resolves.not.toThrow();
  });
});
