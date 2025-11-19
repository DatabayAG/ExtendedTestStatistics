<?php

class ilExteStatSourceMark
{
    private float $min_percent;
    private bool $passed;
    private ?string $short_name;
    private ?string $official_name;
    private int $mark_id;

    public function __construct(
        int $mark_id,
        float $min_percent = 0,
        bool $passed = false,
        string $short_name = '',
        string $official_name = ''
    ) {
        $this->mark_id = $mark_id;
        $this->min_percent = $min_percent;
        $this->passed = $passed;
        $this->short_name = $short_name;
        $this->official_name = $official_name;
    }

    public function getMarkId(): int
    {
        return $this->mark_id;
    }

    public function getMinPercent(): float
    {
        return $this->min_percent;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getShortName(): string
    {
        return $this->short_name;
    }

    public function getOfficialName(): string
    {
        return $this->official_name;
    }

}
