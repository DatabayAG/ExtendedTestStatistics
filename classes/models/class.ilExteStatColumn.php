<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatColumn
 */
class ilExteStatColumn
{
    /**
     * Defined column types
     */
    public const SORT_NONE = '';
    public const SORT_TEXT = 'text';
    public const SORT_NUMBER = 'number';


    /**
     * Unique name of the column in the table
     * (should be like a variable name)
     */
    public string $name = '';


    /**
     * Title to be shown in the header
     * @var string|int|null
     */
    public mixed $title = null;


    /**
     * Sorting type
     */
    public string $sort = self::SORT_NONE;


    /**
     * Comment for the title
     */
    public string $comment = '';


    /**
     * The columns should be used as chart data
     */
    public bool $isChartData = false;


    /**
     * Create a column by parameters
     */
    public static function _create(
        string $a_name,
        mixed $a_title = null,
        string $a_sort = self::SORT_NONE,
        string $a_comment = '',
        bool $a_is_chart_data = false
    ): ilExteStatColumn {
        $column = new self();
        $column->name = $a_name;
        $column->title = $a_title ?? $a_name;
        $column->sort = $a_sort;
        $column->comment = $a_comment;
        $column->isChartData = $a_is_chart_data;

        return $column;
    }


}
