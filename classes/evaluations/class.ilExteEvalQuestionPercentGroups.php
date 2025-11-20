<?php

/**
 * Provide the mean reached percentage of maximum points from all assigned of this question
 */
class ilExteEvalQuestionPercentGroups extends ilExteEvalQuestion implements ilExteEvalQuestionOverview
{
    /**
     * evaluation provides a single value for the overview level
     */
    protected bool $provides_value = true;

    /**
     * list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
     */
    protected array $allowed_test_types = [self::TEST_TYPE_FIXED];

    /**
     * list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
     */
    protected array $allowed_question_types = [];

    /**
     * specific prefix of language variables (lowercase classname is default)
     */
    protected ?string $lang_prefix = 'qst_percent_groups';

    // active_ids of the best and worst test results
    private ?array $high_active_ids = null;
    private ?array $low_active_ids = null;

    // number of correct answers to the question in the group (indexed by question_id)
    private array $high_correct = [];
    private array $low_correct = [];


    /**
     * Get the available parameters for this evaluation
     * @return ilExteStatParam[]
     */
    public function getAvailableParams(): array
    {
        return array(
            ilExteStatParam::_create('limit', ilExteStatParam::TYPE_INT, 27),
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
        $this->calculate($a_question_id);

        if (empty($this->high_active_ids) || empty($this->low_active_ids)) {
            return ilExteStatValue::_create(null, ilExteStatValue::TYPE_NUMBER, 2, ilExteStatValue::ALERT_UNKNOWN);
        }

        return ilExteStatValue::_create(
            2 * ($this->high_correct[$a_question_id] - $this->low_correct[$a_question_id]) /
            (count($this->high_active_ids) + count($this->low_active_ids)),
            ilExteStatValue::TYPE_NUMBER,
            2
        );
    }

    public function getOverviewSpanningHeader(): ?string
    {
        return $this->txt('title_long');
    }

    public function getOverviewColumns(): array
    {
        $limit = (int) $this->getParam('limit');

        return [
            ilExteStatColumn::_create(
                'percent_groups_high',
                sprintf($this->txt('high_percent'), $limit),
                ilExteStatColumn::SORT_NUMBER,
                sprintf($this->txt('high_percent_info'), $limit)
            ),
            ilExteStatColumn::_create(
                'percent_groups_low',
                sprintf($this->txt('low_percent'), $limit),
                ilExteStatColumn::SORT_NUMBER,
                sprintf($this->txt('low_percent_info'), $limit)
            ),
        ];
    }

    public function getOverviewValues(int $question_id): array
    {
        $this->calculate($question_id);

        return [
            empty($this->high_active_ids)
                ? ilExteStatValue::_create(null, ilExteStatValue::TYPE_PERCENTAGE, 2, ilExteStatValue::ALERT_UNKNOWN)
                : ilExteStatValue::_create(
                    100 * ($this->high_correct[$question_id] ?? 0) / count($this->high_active_ids),
                    ilExteStatValue::TYPE_PERCENTAGE,
                    2
                ),

            empty($this->low_active_ids)
                ? ilExteStatValue::_create(null, ilExteStatValue::TYPE_PERCENTAGE, 2, ilExteStatValue::ALERT_UNKNOWN)
                : ilExteStatValue::_create(
                    100 * ($this->low_correct[$question_id] ?? 0) / count($this->low_active_ids),
                    ilExteStatValue::TYPE_PERCENTAGE,
                    2
                ),
        ];
    }

    private function calculate(int $question_id): void
    {
        if ($this->high_active_ids === null || $this->low_active_ids === null) {
            $this->initGroups();
        }

        $max_points = $this->data->getQuestion($question_id)?->maximum_points ?? 0;

        if (!isset($this->high_correct[$question_id])) {
            $this->high_correct[$question_id] = 0;
            foreach ($this->high_active_ids as $active_id) {
                if ($this->data->getAnswer($question_id, $active_id)?->reached_points === $max_points) {
                    $this->high_correct[$question_id]++;
                }
            }
        }

        if (!isset($this->low_correct[$question_id])) {
            $this->low_correct[$question_id] = 0;
            foreach ($this->low_active_ids as $active_id) {
                if ($this->data->getAnswer($question_id, $active_id)?->reached_points === $max_points) {
                    $this->low_correct[$question_id]++;
                }
            }
        }
    }

    private function initGroups(): void
    {
        $participants = $this->data->getAllParticipants();
        usort($participants, fn($p1, $p2) => $p1->current_reached_points <=> $p2->current_reached_points);

        $num = (int) (count($participants) * $this->getParam('limit') / 100);

        $low = array_slice($participants, 0, $num);
        $high = array_slice($participants, -$num, $num);

        $this->high_active_ids = array_map(fn($p) => $p->active_id, $high);
        $this->low_active_ids = array_map(fn($p) => $p->active_id, $low);
    }
}
