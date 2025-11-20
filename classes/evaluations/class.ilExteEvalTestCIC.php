<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Example evaluation for a whole test
 */
class ilExteEvalTestCIC extends ilExteEvalTest
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
    protected array $allowed_test_types = array(self::TEST_TYPE_FIXED);

    /**
     * list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
     */
    protected array $allowed_question_types = array();

    /**
     * specific prefix of language variables (lowercase classname is default)
     */
    protected ?string $lang_prefix = 'tst_cic';

    public function getAvailableParams(): array
    {
        return array(
            ilExteStatParam::_create('min_qst', ilExteStatParam::TYPE_INT, 2),
            ilExteStatParam::_create('min_part', ilExteStatParam::TYPE_INT, 2),
            ilExteStatParam::_create('min_medium', ilExteStatParam::TYPE_FLOAT, 0.7),
            ilExteStatParam::_create('min_good', ilExteStatParam::TYPE_FLOAT, 0.8),
        );
    }

    /**
     * Calculate and get the single value for a test
     * It sorts the list of currently results and returns the middle value
     * if the number of attemps are odd, or return the average between the
     * two middle values if the list number of attemps are even.
     *
     * @return ilExteStatValue
     */
    protected function calculateValue(): ilExteStatValue
    {
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        $value->value = null;

        //Get the data we need.
        $data = array();
        $number_of_questions = count($this->data->getAllQuestions());
        $number_of_users = count($this->data->getAllParticipants());

        // check minimum number of questions
        if ($number_of_questions < $this->getParam('min_qst')) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_qst_alert'), $this->getParam('min_qst'));
            return $value;
        }

        // check minimum number of users
        if ($number_of_users < $this->getParam('min_part') && $this->getParam('min_part') > 2) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_part_alert'), $this->getParam('min_part'));
            return $value;
        } elseif ($number_of_users < 2) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_part_alert'), 2);
            return $value;
        }

        //PART1
        $sumofmarkvariance = 0;
        foreach ($this->data->getAllQuestions() as $question_id => $question) {
            $sum = 0.0;
            foreach ($this->data->getAnswersForQuestion($question_id) as $answer) {
                $sum += $answer->reached_points;
            }
            $question_average = $sum / $number_of_users;

            $calc_markvariancesum = 0;
            foreach ($this->data->getAnswersForQuestion($question_id) as $answer) {
                $mark_difference = $answer->reached_points - $question_average;
                $calc_markvariancesum += pow($mark_difference, 2);
            }
            $sumofmarkvariance += $calc_markvariancesum / ($number_of_users - 1);
        }

        //PART2
        $full_participants = $this->data->getAllParticipants();
        $basic_test_values = $this->data->getBasicTestValues();
        $mean = $basic_test_values['tst_eval_mean_of_reached_points'] ?? 0;

        $sum_of_mean = 0;

        foreach ($full_participants as $active_id => $participant) {
            //Calculate
            $sum_of_mean += pow((float) $participant->current_reached_points - (float) $mean->value, 2);
        }

        if ($sum_of_mean == 0) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = $this->txt('sum_of_mean_is_zero');
            return $value;
        }

        $m2 = $sum_of_mean / $number_of_users;
        $k2 = $number_of_users * $m2 / ($number_of_users - 1);

        //GET VALUE
        $value->value = ($number_of_questions / ($number_of_questions - 1)) * (1 - ($sumofmarkvariance / $k2));
        ;



        // Alert good quality
        if ($this->getParam('min_good') > 0) {
            if ($value->value >= $this->getParam('min_good')) {
                $value->alert = ilExteStatValue::ALERT_GOOD;
                return $value;
            } else {
                $value->alert = ilExteStatValue::ALERT_BAD;
            }
        }

        // Alert medium quality
        if ($this->getParam('min_medium') > 0) {
            if ($value->value >= $this->getParam('min_medium')) {
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
