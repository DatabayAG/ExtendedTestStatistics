<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE
use ILIAS\UI\Factory;

/**
 * Extended Test statistics configuration user interface class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @author Jesus Copado <jesus.copado@fau.de>
 *
 * @ilCtrl_IsCalledBy ilExtendedTestStatisticsConfigGUI: ilObjComponentSettingsGUI
 */
class ilExtendedTestStatisticsConfigGUI extends ilPluginConfigGUI
{
    protected ilCtrlInterface $ctrl;
    protected ilLanguage $lng;
    protected ilTabsGUI $tabs;
    protected ilToolbarGUI $toolbar;
    protected ilGlobalTemplateInterface $tpl;
    protected Factory $factory;

    /** @var ilExtendedTestStatisticsPlugin $plugin */
    protected ilPlugin $plugin;
    protected ilExtendedTestStatisticsConfig $config;
    protected ilExtendedTestStatisticsCache $cache;

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->toolbar = $DIC->toolbar();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();

        $this->lng->loadLanguageModule('assessment');
    }


    /**
     * Handles all commands, default is "configure"
     */
    public function performCommand(string $cmd): void
    {
        $this->plugin = $this->getPluginObject();
        $this->config = $this->plugin->getConfig();

        // Create a dummy cache
        $this->cache = new ilExtendedTestStatisticsCache(0, '');

        switch ($cmd) {
            case "configure":
            case "showTestEvaluations":
                $this->initTabs("test");
                $this->configure("test");
                break;
            case "showQuestionEvaluations":
                $this->initTabs("question");
                $this->configure("question");
                break;
            case "saveTestSettings":
                $this->initTabs("test");
                $this->saveSettings("test");
                break;
            case "saveQuestionSettings":
                $this->initTabs("question");
                $this->saveSettings("question");
                break;
            case "refreshLanguages":
                $this->refreshLanguages();
                break;
        }
    }

    /**
     * Show configuration screen
     * @var	$a_mode	('test' or 'question')
     */
    protected function configure(string $a_mode)
    {
        $this->toolbar->addComponent(
            $this->factory->button()->standard(
                $this->lng->txt('refresh_languages'),
                $this->ctrl->getLinkTarget($this, 'refreshLanguages')
            )
        );

        $form = $this->initConfigurationForm($a_mode);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Refresh the languages
     */
    protected function refreshLanguages()
    {
        $this->plugin->refreshLanguages();
        $this->ctrl->redirect($this, 'configure');
    }


    /**
     * Initialize the configuration form
     * @param $a_type	('test' or 'question')
     */
    protected function initConfigurationForm(string $a_type): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));

        // Run throw all the test evaluations to check if there must be available for admins
        // or users or not available in test of current platform
        /** @var ilExteEvalBase $class	(classname, not object) */
        foreach ($this->config->getEvaluationClasses($a_type) as $class => $availability) {
            $evaluation = new $class($this->plugin, $this->cache);
            $prefix = $evaluation->getLangPrefix();

            $select_input = new ilSelectInputGUI($this->plugin->txt($prefix . "_title_long"), $class);
            $select_input->setOptions($this->config->getAvailabilityOptions());
            $select_input->setValue($availability);
            $select_input->setInfo($this->plugin->txt($prefix . "_description")
                . '<br /><em>' . sprintf($this->plugin->txt('evaluation_info_id'), $class) . '</em>');
            $form->addItem($select_input);

            foreach ($evaluation->getParams() as $name => $param) {
                $title = $evaluation->txt($name . '_title');
                $description = $evaluation->txt($name . '_description');
                $postvar = $class . '_' . $name;

                switch ($param->type) {
                    case ilExteStatParam::TYPE_BOOLEAN:
                        $input = new ilCheckboxInputGUI($title, $postvar);
                        $input->setChecked($param->value);
                        break;
                    case ilExteStatParam::TYPE_FLOAT:
                        $input = new ilNumberInputGUI($title, $postvar);
                        $input->allowDecimals(true);
                        $input->setSize(10);
                        $input->setValue($param->value);
                        break;
                    case ilExteStatParam::TYPE_STRING:
                        $input = new ilTextInputGUI($title, $postvar);
                        $input->setMaxLength(100);
                        $input->setValue($param->value);
                        break;
                    case ilExteStatParam::TYPE_INT:
                    default:
                        $input = new ilNumberInputGUI($title, $postvar);
                        $input->allowDecimals(false);
                        $input->setSize(10);
                        $input->setValue($param->value);
                        break;
                }
                $input->setInfo($description);
                $select_input->addSubItem($input);
            }

        }

        $form->setTitle($this->plugin->txt($a_type == 'test' ? 'test_evaluation_settings' : 'question_evaluation_settings'));
        $form->addCommandButton($a_type == 'test' ? "saveTestSettings" : "saveQuestionSettings", $this->lng->txt("save"));
        return $form;
    }

    /**
     * Save the settings
     * @param $a_type ('test' or 'question')
     */
    protected function saveSettings(string $a_type)
    {
        $form = $this->initConfigurationForm($a_type);
        if ($form->checkInput()) {
            foreach ($this->config->getEvaluationClasses($a_type) as $class => $availability) {
                $new_availability = $form->getInput($class);
                if ($new_availability) {
                    $this->config->writeAvailability($class, $new_availability);

                    /** @var ilExteEvalBase $evaluation */
                    $evaluation = new $class($this->plugin, $this->cache);
                    foreach ($evaluation->getParams() as $name => $param) {
                        $postvar = $class . '_' . $name;
                        $this->config->writeParameter($class, $name, (string) $form->getInput($postvar));
                    }
                }
            }
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt($a_type == 'test' ? "test_settings_saved" : "question_settings_saved"), true);
            $this->ctrl->redirect($this, $a_type == 'test' ? "showTestEvaluations" : "showQuestionEvaluations");
        } else {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHtml());
        }
    }

    /**
     * Init the Tabs
     * @param string $a_mode	active settings mode ('test' or 'question')
     */
    protected function initTabs(string $a_mode = "")
    {
        $this->tabs->addTab("show_test_evaluations", $this->plugin->txt('show_test_evaluations'), $this->ctrl->getLinkTarget($this, 'showTestEvaluations'));
        $this->tabs->addTab("show_question_evaluations", $this->plugin->txt('show_question_evaluations'), $this->ctrl->getLinkTarget($this, 'showQuestionEvaluations'));
        $this->tabs->activateTab($a_mode == 'test' ? 'show_test_evaluations' : 'show_question_evaluations');
    }
}
