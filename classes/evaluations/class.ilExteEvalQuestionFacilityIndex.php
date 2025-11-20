<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Example evaluation for a whole test
 */
class ilExteEvalQuestionFacilityIndex extends ilExteEvalQuestion
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
    protected ?string $lang_prefix = 'qst_facility';

    /**
     * Get the available parameters for this evaluation
     * @return ilExteStatParam[]
     */
    public function getAvailableParams(): array
    {
        return array(
            ilExteStatParam::_create('min_ans', ilExteStatParam::TYPE_INT, 2),
            ilExteStatParam::_create('min_medium', ilExteStatParam::TYPE_FLOAT, 10),
            ilExteStatParam::_create('min_good', ilExteStatParam::TYPE_FLOAT, 20),
            ilExteStatParam::_create('max_good', ilExteStatParam::TYPE_FLOAT, 80),
            ilExteStatParam::_create('max_medium', ilExteStatParam::TYPE_FLOAT, 90),
        );
    }


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
        // Get Data
        $question_data = $this->data->getQuestion($a_question_id);
        $average_points = $question_data->average_points;

        // Get the actual lowest and highest score for this question
        $lowest_score = $question_data->maximum_points;
        $highest_score = 0.0;
        $count = 0;
        foreach ($this->data->getAnswersForQuestion($a_question_id) as $answerObj) {
            if ($answerObj->answered) {
                if ((float) $answerObj->reached_points < (float) $lowest_score) {
                    $lowest_score = (float) $answerObj->reached_points;
                }
                if ((float) $answerObj->reached_points > (float) $highest_score) {
                    $highest_score = (float) $answerObj->reached_points;
                }
            }
            $count++;
        }

        //Calculate facility index, if possible
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_PERCENTAGE;
        $value->precision = 0;

        // check minimum number of answers
        if ($count < $this->getParam('min_ans')) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_ans_alert'), $this->getParam('min_ans'));
            $value->value = null;
            return $value;
        } elseif ($highest_score == $lowest_score) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = $this->txt('all_scores_identical');
            $value->value = null;
            return $value;
        } else {
            $facility_index = 100 * (($average_points - $lowest_score) / ($highest_score - $lowest_score));
            $value->value = $facility_index;
        }

        // Alert good quality
        if ($this->getParam('min_good') > 0 && $this->getParam('max_good') > 0) {
            if ($value->value >= $this->getParam('min_good') && $value->value <= $this->getParam('max_good')) {
                $value->alert = ilExteStatValue::ALERT_GOOD;
                return $value;
            } else {
                $value->alert = ilExteStatValue::ALERT_BAD;
            }
        }

        // Alert medium quality
        if ($this->getParam('min_medium') > 0 && $this->getParam('max_medium') > 0) {
            if ($value->value >= $this->getParam('min_medium') && $value->value <= $this->getParam('max_medium')) {
                $value->alert = ilExteStatValue::ALERT_MEDIUM;
                return $value;
            } else {
                $value->alert = ilExteStatValue::ALERT_BAD;
            }
        }

        // return value with 'bad' or no alert
        return $value;
    }

}
