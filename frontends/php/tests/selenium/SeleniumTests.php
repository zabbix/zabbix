<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/testGeneric.php');
require_once(dirname(__FILE__).'/testClicks.php');
require_once(dirname(__FILE__).'/testPageActionsAutoregistration.php');
require_once(dirname(__FILE__).'/testPageActionsDiscovery.php');
require_once(dirname(__FILE__).'/testPageActionsTriggers.php');
require_once(dirname(__FILE__).'/testPageDashboard.php');
require_once(dirname(__FILE__).'/testPageDiscovery.php');
require_once(dirname(__FILE__).'/testPageEvents.php');
require_once(dirname(__FILE__).'/testPageHosts.php');
require_once(dirname(__FILE__).'/testPageMediaTypes.php');
require_once(dirname(__FILE__).'/testPageScreens.php');
require_once(dirname(__FILE__).'/testPageScripts.php');
require_once(dirname(__FILE__).'/testPageSearch.php');
require_once(dirname(__FILE__).'/testPageSlideShows.php');
require_once(dirname(__FILE__).'/testPageTemplates.php');
require_once(dirname(__FILE__).'/testPageUserGroups.php');
require_once(dirname(__FILE__).'/testPageUsers.php');
require_once(dirname(__FILE__).'/testFormHost.php');
require_once(dirname(__FILE__).'/testFormHostGroup.php');
require_once(dirname(__FILE__).'/testFormProfile.php');
require_once(dirname(__FILE__).'/testFormScreen.php');
// ...

class SeleniumTests
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('selenium');

		$suite->addTestSuite('testGeneric');
		$suite->addTestSuite('testClicks');
		$suite->addTestSuite('testPageActionsAutoregistration');
		$suite->addTestSuite('testPageActionsDiscovery');
		$suite->addTestSuite('testPageActionsTriggers');
		$suite->addTestSuite('testPageDashboard');
		$suite->addTestSuite('testPageDiscovery');
		$suite->addTestSuite('testPageScripts');
		$suite->addTestSuite('testPageMediaTypes');
		$suite->addTestSuite('testPageScreens');
		$suite->addTestSuite('testPageSearch');
		$suite->addTestSuite('testPageSlideShows');
		$suite->addTestSuite('testPageTemplates');
		$suite->addTestSuite('testPageUserGroups');
		$suite->addTestSuite('testPageUsers');
		$suite->addTestSuite('testFormHost');
		$suite->addTestSuite('testFormHostGroup');
		$suite->addTestSuite('testFormProfile');
		$suite->addTestSuite('testFormScreen');
		$suite->addTestSuite('testPageHosts');
		// ...

		return $suite;
	}
}
?>
