<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * User interface hook class
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExtendedTestStatisticsUIHookGUI extends ilUIHookPluginGUI
{
    protected ilCtrlInterface $ctrl;
    protected ilTabsGUI $tabs;


    /**
     * Modify GUI objects, before they generate ouput
     *
     * @param string $a_comp component
     * @param string $a_part string that identifies the part of the UI that is handled
     * @param array $a_par array of parameters (depend on $a_comp and $a_part)
     */
    public function modifyGUI(
        string $a_comp,
        string $a_part,
        array $a_par = []
    ): void {
        switch ($a_part) {
            // case 'tabs':
            case 'sub_tabs':

                // must be done here because ctrl and tabs are not initialized for all calls
                global $DIC;
                $this->ctrl = $DIC->ctrl();
                $this->tabs = $DIC->tabs();


                if ($this->ctrl->getCmdClass() == 'iltestparticipantsgui') {
                    $this->ctrl->saveParameterByClass('ilExtendedTestStatisticsPageGUI', 'ref_id');

                    foreach ($this->tabs->target as $target) {
                        if (($target['id'] ?? '') == 'participants') {
                            $this->tabs->sub_target[] = $target;
                            break;
                        }
                    }
                    $this->tabs->setSubTabActive('participants');

                    // we need to use the deprecated method because evaluation sub tabs work with automatic activation
                    // with addSubTab the new sub tabs would always be activated
                    $this->tabs->addSubTabTarget(
                        $this->plugin_object->txt('test_results'), // text is also the subtab id
                        $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI','ilExtendedTestStatisticsPageGUI'), 'showTestOverview'),
                        array('showTestOverview','showTestDetails'), // commands to be recognized for activation
                        'ilExtendedTestStatisticsPageGUI', 	// cmdClass to be recognized activation
                        '', 								// frame
                        false, 								// manual activation
                        true								// text is direct, not a language var
                    );

                    $this->tabs->addSubTabTarget(
                        $this->plugin_object->txt('questions_results'), // text is also the subtab id
                        $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI','ilExtendedTestStatisticsPageGUI'), 'showQuestionsOverview'),
                        array('showQuestionsOverview', 'showQuestionDetails'), 	// commands to be recognized for activation
                        'ilExtendedTestStatisticsPageGUI', 	// cmdClass to be recognized activation
                        '', 								// frame
                        false, 								// manual activation
                        true								// text is direct, not a language var
                    );

                    // save the tabs for reuse on the plugin pages
                    // (these do not have the test gui as parent)
                    $this->saveTabs('iltestparticipantsgui');
                }

                if (strtolower($this->ctrl->getCmdClass()) == 'ilextendedteststatisticspagegui') {
                    // reuse the tabs that were saved from the test gui
                    $this->restoreTabs('iltestparticipantsgui');
                    // this works because the tabs are rendered after the sub tabs
                    $this->tabs->activateTab('participants');
                }
                break;

            default:
                break;
        }
    }

    /**
     * Save the tabs for reuse on the plugin pages
     * @param string $a_context context for which the tabs should be saved
     */
    protected function saveTabs(string $a_context): void
    {
        $this->setArrayInSession($a_context, 'TabTarget', $this->tabs->target);
        $this->setArrayInSession($a_context, 'TabSubTarget', $this->tabs->sub_target);
    }

    /**
     * Restore the tabs for reuse on the plugin pages
     * @param string $a_context context for which the tabs should be saved
     */
    protected function restoreTabs(string $a_context): void
    {
        // reuse the tabs that were saved from the parent gui
        if (!empty($target = $this->getArrayFromSession($a_context, 'TabTarget'))) {
            $this->tabs->target = $target;
        }
        if (!empty($target = $this->getArrayFromSession($a_context, 'TabSubTarget'))) {
            $this->tabs->sub_target = $target;
        }
    }

    protected function setArrayInSession(string $a_context, string $name, array $array): void
    {
        ilSession::set(__class__ . '.' . $a_context . '.' . $name, serialize($array));
    }

    protected function getArrayFromSession(string $a_context, string $name): ?array
    {
        try {
            return unserialize(ilSession::get(__class__ . '.' . $a_context . '.' . $name));
        } catch (Exception $e) {
            return null;
        }
    }
}
