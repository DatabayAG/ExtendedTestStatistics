<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Choice Evaluation
 */
class ilExteEvalQuestionMultipleChoices extends ilExteEvalQuestion implements ilExteEvalQuestionOverview
{
	/**
	 * evaluation provides a single value for the overview level
	 */
	protected bool $provides_value = false;

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
	protected array $allowed_question_types = array('assSingleChoice', 'assMultipleChoice');

	/**
	 * specific prefix of language variables (lowercase classname is default)
	 */
	protected ?string $lang_prefix = 'qst_choices';

    protected ilDBInterface $db;

    /**
     * Details of all questions with allowed type, indexed by question_id
     * @var array<int, ilExteStatDetails>
     */
    private ?array $allowed_details = null;

    /**
     * Constructor
     */
    public function __construct(ilExtendedTestStatisticsPlugin $a_plugin, ilExtendedTestStatisticsCache $a_cache)
    {
        global $DIC;

        $this->db = $DIC->database();
        parent::__construct($a_plugin, $a_cache);
    }

    /**
     * Calculate the single value for a question (to be overwritten)
     *
     * Note:
     * This function will be called for many questions in sequence
     * - Please avoid instantiation of question objects
     * - Please try to cache question independent intermediate results
     */
    protected function calculateValue(int $a_question_id) : ilExteStatValue
    {
        return new ilExteStatValue;
	}


    /**
     * Calculate the details question (to be overwritten)
     */
    protected function calculateDetails(int $a_question_id) : ilExteStatDetails
	{
        /** @var assMultipleChoice $question */
        $question = assQuestion::instantiateQuestion($a_question_id);
		if (!is_object($question))
		{
			return new ilExteStatDetails();
		}

        /** @var ASS_AnswerMultipleResponse[] $options */
        $options = $question->getAvailableAnswerOptions();

        // answer details
        $details = new ilExteStatDetails();
        $details->columns = array (
            ilExteStatColumn::_create('index',$this->txt('index'), ilExteStatColumn::SORT_NUMBER),
			ilExteStatColumn::_create('points',$this->txt('points'),ilExteStatColumn::SORT_NUMBER),
			ilExteStatColumn::_create('count',$this->txt('count'),ilExteStatColumn::SORT_NUMBER, '', true),
            ilExteStatColumn::_create('percent',$this->txt('percent'),ilExteStatColumn::SORT_NUMBER, $this->txt('percent_info'), false),
            ilExteStatColumn::_create('choice', $this->txt('choice'), ilExteStatColumn::SORT_TEXT)
        );
        $details->chartType = ilExteStatDetails::CHART_BARS;
        $details->chartLabelsColumn = 3;

        $option_count = array();
        foreach ($options as $key => $option) {
            $option_count[$key] = 0;
        }

        /** @var ilExteStatSourceAnswer $answer */
        $count_participants = count($this->data->getAllParticipants());
        foreach ($this->data->getAnswersForQuestion($a_question_id, true) as $answer) {
            $result = $this->db->queryF(
                "SELECT * FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s AND authorized = 1",
                array("integer", "integer", "integer"),
                array($answer->active_id, $answer->pass, $a_question_id)
            );

            while ($data = $this->db->fetchAssoc($result))
            {
                if (isset($data["value1"]) && isset($options[$data["value1"]]))
                {
                    $option_count[$data["value1"]]++;
                    break;  // count option only once per participant
                }
            }
        }

        $i = 1;
        foreach ($options as $key => $option) {
            $percent = 0;
            if ($count_participants > 0) {
                $percent = 100 * ($option_count[$key] / $count_participants);
            }

           $details->rows[] = array(
                'index' => ilExteStatValue::_create($i++, ilExteStatValue::TYPE_NUMBER, 0),
			    'points' => ilExteStatValue::_create($option->getPoints(), ilExteStatValue::TYPE_NUMBER, 2),
			    'count' => ilExteStatValue::_create($option_count[$key], ilExteStatValue::TYPE_NUMBER, 0),
                'percent' => ilExteStatValue::_create($percent, ilExteStatValue::TYPE_PERCENTAGE, 0),
                'choice' => ilExteStatValue::_create($option->getAnswertext(), ilExteStatValue::TYPE_TEXT, 0)
            );
        }

        return $details;
    }

    public function getOverviewSpanningHeader(): ?string
    {
        return $this->plugin->txt('answer_frequency');
    }

    /**
     * Get additional columns fpr the questions overview table
     * - as much as neeed for the question with the most answer options
     * @return ilExteStatColumn[]
     */
    public function getOverviewColumns(): array
    {
        $columns = [];

        $max = 0;
        foreach ($this->getAllowedDetails() as $detail) {
            $max = max($max, count($detail->rows));
        }

        $i = 1;
        while ($i <= $max) {
            $columns[] = ilExteStatColumn::_create((string) $i, (string) $i,ilExteStatColumn::SORT_NONE,
                $this->plugin->txt('answer_frequency_info'));
            $i++;
        }

        return $columns;
    }

    /**
     * @return ilExteStatValue[]
     */
    public function getOverviewValues(int $question_id): array
    {
        $values = [];
        $details = $this->getAllowedDetails()[$question_id] ?? new ilExteStatDetails();

        // get the min count of answers for a correct answer option
        $min_correct = null;
        foreach ($details->rows as $row) {
            if ($row['points']->value > 0) {
                if ($min_correct === null) {
                    $min_correct = $row['count']->value;
                } else {
                    $min_correct = min($min_correct, $row['count']->value);
                }
            }
        };
        $min_correct = (int) $min_correct;

        /** @var ilExteStatValue[] $row */
        foreach ($details->rows as $row) {
            $value = ilExteStatValue::_create(
                $row['percent']->value,
                ilExteStatValue::TYPE_PERCENTAGE,
                2,
                $row['choice']->value
                );

            if ($row['points']->value > 0) {
                $value->weight = ilExteStatValue::WEIGHT_STRONG;
            }
            elseif ($row['count']->value > $min_correct) {
                $value->weight = ilExteStatValue::WEIGHT_STRONG;
                $value->color = ilExteStatValue::COLOR_RED;
            }
            $values[] = $value;
        }
        return $values;
    }

    private function getAllowedDetails()
    {
        if ($this->allowed_details === null) {
            $this->allowed_details = [];
            foreach ($this->data->getAllQuestions() as $question) {
                if (in_array($question->question_type, $this->allowed_question_types)) {
                    $this->allowed_details[$question->question_id] = $this->getDetails($question->question_id);
                }
            }
        }
        return $this->allowed_details;
    }
}