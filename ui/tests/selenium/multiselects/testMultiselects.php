<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup profiles
 *
 * @browsers chrome
 */
class testMultiselects extends CWebTest {

	public function testMultiselects_SuggestExisting() {
		$this->checkSuggest('zabbix.php?action=problem.view&filter_reset=1', 'zbx_filter',
				'Host groups', 'z', 'multiselect-suggest'
		);
	}

	public function testMultiselects_SuggestNoMatches() {
		$this->checkSuggest('zabbix.php?action=problem.view&filter_reset=1', 'zbx_filter',
				'Host groups', 'QQQ', 'multiselect-matches'
		);
	}

	public function testMultiselects_SuggestCreateNew() {
		$this->checkSuggest('zabbix.php?action=host.list', 'host-form', 'Host groups', 'QQQwww',
				'multiselect-suggest'
		);
	}

	public function checkSuggest($link, $query, $name, $string, $class) {
		$this->page->login()->open($link)->waitUntilReady();

		if ($query === 'host-form') {
			$this->query('button:Create host')->one()->waitUntilClickable()->click();
			COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
		}

		$field = $this->query('name:'.$query)->asForm()->one()->getField($name);
		$this->page->updateViewport();
		$element = $field->query('tag:input')->one();
		$element->type($string);
		$this->query('class', $class)->waitUntilVisible();

		// Cover proxy selection field, because it gets changed depending on proxy names' lengths in the dropdown.
		$covered_region = ($query === 'host-form')
			? [$element, ['x' => 111, 'y' => 317, 'width' => 452, 'height' => 22]]
			: [$element];

		$this->assertScreenshotExcept($element->parents('class', (($query === 'host-form') ? 'form-grid' : 'table-forms'))
				->one(), $covered_region, $string
		);
	}

	public function testMultiselects_NotSuggestAlreadySelected() {
		$this->page->login()->open('zabbix.php?action=problem.view&filter_reset=1')->waitUntilReady();
		$this->page->updateViewport();
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$field = $form->getField('Host groups');
		$field->select('Zabbix servers');
		$element = $field->query('tag:input')->one();
		$element->type('Zabbix server');
		$this->query('class:multiselect-matches')->waitUntilVisible();
		$this->assertScreenshotExcept($element->parents('class:table-forms')->one(), [$element]);
	}

	public function testMultiselects_SuggestInOverlay() {
		$widget = 'Item navigator';

		$this->page->login()->open('zabbix.php?action=dashboard.list');
		$this->query('button:Create dashboard')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one()->waitUntilReady();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$dialog->close();
		$dashboard = CDashboardElement::find()->one();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$widget_type = $form->getField('Type')->asDropdown()->getText();
		if ($widget_type !== $widget) {
			$form->getField('Type')->asDropdown()->select($widget);
			$form->waitUntilReloaded();
			/* After selecting "type" focus remains in the suggested list,
			 * need to click on another field to change the position of the mouse.
			 */
			$form->getField('Type')->click();
		}
		$element = $form->getField('Item patterns')->query('tag:input')->one();
		$element->type('Zab');
		$this->query('class:multiselect-suggest')->waitUntilVisible();
		$this->assertScreenshotExcept(null, [
				$element,
				['query' => 'xpath://footer[text()]']
		]);
	}
}

