<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Test for checking Proxy host form.
 *
 * onBefore prepareProxyData
 *
 * backup hosts
 */
class testPageAdministrationGeneralProxies extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function testPageAdministrationGeneralProxies_Layout() {
		$this->page->login()->open('zabbix.php?action=proxy.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of proxies');
		$this->page->assertHeader('Proxies');

		// Check filter collapse/expand.
		foreach ([true, false] as $status) {
			$this->assertEquals($status, $this->query('xpath://div[contains(@class, "ui-tabs-panel")]')
					->one()->isVisible()
			);
			$this->query('xpath://a[contains(@class, "filter-trigger")]')->one()->click();
		}

		$table = $this->query('class:list-table')->asTable()->one()->waitUntilPresent();
		$this->assertEquals(['', 'Name', 'Mode', 'Encryption', 'Compression', 'Last seen (age)', 'Host count', 'Item count',
				'Required performance (vps)', 'Hosts'], $table->getHeadersText()
		);

		// Check that sortable header is clickable.
		$this->assertTrue($table->query('link:Name')->waitUntilClickable()->exists());

		// Check buttons are disabled by default.
		foreach (['Refresh configuration', 'Enable hosts', 'Disable hosts', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isVisible());
			$this->assertFalse($this->query('button', $button)->one()->isClickable());
		}
	}
}

