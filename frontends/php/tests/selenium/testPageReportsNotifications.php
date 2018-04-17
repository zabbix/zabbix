<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageReportsNotifications extends CWebTest {
	public function testPageReportsNotifications_CheckLayout() {
		// Perform a login and open page "report4.php?ddreset=1"
		$this->zbxTestLogin('report4.php');
		// Check title of the page
		$this->zbxTestCheckTitle('Notification report');
		$this->zbxTestCheckHeader('Notifications');
		// Check for dropdown elements
		$this->zbxTestDropdownHasOptions('media_type',['all','Email','Jabber','SMS','SMS via IP']);
		$this->zbxTestDropdownHasOptions('period',['Daily','Weekly','Monthly','Yearly']);
		$this->zbxTestDropdownHasOptions('year',['2012','2013','2014','2015','2016','2017']);
		// Check default selected dropdown values
		$this->zbxTestDropdownAssertSelected('media_type','all');
		$this->zbxTestDropdownAssertSelected('period','Weekly');
		$this->zbxTestDropdownAssertSelected('year','2018');
		// Check links
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=1")]','Email');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=2")]','Jabber');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=3")]','SMS');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=4")]','SMS via IP');
		// Check columns exist for all users
		$this->zbxTestAssertElementText('//table/thead/tr/th[4]/div/div', 'admin-zabbix');
		$this->zbxTestAssertElementText('//table/thead/tr/th[5]/div/div', 'disabled-user');
		$this->zbxTestAssertElementText('//table/thead/tr/th[6]/div/div', 'guest');
		$this->zbxTestAssertElementText('//table/thead/tr/th[7]/div/div', 'no-access-to-the-frontend');
		$this->zbxTestAssertElementText('//table/thead/tr/th[8]/div/div', 'test-user');
		$this->zbxTestAssertElementText('//table/thead/tr/th[9]/div/div', 'user-for-blocking');
		$this->zbxTestAssertElementText('//table/thead/tr/th[10]/div/div', 'user-zabbix');
	}

	// Check media_type drop downs
	public function testPageReportsNotifications_CheckFilters() {
		$this->zbxTestLogin('report4.php');
		// Select 2016 monthly
		$this->zbxTestDropdownSelect('period', 'Monthly');
		// Select 2017 monthly
		$this->zbxTestDropdownSelect('year', '2017');
		// Check report for November 2017
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[2]', '3 (2/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[3]', '2 (1/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[4]', '5 (3/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[5]', '3 (1/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[6]', '2 (0/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[7]', '4 (3/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[8]', '3 (1/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[9]', '2 (1/0/1/0)');
		// Check report for 2016
		$this->zbxTestDropdownSelect('year','2016');
		$this->zbxTestAssertElementText('//table/tbody/tr[2]/td[5]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[2]/td[7]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[11]/td[2]', '1 (1/0/0/0)');

		// Select yearly filtering
		$this->zbxTestDropdownSelect('period', 'Yearly');
		// Check report with yearly filtering
		$this->zbxTestAssertElementNotPresentId('year');
		$this->zbxTestAssertElementText('//table/tbody/tr[1]/td[2]', '5 (5/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[2]/td[6]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[3]/td[4]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[3]/td[8]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[4]/td[3]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[4]/td[9]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[2]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[5]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[7]', '1 (1/0/0/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[2]', '3 (2/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[3]', '2 (1/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[4]', '5 (3/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[5]', '3 (1/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[6]', '2 (0/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[7]', '4 (3/0/1/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[8]', '3 (1/0/2/0)');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[9]', '2 (1/0/1/0)');

		// Check filtering by media type
		$this->zbxTestDropdownSelect('media_type', 'Email');
		// Check links
		$this->zbxTestAssertElementNotPresentXpath('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=1")]');
		$this->zbxTestAssertElementNotPresentXpath('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=2")]');
		$this->zbxTestAssertElementNotPresentXpath('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=3")]');
		$this->zbxTestAssertElementNotPresentXpath('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=4")]');
		$this->zbxTestAssertElementNotPresentId('year');
		// Check report for Emails
		$this->zbxTestAssertElementNotPresentId('year');
		$this->zbxTestAssertElementText('//table/tbody/tr[1]/td[2]', '5');
		$this->zbxTestAssertElementText('//table/tbody/tr[2]/td[6]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[3]/td[4]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[3]/td[8]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[4]/td[3]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[4]/td[9]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[2]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[5]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[5]/td[7]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[2]', '2');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[3]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[4]', '3');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[5]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[7]', '3');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[8]', '1');
		$this->zbxTestAssertElementText('//table/tbody/tr[6]/td[9]', '1');

		// Check links than all media selected
		$this->zbxTestDropdownSelect('media_type', 'all');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=1")]','Email');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=2")]','Jabber');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=3")]','SMS');
		$this->zbxTestAssertElementText('//a[contains(@href, "zabbix.php?action=mediatype.edit&mediatypeid=4")]','SMS via IP');
	}
}
