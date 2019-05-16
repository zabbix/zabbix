<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/**
 * @backup dashboard
 */
class testPageDashboardWidgets extends CWebTest {

	/**
	 * Check "Problem Hosts" widget.
	 */
	public function testPageDashboardWidgets_checkProblemHostsWidget() {
		// Authorize user and open the page 'zabbix.php?action=dashboard.view&dashboardid=100'.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=100');

		// Find dashboard element.
		$dashboard = CDashboardElement::find()->one();

		// Get dashboard widget element by widget title ('Problem hosts').
		$widget = $dashboard->getWidget('Problem hosts');
		// Check refresh interval of widget.
		$this->assertEquals(60, $widget->getRefreshInterval());

		// Get widget content as table.
		$table = $widget->getContent()->asTable();
		// Check text of the table headers.
		$this->assertSame(['Host group', 'Without problems', 'With problems', 'Total'], $table->getHeadersText());

		// Expected table values.
		$expected = [
			'Host group for tag permissions'	=> 1,
			'Zabbix servers'					=> 17,
			'ZBX6648 All Triggers'				=> 1,
			'ZBX6648 Disabled Triggers'			=> 1,
			'ZBX6648 Enabled Triggers'			=> 1
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
	 * Check "Problems by severity" widget.
	 */
	public function testPageDashboardWidgets_checkProblemsBySeverityWidget() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');

		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Problems by severity');
		$table = $widget->getContent()->asTable();

		$this->assertSame(['Host group', 'Disaster', 'High', 'Average', 'Warning', 'Information', 'Not classified'],
				$table->getHeadersText()
		);

		// Expected table values.
		$expected = [
			'Host group for tag permissions' => [
				'Not classified' => 2
			],
			'Zabbix servers' => [
				'Average' => 1,
				'Warning' => 5
			]
		];

		$data = $table->index('Host group');

		foreach ($expected as $group => $fields) {
			$row = $data[$group];

			foreach ($fields as $column => $value) {
				$this->assertEquals($value, $row[$column]);
			}
		}
	}

	/**
	 * Check empty dashboard.
	 */
	public function testPageDashboardWidgets_checkEmptyDashboard() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');

		// Create a new dashboard.
		$this->query('button:Create dashboard')->one()->click();
		// Wait until overlay dialog is visible and ready.
		$dialog = $this->query('id:overlay_dialogue')->waitUntilVisible()->asOverlayDialog()->one()->waitUntilReady();

		// Check title of a dialog.
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		// Close the dialog.
		$dialog->close();

		$dashboard = CDashboardElement::find()->one();
		// Check if dashboard is empty.
		$this->assertTrue($dashboard->isEmpty());

		// Open a new widget form.
		$overlay = $dashboard->edit()->addWidget();
		// Wait until add widget dialog is shown and close it.
		$overlay->close();

		// Check if dashboard is still empty.
		$this->assertTrue($dashboard->isEmpty());
		// Cancel dashboard editing.
		$dashboard->cancelEditing();
	}

	/**
	 * Create dashboard with clock widget.
	 */
	public function testPageDashboardWidgets_checkDashboardCreate() {
		$this->page->login()->open('zabbix.php?action=dashboard.list');

		$this->query('button:Create dashboard')->one()->click();
		$dialog = $this->query('id:overlay_dialogue')->waitUntilVisible()->asOverlayDialog()->one()->waitUntilReady();

		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$configuration = $dialog->asForm();
		// Change dashboard owner.
		$owner = $configuration->getField('Owner');
		$owner->clear();
		$owner->select('test-user');
		// Change dashboard name.
		$configuration->getField('Name')->clear()->type('Dashboard create test');
		$configuration->submit();

		$dashboard = CDashboardElement::find()->one();
		// Check the name of dashboard.
		$this->query('id:page-title-general')->waitUntilTextPresent('Dashboard create test');
		$this->assertEquals('Dashboard create test', $dashboard->getTitle());

		// Add widget.
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		// Set type to "Clock".
		$form->getField('Type')->asDropdown()->select('Clock');
		// Wait until form is reloaded.
		$form->waitUntilReloaded();
		// Set name of widget.
		$form->getField('Name')->type('Clock test');
		$form->submit();

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
	public function testPageDashboardWidgets_checkProblemWidgetEdit() {
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
		// Submit the form.
		$form->submit();

		// Check if widget can be found by a new name.
		$dashboard->getWidget('New widget name');
		$table = $widget->getContent()->asTable();
		// Check if only selected host group is shown.
		$this->assertEquals(['Zabbix servers'], array_keys($table->index('Host group')));

		$dashboard->cancelEditing();
	}
}
