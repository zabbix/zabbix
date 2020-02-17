<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @browsers chrome
 *
 * @on-before disableDebugMode
 * @on-after enableDebugMode
 */
class testMultiselect extends CWebTest {

	/*
	 * Debug button sometimes changes pages layout.
	 */
	public static function setDebugMode($value) {
		DBexecute('UPDATE usrgrp SET debug_mode='.zbx_dbstr($value).' WHERE usrgrpid=7');
	}

	public function disableDebugMode() {
		self::setDebugMode(0);
	}

	public static function enableDebugMode() {
		self::setDebugMode(1);
	}

	public function testMultiselect_SuggestExisting() {
		$this->checkSuggest('zabbix.php?action=problem.view', 'zbx_filter',
			'Host groups', 'z', 'multiselect-suggest'
		);
	}

	public function testMultiselect_SuggestNoMatches() {
		$this->checkSuggest('zabbix.php?action=problem.view','zbx_filter',
			'Host groups', 'QQQ', 'multiselect-matches'
		);
	}

	public function testMultiselect_SuggestCreateNew() {
		$this->checkSuggest('hosts.php?form=create', 'hostsForm',
			'Groups', 'QQQwww', 'multiselect-suggest'
		);
	}

	public function checkSuggest($link, $query, $name, $string, $class) {
		$this->page->login()->open($link)->waitUntilReady();
		$this->page->updateViewport();
		$form = $this->query('name:'.$query)->asForm()->one();
		$field = $form->getField($name);
		$element = $field->query('tag:input')->one();
		$element->type($string);
		$this->query('class', $class)->waitUntilVisible();

		$this->assertScreenshotExcept($element->parents('class:table-forms')->one(),
			[$element], $string
		);
	}

	public function testMultiselect_NotSuggestAlreadySelected() {
		$this->page->login()->open('zabbix.php?action=problem.view')->waitUntilReady();
		$this->page->updateViewport();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$field = $form->getField('Host groups');
		$field->select('Zabbix servers');
		$element = $field->query('tag:input')->one();
		$element->type('Zabbix server');
		$this->query('class:multiselect-matches')->waitUntilVisible();
		$this->assertScreenshotExcept($element->parents('class:table-forms')->one(),
			[$element]
		);
	}

	public function testMultiselect_SuggestInOverlay() {
		$widget = 'Plain text';

		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->query('button:Create dashboard')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$dialog->close();
		$dashboard = CDashboardElement::find()->one();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$widget_type = $form->getField('Type')->asDropdown()->getText();
		if($widget_type !== $widget){
			$form->getField('Type')->asDropdown()->select($widget);
			$form->waitUntilReloaded();
		}
		$element = $form->getField('Items')->query('tag:input')->one();
		$element->type('Zab');
		$this->query('class:multiselect-suggest')->waitUntilVisible();
		$this->assertScreenshotExcept(null, [
			$element,
			['query' => 'xpath://footer[text()]']
		]);
	}
}
