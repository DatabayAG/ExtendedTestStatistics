<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Provide the mean reached percentage of maximum points from all assigned of this question
 */
class ilExteEvalQuestionPercentPoints extends ilExteEvalQuestion
{
    /**
     * evaluation provides a single value for the overview level
     */
    protected bool $provides_value = true;

    /**
     * evaluation provides a chart of the values presented in the overview of questions
     */
    protected bool $provides_overview_chart = true;

    /**
     * evaluation provides data for a details screen
     */
    protected bool $provides_details = false;

    /**
     * evaluation provides data for a details screen
     */
    protected bool $provides_chart = false;

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
    protected ?string $lang_prefix = 'qst_percent_points';


    /**
     * Calculate the single value for a question (to be overwritten)
     *
     * Note:
     * This function will be called for many questions in sequence
     * - Please avoid instantiation of question objects
     * - Please try to cache question independent intermediate results
     */
    protected function calculateValue(int $a_question_id): ilExteStatValue
    {
        $questionObj = $this->data->getQuestion($a_question_id);

        if ($questionObj->assigned_count == 0) {
            return ilExteStatValue::_create(
                0,
                ilExteStatValue::TYPE_PERCENTAGE,
                0,
                $this->txt('not_assigned'),
                ilExteStatValue::ALERT_UNKNOWN
            );
        }

        return ilExteStatValue::_create(
            $questionObj->average_percentage,
            ilExteStatValue::TYPE_PERCENTAGE,
            2
        );
    }


}
