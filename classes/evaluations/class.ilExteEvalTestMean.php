<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Example evaluation for a whole test
 */
class ilExteEvalTestMean extends ilExteEvalTest
{
    /**
     * evaluation provides a single value for the overview level
     */
    protected bool $provides_value = true;

    /**
     * evaluation provides data for a details screen
     */
    protected bool $provides_details = false;

    /**
     * list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
     */
    protected array $allowed_test_types = array();

    /**
     * list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
     */
    protected array $allowed_question_types = array();

    /**
     * specific prefix of language variables (lowercase classname is default)
     */
    protected ?string $lang_prefix = 'tst_mean';


    /**
     * Calculate and get the single value for a test
     * Gets the mean score for all current scored attemps of this test

     */
    protected function calculateValue(): ilExteStatValue
    {
        $basic_test_values = $this->data->getBasicTestValues();
        return $basic_test_values['tst_eval_mean_of_reached_points']
            ?? ilExteStatValue::_create(null, ilExteStatValue::TYPE_NUMBER, 2, '', ilExteStatValue::ALERT_UNKNOWN);
    }
}
