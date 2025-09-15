<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\HTTP\Wrapper\RequestWrapper;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

/**
 * Extended Test Statistic Page GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_IsCalledBy ilExtendedTestStatisticsPageGUI: ilUIPluginRouterGUI
 */
class ilExtendedTestStatisticsPageGUI
{
    protected ilCtrl $ctrl;
    protected ilAccessHandler $access;
    protected ilGlobalTemplateInterface $tpl;
    protected ilLanguage $lng;
    protected ilLocatorGUI $locator;
    protected ilToolbarGUI $toolbar;
    protected Factory $uiFactory;
    protected Renderer $uiRenderer;
    /** @var ilExtendedTestStatisticsPlugin $plugin */
    protected ilPlugin $plugin;
    protected RequestWrapper $query;
    protected RequestWrapper $post;
    protected ILIAS\Refinery\Factory $refinery;

    protected ilObjTest $testObj;
    protected ilExtendedTestStatistics $statObj;

    /**
     * ilExtendedTestStatisticsPageGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->access = $DIC->access();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->locator = $DIC['ilLocator'];
        $this->lng = $DIC->language();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->query = $DIC->http()->wrapper()->query();
        $this->post = $DIC->http()->wrapper()->post();
        $this->refinery = $DIC->refinery();

        $this->lng->loadLanguageModule('assessment');

        /** @var ilComponentFactory $factory */
        $factory = $DIC["component.factory"];
        $this->plugin = $factory->getPlugin('etstat');

