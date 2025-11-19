<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Base class for statistical evaluation of questions in a test
 */
abstract class ilExteEvalQuestion extends ilExteEvalBase
{
    /**
     * evaluation provides a chart of the values presented in the overview of questions
     */
    protected bool $provides_overview_chart = false;

    /**
     * evaluation provides a chart of the values presented in the overview of questions
     */
    public function providesOverviewChart(): bool
    {
        return $this->provides_overview_chart;
    }

    /**
     * Calculate the single value for a question (to be overwritten)
     *
     * Note:
     * This function will be called for many questions in sequence
     * - Please avoid instanciation of question objects
     * - Please try to cache question independent intermediate results
     */
    protected function calculateValue(int $a_question_id): ilExteStatValue
    {
        return new ilExteStatValue();
    }

    /**
     * Calculate the details question (to be overwritten)
     */
    protected function calculateDetails(int $a_question_id): ilExteStatDetails
    {
        return new ilExteStatDetails();
    }

    /**
     * Get a title for the details screen
     */
    public function getDetailsTitle(int $a_question_id): string
    {
        return $this->data->getQuestion($a_question_id)->question_title;
    }

    /**
     * Get a description for the details screen
     */
    public function getDetailsDescription(int $a_question_id): string
    {
        return $this->getTitle();
    }

    /**
     * Get the calculated value
     * This checks if the test type matches before

     */
    final public function getValue(int $a_question_id): ilExteStatValue
    {
        if (!$this->isTestTypeAllowed()) {
            $message = $this->getMessageNotAvailableForTestType();
            return ilExteStatValue::_create(null, ilExteStatValue::TYPE_TEXT, 0, $message, ilExteStatValue::ALERT_UNKNOWN);
        } elseif (!$this->isQuestionTypeAllowed($this->data->getQuestion($a_question_id)->question_type)) {
            $message = $this->getMessageNotAvailableForQuestionType();
            return ilExteStatValue::_create(null, ilExteStatValue::TYPE_TEXT, 0, $message, ilExteStatValue::ALERT_UNKNOWN);
        }

        $value = $this->cache->read(get_called_class(), 'value' . $a_question_id);
        if (!isset($value)) {
            $value = $this->calculateValue($a_question_id);
            $this->cache->write(get_called_class(), 'value' . $a_question_id, serialize($value));
        } else {
            $value = unserialize($value);
        }
        return $value;
    }

    /**
     * Get the calculated details
     * This checks if the test type matches before
     */
    final public function getDetails(int $a_question_id): ilExteStatDetails
    {
        if (!$this->isTestTypeAllowed()) {
            $message = $this->getMessageNotAvailableForTestType();
            $details = new ilExteStatDetails();
            return $details->setEmptyMessage($message);
        } elseif (!$this->isQuestionTypeAllowed($this->data->getQuestion($a_question_id)->question_type)) {
            $message = $this->getMessageNotAvailableForQuestionType();
            $details = new ilExteStatDetails();
            return $details->setEmptyMessage($message);
        }

        $details = $this->cache->read(get_called_class(), 'details' . $a_question_id);
        if (!isset($details)) {
            $details = $this->calculateDetails($a_question_id);
            $this->cache->write(get_called_class(), 'details' . $a_question_id, serialize($details));
        } else {
            $details = unserialize($details);
        }
        return $details;
    }

    /**
     * Get the chart created by this evaluation
     */
    final public function getChart(int $a_question_id): ilChart
    {
        return $this->generateChart($this->getDetails($a_question_id));
    }

    /**
     * Generate a standard bar chart from the calculated values
     * - All values will be multiplied by 100 and rounded to integer for display of the bars
     * - Chart lines are an assoc array of display value => label
     * - Default lines will be generated if not given as parameter
     * - A mean of the values is asses as special line
     */
    public function getOverviewChart(array $question_ids = [], ?array $chart_lines = null): ilChart
    {

        $questions = $this->data->getAllQuestions();

        $num_values = 0;
        $sum_values = 0;
        $min_value = null;
        $max_value = null;
        $is_percent = false;
        $value_suffix = '';

        $values = [];
        foreach ($questions as $question) {
            if ($question->assigned_count > 0) {

                $value = $this->calculateValue($question->question_id);
                $values[$question->question_id] = $value;

                $num_values++;
                $sum_values += $value->value;

                if (!isset($min_value) || $value->value < $min_value) {
                    $min_value = $value->value;
                }
                if (!isset($max_value) || $value->value > $max_value) {
                    $max_value = $value->value;
                }

                if ($value->type == ilExteStatValue::TYPE_PERCENTAGE) {
                    $is_percent = true;
                    $value_suffix = '%';
                }

            }
        }

        $details = new ilExteStatDetails();
        $details->columns = array(
            ilExteStatColumn::_create('question_pos', $this->plugin->txt('question_position'), ilExteStatColumn::SORT_NUMBER),
            ilExteStatColumn::_create('question_title', $this->plugin->txt('question_title'), ilExteStatColumn::SORT_TEXT),
            ilExteStatColumn::_create('question_value', $this->txt('title_long'), ilExteStatColumn::SORT_NUMBER, '', true),
        );

        if (is_array($chart_lines)) {
            $details->chartLines = $chart_lines;
        } elseif ($is_percent) {
            $details->chartLines = [0 => '0',  2500 => '25%', 5000 => '50%', 7500 => '75%', 10000 => '100%'];
        } elseif ($max_value <= 1) {
            $details->chartLines = [0 => '0',  25 => '0.25', 50 => '0.5', 75 => '0.75', 100 => '1'];
        } else {
            $details->chartLines = [
                0 => '0',
                round(25 * $max_value) => round(0.25 * $max_value, 2),
                round(50 * $max_value) => round(0.50 * $max_value, 2),
                round(75 * $max_value) => round(0.75 * $max_value, 2)
            ];
        }

        if ($num_values > 0) {
            $average_value = round($sum_values / $num_values, 2);
            $details->chartLines[round(100 * $average_value)] = '<strong>' . $this->plugin->txt('average_sign') . ' ' . $average_value . $value_suffix . '</strong>';
            ksort($details->chartLines);
        }

        $details->chartType = ilExteStatDetails::CHART_BARS;
        $details->chartLabelsColumn = 1;


        foreach ($question_ids as $question_id) {
            if (isset($questions[$question_id]) && isset($values[$question_id])) {
                $question = $questions[$question_id];

                $title = $question->question_title;
                $title = ilStr::shortenText($question->question_title, 0, 20);
                if ($this->data->getTestType() == ilExteEvalBase::TEST_TYPE_FIXED) {
                    $title .= ' (' . $question->order_position . ')';
                }

                $value = $values[$question_id]; // filled above
                if (isset($value->value)) {
                    $value->value = round($value->value * 100);
                }

                $details->rows[] = array(
                    'question_pos' => ilExteStatValue::_create($question->order_position, ilExteStatValue::TYPE_NUMBER, 0),
                    'question_title' => ilExteStatValue::_create($title),
                    'question_value' => $values[$question_id],
                );
            }
        }

        /**
         * @var ilChartGrid
         */
        $chart = $this->generateChart($details);

        return $chart;
    }
}
