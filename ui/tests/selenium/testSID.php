<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

class testSID extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getLinksData() {
		return [
			// Icon mapping delete
			[['link' => 'zabbix.php?action=iconmap.delete&iconmapid=101']],

			// Icon maping update.
			[['link' => 'zabbix.php?action=iconmap.update&form_refresh=1&form=1&iconmapid='.
					'101&iconmap%5Bname%5D=Icon+mapping+name+change&iconmap%5Bmappings%5D%5B0%5D%5Binventory_link'.
					'%5D=4&iconmap%5Bmappings%5D%5B0%5D%5Bexpression%5D=%281%21%40%23%24%25%5E-%3D2*%29&iconmap'.
					'%5Bmappings%5D%5B0%5D%5Biconid%5D=5&iconmap%5Bdefault_iconid%5D=15&update=Update']],

			// Image icon delete.
			[['link' => 'zabbix.php?action=image.delete&imageid=1&imagetype=1']],

			// Image icon update.
			[['link' => 'zabbix.php?action=image.update&form_refresh=1&imagetype=1&imageid=1&name=new_name2&update=Update']],

			// Module scan.
			[['link' => 'zabbix.php?form_refresh=1&action=module.scan&form=Scan+directory']],

			// Regular expressions delete.
			[['link' => 'zabbix.php?action=regex.delete&regexids%5B0%5D=20']],

			// Regular expressions update.
			[['link' => 'zabbix.php?action=regex.update&regexid=20&form_refresh=1&name=1_regexp_1_1&expressions'.
					'%5B0%5D%5Bexpression_type%5D=0&expressions%5B0%5D%5Bexpression%5D=first+test+string&'.
					'expressions%5B0%5D%5Bexp_delimiter%5D=%2C&expressions%5B0%5D%5Bcase_sensitive%5D='.
					'1&expressions%5B0%5D%5Bexpressionid%5D=20&test_string=first+test+string&update=Update']],

			// Timeselector update.
			[['link' => 'zabbix.php?action=timeselector.update&type=11&method=rangechange']],

			// Value mapping delete.
			[['link' => 'zabbix.php?action=valuemap.delete&valuemapids%5B0%5D=83']],

			// Value mapping update.
			[['link' => 'zabbix.php?action=valuemap.update&form_refresh=1&valuemapid=161&name=new_name&mappings'.
					'%5B0%5D%5Bvalue%5D=test&mappings%5B0%5D%5Bnewvalue%5D=test&update=Update']],
		];
	}

	/**
	 * @dataProvider getLinksData
	 */
	public function testSID_Links($data) {
		foreach ([$data['link'], $data['link'].'&sid=test111116666666'] as $link) {
			$this->page->login()->open($link)->waitUntilReady();
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
			$this->query('button:Go to dashboard')->one()->waitUntilClickable()->click();
			$this->assertContains('zabbix.php?action=dashboard.view', $this->page->getCurrentUrl());
		}
	}
}