        $this->testObj = new ilObjTest($this->query->retrieve('ref_id', $this->refinery->kindlyTo()->int()));
        $this->statObj = new ilExtendedTestStatistics($this->testObj, $this->plugin);
    }

    /**
    * Handles all commands, default is "show"
    */
    public function executeCommand()
    {
        if (!$this->access->checkAccess('tst_statistics', '', $this->testObj->getRefId())) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
            $this->ctrl->redirectToURL(ilLink::_getLink($this->testObj->getRefId()));
        }

        $this->ctrl->saveParameter($this, 'ref_id');
        $cmd = $this->ctrl->getCmd('showTestOverview');

        switch ($cmd) {
            case "showTestOverview":
            case "showTestDetails":
            case "showQuestionsOverview":
            case "showQuestionDetails":
                if ($this->prepareOutput()) {
                    $this->$cmd();
                }
                break;
            case "exportEvaluations":
            case "deliverExportFile":
            case "selectEvaluatedPass":
            case "selectQuestionsChart":
            case "selectTestChart":
            case "flushCache":
                $this->$cmd();
                break;
            case "applyFilter":
            case "resetFilter":
                if ($this->prepareOutput()) {
                    $this->showQuestionsOverview();
                }
                break;

            default:
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
                $this->ctrl->redirectToURL(ilLink::_getLink($this->testObj->getRefId()));
                break;
        }
    }

    /**
     * Get the plugin object
     * @return ilExtendedTestStatisticsPlugin
     */
    public function getPlugin(): ilPlugin
    {
        return $this->plugin;
    }

    /**
     * Get the statistics object
     */
    public function getStatisticsObject(): ilExtendedTestStatistics
    {
        return $this->statObj;
    }

    /**
     * Get the test object id (needed for table filter)
     */
    public function getId(): int
    {
        return $this->testObj->getId();
    }

    /**
     * Prepare the test header, tabs etc.
     */
    protected function prepareOutput()
    {
        $this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id', $this->testObj->getRefId());
        $this->locator->addRepositoryItems($this->testObj->getRefId());
        $this->locator->addItem($this->testObj->getTitle(), $this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

        $this->tpl->setLocator();
        $this->tpl->setTitle($this->testObj->getPresentationTitle());
        $this->tpl->setDescription($this->testObj->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon($this->testObj->getId(), 'big', 'tst'), $this->lng->txt('obj_tst'));
        $this->tpl->addCss($this->plugin->getStyleSheetLocation('exte_stat.css'));

        if ($this->testObj->getOfflineStatus() == 1) {
            $properties = array();
            $properties[] = array('property' => $this->lng->txt('status'), 'value' => $this->lng->txt('offline'));
            $this->tpl->setAlertProperties($properties);
        }

        return true;
    }

    /**
     * Show the test overview
     */
    protected function showTestOverview()
    {
        $this->setOverviewToolbar(ilExtendedTestStatistics::LEVEL_TEST);

        /** @var  ilExteStatTestOverviewTableGUI $tableGUI */
        $tableGUI = ilExteStatTableGUI::_create('ilExteStatTestOverviewTableGUI', $this, 'showTestOverview');
        $tableGUI->prepareData();
        $tableHtml = $tableGUI->getHTML();

        $chartHtml = '';
        $chartActions = [];
        $chartEvaluation = null;
        $chartKey = null;
        /** @var ilExteEvalTest $evaluation */
        foreach ($this->statObj->getEvaluations(ilExtendedTestStatistics::LEVEL_TEST) as $evaluation) {
            foreach ($evaluation->getOverviewCharts() as $key => $title) {
                if (empty($chartEvaluation) || (get_class($evaluation) == $this->plugin->getUserPreference('test_chart_class') &&
                    $key == $this->plugin->getUserPreference('test_chart_key'))) {
                    $chartEvaluation = $evaluation;
                    $chartKey = $key;
                }
                $this->ctrl->setParameter($this, 'chart_class', get_class($evaluation));
                $this->ctrl->setParameter($this, 'chart_key', $key);
                $chartActions[] = $this->uiFactory->button()->shy($title, $this->ctrl->getLinkTarget($this, 'selectTestChart'));
            }
        }
        if (!empty($chartActions)) {
            $chartHtml = $this->uiRenderer->render($this->uiFactory->dropdown()->standard($chartActions)->withLabel($this->plugin->txt('select_chart')));
        }

        /** @var ilExteEvalTest $chartEvaluation */
        /** @var ilChart $chart */
        if (!empty($chartEvaluation)) {
            $chart = $chartEvaluation->getChart($chartKey);
            $chartHtml .= $chart->getHTML();
        }

        $legendGUI = ilExteStatTableGUI::_create('ilExteStatLegendTableGUI', $this, 'showTestOverview');
        $legendHtml = $legendGUI->getHTML();

        $this->tpl->setContent($tableHtml . $chartHtml . $legendHtml);
        $this->tpl->printToStdout();
    }

    /**
     * Show the detailed evaluation for a test
     */
    protected function showTestDetails()
    {
        $this->setDetailsToolbar('showTestOverview');
        $this->ctrl->saveParameter($this, 'details');

        $evaluation = $this->statObj->getEvaluation($this->query->retrieve('details', $this->refinery->kindlyTo()->string()));
        $chartHTML = '';
        if ($evaluation->providesChart()) {
            $chart = $evaluation->getChart();
            $chartHTML = $chart->getHTML();
        }

        $customHTML = '';
        if ($evaluation->providesHTML()) {
            $customHTML = $evaluation->getCustomHTML();
        }

        /** @var  ilExteStatDetailsTableGUI $tableGUI */
        $tableGUI = ilExteStatTableGUI::_create('ilExteStatDetailsTableGUI', $this, 'showTestDetails');
        $tableGUI->prepareData($evaluation->getDetails());
        $tableGUI->setTitle($evaluation->getDetailsTitle());
        $tableGUI->setDescription($evaluation->getDetailsDescription());

        $legendGUI = ilExteStatTableGUI::_create('ilExteStatLegendTableGUI', $this, 'showTestDetails');
        $this->tpl->setContent($customHTML . $chartHTML . $tableGUI->getHTML() . $legendGUI->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Show the questions overview
     */
    protected function showQuestionsOverview()
    {
        $this->setOverviewToolbar(ilExtendedTestStatistics::LEVEL_QUESTION);

        /** @var  ilExteStatQuestionsOverviewTableGUI $tableGUI */
        $tableGUI = ilExteStatTableGUI::_create('ilExteStatQuestionsOverviewTableGUI', $this, 'showQuestionsOverview');

        if ($this->ctrl->getCmd() == 'applyFilter') {
            $tableGUI->resetOffset();
            $tableGUI->writeFilterToSession();
        } elseif (($this->ctrl->getCmd() == 'resetFilter')) {
            $tableGUI->resetOffset();
            $tableGUI->resetFilter();
        }

        $tableGUI->prepareData();
        $tableHtml = $tableGUI->getHTML();

        $chartHtml = '';
        $chartActions = [];
        $chartEvaluation = null;
        /** @var ilExteEvalQuestion $evaluation */
        foreach ($this->statObj->getEvaluations(ilExtendedTestStatistics::LEVEL_QUESTION) as $evaluation) {
            if ($evaluation->providesOverviewChart()) {
                if (empty($chartEvaluation) || get_class($evaluation) == $this->plugin->getUserPreference('questions_chart_class')) {
                    $chartEvaluation = $evaluation;
                }
                $this->ctrl->setParameter($this, 'chart_class', get_class($evaluation));
                $chartActions[] = $this->uiFactory->button()->shy($evaluation->getTitle(), $this->ctrl->getLinkTarget($this, 'selectQuestionsChart'));
            }
        }
        if (!empty($chartActions)) {
            $chartHtml = $this->uiRenderer->render($this->uiFactory->dropdown()->standard($chartActions)->withLabel($this->plugin->txt('select_chart')));
        }

        /** @var ilExteEvalQuestionPercentCorrect $evaluation */
        /** @var ilChart $chart */
        if (!empty($chartEvaluation)) {
            if (count($tableGUI->getShownQuestionIds()) > 10) {
                $this->tpl->addCss($this->plugin->getStyleSheetLocation('exte_stat_large_grid.css'));
            }
            $chart = $chartEvaluation->getOverviewChart($tableGUI->getShownQuestionIds());
            $chartHtml .= $chart->getHTML();
        }

        $legendGUI = ilExteStatTableGUI::_create('ilExteStatLegendTableGUI', $this, 'showQuestionsOverview');
        $legendHtml = $legendGUI->getHTML();
        $this->tpl->setContent($tableHtml . $chartHtml . $legendHtml);
        $this->tpl->printToStdout();
    }



    /**
     * Show the detailed evaluation for a question
     */
    protected function showQuestionDetails()
    {
        $this->setDetailsToolbar('showQuestionsOverview');
        $this->ctrl->saveParameter($this, 'details');
        $this->ctrl->saveParameter($this, 'qid');

        $details = $this->query->retrieve('details', $this->refinery->kindlyTo()->string());
        $qid = $this->query->retrieve('qid', $this->refinery->kindlyTo()->int());

        $evaluation = $this->statObj->getEvaluation($details);

        //Extra STACK features
        if (is_a($evaluation, 'ilExteEvalQuestionStack')) {
            $extra_content = $evaluation->getExtraInfo($qid);
        } else {
            $extra_content = '';
        }

        $chartHTML = '';
        if ($evaluation->providesChart()) {
            $chart = $evaluation->getChart($qid);
            $chartHTML = $chart->getHTML();
        }

        /** @var  ilExteStatDetailsTableGUI $tableGUI */
        $tableGUI = ilExteStatTableGUI::_create('ilExteStatDetailsTableGUI', $this, 'showQuestionDetails');
        $tableGUI->prepareData($evaluation->getDetails($qid));
        $tableGUI->setTitle($evaluation->getDetailsTitle($qid));
        $tableGUI->setDescription($evaluation->getDetailsDescription($qid));

        $legendGUI = ilExteStatTableGUI::_create('ilExteStatLegendTableGUI', $this, 'showQuestionDetails');
        $this->tpl->setContent($chartHTML . $tableGUI->getHTML() . $extra_content . $legendGUI->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Set the Toolbar for the overview page
     * @param string	$level
     */
    protected function setOverviewToolbar($level)
    {
        $this->toolbar->setFormName('etstat_toolbar');
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));

        $export_type = new ilSelectInputGUI($this->lng->txt('type'), 'export_type');
        $options = array(
            'excel_overview' => $this->plugin->txt('exp_type_excel_overviews'),
            'excel_details' => $this->plugin->txt('exp_type_excel_details'),
            'csv_test' => $this->plugin->txt('exp_type_csv_test'),
            'csv_questions' => $this->plugin->txt('exp_type_csv_questions'),
        );
        $export_type->setOptions($options);
        $export_type->setValue($this->plugin->getUserPreference('export_type', 'excel_overview'));
        $this->toolbar->addInputItem($export_type, true);

        $button = ilSubmitButton::getInstance();
        $button->setCommand('exportEvaluations');
        $button->setCaption('export');
        $button->setOmitPreventDoubleSubmission(true);
        $this->toolbar->addButtonInstance($button);

        $this->toolbar->addSeparator();

        $pass_selection = new ilSelectInputGUI($this->plugin->txt('evaluated_pass'), 'evaluated_pass');
        $options = array(
            ilExteStatSourceData::PASS_SCORED => $this->plugin->txt('pass_scored'),
            ilExteStatSourceData::PASS_BEST => $this->plugin->txt('pass_best'),
            ilExteStatSourceData::PASS_FIRST => $this->plugin->txt('pass_first'),
            ilExteStatSourceData::PASS_LAST => $this->plugin->txt('pass_last'),
        );
        $pass_selection->setOptions($options);
        $pass_selection->setValue($this->plugin->getUserPreference('evaluated_pass', ilExteStatSourceData::PASS_SCORED));
        $this->toolbar->addInputItem($pass_selection, true);

        $button = ilSubmitButton::getInstance();
        $button->setCommand('selectEvaluatedPass');
        $button->setCaption('select');
        $button->setOmitPreventDoubleSubmission(true);
        $this->toolbar->addButtonInstance($button);

        $this->toolbar->addSeparator();

        $button = ilSubmitButton::getInstance();
        $button->setCommand('flushCache');
        $button->setCaption($this->plugin->txt('flush_cache'), false);
        $button->setOmitPreventDoubleSubmission(true);
        $this->toolbar->addButtonInstance($button);

        $levelField = new ilHiddenInputGUI('level');
        $levelField->setValue($level);
        $this->toolbar->addInputItem($levelField);
    }

    /**
     * Set the Toolbar for the details page
     * @param string  $backCmd
     */
    protected function setDetailsToolbar($backCmd)
    {
        $this->toolbar->setFormName('etstat_toolbar');
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));

        $button = ilSubmitButton::getInstance();
        $button->setCommand($backCmd);
        $button->setCaption('back');
        $button->setOmitPreventDoubleSubmission(true);
        $this->toolbar->addButtonInstance($button);
    }

    /**
     * Export the evaluations
     */
    protected function exportEvaluations()
    {
        $export_type = $this->post->retrieve('export_type', $this->refinery->kindlyTo()->string());

        // set the parameters based on the selection
        $this->plugin->setUserPreference('export_type', ilUtil::secureString($export_type));
        switch ($export_type) {
            case 'csv_test':
                $name = 'test_statistics';
                $type = ilExteStatExport::TYPE_CSV;
                $level = ilExtendedTestStatistics::LEVEL_TEST;
                $details = false;
                break;

            case 'csv_questions':
                $name = 'questions_statistics';
                $type = ilExteStatExport::TYPE_CSV;
                $level = ilExtendedTestStatistics::LEVEL_QUESTION;
                $details = false;
                break;

            case 'excel_details':
                $name = 'detailed_statistics';
                $type = ilExteStatExport::TYPE_EXCEL;
                $level = '';
                $details = true;
                break;

            case 'excel_overview':
            default:
                $name = 'statistics';
                $type = ilExteStatExport::TYPE_EXCEL;
                $level = '';
                $details = false;
                break;
        }

        // add a suffix for the pass selection
        switch ($this->plugin->getUserPreference('evaluated_pass')) {
            case ilExteStatSourceData::PASS_LAST:
                $name .= '_last_pass';
                break;
            case ilExteStatSourceData::PASS_BEST:
                $name .= '_best_pass';
                break;
            case ilExteStatSourceData::PASS_FIRST:
                $name .= '_first_pass';
                break;
        }

        // write the export file
        $export = new ilExteStatExport($this->plugin, $this->statObj);
        $resource_id = $export->buildExportFile($name, $type, $level, $details);

        // build the success message with download link for the file
        $this->ctrl->setParameter($this, 'resource_id', $resource_id);
        $link = $this->ctrl->getLinkTarget($this, 'deliverExportFile');
        $this->tpl->setOnScreenMessage('success', sprintf($this->plugin->txt('export_written'), $link), true);
        $this->ctrl->clearParameters($this);

        // show the screen from which the export was started
        switch ($this->post->retrieve('level', $this->refinery->kindlyTo()->string())) {
            case ilExtendedTestStatistics::LEVEL_QUESTION:
                $this->ctrl->redirect($this, 'showQuestionsOverview');
                break;
            default:
                $this->ctrl->redirect($this, 'showTestOverview');
        }
    }

    /**
     * Deliver a previously generated export file
     */
    protected function deliverExportFile()
    {
        $resource_id = new ResourceIdentification($this->query->retrieve('resource_id', $this->refinery->kindlyTo()->string()));
        $export = new ilExteStatExport($this->plugin, $this->statObj);
        $export->deliverExportFile($resource_id);
    }

    /**
     * Set the evaluated pass
     */
    protected function selectEvaluatedPass()
    {
        $evaluated_pass = $this->post->retrieve('evaluated_pass', $this->refinery->kindlyTo()->string());
        $this->plugin->setUserPreference('evaluated_pass', ilUtil::secureString($evaluated_pass));

        // show the screen from which the export was started
        switch ($this->post->retrieve('level', $this->refinery->kindlyTo()->string())) {
            case ilExtendedTestStatistics::LEVEL_QUESTION:
                $this->ctrl->redirect($this, 'showQuestionsOverview');
                break;
            default:
                $this->ctrl->redirect($this, 'showTestOverview');
        }

    }

    /**
     * Select the chart to be shown on the test overview
     */
    protected function selectTestChart()
    {
        $chart = $this->query->retrieve('chart_class', $this->refinery->kindlyTo()->string());
        $key = $this->query->retrieve('chart_key', $this->refinery->kindlyTo()->string());
        $this->plugin->setUserPreference('test_chart_class', ilUtil::secureString($chart));
        $this->plugin->setUserPreference('test_chart_key', ilUtil::secureString($key));
        $this->ctrl->redirect($this, 'showTestOverview');
    }


    /**
     * Select the chart to be shown on the questions overview
     */
    protected function selectQuestionsChart()
    {
        $chart = $this->query->retrieve('chart_class', $this->refinery->kindlyTo()->string());
        $this->plugin->setUserPreference('questions_chart_class', ilUtil::secureString($chart));
        $this->ctrl->redirect($this, 'showQuestionsOverview');
    }


    /**
     * Flush the cache
     */
    protected function flushCache()
    {
        $this->statObj->flushCache();

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt('cache_flushed'), true);

        // show the screen from which the export was started
        switch ($this->post->retrieve('level', $this->refinery->kindlyTo()->string())) {
            case ilExtendedTestStatistics::LEVEL_QUESTION:
                $this->ctrl->redirect($this, 'showQuestionsOverview');
                break;
            default:
                $this->ctrl->redirect($this, 'showTestOverview');
        }
    }
}
