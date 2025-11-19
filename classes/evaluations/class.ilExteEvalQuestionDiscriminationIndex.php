<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Example evaluation for a whole test
 */
class ilExteEvalQuestionDiscriminationIndex extends ilExteEvalQuestion
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
    protected bool $provides_details = true;

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
    protected ?string $lang_prefix = 'qst_discrimination';


    /**
     * Get the available parameters for this evaluation
     * @return ilExteStatParam[]
     */
    public function getAvailableParams(): array
    {
        return array(
            ilExteStatParam::_create('min_qst', ilExteStatParam::TYPE_INT, 0),
            ilExteStatParam::_create('min_ans', ilExteStatParam::TYPE_INT, 2),
            ilExteStatParam::_create('min_good', ilExteStatParam::TYPE_FLOAT, 0.3),
            ilExteStatParam::_create('min_medium', ilExteStatParam::TYPE_FLOAT, 0.1)
        );
    }

    /**
     * Calculate the discrimination index
     */
    protected function calculateValue(int $a_question_id): ilExteStatValue
    {
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        $value->value = null;

        // check minimum number of total questions
        if (count($this->data->getAllQuestions()) < $this->getParam('min_qst')) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_qst_alert'), $this->getParam('min_qst'));
            return $value;
        }

        // get and check minimum number of answers
        $answers = $this->data->getAnswersForQuestion($a_question_id);
        if (count($answers) < 2) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = $this->plugin->txt('not_enough_answers');
            return $value;
        } elseif (count($answers) < $this->getParam('min_ans')) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = sprintf($this->txt('min_ans_alert'), $this->getParam('min_ans'));
            return $value;
        }

        $question_points = [];
        $other_points = [];
        foreach ($answers as $answer) {
            $participant = $this->data->getParticipant($answer->active_id);
            $question_points[] = $answer->reached_points;
            $other_points[] = $participant->current_reached_points - $answer->reached_points;
        }

        $question_variance = $this->calcVariance($question_points, true);
        if (empty($question_variance)) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = $this->txt('zero_variance_of_question');
            return $value;
        }

        $other_variance = $this->calcVariance($other_points, true);
        if (empty($other_variance)) {
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
            $value->comment = $this->txt('zero_variance_of_other_questions');
            return $value;
        }

        $covariance = $this->calcCovariance($question_points, $other_points, true);
        $discrimination_index = $covariance / sqrt($question_variance * $other_variance);
        $value->value = $discrimination_index;

        // Note on random values
        if ($this->data->getTestType() !== ilExteEvalBase::TEST_TYPE_FIXED) {
            $value->uncertain = true;
            $value->comment = $this->txt('random_test');
        }

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

    /**
     * @inheritdoc
     */
    protected function calculateDetails(int $a_question_id): ilExteStatDetails
    {
        $details = new ilExteStatDetails();
        $details->columns = [
            ilExteStatColumn::_create('active_id', $this->txt('active_id'), ilExteStatColumn::SORT_NUMBER),
            ilExteStatColumn::_create('difference_question_points', $this->txt('difference_question_points'), ilExteStatColumn::SORT_NUMBER, $this->txt('difference_question_points_description')),
            ilExteStatColumn::_create('difference_other_points', $this->txt('difference_other_points'), ilExteStatColumn::SORT_NUMBER, $this->txt('difference_other_points_description')),
        ];

        $active_ids = [];
        $question_points = [];
        $other_points = [];

        $answers = $this->data->getAnswersForQuestion($a_question_id);
        foreach ($answers as $answer) {
            $participant = $this->data->getParticipant($answer->active_id);
            $active_ids[] = $answer->active_id;
            $question_points[$answer->active_id] = $answer->reached_points;
            $other_points[$answer->active_id] = $participant->current_reached_points - $answer->reached_points;
        }

        $mean_question_points = $this->calcMean($question_points);
        $mean_other_points = $this->calcMean($other_points);

        foreach ($active_ids as $active_id) {
            $details->rows[] = [
                'active_id' => ilExteStatValue::_create($active_id, ilExteStatValue::TYPE_NUMBER, 0),
                'difference_question_points' => ilExteStatValue::_create($question_points[$active_id] - $mean_question_points, ilExteStatValue::TYPE_NUMBER, 2),
                'difference_other_points' => ilExteStatValue::_create($other_points[$active_id] - $mean_other_points, ilExteStatValue::TYPE_NUMBER, 2),
            ];
        }

        return $details;
    }

    public function getOverviewChart(array $question_ids = [], ?array $chart_lines = null): ilChart
    {
        return parent::getOverviewChart(
            $question_ids,
            [-100 => '-1', -75 => '-0.75', -50 => '-0.5', -25 => '-0.25', 0 => '0',  25 => '0.25', 50 => '0.5', 75 => '0.75', 100 => '1']
        );
    }
}
