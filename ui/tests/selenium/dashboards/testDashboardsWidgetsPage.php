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


require_once __DIR__ . '/../../include/CWebTest.php';

/**
 * @backup dashboard, profiles
 *
 * @dataSource Actions, AllItemValueTypes, CopyWidgetsDashboards, ItemValueWidget, LoginUsers, Proxies, UserPermissions
 */
class testDashboardsWidgetsPage extends CWebTest {

	/**
	 * Default selected widget type.
	 * The widget type should not be changed in frontend and in DB.
	 */
	public function testDashboardsWidgetsPage_checkUnchangedWidgetType() {
		// Opening widget configuration form for new widget first time.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$dashboard = CDashboardElement::find()->one()->edit();
		// Check that widget type isn't changed in frontend and in DB.
		$this->checkLastSelectedWidgetType();

		// Opening edit widget form.
		$form_system_info = $dashboard->getWidget('System information')->edit();
		$this->assertEquals('System information', $form_system_info->getField('Type')->getValue());
		$form_system_info->submit();
		// Check that widget type isn't changed in frontend and in DB.
		$this->checkLastSelectedWidgetType();

		// Making changes in widget form that are not "Widget type".
		$form_problems = $dashboard->getWidget('Current problems')->edit();
		$this->assertEquals('Problems', $form_problems->getField('Type')->getValue());
		$data =[
			'Name' => 'check widget type',
			'Refresh interval' => 'No refresh',
			'Show' => 'Recent problems',
			'Show tags' => 'None'
		];
		$form_problems->fill($data);
		$form_problems->submit();
		$this->checkLastSelectedWidgetType();

		// Add widget with current default type "Action log".
		$dashboard->addWidget();
		$this->query('xpath://div[@role="dialog"]//button[text()="Add"]')->waitUntilPresent()->one()->click();
		// Check if widget was added.
		$dashboard->getWidget('Action log');
		$this->checkLastSelectedWidgetType();

		$dashboard->cancelEditing();
	}

	/**
	 * Check last selected widget type in frontend and in DB.
	 * By default is 'Action log' type and without record in DB.
	 *
	 * @param string $type			widget type name
	 * @param string $db_type		widget type name stored in DB
	 */
	private function checkLastSelectedWidgetType($type = 'Action log', $db_type = null) {
		$dashboard = CDashboardElement::find()->one();
		COverlayDialogElement::ensureNotPresent();
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		$this->assertEquals($type, $form->getField('Type')->getValue());

		if ($db_type) {
			$this->assertEquals($db_type, CDBHelper::getValue("SELECT value_str FROM profiles".
					" WHERE userid=1 AND idx='web.dashboard.last_widget_type'"));
		}
		else {
			$this->assertEquals(0, CDBHelper::getCount("SELECT * FROM profiles".
					" WHERE userid=1 AND idx='web.dashboard.last_widget_type'"));
		}

		$overlay->close();
	}

	/**
	 * Widget type should be inherited from the one that was selected last time.
	 */
	public function testDashboardsWidgetsPage_checkWidgetTypeRemembering() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$dashboard = CDashboardElement::find()->one()->edit();
		// Opening widget configuration form for new Clock widget.
		$overlay = $dashboard->addWidget();
		$default_form = $overlay->asForm();
		$default_form->fill(['Type' => 'Clock']);
		$default_form->waitUntilReloaded();
		$overlay->close();
		// Check that widget type is not remembered without submitting the form.
		$this->checkLastSelectedWidgetType();

		// Save edit widget form without changing widget type.
		$sys_info_form = $dashboard->getWidget('System information')->edit();
		$this->assertEquals('System information', $sys_info_form->getField('Type')->getValue());
		$sys_info_form->submit();
		$this->page->waitUntilReady();
		// Check that widget type is still unchanged.
		$this->checkLastSelectedWidgetType();

