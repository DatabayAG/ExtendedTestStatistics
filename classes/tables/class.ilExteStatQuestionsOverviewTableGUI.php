<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatQuestionsOverviewTableGUI
 */
class ilExteStatQuestionsOverviewTableGUI extends ilExteStatTableGUI
{
    protected ilLanguage $lng;

    /** @var array question_id => ilExteStatValue[] */
    protected array $basicValues = [];

    /** @var array names of the columns with basic values */
    protected array $basicColumns = [];

    /** @var int[] question ids of the currently shown questions */
    protected array $shownQuestionIds = [];

    /**
     * Constructor
     */
    public function __construct(ilExtendedTestStatisticsPageGUI $a_parent_obj, string $a_parent_cmd)
    {
        global $DIC;
        $this->lng = $DIC->language();

        $this->setId('ilExteStatQuestionsOverview');
        $this->setPrefix('ilExteStatQuestionsOverview');

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setFormName('questions_overview');
        $this->setTitle($this->plugin->txt('questions_results'));
        $this->setStyle('table', 'fullwidth');

        foreach ($this->getSelectableColumns() as $colid => $settings) {
            if ($this->isColumnSelected($colid)) {
                $this->addColumn(
                    $settings['txt'],
                    in_array($colid, array_keys($this->getBasicSelectableColumns())) ? $colid : '',
                    '',
                    false,
                    '',
                    $settings['tooltip']
                );
            }
        }
        $this->addColumn('');

        $this->setRowTemplate("tpl.il_exte_stat_questions_overview_row.html", $a_parent_obj->getPlugin()->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->setDefaultOrderField("title");
        $this->setDefaultOrderDirection("asc");
        $this->setDisableFilterHiding(true);
        $this->setEnableHeader(true);
        $this->setEnableAllCommand(false);
        $this->initFilter();
    }

    /**
     * Initialize the filter controls
     */
    public function initFilter(): void
    {
        $ti = new ilTextInputGUI($this->lng->txt('id'), 'question_id');
        $ti->setParentTable($this);
        $ti->readFromSession();
        $this->addFilterItem($ti);

        $ti = new ilTextInputGUI($this->lng->txt('title'), 'question_title');
        $ti->setParentTable($this);
        $ti->readFromSession();
        $this->addFilterItem($ti);

        $options = array();
        $options[""] = $this->plugin->txt("any_question_type");
        $options = array_merge($options, $this->statObj->getSourceData()->getQuestionTypes());

        $si = new ilSelectInputGUI($this->lng->txt('type'), "question_type");
        $si->setParentTable($this);
        $si->setOptions($options);
        $si->readFromSession();
        $this->addFilterItem($si);
    }


    /**
     * Get the selectable columns with basic question data
     */
    public function getBasicSelectableColumns(): array
    {
        $columns = array(
            'order_position' => array(
                'txt' => $this->lng->txt('position'),
                'tooltip' => '',
                'default' => true
            ),
            'question_id' => array(
                'txt' => $this->lng->txt('id'),
                'tooltip' => '',
                'default' => true
            ),
            'question_title' => array(
                'txt' => $this->lng->txt('title'),
                'tooltip' => '',
                'default' => true
            ),
            'question_type_label' => array(
                'txt' => $this->lng->txt('type'),
                'tooltip' => '',
                'default' => false
            ),
            'assigned_count' => array(
                'txt' => $this->plugin->txt('assigned_count'),
                'tooltip' => $this->plugin->txt('assigned_count_description'),
                'default' => false
            ),
            'answers_count' => array(
                'txt' => $this->plugin->txt('answers_count'),
                'tooltip' => $this->plugin->txt('answers_count_description'),
                'default' => true
            ),
            'maximum_points' => array(
                'txt' => $this->plugin->txt('max_points'),
                'tooltip' => $this->plugin->txt('max_points_description'),
                'default' => true
            ),
            'average_points' => array(
                'txt' => $this->plugin->txt('average_points'),
                'tooltip' => $this->plugin->txt('average_points_description'),
                'default' => true
            ),
//			'average_percentage' => array(
//                'txt' => $this->plugin->txt('average_percentage'),
//                'tooltip' => $this->plugin->txt('average_percentage_description'),
//                'default' => false
//            ),
        );

        if ($this->statObj->getSourceData()->getTestType() != ilExteEvalBase::TEST_TYPE_FIXED) {
            unset($columns['order_position']);
        }

        return $columns;
    }


    /**
     * Get selectable columns
     */
    public function getSelectableColumns(): array
    {
        // basic question values
        $columns = $this->getBasicSelectableColumns();

        foreach ($this->statObj->getEvaluations(
            ilExtendedTestStatistics::LEVEL_QUESTION,
            ilExtendedTestStatistics::PROVIDES_VALUE
        ) as $id => $evaluation) {
            $columns[$id] = array(
                'txt' => $evaluation->getShortTitle(),
                'tooltip' => $evaluation->getDescription(),
                'default' => true
            );
        }
        return $columns;
    }

    /**
     * Prepare the data to be shown
     * This only adds the basic question values that will be used for filtering and sorting
     * The more complex evaluations are only applied for the filled rows of the page
     */
    public function prepareData()
    {
        $filter_id = $this->getFilterItemByPostVar('question_id')->getValue();
        $filter_title = $this->getFilterItemByPostVar('question_title')->getValue();
        $filter_type = $this->getFilterItemByPostVar('question_type')->getValue();

        $data = array();
        $this->basicColumns = array_keys($this->getBasicSelectableColumns());
        $this->basicValues = $this->statObj->getSourceData()->getBasicQuestionValues();
        foreach ($this->basicValues as $question_id => $values) {
            if ((!empty($filter_id) && $filter_id != $question_id) ||
                (!empty($filter_title) && strpos($values['question_title']->value, $filter_title) === false) ||
                (!empty($filter_type) && $values['question_type']->value != $filter_type)
            ) {
                continue;
            }

            $row = array();

            /** @var ilExteStatValue  $value */
            foreach ($values as $value_id => $value) {
                $row[$value_id] = $value->value;
            }
            $data[] = $row;
        }
        $this->setData($data);
    }


    /**
     * Should this field be sorted numeric?
     * @return    bool        numeric ordering; default is false
     */
    public function numericOrdering($a_field): bool
    {
        switch ($a_field) {
            case 'order_position':
            case 'question_id':
            case 'assigned_count':
            case 'answers_count':
            case 'maximum_points':
            case 'average_points':
            case 'average_percentage':
                return true;

            default:
                return false;
        }
    }

    /**
     * fill row
     */
    protected function fillRow(array $data): void
    {
        $question_id = $data['question_id'];

        $this->shownQuestionIds[] = (int) $question_id;

        foreach ($this->getSelectedColumns() as $colid) {
            $content = '';
            if (in_array($colid, $this->basicColumns)) {
                $value = $this->basicValues[$question_id][$colid];
                $content = $this->valueGUI->getHTML($value);
            } else {
                $evaluation = $this->statObj->getEvaluation($colid);
                if (isset($evaluation) && $evaluation->providesValue()) {
                    $value = $evaluation->getValue($data['question_id']);
                    $content = $this->valueGUI->getHTML($value);
                }
            }

            $this->tpl->setCurrentBlock('column');
            $this->tpl->setVariable('CONTENT', $content);
            $this->tpl->parseCurrentBlock();
        }

        // evaluations with details
        $details = $this->statObj->getEvaluations(
            ilExtendedTestStatistics::LEVEL_QUESTION,
            ilExtendedTestStatistics::PROVIDES_DETAILS,
            $data['question_type']
        );

        if (!empty($details)) {
            // show action menu
            $list = new ilAdvancedSelectionListGUI();
            $list->setSelectionHeaderClass('small');
            $list->setItemLinkClass('small');
            $list->setId('actl_' . $data['question_id'] . '_' . $this->getId());
            $list->setListTitle($this->plugin->txt('show_details'));

            foreach ($details as $class => $evaluation) {
                $this->ctrl->setParameter($this->parent_obj, 'qid', $data['question_id']);
                $this->ctrl->setParameter($this->parent_obj, 'details', $class);
                $list->addItem($evaluation->getTitle(), '', $this->ctrl->getLinkTarget($this->parent_obj, 'showQuestionDetails'));
            }
            $content = $list->getHTML();
        } else {
            $content = '';
        }

        $this->tpl->setCurrentBlock('column');
        $this->tpl->setVariable('CONTENT', $content);
        $this->tpl->parseCurrentBlock();

    }

    /**
     * Get the ids of the shown questions
     * This must be called after getHTML()
     *
     * @return int[]
     */
    public function getShownQuestionIds(): array
    {
        return $this->shownQuestionIds;
    }
}
