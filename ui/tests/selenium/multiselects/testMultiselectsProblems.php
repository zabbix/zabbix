<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testMultiselectDialogs.php';

/**
 * Test for checking that multiselects' dialogs do not contain any errors before and after filling.
 */
class testMultiselectsProblems extends testMultiselectDialogs {

	public static function getCheckDialogsData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Host groups' => 'Zabbix servers'
					]
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Hosts' => 'ЗАББИКС Сервер'
					]
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Triggers' => 'First test trigger with tag priority'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckDialogsData
	 */
	public function testMultiselectsProblems_CheckDialogs($data) {
		$this->page->login()->open('zabbix.php?action=problem.view');
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$multiselects = [
			['Host groups' => 'Host groups'],
			['Hosts' => 'Hosts', 'Host group' => 'Host groups'],
			['Triggers' => 'Triggers', 'Host' => 'Hosts', 'Host group' => 'Host groups']
		];

		// Check all multiselects in filter before one of the multiselects is filled.
		$this->checkMultiselectDialogs($filter_form, $multiselects);
		$filter_form->fill($data['fields']);

		// Check all multiselects in filter after one of the multiselects is filled.
		$this->checkMultiselectDialogs($filter_form, $multiselects);

		$this->query('button:Reset')->waitUntilClickable()->one()->click();
	}
}
