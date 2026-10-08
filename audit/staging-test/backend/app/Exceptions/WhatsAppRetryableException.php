<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when a WhatsApp API error is retryable (429, 5xx). */
class WhatsAppRetryableException extends RuntimeException {}
