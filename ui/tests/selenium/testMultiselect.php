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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @browsers chrome
 */
class testMultiselect extends CWebTest {

	public function testMultiselect_SuggestExisting() {
		$this->checkSuggest('zabbix.php?action=problem.view&filter_reset=1', 'zbx_filter',
			'Host groups', 'z', 'multiselect-suggest'
		);
	}

	public function testMultiselect_SuggestNoMatches() {
		$this->checkSuggest('zabbix.php?action=problem.view&filter_reset=1','zbx_filter',
			'Host groups', 'QQQ', 'multiselect-matches'
		);
	}

	public function testMultiselect_SuggestCreateNew() {
		$this->checkSuggest('zabbix.php?action=host.edit','host-form', 'Groups', 'QQQwww', 'multiselect-suggest');
	}

	public function checkSuggest($link, $query, $name, $string, $class) {
		$this->page->login()->open($link)->waitUntilReady();
		$this->page->updateViewport();
		$field = $this->query('name:'.$query)->asForm()->one()->getField($name);
		$element = $field->query('tag:input')->one();
		$element->type($string);
		$this->query('class', $class)->waitUntilVisible();

		$this->assertScreenshotExcept($element->parents('class', (($query === 'host-form') ? 'form-grid' : 'table-forms'))
				->one(), [$element], $string);
	}

	public function testMultiselect_NotSuggestAlreadySelected() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1')->waitUntilReady();
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
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one()->waitUntilReady();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$dialog->close();
		$dashboard = CDashboardElement::find()->one();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$widget_type = $form->getField('Type')->asZDropdown()->getText();
		if ($widget_type !== $widget) {
			$form->getField('Type')->asZDropdown()->select($widget);
			$form->waitUntilReloaded();
			/* After selecting "type" focus remains in the suggested list,
			 * need to click on another field to change the position of the mouse.
			 */
			$form->getField('Type')->click();
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
