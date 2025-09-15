<#1>
<?php
// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Database creation script.
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 * @author Jesus Copado <jesus.copado@ili.fau.de>
 *
 * $Id$
 */
/*
 * Create the new table for evaluation settings
 */
global $ilDB;

if (!$ilDB->tableExists('etstat_settings'))
{
    $fields = array(
        'evaluation_name' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
        ),
        'value' => array(
            'type' => 'text',
            'length' => 8,
            'notnull' => true,
            'default' => 'admin'
        )
    );
    $ilDB->createTable("etstat_settings", $fields);
    $ilDB->addPrimaryKey("etstat_settings", array("evaluation_name"));
}
?>
<#2>
<?php
    // nothig todo
?>
<#3>
<?php
if (!$ilDB->tableExists('etstat_params'))
{
    $fields = array(
        'evaluation_name' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
        ),
        'parameter_name' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
        ),
        'value' => array(
            'type' => 'text',
            'length' => 8,
            'notnull' => false,
            'default' => null
        )
    );
    $ilDB->createTable("etstat_params", $fields);
    $ilDB->addPrimaryKey("etstat_params", array("evaluation_name", "parameter_name"));
}
?>
<#4>
<?php
    if (!$ilDB->tableExists('etstat_cache'))
    {
        $fields = array(
            'test_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
            ),
            'pass_selection' => array(
				'type' => 'text',
				'length' => 10,
				'notnull' => true,
            ),
			'consumer_class' => array(
				'type' => 'text',
				'length' => 50,
				'notnull' => true,
			),
			'content_key' => array(
				'type' => 'text',
				'length' => 50,
				'notnull' => false,
                'default' => null
			),
            'content' => array(
                'type' => 'clob',
                'notnull' => false,
                'default' => null
            ),
            'tstamp' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
	        ),
        );
        $ilDB->createTable("etstat_cache", $fields);
        $ilDB->addPrimaryKey("etstat_cache", array("test_id", "pass_selection", 'consumer_class', 'content_key'));
		$ilDB->addIndex('etstat_cache', array('tstamp'), 'i1');
    }
?>
<#5>
<?php
	//Enlarge storage for params
    if($ilDB->tableColumnExists('etstat_params', 'value'))
    {
    	$ilDB->query('ALTER TABLE etstat_params MODIFY value VARCHAR(100)');
    }
?>
<#6>
<?php
if (!$ilDB->tableExists('etstat_exports'))
{
    $fields = [
        'obj_id' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ],
        'rid' => [
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
        ]
    ];
    $ilDB->createTable("etstat_exports", $fields);
    $ilDB->addPrimaryKey("etstat_exports", ['rid']);
    $ilDB->addIndex('etstat_exports', ['obj_id'], 'i1');
}
?>
