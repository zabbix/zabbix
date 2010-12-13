<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';
 
class testGeneric extends PHPUnit_Extensions_SeleniumTestCase
{
	protected $captureScreenshotOnFailure = TRUE;
	protected $screenshotPath = '/tmp/screenshots';
	protected $screenshotUrl = 'http://localhost/screenshots';

	public $failIfExists = array (
		"pg_query",
		"Error in"
	);

	public static function provider()
	{
		return array(
			// Monitoring
			array('index.php',"ZABBIX"),
			array('dashboard.php',"Dashboard"),
			array('overview.php',"Overview [refreshed every 30 sec]"),
			array('httpmon.php',"Status of Web monitoring [refreshed every 30 sec]"),
			array('latest.php',"Latest data [refreshed every 30 sec]"),
			array('hosts_mon.php',"Hosts"),
			array('tr_status.php',"Status of triggers [refreshed every 30 sec]"),
			array('events.php',"Latest events [refreshed every 30 sec]"),
			array('charts.php',"Custom graphs [refreshed every 30 sec]"),
			array('screens.php',"Custom screens [refreshed every 30 sec]"),
			array('maps.php',"Network maps [refreshed every 30 sec]"),
			array('srv_status.php',"Status of triggers [refreshed every&nbsp;30&nbsp;sec]"),
			// Inventory
			array('hostprofiles.php',"Host profiles"),
			// Reports
			array('report2.php',"Availability report"),
			array('report5.php',"Most busy triggers top 100"),
			array('report6.php',"Bar reports")
			);
	}

	protected function setUp()
	{
		$this->setBrowser('*firefox');
		$this->setBrowserUrl('http://localhost/~zabbix/trunk/frontends/php/');
	}
 
	/**
	* @dataProvider provider
	*/
        public function testTitles($a, $b)
	{
		$this->open($a);
		$this->assertTitleEquals($b);
	}

	/**
	* @dataProvider provider
	*/
        public function testNotExists($a,$b)
	{
		$this->open($a);
		foreach($this->failIfExists as $str)
		{
			$this->assertTextNotPresent($str);
		}
	}
}
?>
