<?php

declare(strict_types=1);

interface ilExteEvalQuestionOverview
{
    /**
     * Get a header text that should span over all columns
     */
    public function getOverviewSpanningHeader() : ?string;

    /**
     * Get the columns that should be added to the overview
     * @return ilExteStatColumn[]
     */
    public function getOverviewColumns(): array;

    /**
     * Get the values for a question
     * This must match the array size and order of getColumns())
     * @return ilExteStatValue[]
     */
    public function getOverviewValues(int $question_id): array;
}