<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatLegendTableGUI
 */
class ilExteStatLegendTableGUI extends ilExteStatTableGUI
{
    /**
     * Constructor
     * @param object    $a_parent_obj
     * @param string    $a_parent_cmd
     */
    public function __construct($a_parent_obj, $a_parent_cmd)
    {
        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setStyle('table', 'fullwidth');
        $this->setRowTemplate("tpl.il_exte_stat_legend_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->setEnableHeader(false);
        $this->setEnableAllCommand(false);
        $this->setEnableNumInfo(false);
        $this->setExternalSegmentation(true);
        $this->setId('ilExteStatLegend');

        parent::__construct($this->parent_obj, $this->parent_cmd);

        $this->setTitle($this->lng->txt('legend'));
        $this->addColumn($this->plugin->txt("legend_symbol_format"));
        $this->addColumn($this->lng->txt("description"));

        $this->setData($this->valueGUI->getLegendData());
    }

    /**
     * fill row
     */
    protected function fillRow(array $a_set): void
    {
        $this->tpl->setVariable('VALUE', $this->valueGUI->getHTML($a_set['value']));
        $this->tpl->setVariable('DESCRIPTION', $a_set['description']);
    }
}
