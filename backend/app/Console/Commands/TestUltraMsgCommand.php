<?php

namespace App\Console\Commands;

use App\WhatsApp\UltraMsgProvider;
use Illuminate\Console\Command;
use Throwable;

class TestUltraMsgCommand extends Command
{
    protected $signature = 'whatsapp:test-ultramsg 
                            {phone : Recipient phone number (e.g. 201278842350)}
                            {--text= : Custom message text}
                            {--token= : Override UltraMsg API token}
                            {--instance= : Override UltraMsg Instance ID}';

    protected $description = 'Test sending a WhatsApp message via UltraMsg API (https://ultramsg.com)';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');
        $text = (string) ($this->option('text') ?: 'السلام عليكم، رسالة اختبار تجريبية من نظام إدارة السائقين عبر UltraMsg ✅');

        if ($token = $this->option('token')) {
            config(['whatsapp.ultramsg.token' => $token]);
        }

        if ($instance = $this->option('instance')) {
            config(['whatsapp.ultramsg.instance_id' => $instance]);
        }

        $configuredToken = config('whatsapp.ultramsg.token');
        $configuredInstance = config('whatsapp.ultramsg.instance_id');

        $this->info('---------------------------------------------');
        $this->info(' Testing UltraMsg WhatsApp Integration');
        $this->info('---------------------------------------------');
        $this->line("Target Phone: <comment>{$phone}</comment>");
        $this->line('Instance ID : <comment>'.($configuredInstance ?: '[NOT SET]').'</comment>');
        $this->line('API Token   : <comment>'.($configuredToken ? substr($configuredToken, 0, 8).'...' : '[NOT SET]').'</comment>');
        $this->line("Message Text: <info>{$text}</info>");
        $this->info('---------------------------------------------');

        if (empty($configuredToken) || empty($configuredInstance)) {
            $this->error('❌ UltraMsg credentials are missing!');
            $this->warn('Please provide --token and --instance or set ULTRAMSG_TOKEN and ULTRAMSG_INSTANCE_ID in .env');

            return self::FAILURE;
        }

        try {
            $this->comment('Sending message to UltraMsg...');
            $provider = new UltraMsgProvider;
            $messageId = $provider->sendText($phone, $text);

            $this->info('✅ Message sent / queued successfully via UltraMsg!');
            $this->line("Provider Message ID: <info>{$messageId}</info>");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('❌ Failed to send message: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
