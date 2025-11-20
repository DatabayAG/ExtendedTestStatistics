<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatParam
 */
class ilExteStatParam
{
    /**
     * Defined parameter types
     */
    public const TYPE_FLOAT = 'float';
    public const TYPE_INT = 'int';
    public const TYPE_BOOLEAN = 'bool';
    public const TYPE_STRING = 'string';

    /**
     * Name of the parameter (should be unique within an evaluation class)
     */
    public string $name;

    /**
     * Type of the parameter
     */
    public string $type;

    /**
     * actual value
     * @var mixed
     */
    public $value;


    /**
     * Create a parameter
     * @param mixed $a_value
     */
    public static function _create(string $a_name, string $a_type = self::TYPE_INT, $a_value = 0): ilExteStatParam
    {
        $param = new self();
        $param->name = $a_name;
        $param->type = $a_type;
        $param->value = $a_value;

        return $param;
    }
}
