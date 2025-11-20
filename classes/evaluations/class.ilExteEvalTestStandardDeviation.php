<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Example evaluation for a whole test
 */
class ilExteEvalTestStandardDeviation extends ilExteEvalTest
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
    protected ?string $lang_prefix = 'tst_standarddeviation';


    /**
     * Calculate and get the single value for a test
     * It sorts the list of currently results and returns the middle value
     * if the number of attempts are odd, or return the average between the
     * two middle values if the list number of attemps are even.
     */
    protected function calculateValue(): ilExteStatValue
    {
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        $value->value = null;

        //Needed values
        $participants_data = $this->data->getAllParticipants();
        $basic_test_values = $this->data->getBasicTestValues();
        $mean = $basic_test_values['tst_eval_mean_of_reached_points'];

        $value_data = array();
        foreach ($participants_data as $participant) {
            $value_data[$participant->active_id] = $participant->current_reached_points;
        }

        //If more than one participant, then calculate.
        if (count($value_data) > 1) {
            //Fetch the sum of squared differences between total score and it's mean
            $sum_sq_diff = $this->sumOfPowersOfDifferenceToMean($value_data, $mean->value, 2);

            //Calculate Standard deviation
            $value->value = sqrt($sum_sq_diff / (count($value_data) - 1));
        } else {
            $value->comment = $this->txt("only_one_participant");
            $value->alert = ilExteStatValue::ALERT_MEDIUM;
        }

        return $value;
    }


    /**
     * Calculate the sum of powers of the difference from values to their mean
     * (intermediate calculation for the standard deviation)
     *
     * @return float|int            calculated sum
     */
    protected function sumOfPowersOfDifferenceToMean(array $data, float $mean, int $power = 2)
    {
        $sum_power_diff = 0.0;

        //Fetch the sum of squared differences between total score and it's mean
        foreach ($data as $id => $item) {
            $sum_power_diff += pow((float) $item - $mean, $power);
        }

        return $sum_power_diff;
    }
}
