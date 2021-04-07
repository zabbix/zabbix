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
			[
				[
					'link' => 'zabbix.php?action=iconmap.delete&iconmapid=101'
				]
			],
			// Icon mapping update.
			[
				[
					'link' => 'zabbix.php?action=iconmap.update&form_refresh=1&form=1&iconmapid='.
							'101&iconmap%5Bname%5D=Icon+mapping+name+change&iconmap%5Bmappings%5D%5B0%5D%5Binventory_link'.
							'%5D=4&iconmap%5Bmappings%5D%5B0%5D%5Bexpression%5D=%281%21%40%23%24%25%5E-%3D2*%29&iconmap'.
							'%5Bmappings%5D%5B0%5D%5Biconid%5D=5&iconmap%5Bdefault_iconid%5D=15&update=Update'
				]
			],
			// Image icon delete.
			[
				[
					'link' => 'zabbix.php?action=image.delete&imageid=1&imagetype=1'
				]
			],
			// Image icon update.
			[
				[
					'link' => 'zabbix.php?action=image.update&form_refresh=1&imagetype=1&imageid=1&name=new_name2&update=Update'
				]
			],
			// Module scan.
			[
				[
					'link' => 'zabbix.php?form_refresh=1&action=module.scan&form=Scan+directory'
				]
			],
			// Regular expressions delete.
			[
				[
					'link' => 'zabbix.php?action=regex.delete&regexids%5B0%5D=20'
				]
			],
			// Regular expressions update.
			[
				[
					'link' => 'zabbix.php?action=regex.update&regexid=20&form_refresh=1&name=1_regexp_1_1&expressions'.
							'%5B0%5D%5Bexpression_type%5D=0&expressions%5B0%5D%5Bexpression%5D=first+test+string&'.
							'expressions%5B0%5D%5Bexp_delimiter%5D=%2C&expressions%5B0%5D%5Bcase_sensitive%5D='.
							'1&expressions%5B0%5D%5Bexpressionid%5D=20&test_string=first+test+string&update=Update'
				]
			],
			// Timeselector update.
			[
				[
					'link' => 'zabbix.php?action=timeselector.update&type=11&method=rangechange'
				]
			],
			// Monitoring hosts, tab filter clicking.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.hosts.selected'
				]
			],
			// Monitoring hosts, tab filter collapse.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=0&idx=web.monitoring.hosts.expanded'
				]
			],
			// Monitoring hosts, tab filter expand.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.hosts.expanded'
				]
			],
			// Monitoring hosts, tab filter order.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_str=0%2C2%2C1&idx=web.monitoring.hosts.taborder'
				]
			],
			// Monitoring hosts, tab filter update.
			[
				[
					'link' => 'zabbix.php?action=popup.tabfilter.update&idx=web.monitoring.hosts&idx2=1&create=0&'.
							'support_custom_time=0&filter_name=Untitled'
				]
			],
			// Monitoring hosts, tab filter delete.
			[
				[
					'link' => 'zabbix.php?action=popup.tabfilter.delete&idx=web.monitoring.hosts&idx2=1'
				]
			],
			// Monitoring problems, tab filter clicking.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.problem.selected'
				]
			],
			// Monitoring problems, tab filter collapse.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=0&idx=web.monitoring.problem.expanded'
				]
			],
			// Monitoring problems, tab filter expand.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.problem.expanded'
				]
			],
			// Monitoring problems, tab filter order.
			[
				[
					'link' => 'zabbix.php?action=tabfilter.profile.update&value_str=0%2C2%2C1%2C3&idx=web.monitoring.problem.taborder'
				]
			],
			// Monitoring problems, tab filter update.
			[
				[
					'link' => 'zabbix.php?action=popup.tabfilter.update&idx=web.monitoring.problem&idx2=1&create=0&'.
							'support_custom_time=1&filter_name=Untitled_2'
				]
			],
			// Monitoring problems, tab filter delete.
			[
				[
					'link' => 'zabbix.php?action=popup.tabfilter.delete&idx=web.monitoring.problem&idx2=1'
				]
			],
			// Host mass update.
			[
				[
					'link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.host&ids%5B0%5D=50011&ids%5B1%5D=50012&'.
							'tls_accept=0&update=1&location_url=hosts.php&visible%5Bstatus%5D=1&status=1'
				]
			],
			// Item mass update.
			[
				[
					'link' => 'zabbix.php?form_refresh=1&ids%5B0%5D=99086&ids%5B1%5D=99091&action=popup.massupdate.item&'.
							'prototype=0&update=1&location_url=items.php%3Fcontext%3Dhost&context=host&'.
							'visible%5Bstatus%5D=1&status=1'
				]
			],
			// Template mass update.
			[
				[
					'link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.template&update=1&ids%5B0%5D=10076&'.
							'ids%5B1%5D=10207&location_url=templates.php&visible%5Bdescription%5D=1&description=%2C'
				]
			],
			// Trigger mass update.
			[
				[
					'link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.trigger&ids%5B0%5D=100034&'.
							'ids%5B1%5D=100036&update=1&location_url=triggers.php%3Fcontext%3Dhost&context=host&'.
							'visible%5Bmanual_close%5D=1&manual_close=1'
				]
			]
		];
	}

	/**
	 * @dataProvider getLinksData
	 */
	public function testSID_Links($data) {
		foreach ([$data['link'], $data['link'].'&sid=test111116666666'] as $link) {
			$this->page->login()->open($link)->waitUntilReady();
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
			$this->query('button:Go to "Dashboard"')->one()->waitUntilClickable()->click();
			$this->assertContains('zabbix.php?action=dashboard.view', $this->page->getCurrentUrl());
		}
	}
}
