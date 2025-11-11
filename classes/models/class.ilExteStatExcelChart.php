<?php

declare(strict_types=1);

class ilExteStatExcelChart
{
    public function __construct(
        private string $title,
        private string $x_column,
        private string $y_column,
    ) {

    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getXColumn(): string
    {
        return $this->x_column;
    }

    public function getYColumn(): string
    {
        return $this->y_column;
    }
}
