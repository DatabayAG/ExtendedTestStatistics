<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Class ilExteStatDetailsTableGUI
 */
class ilExteStatDetailsTableGUI extends ilExteStatTableGUI
{
    protected ilExteStatDetails $details;


    /**
     * ilExteStatDetailsTableGUI constructor.
     */
	public function __construct(?object $a_parent_obj, string $a_parent_cmd)
	{
        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setStyle('table', 'fullwidth');
        $this->setRowTemplate("tpl.il_exte_stat_details_row.html", $this->plugin->getDirectory());
        $this->setFormAction($this->ctrl->getFormAction($a_parent_obj, $a_parent_cmd));

        $this->setEnableHeader(true);
        $this->setEnableAllCommand(false);
    }


    /**
     * Prepare the data to be shown
     */
    public function prepareData(ilExteStatDetails $a_details)
    {
        $this->details = $a_details;

        $this->setId('ilExteStatDetails');
        $this->setPrefix('ilExteStatDetails');
        $this->setFormName('ilExteStatDetails');

        // we have to call the parent constructor here
        // because it needs the ids to determine the sorting
        parent::__construct($this->parent_obj, $this->parent_cmd);

        //Columns
        foreach ($this->details->columns as $column)
        {
        	if ($column->sort == ilExteStatColumn::SORT_NUMBER)
			{
				$title = "<span class='ilExteStatHeaderRight'>".$column->title."</span>";
			}
			else
			{
				$title = (string) $column->title;
			}
            $this->addColumn($title, $column->sort ? 'sort_'.$column->name : '', '', false, '', $column->comment);
        }

        // Row data
        $data = array();
        foreach ($this->details->rows as $rownum => $values)
        {
            foreach ($this->details->columns as $column)
            {
                if (!empty($column->sort) && isset($values[$column->name]))
                {
                    // add scalar value for sorting
                    $values['sort_'.$column->name] = $values[$column->name]->value;
                }
            }
            $data[] = $values;
        }
        $this->setData($data);
        $this->setNoEntriesText($a_details->getEmptyMessage() ?? $this->lng->txt('no_items'));
    }

    /**
     * Should this field be sorted numeric?
     */
    public function numericOrdering(string $a_field): bool
    {
        foreach ($this->details->columns as $column)
        {
            if ($column->name == $a_field)
            {
                if ($column->sort == ilExteStatColumn::SORT_NUMBER)
                {
                    return true;
                }
                else
                {
                    return false;
                }
            }
        }
        return false;
    }


    /**
	 * fill row 
	 */
    protected function fillRow(array $a_set): void
	{
        foreach ($this->details->columns as $column)
        {
            if (isset($a_set[$column->name]))
            {
                $content = $this->valueGUI->getHTML($a_set[$column->name]);
            }
            else
            {
                $content = '';
            }

            $this->tpl->setCurrentBlock('column');
            $this->tpl->setVariable('CONTENT', $content);
            $this->tpl->parseCurrentBlock();
        }
	}
}