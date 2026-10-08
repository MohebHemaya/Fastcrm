<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a WhatsApp API error is permanent (4xx except 429). */
class WhatsAppPermanentException extends RuntimeException {}
