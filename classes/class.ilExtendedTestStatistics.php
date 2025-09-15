<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Basic class for doing statistics
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExtendedTestStatistics
{
    public const LEVEL_TEST = 'test';
    public const LEVEL_QUESTION = 'question';

    public const PROVIDES_VALUE = 'value';
    public const PROVIDES_DETAILS = 'details';

    protected iltree $tree;
    protected ilAccessHandler $access;

    protected ilExtendedTestStatisticsPlugin $plugin;
    protected ilExtendedTestStatisticsConfig $config;
    protected ilObjTest $object;
    protected ilExteStatSourceData $data;
    protected ilExtendedTestStatisticsCache $cache;

    /** @var ilExteEvalBase[]	indexed by class name */
    protected array $evaluations;
    protected string $pass_selection;

    /**
     * Constructor.
     */
    public function __construct(ilObjTest $a_test_obj, ilExtendedTestStatisticsPlugin $a_plugin)
    {
        global $DIC;

        $this->tree = $DIC->repositoryTree();
        $this->access = $DIC->access();

        $this->plugin = $a_plugin;
        $this->object = $a_test_obj;
        $this->config = $this->plugin->getConfig();

        // Set which pass should be evaluated
        $this->pass_selection = $this->plugin->getUserPreference('evaluated_pass', ilExteStatSourceData::PASS_SCORED);

        // load the cache object
        $this->cache = new ilExtendedTestStatisticsCache($this->object->getTestId(), $this->pass_selection);
    }

    /**
     * Clear the cache of this test
     */
    public function flushCache()
    {
        $this->cache->flush();
    }

    public function getObjId(): int
    {
        return $this->object->getId();
    }

    /**
     * Get the source data object
     */
    public function getSourceData(): ilExteStatSourceData
    {
        if (!isset($this->data)) {
            $this->loadSourceData();
        }
        return $this->data;
    }

    /**
     * Get a single loaded evaluation
     * @param string $a_class  class name of the evaluation
     * @return ilExteEvalBase|ilExteEvalTest|ilExteEvalQuestion|null
     */
    public function getEvaluation(string $a_class): ?ilExteEvalBase
    {
        if (!isset($this->evaluations)) {
            $this->loadEvaluations();
        }
        return $this->evaluations[$a_class] ?? null;
    }

    /**
     * Get a subset of the loaded evaluation objects
     * @param   string  $a_level			level of the statistics, e.g. self::LEVEL_TEST or empty for all
     * @param   string  $a_provides			provided result, e.g. self::PROVIDES_VALUE or empty for all
     * @param   string  $a_question_type	question type (for question evaluations) or empty for all
     * @return  ilExteEvalBase[]    indexed by class name
     */
    public function getEvaluations(string $a_level = '', string $a_provides = '', string $a_question_type = ''): array
    {
        if (!isset($this->evaluations)) {
            $this->loadEvaluations();
        }

        $selected = array();
        foreach ($this->evaluations as $class => $evaluation) {
            // check evaluation type
            if (($a_level == self::LEVEL_TEST && !is_subclass_of($class, 'ilExteEvalTest')) ||
                ($a_level == self::LEVEL_QUESTION && !is_subclass_of($class, 'ilExteEvalQuestion'))) {
                continue;
            }

            // check if value or details are provided
            if (($a_provides == self::PROVIDES_VALUE && !$evaluation->providesValue()) ||
                ($a_provides == self::PROVIDES_DETAILS && !$evaluation->providesDetails())) {
                continue;
            }

            // check if question type is supported
            if (!empty($a_question_type) && !$evaluation->isQuestionTypeAllowed($a_question_type)) {
                continue;
            }

            $selected[$class] = $evaluation;
        }
        return $selected;
    }

    /**
     * Load the source data for all evaluations
     */
    protected function loadSourceData()
    {
        $this->data = new ilExteStatSourceData($this->object, $this->plugin, $this->cache);
        $this->data->load($this->pass_selection);
    }

    /**
     * Load the evaluation objects
     */
    protected function loadEvaluations()
    {
        $isAdmin = $this->isAdmin();

        $this->evaluations = array();
        foreach ($this->config->getEvaluationClasses() as $class => $availability) {
            // check configured availability
            if ($availability == ilExtendedTestStatisticsConfig::FOR_NONE ||
                !($isAdmin || $availability == ilExtendedTestStatisticsConfig::FOR_USER)) {
                continue;
            }

            // instantiate the evaluation object
            $this->evaluations[$class] = new $class($this->plugin, $this->cache);
            $this->evaluations[$class]->setData($this->getSourceData());
        }
    }


    /**
     * Check if the current user is administrator of the test system
     */
    protected function isAdmin(): bool
    {
        foreach ($this->tree->getChilds(SYSTEM_FOLDER_ID) as $object) {
            if (($object["type"] ?? '') == "assf") {
                return $this->access->checkAccess("visible", '', (int) ($object["ref_id"] ?? 0));
            }
        }
        return false;
    }
}
