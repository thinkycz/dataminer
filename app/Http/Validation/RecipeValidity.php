<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Thinkycz\LaravelCore\Validation\BaseValidity;
use Thinkycz\LaravelCore\Validation\Validity;

class RecipeValidity
{
    /**
     * Base validity.
     */
    public BaseValidity $baseValidity;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseValidity = new BaseValidity();
    }

    /**
     * Id validation rules.
     */
    public function id(): Validity
    {
        return $this->baseValidity->id()->exists('recipes', 'id');
    }

    /**
     * Created at validation rules.
     */
    public function createdAt(): Validity
    {
        return $this->baseValidity->dateTime();
    }

    /**
     * Updated at validation rules.
     */
    public function updatedAt(): Validity
    {
        return $this->baseValidity->dateTime();
    }

    /**
     * Name validation rules.
     */
    public function name(): Validity
    {
        return $this->baseValidity->make()->varchar(160, 2);
    }

    /**
     * Start URL validation rules.
     */
    public function startUrl(): Validity
    {
        return $this->baseValidity->make()->text(2048, 8)->url();
    }

    /**
     * Supported collector source formats.
     */
    public function sourceType(): Validity
    {
        return $this->baseValidity->make()->varchar(16)->inString(['website', 'json', 'csv', 'xml']);
    }

    /**
     * Instructions validation rules.
     */
    public function instructions(): Validity
    {
        return $this->baseValidity->make()->longText(20_000, 10);
    }
}
