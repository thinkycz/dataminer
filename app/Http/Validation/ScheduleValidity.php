<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class ScheduleValidity
{
    /**
     * Base validity helpers.
     */
    public BaseValidity $baseValidity;

    /**
     * Create field wrappers.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Allowed cadence names.
     */
    public function cadence(): Validity
    {
        return $this->baseValidity->make()->varchar(32)->inString(['every_15_minutes', 'hourly', 'daily', 'weekly', 'advanced']);
    }

    /**
     * IANA timezone.
     */
    public function timezone(): Validity
    {
        return $this->baseValidity->make()->varchar(80)->timezone();
    }

    /**
     * HH:MM local anchor.
     */
    public function localTime(): Validity
    {
        return $this->baseValidity->make()->varchar(5);
    }

    /**
     * ISO weekday.
     */
    public function weekday(): Validity
    {
        return $this->baseValidity->make()->int(7, 1);
    }

    /**
     * Advanced standard cron expression.
     */
    public function cronExpression(): Validity
    {
        return $this->baseValidity->make()->varchar(100);
    }
}
