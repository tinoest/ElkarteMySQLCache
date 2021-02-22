<?php

/**
 * This file contains functions that deal with getting and setting cache values using MySQLCache.
 *
 * @author    tinoest http://tinoest.co.uk
 * @copyright tinoest
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 * @mod       MySQLCache Cache - MySQL based caching mechanism
 *
 * @version 1.0.0
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * MySQLbased caching stores the cache data in a MySQL database
 * The performance gain may or may not exist depending on many factors.
 *
 * It requires the a MySQL database greater than 5.0.0 to work
 */
class Mysqlbased extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Mysql-based caching';

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{
		global $db_name;

		parent::__construct($options);
        self::disableQueryCheck();

		$db = database();
		if( $db->num_rows ( $db->query('', 'SHOW TABLES FROM '.$db_name.' LIKE \'{db_prefix}cache\';') ) == 0 );
		{
			$db_table = db_table();
			$db_table->db_create_table('{db_prefix}cache',
				array(
					array('name' => 'ckey',		'type' => 'text' ),
					array('name' => 'ckey_hash','type' => 'varchar', 'size' => 32 ),
					array('name' => 'value',	'type' => 'text' ),
					array('name' => 'ttl',    	'type' => 'bigint' ),
				),
				array(
					array('name' => 'ckey_hash', 'columns' => array('ckey_hash'), 'type' => 'unique'),
				),
				array(),
				'ignore'
			);
		}

        self::disableQueryCheck(false);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key) {

	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
        global $db_prefix;

        self::disableQueryCheck(true);

		$db	= database();
		$key	= $db->escape_string($key);
		$value	= $db->escape_string($value);
		$ttl	= time();
		$ttl	= $db->escape_string($ttl);

		$query	= 'INSERT INTO '.$db_prefix.'cache (ckey, value, ttl, ckey_hash ) VALUES ( \''.$key.'\', \''.$value.'\', \''.$ttl.'\', MD5(\''.$key.'\') )
				ON DUPLICATE KEY UPDATE value = \''.$value.'\', ttl = \''.$ttl.'\'';
		$result = $db->query('', $query);
        
        self::disableQueryCheck(false);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
        self::disableQueryCheck(true);

		$db	= database();
		$ttl	= time() - $ttl;
		$query	= 'SELECT value FROM {db_prefix}cache WHERE ckey = \'' . $db->escape_string($key) . '\' AND ttl >= ' . $ttl . ' LIMIT 1';
		$result = $db->query('', $query);
        $value  = !empty($value) ? $value : null;

        $this->is_miss = $value === null;

        self::disableQueryCheck(false);
        
        return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
        self::disableQueryCheck(true);

		$db	= database();
		$query	= 'DELETE FROM {db_prefix}cache;';
		$result = $db->query('', $query);

        self::disableQueryCheck(false);

		return $result;

	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		$db = database();
		if( ($db->db_title() == 'MySQL') && (version_compare($db->db_server_version(), '5.0.0', '>=')) ) {
			return true;
        }

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => '1.0.2');
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array $config_vars
	 */
	public function settings(&$config_vars)
	{

	}

    private function disableQueryCheck($state = true)
	{
		static $oldModSetting = null;
		global $modSettings;

		if(isset($modSettings['disableQueryCheck']) && ($state == true)) {
			$oldModSetting	= $modSettings['disableQueryCheck'];
		}

		if($state == false && is_null($oldModSetting)) {
			unset($modSettings['disableQueryCheck']);
		}
		else if( $state == false) {
			$modSettings['disableQueyCheck'] = $oldModSetting;
		}
		else {
			$modSettings['disableQueryCheck'] = $state;
		}

	}

}
