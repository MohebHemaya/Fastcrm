<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case TEXT = 'text';
    case BUTTON = 'button';
    case LIST = 'list';
    case TEMPLATE = 'template';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case OTHER = 'other';
}
