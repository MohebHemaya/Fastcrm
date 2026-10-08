<?php

namespace App\Models;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property WhatsAppMessageDirection $direction
 * @property string|null $provider_message_id
 * @property WhatsAppMessageType $type
 * @property string|null $template_name
 * @property array|null $payload
 * @property string|null $body
 * @property WhatsAppMessageStatus $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $order_id
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Driver $driver
 */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'driver_id',
        'direction',
        'provider_message_id',
        'type',
        'template_name',
        'payload',
        'body',
        'status',
        'error_code',
        'error_message',
        'order_id',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => WhatsAppMessageDirection::class,
            'type' => WhatsAppMessageType::class,
            'status' => WhatsAppMessageStatus::class,
            'payload' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
