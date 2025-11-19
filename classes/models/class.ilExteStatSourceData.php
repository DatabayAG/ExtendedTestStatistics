<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Source data for test and question evaluations
 * Provides data objects
 * - for participants of tte test
 * - for questions which are assigned to the participants
 * - for answers in the relevant pass of each participant (also if not answered)
 * - for basic test and question values
 *
 * A call of load() must be done before any get...() function is used
 * This determines the relevant pass for each participant
 * Only assigned questions are included
 * Only answers of the relevant pass of each participant are included
 */
class ilExteStatSourceData
{
    public const PASS_LAST = 'last';
    public const PASS_BEST = 'best';
    public const PASS_FIRST = 'first';
    public const PASS_SCORED = 'scored';

    protected ilDBInterface $db;
    protected ilLanguage $lng;
    protected ilExtendedTestStatisticsPlugin $plugin;
    protected ilObjTest $object;
    protected ilExtendedTestStatisticsCache $cache;
    protected ilTestEvaluationData $eval;

    /**
     * Pass that should be selected for each participant to get the question and answer data
     * Only questions and answers of these passes (one per participant) will be loaded
     */
    protected string $pass_selection = self::PASS_SCORED;

    /**
     * List of question type titles
     * class => title
     */
    protected array $question_types = [];

    /**
     * List of marks defined in the test (ordered by descending minimum percentage)
     * @var ilExteStatSourceMark[]
     */
    protected array $marks = [];

    /**
     * List of question data
     * @var ilExteStatSourceQuestion[]  (indexed by question_id)
     */
    protected array $questions = [];

    /**
     * List of participant data
     * @var ilExteStatSourceParticipant[]   (indexed by active_id)
     */
    protected array $participants = [];

    /**
     * List of answer data
     * @var ilExteStatSourceAnswer[]    (flat list)
     */
    protected array $answers = [];

    /**
     * List of answer data
     * question_id => active_id => ilExteStatSourceAnswer
     */
    protected array $answers_by_question_id = [];

    /**
     * List of answer data
     * active_id => question_id => ilExteStatSourceAnswer
     */
    protected array $answers_by_active_id = [];

    /**
     * List of basic test values
     * value_id => ilExteStatValue
     */
    protected array $basic_test_values = [];

    /**
     * List of basic question values
     * question_id => value_id => ilExteStatValue
     */
    protected array $basic_question_values = [];


