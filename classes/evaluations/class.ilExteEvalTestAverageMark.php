<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Evaluate the distribution of grades in a test
 */
class ilExteEvalTestAverageMark extends ilExteEvalTest
{
    public const NUMBERS_ABSOLUTE = 'absolute';
    public const NUMBERS_RELATIVE = 'relative';

    /**
     * evaluation provides a single value for the overview level
     */
    protected bool $provides_value = true;

    /**
     * evaluation provides data for a details screen
     */
    protected bool $provides_details = true;

    /**
     * evaluation provides a chart
     */
    protected bool $provides_chart = true;

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
    protected ?string $lang_prefix = 'tst_avg_mark';


    /**
     * Get the charts to be provided on the test overview page
     * @return string[] titles indexed by keys of details
     */
    public function getOverviewCharts(): array
    {
        return [
            self::NUMBERS_ABSOLUTE => $this->txt('chart_absolute'),
            self::NUMBERS_RELATIVE => $this->txt('chart_relative'),
        ];
    }

    /**
     * Get a title for the details screen
     */
    public function getDetailsTitle(): string
    {
        return $this->txt('details_title');
    }


    /**
     * Get a description for the details screen
     */
    public function getDetailsDescription(): string
    {
        return $this->txt('details_description');
    }


    /**
     * Calculate and get the single value for a test
     * gets the grade level for the average percentage reached by all participants
     */
    protected function calculateValue(): ilExteStatValue
    {
        $sum = 0;
        $count = 0;
        foreach ($this->data->getAllParticipants() as $participant) {
            if ($participant->current_maximum_points > 0) {
                $sum += 100 * $participant->current_reached_points / $participant->current_maximum_points;
                $count++;
            }
        }

        if ($count > 0) {
            $mark = $this->data->getMarkByPercent($sum / $count);
            if ($mark !== null) {
                return ilExteStatValue::_create($mark->getShortName(), ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_NONE, false, ilExteStatValue::ALIGN_RIGHT);
            }
        }

        return ilExteStatValue::_create($this->txt('undefined'), ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_UNKNOWN, false, ilExteStatValue::ALIGN_RIGHT);
    }


    /**
     * Calculate details for a test identified by a key
     */
    protected function calculateDetails(): ilExteStatDetails
    {
        return $this->calculateDetailsByKey(self::NUMBERS_ABSOLUTE);
    }

    /**
     * Calculate details for a test identified by a key
     */
    protected function calculateDetailsByKey(string $key): ilExteStatDetails
    {
        $unknown_value = ilExteStatValue::_create($this->txt('undefined'), ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_UNKNOWN);

        $details = new ilExteStatDetails();
        $details->columns = array(
            0 => ilExteStatColumn::_create('short_name', $this->txt('short_name'), ilExteStatColumn::SORT_TEXT),
            1 => ilExteStatColumn::_create('official_name', $this->txt('official_name'), ilExteStatColumn::SORT_TEXT),
            2 => ilExteStatColumn::_create('min_percent', $this->txt('min_percent'), ilExteStatColumn::SORT_NUMBER),
            3 => ilExteStatColumn::_create('participants_count', $this->txt('participants_count'), ilExteStatColumn::SORT_NUMBER, '', $key == self::NUMBERS_ABSOLUTE),
            4 => ilExteStatColumn::_create('participants_percent', $this->txt('participants_percent'), ilExteStatColumn::SORT_NUMBER, '', $key == self::NUMBERS_RELATIVE)
        );

        $total = 0;
        $counts = [0 => 0];
        foreach ($this->data->getAllMarks() as $mark) {
            $counts[$mark->getMarkId()] = 0;
        }
        foreach ($this->data->getAllParticipants() as $participant) {
            $total++;
            if ($participant->current_maximum_points == 0) {
                $counts[0]++;
            } else {
                $percent = 100 * $participant->current_reached_points / $participant->current_maximum_points;
                $mark = $this->data->getMarkByPercent($percent);
                if ($mark !== null) {
                    $counts[$mark->getMarkId()]++;
                } else {
                    $counts[0]++;
                }
            }
        }

        foreach ($this->data->getAllMarks() as $mark) {
            $details->rows[] = [
                'short_name' => ilExteStatValue::_create($mark->getShortName(), ilExteStatValue::TYPE_TEXT),
                'official_name' => ilExteStatValue::_create($mark->getOfficialName(), ilExteStatValue::TYPE_TEXT),
                'min_percent' => ilExteStatValue::_create($mark->getMinPercent(), ilExteStatValue::TYPE_PERCENTAGE),
                'participants_count' => ilExteStatValue::_create($counts[$mark->getMarkId()], ilExteStatValue::TYPE_NUMBER, 0),
                'participants_percent' => $total == 0 ? $unknown_value : ilExteStatValue::_create(100 * $counts[$mark->getMarkId()] / $total, ilExteStatValue::TYPE_PERCENTAGE)
            ];
        }
        if ($counts[0] > 0) {
            $details->rows[] = [
                'short_name' => ilExteStatValue::_create($this->txt('undefined'), ilExteStatValue::TYPE_TEXT),
                'official_name' => ilExteStatValue::_create($this->txt('undefined_mark'), ilExteStatValue::TYPE_TEXT),
                'min_percent' => $unknown_value,
                'passed' => $unknown_value,
                'participants_count' => ilExteStatValue::_create(0, ilExteStatValue::TYPE_NUMBER, 0),
                'participants_percent' => $total == 0 ? $unknown_value : ilExteStatValue::_create(100 * $counts[0] / $total, ilExteStatValue::TYPE_PERCENTAGE)
            ];
        }

        return $details;
    }

