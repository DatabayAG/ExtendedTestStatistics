<?php

require_once ('Modules/Test/classes/class.ilObjTest.php');

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
	/** @var ilCtrl $ctrl */
	protected $ctrl;

	/** @var ilTemplate $tpl */
	protected $tpl;

	/** @var ilExtendedTestStatisticsPlugin $plugin */
	protected $plugin;

	/** @var ilObjTest $testObj */
	protected $testObj;

	/** @var ilExtendedTestStatistics $statObj */
	protected $statObj;

	/**
	 * ilExtendedTestStatisticsPageGUI constructor.
	 */
	public function __construct()
	{
		global $ilCtrl, $tpl, $lng;

		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;

		$lng->loadLanguageModule('assessment');

		$this->plugin = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'ExtendedTestStatistics');
		$this->plugin->includeClass('class.ilExtendedTestStatistics.php');

		$this->testObj = new ilObjTest($_GET['ref_id']);
		$this->statObj = new ilExtendedTestStatistics($this->testObj, $this->plugin);
	}

	/**
	* Handles all commmands, default is "show"
	*/
	public function executeCommand()
	{
		/** @var ilAccessHandler $ilAccess */
		/** @var ilErrorHandling $ilErr */
		global $ilAccess, $ilErr, $lng;

		if (!$ilAccess->checkAccess('write','',$this->testObj->getRefId()))
		{
            ilUtil::sendFailure($lng->txt("permission_denied"), true);
            ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
		}

		$this->ctrl->saveParameter($this, 'ref_id');
		$cmd = $this->ctrl->getCmd('showTestOverview');

		switch ($cmd)
		{
			case "showTestOverview":
            case "showTestDetails":
			case "showQuestionsOverview":
            case "showQuestionDetails":
                $this->prepareOutput();
                $this->$cmd();
                break;
			default:
                ilUtil::sendFailure($lng->txt("permission_denied"), true);
                ilUtil::redirect("goto.php?target=tst_".$this->testObj->getRefId());
				break;
		}
	}

	/**
	 * Get the plugin object
	 * @return ilExtendedTestStatisticsPlugin|null
	 */
	public function getPlugin()
	{
		return $this->plugin;
	}

    /**
     * Get the statistics object
     * @return     ilExtendedTestStatistics|null
     */
    public function getStatisticsObject()
    {
        return $this->statObj;
    }

	/**
	 * Prepare the test header, tabs etc.
	 */
	protected function prepareOutput()
	{
		/** @var ilLocatorGUI $ilLocator */
		/** @var ilLanguage $lng */
		global $ilLocator, $lng;

		$this->ctrl->setParameterByClass('ilObjTestGUI', 'ref_id',  $this->testObj->getRefId());
		$ilLocator->addRepositoryItems($this->testObj->getRefId());
		$ilLocator->addItem($this->testObj->getTitle(),$this->ctrl->getLinkTargetByClass('ilObjTestGUI'));

		$this->tpl->getStandardTemplate();
		$this->tpl->setLocator();
		$this->tpl->setTitle($this->testObj->getPresentationTitle());
		$this->tpl->setDescription($this->testObj->getLongDescription());
		$this->tpl->setTitleIcon(ilObject::_getIcon('', 'big', 'tst'), $lng->txt('obj_tst'));
	}

	/**
	 * Show the test overview
	 */
	protected function showTestOverview()
	{
		$this->statObj->loadSourceData();
		$this->statObj->loadEvaluations(ilExtendedTestStatistics::LEVEL_TEST);

		$this->plugin->includeClass('tables/class.ilExteStatTestOverviewTableGUI.php');
		$tableGUI = new ilExteStatTestOverviewTableGUI($this, 'showTestOverview');
		$tableGUI->prepareData();

		$this->tpl->setContent($tableGUI->getHTML());
		$this->tpl->show();
	}

    /**
     * Show the detailed evaluation for a test
     */
    protected function showTestDetails()
    {
        $this->ctrl->saveParameter($this, 'details');

        $this->statObj->loadSourceData();
        $this->statObj->loadEvaluations(ilExtendedTestStatistics::LEVEL_TEST, $_GET['details']);

        $content = '';
        $evaluation = $this->statObj->getEvaluation($_GET['details']);
        foreach ($evaluation->calculateDetails() as $detailsObj)
        {
            $this->plugin->includeClass('tables/class.ilExteStatDetailsTableGUI.php');
            $tableGUI = new ilExteStatDetailsTableGUI($this, 'showTestDetails');
            $tableGUI->prepareData($detailsObj);
            $content .= $tableGUI->getHTML();
        }

        $this->tpl->setContent($content);
        $this->tpl->show();
    }

    /**
     * Show the questions overview
     */
	protected function showQuestionsOverview()
	{
        $this->statObj->loadSourceData();
        $this->statObj->loadEvaluations(ilExtendedTestStatistics::LEVEL_QUESTION);

        $this->plugin->includeClass('tables/class.ilExteStatQuestionsOverviewTableGUI.php');
        $tableGUI = new ilExteStatQuestionsOverviewTableGUI($this, 'showQuestionsOverview');
        $tableGUI->prepareData();

        $this->tpl->setContent($tableGUI->getHTML());
        $this->tpl->show();
	}


    /**
     * Show the detailed evaluation for a question
     */
    protected function showQuestionDetails()
    {
        $this->ctrl->saveParameter($this, 'details');
        $this->ctrl->saveParameter($this, 'qid');

        $this->statObj->loadSourceData();
        $this->statObj->loadEvaluations(ilExtendedTestStatistics::LEVEL_QUESTION, $_GET['details']);

        $content = '';
        $evaluation = $this->statObj->getEvaluation($_GET['details']);
        foreach ($evaluation->calculateDetails($_GET['qid']) as $detailsObj)
        {
            $this->plugin->includeClass('tables/class.ilExteStatDetailsTableGUI.php');
            $tableGUI = new ilExteStatDetailsTableGUI($this, 'showTestDetails');
            $tableGUI->prepareData($detailsObj);
            $content .= $tableGUI->getHTML();
        }

        $this->tpl->setContent($content);
        $this->tpl->show();
    }
}
?>