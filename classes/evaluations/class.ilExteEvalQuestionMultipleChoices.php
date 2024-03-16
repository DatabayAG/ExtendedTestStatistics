<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Choice Evaluation
 */
class ilExteEvalQuestionMultipleChoices extends ilExteEvalQuestion
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
        $question = assQuestion::_instantiateQuestion($a_question_id);
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
            ilExteStatColumn::_create('choice', $this->txt('choice'), ilExteStatColumn::SORT_TEXT)
        );
        $details->chartType = ilExteStatDetails::CHART_BARS;
        $details->chartLabelsColumn = 3;

        $option_count = array();
        foreach ($options as $key => $option)
        {
            $option_count[$key] = 0;
        }

        /** @var ilExteStatSourceAnswer $answer */
        foreach ($this->data->getAnswersForQuestion($a_question_id, true) as $answer)
        {
            $result = $this->db->queryF(
                "SELECT * FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s",
                array("integer", "integer", "integer"),
                array($answer->active_id, $answer->pass, $a_question_id)
            );

            while ($data = $this->db->fetchAssoc($result))
            {
                if (isset($data["value1"]) && isset($options[$data["value1"]]))
                {
                    $option_count[$data["value1"]]++;
                }
            }
        }

        foreach ($options as $key => $option)
        {
           $details->rows[] = array(
                'index' => ilExteStatValue::_create($option->getOrder(), ilExteStatValue::TYPE_NUMBER, 0),
			    'points' => ilExteStatValue::_create($option->getPoints(), ilExteStatValue::TYPE_NUMBER, 2),
			    'count' => ilExteStatValue::_create($option_count[$key], ilExteStatValue::TYPE_NUMBER, 0),
                'choice' => ilExteStatValue::_create($option->getAnswertext(), ilExteStatValue::TYPE_TEXT, 0)
            );
        }

        return $details;
    }
}