    /**
     * Get the definition of an excel chart that should be included on a details page
     */
    public function getExcelChartDefinition(): ?ilExteStatExcelChart
    {
        return new ilExteStatExcelChart(
            $this->getDetailsTitle(),
            'short_name',
            'participants_count'
        );
    }

    /**
     * Get the chart created by this evaluation
     */
    public function getChart(?string $key = self::NUMBERS_ABSOLUTE): ilChart
    {
        $details = clone $this->calculateDetailsByKey($key);
        $details->chartType = ilExteStatDetails::CHART_BARS;

        if ($key == self::NUMBERS_ABSOLUTE) {
            /** @var ilExteStatValue[] $row */
            $max = 0;
            foreach ($details->rows as $row) {
                if ($row['participants_count']->type == ilExteStatValue::TYPE_NUMBER) {
                    $row['short_name']->value = $row['short_name']->value . ' (' . $row['participants_count']->value . ')';
                    if ($row['participants_count']->value > $max) {
                        $max = $row['participants_count']->value;
                    }
                }
            }
            $details->columns[3]->isChartData = true;
            $details->chartLines = $this->getIntegerTicks($max);

        } else {

            /** @var ilExteStatValue[] $row */
            foreach ($details->rows as $row) {
                if ($row['participants_percent']->type == ilExteStatValue::TYPE_PERCENTAGE) {
                    $row['short_name']->value = $row['short_name']->value . ' (' . round($row['participants_percent']->value, 2) . '%)';
                    $row['participants_percent']->value = round($row['participants_percent']->value * 100);
                }
            }

            $details->columns[4]->isChartData = true;
            $details->chartLines = [0 => '0',  2500 => '25%', 5000 => '50%', 7500 => '75%', 10000 => '100%'];
        }

        return $this->generateChart($details);
    }

    protected function getIntegerTicks($value)
    {
        $factor = 1;
        $temp = $value;
        while ($temp > 100) {
            $factor = $factor * 10;
            $temp = $temp / 10;
        }

        if ($temp <= 10) {
            $steps = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        } elseif ($temp <= 20) {
            $steps = [0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20];
        } elseif ($temp <= 50) {
            $steps = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50];
        } else {
            $steps = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        }

        $values = [];
        foreach ($steps as $step) {
            $stepval = $step * $factor;
            $values[$stepval] = $stepval;
            if ($stepval >= $value) {
                break;
            }
        }
        return $values;
    }
}
