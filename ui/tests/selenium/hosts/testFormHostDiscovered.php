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

require_once dirname(__FILE__).'/../common/testFormHost.php';

/**
 * @backup hosts
 */
class testFormHostDiscovered extends testFormHost {

	public static function getState() {
		return [
			[
				[
					'standalone' => true,
					'url' => 'zabbix.php?action=host.edit&hostid=',
					'monitoring' => false
				]
			],
			[
				[
					'standalone' => false,
					'url' => 'zabbix.php?action=host.list',
					'monitoring' => false
				]
			],
			[
				[
					'standalone' => false,
					'url' => 'zabbix.php?action=host.view',
					'monitoring' => true
				]
			]
		];
	}

	/**
	 * Test to check LLD Host with templates.
	 *
	 * @dataProvider getState
	 */
	public function testFormHostDiscovered_FormLayout($data) {
		$this->standalone = $data['standalone'];
		$this->link = $data['url'];
		$this->monitoring = $data['monitoring'];
		$host = 'Host prototype LLD';
		$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($host));
		$form = $this->openForm(($this->standalone ? $this->link.$hostid : $this->link), $host);

		// Check hintbox.
		$form->query('class:icon-help-hint')->one()->click();
		$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

		// Assert text.
		$this->assertEquals("Templates linked by host discovery cannot be unlinked.".
				"\nUse host prototype configuration form to remove automatically linked templates on upcoming discovery.",
				$hint->one()->getText());

		// Close the hint-box.
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();

		$template_table = $form->query('id:linked-templates')->asTable()->one()->waitUntilVisible();

		foreach (['Unlink', 'Unlink and clear'] as $button) {
			$this->assertTrue($template_table->findRow('Name', 'Apache by Zabbix agent')->getColumn('Action')
					->query('button:'.$button)->one()->isClickable());
		}

		$this->assertEquals('', ($template_table->findRow('Name', 'Linux by Zabbix agent', true)->getColumn('Action')->getText()));
		$this->assertEquals('(linked by host discovery)', $template_table->findRow('Name', 'Linux by Zabbix agent', true)
				->getColumn('Name')->query('xpath:.//sup')->one()->getText());

		if (!$this->standalone) {
			$this->query('xpath:.//div[@class="dashboard-widget-head"]/button[@class="overlay-close-btn"]')->one()->click();
		}

	}

	private function openForm($url, $host) {
		$this->page->login()->open($url)->waitUntilReady();

		return ($this->standalone)
			? $this->query('id:host-form')->asForm()->waitUntilReady()->one()
			: $this->filterAndSelectHost($host);
	}

	public function filterAndSelectHost($host) {
		$this->query('button:Reset')->one()->click();
		$this->query('name:zbx_filter')->asForm()->waitUntilReady()->one()->fill(['Name' => $host]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		$host_link = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilVisible()
				->findRow('Name', $host, true)->getColumn('Name')->query($this->monitoring ? 'tag:a' : 'xpath://a[@onclick]')
				->waitUntilClickable();

		if ($this->monitoring) {
			$host_link->asPopupButton()->one()->select('Configuration');
		}
		else {
			$host_link->one()->click();
		}

		return COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
	}
}

