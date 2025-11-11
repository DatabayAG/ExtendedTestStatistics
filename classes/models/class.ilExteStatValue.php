<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatValue
 */
class ilExteStatValue
{
    /**
     * Defined cell types
     */
    public const TYPE_ALERT = 'alert';
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_DURATION = 'duration';
    public const TYPE_BOOLEAN = 'bool';

    /**
     * Defined alert modes
     */
    public const ALERT_NONE = 'none';			// no alert
    public const ALERT_UNKNOWN = 'unknown';	// grey icon or background
    public const ALERT_GOOD = 'good';			// green icon or background
    public const ALERT_MEDIUM = 'medium';		// yellow icon or background
    public const ALERT_BAD = 'bad';			// red icon or background

    /**
     * Defined alignment in tables
     */
    public const ALIGN_LEFT = 'left';
    public const ALIGN_RIGHT = 'right';

    public const COLOR_BLACK = 'black';
    public const COLOR_RED = 'red';

    public const WEIGHT_NORMAL = 'normal';
    public const WEIGHT_STRONG = 'strong';

    /**
     * Type of the value
     *
     * TYPE_ALERT will show an icon with $value as alt text
     * @var string
     */
    public $type = self::TYPE_TEXT;

    /**
     * Value to be displayed
     *
     * The data type and semantics depends on $type
     * TYPE_ALERT: string (used as alt text of the icon or for text in Excel cells)
     * TYPE_TEXT: string
     * TYPE_NUMBER float or integer (will be rounded with $precision)
     * TYPE_PERCENTAGE: float (0 to 100, the '%' sign will be added when displayed
     * TYPE_DATETIME: ilDateTime
     * TYPE_DURATION: integer (seconds)
     * TYPE_BOOLEAN: boolean
     * @var mixed
     */
    public $value = null;

    /**
     * Display precision to be used for TYPE_NUMERIC and TYPE_PERCENTAGE
     */
    public int $precision = 2;

    /**
     * Optional textual comment
     *
     * This may be shown as tooltip or additional info text
     */
    public string $comment = '';


    /**
     * Optional alert sign
     * If $type is TYPE_ALERT, then only the sign will be shown
     * Otherwise the sign will be shown beneath the text
     */
    public string $alert = self::ALERT_NONE;


    /**
     * Optional uncertainty
     *
     * Can be set if the calculated value has an uncertainty, e.g. due to a small data set
     * This will be shown with a specific markup
     */
    public bool $uncertain = false;


    /**
     * Optional alignment
     * If null, then the standard alignment of the type will be taken
     */
    public ?string $align = null;


    public string $color = self::COLOR_BLACK;
    public string $weight = self::WEIGHT_NORMAL;

    /**
     * Create a value by parameters
     */
    public static function _create(
        $a_value,
        string $a_type = self::TYPE_TEXT,
        int $a_precision = 2,
        string $a_comment = '',
        string $a_alert = self::ALERT_NONE,
        bool $a_uncertain = false,
        ?string $align = null
    ): ilExteStatValue {
        $value = new self();
        $value->value = $a_value;
        $value->type = $a_type;
        $value->precision = $a_precision;
        $value->comment = $a_comment;
        $value->alert = $a_alert;
        $value->uncertain = $a_uncertain;
        $value->align = $align;

        return $value;
    }

    /**
     * Get a list of  demo values for testing purpose
     * @return ilExteStatValue[]
     */
    public static function _getDemoValues(): array
    {
        return array(
            self::_create('Hallo', self::TYPE_TEXT, 0, 'Text'),
            self::_create(0.13, self::TYPE_NUMBER, 2, 'Float'),
            self::_create(48, self::TYPE_NUMBER, 0, 'Integer'),
            self::_create(null, self::TYPE_NUMBER, 0, 'Unknown Integer', self::ALERT_UNKNOWN, true),
            self::_create(true, self::TYPE_BOOLEAN, 0, 'True Boolean', self::ALERT_GOOD, true),
            self::_create(false, self::TYPE_BOOLEAN, 0, 'False Boolean', self::ALERT_BAD, true),
            self::_create('Alert Text', self::TYPE_ALERT, 2, 'Alert', self::ALERT_MEDIUM, true),
            self::_create(new ilDateTime(time(), IL_CAL_UNIX), self::TYPE_DATETIME, 0, 'DateTime'),
            self::_create(10, self::TYPE_DURATION, 0, 'Duration'),
            self::_create(50, self::TYPE_PERCENTAGE, 0, 'Percentge'),
            self::_create(50.52, self::TYPE_PERCENTAGE, 2, 'Percentge')

        );
    }
}
