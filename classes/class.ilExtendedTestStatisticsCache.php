<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Data cache for extended test statistics
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
class ilExtendedTestStatisticsCache
{
    protected ilDBInterface $db;
    protected int $test_id;             // (not the object_id)
    protected string $pass_selection;   // setting for the selected pass
    protected array $content = [];      // consumer_class => content_key => content
    private bool $validated = false;    // cache is already validated

    /**
     * Constructor.
     * @param integer	$a_test_id	(not the object id)
     * @param string	$a_pass_selection
     */
    public function __construct(int $a_test_id, string $a_pass_selection)
    {
        global $DIC;
        $this->db = $DIC->database();
        $this->test_id = $a_test_id;
        $this->pass_selection = $a_pass_selection;
    }

    /**
     * Set the pass selection
     */
    public function setPassSelection(string $a_pass_selection)
    {
        // cleanup all read content if pass selection changes
        if (isset($this->pass_selection) && $this->pass_selection != $a_pass_selection) {
            $this->content = array();
            $this->validated = false;
        }
        $this->pass_selection = $a_pass_selection;
    }

    /**
     * Preload cached content
     * The content key is dependent from the consumer class
     */
    public function preload(string $a_consumer_class, string $a_content_key_prefix)
    {
        $query = "SELECT content_key, content FROM etstat_cache"
            . " WHERE test_id = " . $this->db->quote($this->test_id, 'integer')
            . " AND pass_selection = " . $this->db->quote($this->pass_selection, 'text')
            . " AND consumer_class = " . $this->db->quote($a_consumer_class, 'text')
            . " AND " . $this->db->like('content_key', 'text', $a_content_key_prefix . '%', false);

        $result = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($result)) {
            $this->content[$a_consumer_class][$row['content_key']] = $row['content'];
        }
    }

    /**
     * Read a cached content
     * The content key is dependent from the consumer class
     */
    public function read(string $a_consumer_class, string $a_content_key): ?string
    {
        $this->validate();

        if (!isset($this->content[$a_consumer_class][$a_content_key])) {
            $query = "SELECT content FROM etstat_cache"
                . " WHERE test_id = " . $this->db->quote($this->test_id, 'integer')
                . " AND pass_selection = " . $this->db->quote($this->pass_selection, 'text')
                . " AND consumer_class = " . $this->db->quote($a_consumer_class, 'text')
                . " AND content_key = " . $this->db->quote($a_content_key, 'text');

            $result = $this->db->query($query);
            if ($row = $this->db->fetchAssoc($result)) {
                $this->content[$a_consumer_class][$a_content_key] = $row['content'];
            }
        }

        return $this->content[$a_consumer_class][$a_content_key] ?? null;
    }


    /**
     * Write a cached content
     * The content key is dependent from the consumer class
     */
    public function write(string $a_consumer_class, string $a_content_key, string $a_content)
    {
        $this->db->replace('etstat_cache', array(
            'test_id' => array('integer', $this->test_id),
            'pass_selection' => array('text', $this->pass_selection),
            'consumer_class' => array('text', $a_consumer_class),
            'content_key' => array('text', $a_content_key)
        ), array(
            'content' => array('clob', $a_content),
            'tstamp' => array('integer', time())
        ));

        $this->content[$a_consumer_class][$a_content_key] = $a_content;
    }

    /**
     * Check if the cache is still valid
     * Delete the cache if it is not valid
     */
    private function validate()
    {
        if ($this->validated) {
            return;
        }

        $test_query = "SELECT max(r.tstamp) last_update FROM tst_test_result r"
            . " INNER JOIN tst_active a ON r.active_fi = a.active_id"
            . " WHERE a.test_fi = " . $this->db->quote($this->test_id, 'integer');

        $test_result = $this->db->query($test_query);
        $test_row = $this->db->fetchAssoc($test_result);


        $cache_query = "SELECT min(tstamp) first_cache FROM etstat_cache"
            . " WHERE test_id = " . $this->db->quote($this->test_id, 'integer');
        $cache_result = $this->db->query($cache_query);
        $cache_row = $this->db->fetchAssoc($cache_result);

        if (empty($test_row) || (!empty($cache_row) && $cache_row['first_cache'] <= $test_row['last_update'])) {
            $this->flush();
        }

        $this->validated = true;
    }

    /**
     * Flush the cached values of a test
     */
    public function flush()
    {
        $this->db->manipulate("DELETE FROM etstat_cache WHERE test_id=" . $this->db->quote($this->test_id, 'integer'));
        $this->content = array();
    }

    /**
     * Flush all cached values (e.g. after an update)
     */
    public static function flushAll()
    {
        global $DIC;
        $DIC->database()->manipulate("DELETE FROM etstat_cache");
    }
}
