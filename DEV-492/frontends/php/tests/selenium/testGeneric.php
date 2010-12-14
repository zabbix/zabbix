<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';
 
class testGeneric extends PHPUnit_Extensions_SeleniumTestCase
{
	protected $captureScreenshotOnFailure = TRUE;
	protected $screenshotPath = '/tmp/screenshots';
	protected $screenshotUrl = 'http://localhost/screenshots';

	public $failIfExists = array (
		"pg_query",
		"Error in",
		"Undefined index",
		"Undefined variable",
		"Undefined offset",
		"Fatal error",
		"Call to undefined method",
		"Invalid argument supplied",
		"Warning:",
		"PHP notice",
		"PHP warning",
		"Use of undefined",
		"You must login"
	);

	public static function provider()
	{
		return array(
			// Configuration
			array('hostgroups.php',	'Host groups'),
			array('templates.php',	'Templates'),
			array('hosts.php',	'Hosts'),
			array('maintenance.php','Maintenance'),
			array('httpconf.php',	'Configuration of Web monitoring'),
			array('actionconf.php',	'Configuration of actions'),
			array('screenconf.php',	'Configuration of screens'),
			array('slideconf.php',	'Configuration of slideshows'),
			array('sysmaps.php',	'Network maps'),
			array('services.php',	'Configuration of IT services'),
			array('discoveryconf.php','Configuration of discovery'),

			// Administration
			array('config.php',	'Configuration of Zabbix'),
			array('nodes.php',	'Nodes'),
			array('authentication.php','Authentication to Zabbix'),
			array('usergrps.php',	'User groups'),
			array('media_types.php','Media types'),
			array('scripts.php',	'Scripts'),
			array('auditlogs.php',	'Audit'),
			array('queue.php',	'Queue [refreshed every 30 sec]'),
			array('report4.php',	'Notification report'),
			array('instal.php',	'Installation'),

			// Monitoring
			array('index.php',	'ZABBIX'),
			array('dashboard.php',	'Dashboard'),
			array('overview.php',	'Overview [refreshed every 30 sec]'),
			array('httpmon.php',	'Status of Web monitoring [refreshed every 30 sec]'),
			array('latest.php',	'Latest data [refreshed every 30 sec]'),
			array('hosts_mon.php',	'Hosts'),
			array('tr_status.php',	'Status of triggers [refreshed every 30 sec]'),
			array('events.php',	'Latest events [refreshed every 30 sec]'),
			array('charts.php',	'Custom graphs [refreshed every 30 sec]'),
			array('screens.php',	'Custom screens [refreshed every 30 sec]'),
			array('maps.php',	'Network maps [refreshed every 30 sec]'),
			array('srv_status.php',	'IT services [refreshed every 30 sec]'),
			// Inventory
			array('hostprofiles.php','Host profiles'),
			// Reports
			array('report2.php',	'Availability report'),
			array('report5.php',	'Most busy triggers top 100'),
			array('report6.php',	'Bar reports')
			);
	}

	protected function setUp()
	{
		$this->setBrowser('*firefox');
		$this->setBrowserUrl('http://localhost/~zabbix/DEV-492/frontends/php/');
	}

	public function login()
	{
		$this->open('index.php');
		$this->click('link=Login');
		$this->waitForPageToLoad();
		$this->type('name','Admin');
		$this->type('password','zabbix');
		$this->click('enter');
		$this->waitForPageToLoad();
	}

	public function logout()
	{
		$this->click('link=Logout');
		$this->waitForPageToLoad();
	}

	/**
	* @dataProvider provider
	*/
	public function testTitles($a, $b)
	{
		$this->login();
		$this->open($a);
		$this->assertTitleEquals($b);
		$this->logout();
	}

	/**
	* @dataProvider provider
	*/
	public function testNotExists($a,$b)
	{
		$this->login();
		$this->open($a);
		foreach($this->failIfExists as $str)
		{
			$this->assertTextNotPresent($str);
		}
		$this->logout();
	}
}
?>