		// Opening edit widget form and change widget type.
		$change_form = $dashboard->getWidget('System information')->edit();
		$change_form->fill(['Type' => 'Trigger overview']);
		$change_form->waitUntilReloaded();
		$change_form->submit();
		// Check that widget type inherited from previous widget.
		$this->checkLastSelectedWidgetType('Trigger overview', 'trigover');

		$dashboard->cancelEditing();
	}

	/**
	 * Check "Problem Hosts" widget.
	 */
	public function testDashboardsWidgetsPage_checkProblemHostsWidget() {
		// Authorize user and open the page with the desired widget.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1000');

		// Find dashboard element.
		$dashboard = CDashboardElement::find()->one();

		// Get dashboard widget element by widget title ('Problem hosts').
		$widget = $dashboard->getWidget('Problem hosts');
		// Check refresh interval of widget.
		$this->assertEquals('1 minute', $widget->getRefreshInterval());

		// Get widget content as table.
		$table = $widget->getContent()->asTable();
		// Check text of the table headers.
		$this->assertSame(['Host group', 'Without problems', 'With problems', 'Total'], $table->getHeadersText());

		// Expected table values.
		$expected = [
			'Zabbix servers'					=> 18,
			'Inheritance test'					=> 1,
			'Host group for suppression'		=> 1
		];

		/*
		 * Index rows by column "Host group":
		 * Get table data as array where values from column 'Host group' are used as array keys.
		 */
		$data = $table->index('Host group');

		foreach ($expected as $group => $problems) {
			// Get table row by host name.
			$row = $data[$group];

			// Check the value in table.
			$this->assertEquals($problems, $row['Without problems']);
			$this->assertEquals($problems, $row['Total']);
		}
	}

	/**
	 * Create dashboard with clock widget.
	 */
	public function testDashboardsWidgetsPage_checkDashboardCreate() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');

		$this->query('button:Create dashboard')->one()->click();
		$this->page->waitUntilReady();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$configuration = $dialog->asForm();
		// Change dashboard owner.
		$owner = $configuration->getField('Owner');
		$owner->clear();
		$owner->fill('test-user');
		// Change dashboard name.
		$configuration->getField('Name')->clear()->type('Dashboard create test');
		$configuration->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		$dashboard = CDashboardElement::find()->one();
		// Check the name of dashboard.
		$this->query('id:page-title-general')->waitUntilTextPresent('Dashboard create test');
		$this->assertEquals('Dashboard create test', $dashboard->getTitle());

		// Add widget.
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		// Set type to "Clock".
		$form->getField('Type')->asDropdown()->select('Clock');
		// Wait until overlay is reloaded.
		$overlay->waitUntilReady();
		// Set name of widget.
		$form->getField('Name')->type('Clock test');
		$form->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		// Check if widget was added.
		$widget = $dashboard->getWidget('Clock test');
		$widget->getContent()->query('class:clock')->waitUntilVisible();

		// Save dashboard.
		$dashboard->save();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();
		// Check if message is positive.
		$this->assertTrue($message->isGood());
		// Check message title.
		$this->assertEquals('Dashboard created', $message->getTitle());
	}

	/**
	 * Edit widget.
	 */
	public function testDashboardsWidgetsPage_checkProblemWidgetEdit() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');

		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget('Problems by severity');

		// Check if widget is editable.
		$this->assertEquals(true, $widget->isEditable());

		// Open widget edit form and get the form element.
		$form = $widget->edit();

		// Get "Host groups" multiselect.
		$groups = $form->getField('Host groups');

		// Select a single group.
		$groups->select('Zabbix servers');

		// Change the name of widget.
		$form->getField('Name')->clear()->type('New widget name');
		// Change show option.
		$form->getField('Show')->select('Host groups');
		// Submit the form.
		$form->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		// Check if widget can be found by a new name.
		$table = $dashboard->getWidget('New widget name')->getContent()->asTable();

		// Check if only selected host group is shown.
		$this->assertEquals(['Zabbix servers'], array_keys($table->index('Host group')));

		$dashboard->cancelEditing();
	}
}
