<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatDetails
 */
class ilExteStatDetails
{
    public const CHART_BARS = 'bars';
    public const CHART_PIE = 'pie';
    public const CHART_SPIDER = 'spider';

    /**
     * Individual message for empty details
     */
    protected ?string $emptyMessage = null;

    /**
     * Table columns
     * @var ilExteStatColumn[]
     */
    public array $columns = [];

    /**
     * Table rows
     * rownum => colname => ilExteStatValue
     * @var ilExteStatValue[][]
     */
    public array $rows = [];


    /**
     * Type of the chart to be generated
     */
    public ?string $chartType = null;

    /**
     * Index of the column to define the X axis
     */
    public int $chartLabelsColumn = 0;

    /**
     * Horizontal lines to be presented in a bar chart
     * This also allow to set a maximum value for the diagram
     * Lines will be auto-generated, if null
     * value => label
     */
    public ?array $chartLines = null;

    /**
     * Custom HTML for the evaluation
     */
    public string $customHTML = '';


    /**
     * Get the message for empty details
     */
    public function getEmptyMessage(): ?string
    {
        return $this->emptyMessage;
    }

    /**
     * Get the message for empty details
     * @param string	$message
     * @return self
     */
    public function setEmptyMessage($message)
    {
        $this->emptyMessage = $message;
        return $this;
    }
}
