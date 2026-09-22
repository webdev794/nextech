<?php

namespace App\Support\Courier;

/**
 * Thrown by any CourierProvider implementation that can't answer right now
 * (not configured, unreachable, malformed response, ...). Courier's
 * passthroughs catch this uniformly and fall back to MockCourierProvider.
 */
class CourierProviderUnavailableException extends \RuntimeException
{
}
