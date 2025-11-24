<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

abstract class ilExteStatTableGUI extends ilTable2GUI
{
    protected $uiFactory;
    protected $uiRenderer;
    protected ?object $parent_obj;
    protected string $parent_cmd;

    protected ilExtendedTestStatisticsPlugin $plugin;
    protected ilExtendedTestStatistics $statObj;
    protected ilExteStatValueGUI $valueGUI;


    /**
     * Constructor.
     */
    public function __construct(?object $a_parent_obj, string $a_parent_cmd = "")
    {
        global $DIC;

        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();

        $this->parent_obj = $a_parent_obj;
        $this->parent_cmd = $a_parent_cmd;
        $this->plugin = $a_parent_obj->getPlugin();
        $this->statObj = $a_parent_obj->getStatisticsObject();

        if (!isset($this->valueGUI)) {
            $this->valueGUI = new ilExteStatValueGUI($this->plugin);
            $this->valueGUI->setShowComment(true);
        }

        if (isset($this->id)) {
            // wait for a second call if id is set by child class
            parent::__construct($a_parent_obj, $a_parent_cmd);
        }
    }

    /**
     * Create a table
     */
    public static function _create(string $a_class, object $a_parent_obj, string $a_parent_cmd): ilExteStatTableGUI
    {
        /** @var ilExtendedTestStatisticsPlugin $plugin */
        $plugin = $a_parent_obj->getPlugin();

        return new $a_class($a_parent_obj, $a_parent_cmd);
    }
}
