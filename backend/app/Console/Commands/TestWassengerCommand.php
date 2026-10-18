<?php

namespace App\Console\Commands;

use App\WhatsApp\WassengerProvider;
use Illuminate\Console\Command;
use Throwable;

class TestWassengerCommand extends Command
{
    protected $signature = 'whatsapp:test-wassenger 
                            {phone : Recipient phone number (e.g. 201278842350)}
                            {--text= : Custom message text}
                            {--poll : Send an interactive WhatsApp Poll}
                            {--token= : Override Wassenger API token}
                            {--device= : Override Wassenger Device ID}';

    protected $description = 'Test sending a WhatsApp message or poll via Wassenger API (https://wassenger.com)';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');
        $text = (string) ($this->option('text') ?: 'السلام عليكم، رسالة اختبار تجريبية من نظام فاست اكسبريس عبر Wassenger ✅');

        if ($token = $this->option('token')) {
            config(['whatsapp.wassenger.token' => $token]);
        }

        if ($device = $this->option('device')) {
            config(['whatsapp.wassenger.device_id' => $device]);
        }

        $configuredToken = config('whatsapp.wassenger.token');
        $configuredDevice = config('whatsapp.wassenger.device_id');

        $this->info('---------------------------------------------');
        $this->info(' Testing Wassenger WhatsApp Integration');
        $this->info('---------------------------------------------');
        $this->line("Target Phone: <comment>{$phone}</comment>");
        $this->line('Device ID   : <comment>'.($configuredDevice ?: '[NOT SET]').'</comment>');
        $this->line('API Token   : <comment>'.($configuredToken ? substr($configuredToken, 0, 8).'...' : '[NOT SET]').'</comment>');
        $this->line("Message Text: <info>{$text}</info>");
        $this->info('---------------------------------------------');

        if (empty($configuredToken)) {
            $this->error('❌ Wassenger API token is missing!');
            $this->warn('Please set WASSENGER_TOKEN in .env or pass --token=');

            return self::FAILURE;
        }

        try {
            $provider = new WassengerProvider;

            if ($this->option('poll')) {
                $this->comment('Sending interactive WhatsApp Poll to Wassenger...');
                $messageId = $provider->sendButtons($phone, $text, [
                    ['id' => 'accept', 'title' => '🟢 قبول الرحلة'],
                    ['id' => 'decline', 'title' => '🔴 اعتذار'],
                ]);
            } else {
                $this->comment('Sending text message to Wassenger...');
                $messageId = $provider->sendText($phone, $text);
            }

            $this->info('✅ Message sent / queued successfully via Wassenger!');
            $this->line("Provider Message ID: <info>{$messageId}</info>");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('❌ Failed to send message via Wassenger: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
