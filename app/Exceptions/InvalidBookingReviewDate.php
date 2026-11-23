<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidBookingReviewDate extends RuntimeException
{
    public function __construct(int $record)
    {
        parent::__construct(__('messages.errors.booking_review_invalid_date', ['record' => $record]));
    }
}