    /**
     * ilExteStatSourceData constructor.
     */
    public function __construct(ilObjTest $a_test_obj, ilExtendedTestStatisticsPlugin $a_plugin, ilExtendedTestStatisticsCache $a_cache)
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->object = $a_test_obj;
        $this->plugin = $a_plugin;
        $this->cache = $a_cache;
    }

    /**
     * Get the test title
     */
    public function getTestTitle(): string
    {
        return (string) $this->object->getTitle();
    }

    /**
     * Get the test type (fixed, random, dynamic)
     */
    public function getTestType(): string
    {
        if ($this->object->isFixedTest()) {
            return ilExteEvalBase::TEST_TYPE_FIXED;
        } elseif ($this->object->isRandomTest()) {
            return ilExteEvalBase::TEST_TYPE_RANDOM;
        } elseif ($this->object->isDynamicTest()) {
            return ilExteEvalBase::TEST_TYPE_DYNAMIC;
        } else {
            return ilExteEvalBase::TEST_TYPE_UNKNOWN;
        }
    }

    /**
     * Get an assoc list of the question types
     * @return	array	class => type name
     */
    public function getQuestionTypes(): array
    {
        return $this->question_types;
    }

    /**
     * Get the selection of the evaluated pass
     */
    public function getPassSelection(): string
    {
        return $this->pass_selection;
    }


    /**
     * Load the source data
     * @see ilObjTest::getUnfilteredEvaluationData()
     */
    public function load(string $a_pass_selection = self::PASS_SCORED): void
    {
        $this->pass_selection = $a_pass_selection;
        $this->cache->setPassSelection($a_pass_selection);

        if (!$this->readFromCache()) {
            $this->readFromTest();
            $this->writeToCache();
        }
    }

    /**
     * Reset all source data
     */
    protected function reset(): void
    {
        $this->marks = [];
        $this->question_types = [];
        $this->questions = [];
        $this->answers = [];
        $this->participants = [];
        $this->answers_by_question_id = [];
        $this->answers_by_active_id = [];
        $this->basic_test_values = [];
        $this->basic_question_values = [];
    }

    /**
     * Read the data from the cache
     * @return bool	cache could be read
     */
    protected function readFromCache(): bool
    {
        $this->reset();
        try {
            $marks = unserialize($this->cache->read(__CLASS__, 'marks'));
            $question_types = unserialize($this->cache->read(__CLASS__, 'question_types'));
            $questions = unserialize($this->cache->read(__CLASS__, 'questions'));
            $participants = unserialize($this->cache->read(__CLASS__, 'participants'));
            $answers = unserialize($this->cache->read(__CLASS__, 'answers'));
            $basic_test_values = unserialize($this->cache->read(__CLASS__, 'basic_test_values'));
            $basic_question_values = unserialize($this->cache->read(__CLASS__, 'basic_question_values'));
        } catch (Exception $e) {
            $this->reset();
            return false;
        }

        if ($marks === false || $question_types === false || $questions === false || $participants === false || $answers === false
            || $basic_test_values === false || $basic_question_values === false) {
            $this->reset();
            return false;
        } else {
            $this->marks = $marks;
            $this->question_types = $question_types;
            $this->questions = $questions;
            $this->participants = $participants;
            $this->answers = $answers;
            $this->basic_test_values = $basic_test_values;
            $this->basic_question_values = $basic_question_values;
        }

        foreach ($this->answers as $answer) {
            $this->answers_by_question_id[$answer->question_id][$answer->active_id] = $answer;
            $this->answers_by_active_id[$answer->active_id][$answer->question_id] = $answer;
        }

        return true;
    }


    /**
     * Write the data to the cache
     */
    protected function writeToCache(): void
    {
        $this->cache->write(__CLASS__, 'marks', serialize($this->marks));
        $this->cache->write(__CLASS__, 'question_types', serialize($this->question_types));
        $this->cache->write(__CLASS__, 'questions', serialize($this->questions));
        $this->cache->write(__CLASS__, 'participants', serialize($this->participants));
        $this->cache->write(__CLASS__, 'answers', serialize($this->answers));
        $this->cache->write(__CLASS__, 'basic_test_values', serialize($this->basic_test_values));
        $this->cache->write(__CLASS__, 'basic_question_values', serialize($this->basic_question_values));
    }


    /**
     * Read the source data from the test
     * @see ilObjTest::getUnfilteredEvaluationData()
     */
    protected function readFromTest()
    {
        $this->eval = $this->object->getUnfilteredEvaluationData();

        // get the basic data of questions in a fixed test
        // this is needed if test has no participants
        // other question data will be added later
        if ($this->object->isFixedTest()) {
            $this->readFixedTestQuestionData();
        }

        // get the question titles
        $question_titles = $this->eval->getQuestionTitles();

        /** @var ilTestEvaluationUserData[] $participants */
        $participants = $this->eval->getParticipants();
        foreach ($participants as $active_id => $userdata) {
            $participant = $this->createParticipant($active_id);
            $participant->best_pass = $userdata->getBestPass();
            $participant->last_pass = $userdata->getLastPass();
            $participant->first_pass = 0;
            $participant->scored_pass = $userdata->getScoredPass();

            switch ($this->pass_selection) {
                case self::PASS_LAST:
                    $pass = $userdata->getLastPassObject();
                    break;
                case self::PASS_BEST:
                    $pass = $userdata->getBestPassObject();
                    break;
                case self::PASS_FIRST:
                    $pass = $userdata->passes[0];
                    break;
                case self::PASS_SCORED:
                default:
                    $pass = $userdata->getScoredPassObject();
                    break;
            }
            if ($pass instanceof ilTestEvaluationPassData) {
                // all questions for a participant in the test pass
                // these may differ between participants in a random test
                $pass_questions = $userdata->getQuestions($pass->getPass());

                $current_maximum_points = 0.0;
                if (is_array($pass_questions)) {
                    foreach ($pass_questions as $pass_question) {
                        if (empty($question = $this->getQuestion($pass_question['id']))) {
                            $question = $this->createQuestion($pass_question['id']); // reference
                        }
                        $question->original_id = $pass_question['o_id'];
                        $question->maximum_points = $pass_question['points'];
                        $question->question_title = $question_titles[$pass_question['id']];

                        // initiate the answers for all questions in this pass (even if not answered)
                        $answer = $this->createAnswer($pass_question['id'], $active_id, $pass->getPass());
                        $answer->sequence = $pass_question['sequence'];

                        $current_maximum_points += (float) $pass_question['points'];
                    }
                }

                // questions answered by a participant in the test pass
                $current_reached_points = 0.0;
                $pass_answers = $pass->getAnsweredQuestions();
                {
                    if (is_array($pass_answers)) {
                        foreach ($pass_answers as $pass_answer) {
                            // answer should be already created above, just to be sure
                            if (!empty($answer = $this->getAnswer($pass_answer['id'], $active_id))) {
                                $answer->reached_points = (float) $pass_answer['reached'];
                                $answer->answered = (bool) $pass_answer['isAnswered'];
                                $answer->manual_scored = (bool) $pass_answer['manual'];
                            }
                            $current_reached_points += (float) $pass_answer['reached'];
                        }
                    }
                }

                // sum of maximum and reached points for the selected pass
                $participant->current_maximum_points = $current_maximum_points;
                $participant->current_reached_points = $current_reached_points;
            }
        }

        $this->readMarks();
        $this->readQuestionTypes();
        $this->calculateBasicTestValues();
        $this->calculateBasicQuestionValues();
    }

    /**
     * Read the marks from the test
     * @see \ASS_MarkSchema::_getMatchingMark
     */
    protected function readMarks()
    {
        $result = $this->db->queryF(
            "SELECT * FROM tst_mark WHERE test_fi = %s  ORDER BY minimum_level DESC",
            ['integer'],
            [$this->object->getTestId()]
        );

        while ($row = $this->db->fetchAssoc($result)) {
            $this->marks[] = new ilExteStatSourceMark(
                (int) $row['mark_id'],
                (float) $row['minimum_level'],
                (bool) ($row['passed'] ?? false),
                (string) ($row['short_name'] ?? ''),
                (string) ($row['official_name'] ?? '')
            );
        }
    }

    /**
     * Get the matching mark by percentage
     * @see \ASS_MarkSchema::_getMatchingMark
     */
    public function getMarkByPercent(float $percentage): ?ilExteStatSourceMark
    {
        foreach ($this->marks as $mark) {
            if ($percentage >= $mark->getMinPercent()) {
                return $mark;
            }
        }
        return null;
    }

    /**
     * Read the types of the relevant questions
     */
    protected function readQuestionTypes()
    {
        $type_translations = ilObjQuestionPool::getQuestionTypeTranslations();
        $this->question_types = array();

        if (!empty($this->questions)) {
            $query = "
                SELECT q.question_id, t.type_tag FROM qpl_questions q
                INNER JOIN qpl_qst_type t ON t.question_type_id = q.question_type_fi
                WHERE " . $this->db->in('q.question_id', array_keys($this->questions), false, 'integer');

            $result = $this->db->query($query);
            while ($row = $this->db->fetchAssoc($result)) {
                $this->question_types[$row['type_tag']] = $type_translations[$row['type_tag']] ?? '';

                $this->questions[$row['question_id']]->question_type = $row['type_tag'];
                $this->questions[$row['question_id']]->question_type_label = $type_translations[$row['type_tag']] ?? '';
            }
        }
    }

    /**
     * Read the ordering data of questions in a fixed test
     */
    protected function readFixedTestQuestionData()
    {
        $query = "SELECT t.question_fi, t.sequence, t.obligatory, q.title, q.points
            FROM tst_test_question t 
            JOIN qpl_questions q ON t.question_fi = q.question_id
            WHERE test_fi = "
            . $this->db->quote($this->object->getTestId())
            . " ORDER BY sequence";
        $result = $this->db->query($query);

        while ($row = $this->db->fetchAssoc($result)) {
            if (empty($question = $this->getQuestion($row['question_fi']))) {
                $question = $this->createQuestion($row['question_fi']); // reference
            }
            $question->question_title = (string) $row['title'];
            $question->maximum_points = (float) $row['points'];
            $question->order_position = (int) $row['sequence'];
            $question->obligatory = (bool) $row['obligatory'];
        }
    }


    /**
     * Calculate the basic values for a test (as in original ILIAS)
     * @see ilTestEvaluationGUI::eval_a()
     */
    protected function calculateBasicTestValues()
    {
        // Total number of people who started the test
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 0;
        $value->value = count($this->participants);
        $this->basic_test_values['tst_eval_total_persons'] = $value;

        // Total finished tests (Users that used up all possible passes.)
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 0;
        $value->value = $this->eval->getTotalFinishedParticipants();
        $this->basic_test_values['tst_eval_total_finished'] = $value;

        // Average test processing time
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_DURATION;
        $value->value = (int) $this->object->evalTotalStartedAverageTime();
        $this->basic_test_values['tst_eval_total_finished_average_time'] = $value;

        // (intermediate calculations)
        $total_passed = 0;
        $total_passed_reached = 0;
        $total_passed_max = 0;
        $total_passed_time = 0;
        $total_max = 0;

        /** @var ilTestEvaluationUserData[] $foundParticipants */
        $foundParticipants = $this->eval->getParticipants();
        foreach ($foundParticipants as $userdata) {
            $total_max = max($total_max, $userdata->getMaxpoints());
            if ($userdata->getPassed()) {
                $total_passed++;
                $total_passed_reached += $userdata->getReached();
                $total_passed_max += $userdata->getMaxpoints();
                $total_passed_time += $userdata->getTimeOfWork();
            }
        }
        $average_passed_reached = $total_passed ? $total_passed_reached / $total_passed : 0;
        $average_passed_max = $total_passed ? $total_passed_max / $total_passed : 0;
        $average_passed_time = $total_passed ? $total_passed_time / $total_passed : 0;

        // Total passed tests
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 0;
        $value->value = $total_passed;
        $this->basic_test_values['tst_eval_total_passed'] = $value;

        // Average maximum points of passed tests
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        if ($this->object->isFixedTest()) {
            $value->value = $this->object->getFixedQuestionSetTotalPoints();
        } elseif ($this->object->getQuestionSetConfig()->arePoolsWithHomogeneousScoredQuestionsRequired()) {
            $value->value = $total_max;
        } else {
            $value->value = $average_passed_max;
            $value->comment = $this->plugin->txt('tst_passed_average_max_points_random_info');
        }

        $this->basic_test_values['tst_eval_total_passed_average_max_points'] = $value;

        // Average points of passed tests
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        $value->value = $average_passed_reached;
        $this->basic_test_values['tst_eval_total_passed_average_points'] = $value;

        // Average processing time of all passed tests
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_DURATION;
        $value->value = (int) $average_passed_time;
        $this->basic_test_values['tst_eval_total_passed_average_time'] = $value;

        // Mean of reached points
        $sum_of_points = 0.0;
        foreach ($this->participants as $participant) {
            $sum_of_points += $participant->current_reached_points;
        }
        $value = new ilExteStatValue();
        $value->type = ilExteStatValue::TYPE_NUMBER;
        $value->precision = 2;
        if (count($this->participants) > 0) {
            $value->value = $sum_of_points / count($this->participants);
        } else {
            $value->value = null;
            $value->alert = ilExteStatValue::ALERT_UNKNOWN;
        }
        $this->basic_test_values['tst_eval_mean_of_reached_points'] = $value;
    }


    /**
     * Get a list of id, title and description of the basic question values
     * The list includes only values that are intended to be displayed
     * getBasicTestValues() may provide more values
     * @return array	[['id' => string, 'title' => string, 'description' => string], ...]
     */
    public function getBasicTestValuesList(): array
    {
        $list = array(
        );

        if ($this->pass_selection == self::PASS_SCORED) {
            $list = array_merge($list, array(
                array(
                    'id' => 'tst_eval_total_persons',
                    'title' => $this->lng->txt('tst_eval_total_persons'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_finished',
                    'title' => $this->lng->txt('tst_eval_total_finished'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_passed',
                    'title' => $this->lng->txt('tst_eval_total_passed'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_passed_average_max_points',
                    'title' => $this->plugin->txt('tst_passed_average_max_points'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_passed_average_points',
                    'title' => $this->lng->txt('tst_eval_total_passed_average_points'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_finished_average_time',
                    'title' => $this->lng->txt('tst_eval_total_finished_average_time'),
                    'description' => '',
                ),
                array(
                    'id' => 'tst_eval_total_passed_average_time',
                    'title' => $this->lng->txt('tst_eval_total_passed_average_time'),
                    'description' => '',
                )
            ));
        }

        return $list;
    }

    /**
     * Calculate the basic values for test questions (as in original ILIAS)
     * @see ilTestEvaluationGUI::eval_a()
     */
    protected function calculateBasicQuestionValues()
    {
        foreach ($this->getAllQuestions() as $question_id => $question) {
            $assigned = 0;
            $answered = 0;
            $reached = 0;

            // calculate the average points of all participants to which the question is assigned
            // poits will be 0 if the question is not answered
            foreach ($this->getAnswersForQuestion($question_id) as $answer) {
                $assigned++;
                $answered += $answer->answered ? 1 : 0;
                $reached += $answer->reached_points;
            }

            $question->assigned_count = $assigned;
            $question->answers_count = $answered;
            $question->average_points = ($assigned ? $reached / $assigned : 0);
            $question->average_percentage = ($question->maximum_points ? 100 * $question->average_points / $question->maximum_points : 0);

            $values = array();
            $values['question_id'] = ilExteStatValue::_create($question->question_id, ilExteStatValue::TYPE_NUMBER, 0);
            $values['question_title'] = ilExteStatValue::_create($question->question_title, ilExteStatValue::TYPE_TEXT);
            $values['question_type'] = ilExteStatValue::_create($question->question_type, ilExteStatValue::TYPE_TEXT);
            $values['question_type_label'] = ilExteStatValue::_create($question->question_type_label, ilExteStatValue::TYPE_TEXT);
            $values['order_position'] = ilExteStatValue::_create($question->order_position, ilExteStatValue::TYPE_NUMBER, 0);
            $values['obligatory'] = ilExteStatValue::_create($question->obligatory, ilExteStatValue::TYPE_BOOLEAN);
            $values['assigned_count'] = ilExteStatValue::_create($question->assigned_count, ilExteStatValue::TYPE_NUMBER, 0);
            $values['answers_count'] = ilExteStatValue::_create($question->answers_count, ilExteStatValue::TYPE_NUMBER, 0);
            $values['maximum_points'] = ilExteStatValue::_create($question->maximum_points, ilExteStatValue::TYPE_NUMBER, 2);
            $values['average_points'] = ilExteStatValue::_create($question->average_points, ilExteStatValue::TYPE_NUMBER, 2);
            $values['average_percentage'] = ilExteStatValue::_create($question->average_percentage, ilExteStatValue::TYPE_PERCENTAGE, 2);

            $this->basic_question_values[$question->question_id] = $values;
        }
    }

    /**
     * Get a list of id, title and description of the basic question values
     * The list includes only values that are intended to be displayed
     * getBasicQuestionValues() may provide more values
     * @return array	[['id' => string, 'title' => string, 'description' => string], ...]
     */
    public function getBasicQuestionValuesList(): array
    {
        return array(
            'order_position' => array(
                'title' => $this->lng->txt('position'),
                'description' => '',
                'test_types' => array(ilExteEvalBase::TEST_TYPE_FIXED)
            ),
            'question_id' => array(
                'title' => $this->lng->txt('question_id'),
                'description' => '',
                'test_types' => array()
            ),
            'question_title' => array(
                'title' => $this->lng->txt('question_title'),
                'description' => '',
                'test_types' => array()
            ),
            'question_type_label' => array(
                'title' => $this->plugin->txt('question_type'),
                'description' => '',
                'test_types' => array()
            ),
            'obligatory' => array(
                'title' => $this->lng->txt('obligatory'),
                'description' => '',
                'test_types' => array(ilExteEvalBase::TEST_TYPE_FIXED)
            ),
            'assigned_count' => array(
                'title' => $this->plugin->txt('assigned_count'),
                'description' => $this->plugin->txt('assigned_count_description'),
                'test_types' => array()
            ),
            'answers_count' => array(
                'title' => $this->plugin->txt('answers_count'),
                'description' => $this->plugin->txt('answers_count_description'),
                'test_types' => array()
            ),
            'maximum_points' => array(
                'title' => $this->plugin->txt('max_points'),
                'description' => $this->plugin->txt('max_points_description'),
                'test_types' => array()
            ),
            'average_points' => array(
                'title' => $this->plugin->txt('average_points'),
                'description' => $this->plugin->txt('average_points_description'),
                'test_types' => array()
            )
        );
    }

    /**
     * Get the basic test values
     * @return ilExteStatValue[] value_id => ilExteStatValue
     */
    public function getBasicTestValues(): array
    {
        return $this->basic_test_values;
    }


    /**
     * Get the basic question values
     * @return	ilExteStatValue[][] question_id => value_id => ilExteStatValue
     */
    public function getBasicQuestionValues(): array
    {
        return $this->basic_question_values;
    }

    /**
     * Create a participant object for an active_id
     */
    public function createParticipant(int $a_active_id): ilExteStatSourceParticipant
    {
        $participant = new ilExteStatSourceParticipant();
        $participant->active_id = $a_active_id;
        $this->participants[$a_active_id] = $participant;
        return $participant;
    }


    /**
     * Get a participant object
     */
    public function getParticipant(int $a_active_id, bool $a_create = false): ?ilExteStatSourceParticipant
    {
        if (isset($this->participants[$a_active_id])) {
            return $this->participants[$a_active_id];
        }
        return null;
    }

    /**
     * Create the question object for a question id
     */
    protected function createQuestion(int $a_question_id): ilExteStatSourceQuestion
    {
        $question = new ilExteStatSourceQuestion();
        $question->question_id = $a_question_id;
        $this->questions[$a_question_id] = $question;
        return $question;
    }

    /**
     * Get the question object for a question id
     */
    public function getQuestion(int $a_question_id): ?ilExteStatSourceQuestion
    {
        if (isset($this->questions[$a_question_id])) {
            return $this->questions[$a_question_id];
        }
        return null;
    }

    /**
     * Create an answer object
     */
    protected function createAnswer(int $a_question_id, int $a_active_id, int $a_pass): ilExteStatSourceAnswer
    {
        $answer = new ilExteStatSourceAnswer();
        $answer->question_id = $a_question_id;
        $answer->active_id = $a_active_id;
        $answer->pass = $a_pass;

        $this->answers[] = $answer;
        $this->answers_by_question_id[$a_question_id][$a_active_id] = $answer;
        $this->answers_by_active_id[$a_active_id][$a_question_id] = $answer;

        return $answer;
    }


    /**
     * Get or create the answer status of a question and participant
     */
    public function getAnswer(int $a_question_id, int $a_active_id): ?ilExteStatSourceAnswer
    {
        if (isset($this->answers_by_question_id[$a_question_id][$a_active_id])) {
            return $this->answers_by_question_id[$a_question_id][$a_active_id];
        }
        return null;
    }

    /**
     * Get all marks defined in the test  (ordered by descending minimum percentage)
     * @return ilExteStatSourceMark[]
     */
    public function getAllMarks(): array
    {
        return $this->marks;
    }

    /**
     * Get all participants in the test (indexed by active_id)
     * @return ilExteStatSourceParticipant[]
     */
    public function getAllParticipants(): array
    {
        return $this->participants;
    }


    /**
     * Get all questions which are assigned to the participants in the test (indexed by question_id)
     * @return ilExteStatSourceQuestion[]
     */
    public function getAllQuestions(): array
    {
        return $this->questions;
    }


    /**
     * Get answer objects for all questions assigned to the participants in their relevant pass (flat list)
     * - The answer objects provide information about the answer status of questions assigned to the participants
     * - The 'answered' property indicates if an answer was saved by the participant
     * @return ilExteStatSourceAnswer[]
     */
    public function getAllAnswers(): array
    {
        return $this->answers;
    }


    /**
     * Get all answers for a question of any participant in his/her relevant pass (indexed by active_id)
     * @return ilExteStatSourceAnswer[]
     */
    public function getAnswersForQuestion(int $a_question_id, bool $a_only_answered = false): array
    {
        $answers = [];
        if (isset($this->answers_by_question_id[$a_question_id]) && is_array($this->answers_by_question_id[$a_question_id])) {
            if (!$a_only_answered) {
                return $this->answers_by_question_id[$a_question_id];
            } else {
                foreach ($this->answers_by_question_id[$a_question_id] as $active_id => $answer) {
                    if ($answer->answered) {
                        $answers[$active_id] = $answer;
                    }
                }
            }
        }
        return $answers;
    }


    /**
     * Get all answers for a participant in his/her relevant pass (indexed by question_id)
     * @return ilExteStatSourceAnswer[]
     */
    public function getAnswersForParticipant(int $a_active_id, bool $a_only_answered = false): array
    {
        $answers = [];
        if (isset($this->answers_by_active_id[$a_active_id]) && is_array($this->answers_by_active_id[$a_active_id])) {
            if (!$a_only_answered) {
                return $this->answers_by_active_id[$a_active_id];
            } else {
                foreach ($this->answers_by_active_id[$a_active_id] as $question_id => $answer) {
                    if ($answer->answered) {
                        $answers[$question_id] = $answer;
                    }
                }
            }
        }
        return $answers;
    }


    /**
     * Get the original test evaluation data
     *
     * IMPORTANT NOTE:    This returns an internal object of ILIAS
     *                    Its API is not wrapped by the plugin
     *                    Use it in exceptional cases only!
     */
    public function getOriginalTestEvaluationData(): ilTestEvaluationData
    {
        return $this->eval;
    }
}
