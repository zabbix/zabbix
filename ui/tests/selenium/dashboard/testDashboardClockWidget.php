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

/**
 * @backup widget
 * @backup profiles
 * @dataSource ClockWidgets
 */

class testDashboardClockWidget extends CWebTest {

	/**
	 * Select data from DB in order to check hash.
	 */
	private function checkDB($dashboardid) {
		$sql = 'SELECT * FROM widget, widget_field WHERE widget.dashboardid =\''.$dashboardid.'\'AND widget.widgetid = widget_field.widgetid';
		return $sql;
	}

	/**
	 * Open dashboard and add new widget or edit widget by its name.
	 *
	 * @param string $name		name of widget to be opened
	 */
	private function openWidgetConfiguration($name = null) {
		$dashboard = CDashboardElement::find()->one()->edit();
		// Open existed widget by widget name.
		if ($name) {
			$widget = $dashboard->getWidget($name);
			$this->assertEquals(true, $widget->isEditable());
			$form = $widget->edit();
		}
		// Add new widget.
		else {
			$overlay = $dashboard->addWidget();
			$form = $overlay->asForm();
		}
		return $form;
	}

	/**
	 * Save dashboard and check added/updated widget.
	 *
	 * @param string $name		name of widget to be checked
	 */
	private function saveWidget($name) {
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget($name);
		$widget->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$widget->getContent()->query('class:svg-clock')->waitUntilVisible();
		$dashboard->save();
		$message = CMessageElement::find()->waitUntilPresent()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Dashboard updated', $message->getTitle());
	}

	/**
	 * Check clock widget field values after creating or updating.
	 */
	private function checkClockWidgetForm($data) {
		$form = $this->query('id:widget_dialogue_form')->asForm()->one();

		// Check fields value.
		$form->checkValue($data_set);

	}

	public static function getCreateData() {
		return [
			[
				[
					'MandatoryFields' => [
						'Type' => 'Clock',
						'Name' => 'ManuallyCreatedClockWidget'
					]
				]
			]
		];
	}

	/**
	 * Check clock widget successful creation.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardClockWidget_Create($data) {
		$dashboardid = CDataHelper::get('ClockWidgets.dashboardids.DEV-2236');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$form = $this->openWidgetConfiguration();

		$this->fillForm($data, $form);
		$form->parents('class:overlay-dialogue-body')->one()->query('tag:output')->asMessage()->waitUntilNotVisible();
		$form->submit();
		$this->saveWidget(CTestArrayHelper::get($data, 'MandatoryFields.Name', 'Clock'));

		// Check values in created widget.
		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->openWidgetConfiguration(CTestArrayHelper::get($data, 'MandatoryFields.Name', 'Clock'));
			$this->checkClockWidgetForm($data);
		}
	}
	/**
	 * Check clock widgets successful ghost update.
	 */

	public function testDashboardClockWidget_GhostUpdate() {}
	/**
	 * Check clock widgets successful update.
	 */
	public function testDashboardClockWidget_Update() {}
	/**
	 * Check clock widgets successful copy. <----using mby already function from testDashboardCopyWidgets?
	 */
	public function testDashboardClockWidget_Copy() {}
	/**
	 * Check clock widgets deletion.
	 */
	public function testDashboardClockWidget_Delete() {}
	/**
	 * Check if it's possible to cancel creation of clock widget.
	 */
	public function testDashboardClockWidget_Cancel() {}
}
