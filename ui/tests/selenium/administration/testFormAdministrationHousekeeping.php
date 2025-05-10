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


require_once __DIR__.'/../common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationHousekeeping extends testFormAdministrationGeneral {

	public $config_link = 'zabbix.php?action=housekeeping.edit';
	public $form_selector = 'id:housekeeping-form';

	public $default_values = [
		// Events and alerts.
		'id:hk_events_mode' => true,
		'id:hk_events_trigger' => '365d',
		'id:hk_events_internal' => '1d',
		'id:hk_events_discovery' => '1d',
		'id:hk_events_autoreg' => '1d',
		// Services.
		'id:hk_services_mode' => true,
		'id:hk_services' => '365d',
		// User sessions.
		'id:hk_sessions_mode' => true,
		'id:hk_sessions' => '365d',
		// History.
		'id:hk_history_mode' => true,
		'id:hk_history_global' => false,
		'id:hk_history' => '31d',
		// Trends.
		'id:hk_trends_mode' => true,
		'id:hk_trends_global' => false,
		'id:hk_trends' => '365d'
	];

	public $db_default_values = [
		'hk_events_mode' => 1,
		'hk_events_trigger' => '365d',
		'hk_events_internal' => '1d',
		'hk_events_discovery' => '1d',
		'hk_events_autoreg' => '1d',
		'hk_services_mode' => 1,
		'hk_services' => '365d',
		'hk_sessions_mode' => 1,
		'hk_sessions' => '365d',
		'hk_history_mode' => 1,
		'hk_history_global' => 0,
		'hk_history' => '31d',
		'hk_trends_mode' => 1,
		'hk_trends_global' => 0,
		'hk_trends' => '365d'
	];

	public $custom_values = [
		// Events and alerts.
		'id:hk_events_mode' => true,
		'id:hk_events_trigger' => '43d',
		'id:hk_events_internal' => '28d',
		'id:hk_events_discovery' => '33d',
		'id:hk_events_autoreg' => '115d',
		// Services.
		'id:hk_services_mode' => true,
		'id:hk_services' => '213d',
		// User sessions.
		'id:hk_sessions_mode' => true,
		'id:hk_sessions' => '151d',
		// History.
		'id:hk_history_mode' => false,
		'id:hk_history_global' => true,
		'id:hk_history' => '31d',   // This should be changed to another custom value after DEV-1673 is fixed.
		// Trends.
		'id:hk_trends_mode' => false,
		'id:hk_trends_global' => true,
		'id:hk_trends' => '365d'	// This should be changed to another custom value after DEV-1673 is fixed.
	];

	/**
	 * Test for checking form layout.
	 */
	public function testFormAdministrationHousekeeping_CheckLayout() {
		$this->page->login()->open($this->config_link);
		$this->page->assertTitle('Configuration of housekeeping');
		$this->page->assertHeader('Housekeeping');
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();
		$this->assertTrue($form->query('link:Audit settings')->exists());

		$headers = ['Events and alerts', 'Services', 'Audit log', 'User sessions', 'History', 'Trends'];
		foreach ($headers as $header) {
			$this->assertTrue($this->query('xpath://h4[text()="'.$header.'"]')->one()->isVisible());
		}

		$checkboxes = [
			'hk_events_mode',
			'hk_services_mode',
			'hk_sessions_mode',
			'hk_history_mode',
			'hk_history_global',
			'hk_trends_mode',
			'hk_trends_global'
		];

		$inputs = [
			'hk_events_trigger',
			'hk_events_internal',
			'hk_events_discovery',
			'hk_events_autoreg',
			'hk_services',
			'hk_sessions',
			'hk_history',
			'hk_trends'
		];

		foreach ([true, false] as $status) {

			foreach ($checkboxes as $checkbox) {
				$checkbox = $form->getField('id:'.$checkbox);
				$this->assertTrue($checkbox->isEnabled());
				$checkbox->fill($status);
			}

			foreach ($inputs as $input) {
				$input = $this->query('id', $input)->one();
				$this->assertEquals(32, $input->getAttribute('maxlength'));
				$this->assertTrue($input->isEnabled($status));
			}
		}

		foreach (['Update', 'Reset defaults'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled());
		}
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationHousekeeping_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationHousekeeping_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test data for Housekeeping form.
	 */
	public function getCheckFormData() {
		return [
			// Unchecked checkboxes.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => false,
						// Services.
						'id:hk_services_mode' => false,
						// User sessions.
						'id:hk_sessions_mode' => false,
						// History.
						'id:hk_history_mode' => false,
						'id:hk_history_global' => false,
						// Trends.
						'id:hk_trends_mode' => false,
						'id:hk_trends_global' => false
					],
					'db' => [
						'hk_events_mode' => 0,
						'hk_services_mode' => 0,
						'hk_sessions_mode' => 0,
						'hk_history_mode' => 0,
						'hk_history_global' => 0,
						'hk_trends_mode' => 0,
						'hk_trends_global' => 0
					]
				]
			],
			// Valid zero values without 's'.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0',
						'hk_trends_global' => 1,
						'hk_trends' => '0'
					]
				]
			],
			// Valid zero values with 's'.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0s',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0s'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0s',
						'hk_trends_global' => 1,
						'hk_trends' => '0s'
					]
				]
			],
			// Valid zero values in minutes.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0m',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0m'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0m',
						'hk_trends_global' => 1,
						'hk_trends' => '0m'
					]
				]
			],
			// Valid zero values in hours.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0h',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0h'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0h',
						'hk_trends_global' => 1,
						'hk_trends' => '0h'
					]
				]
			],
			// Valid zero values in days.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0d',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0d'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0d',
						'hk_trends_global' => 1,
						'hk_trends' => '0d'
					]
				]
			],
			// Valid zero values in weeks.
			[
				[
					'fields' => [
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0w',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0w'
					],
					'db' => [
						'hk_history_global' => 1,
						'hk_history' => '0w',
						'hk_trends_global' => 1,
						'hk_trends' => '0w'
					]
				]
			],
			// Minimal valid values in seconds without 's'.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '86400',
						'id:hk_events_internal' => '86400',
						'id:hk_events_discovery' => '86400',
						'id:hk_events_autoreg' => '86400',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '86400',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '86400',
						// History.
						'id:hk_history_mode' => true,
						'id:hk_history_global' => true,
						'id:hk_history' => '3600',
						// Trends.
						'id:hk_trends_mode' => true,
						'id:hk_trends_global' => true,
						'id:hk_trends' => '86400'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => 86400,
						'hk_events_internal' => 86400,
						'hk_events_discovery' => 86400,
						'hk_events_autoreg' => 86400,
						'hk_services_mode' => 1,
						'hk_services' => 86400,
						'hk_sessions_mode' => 1,
						'hk_sessions' => 86400,
						'hk_history_mode' => 1,
						'hk_history_global' => 1,
						'hk_history' => 3600,
						'hk_trends_mode' => 1,
						'hk_trends_global' => 1,
						'hk_trends' => 86400
					]
				]
			],
			// Minimal valid values in seconds with 's'.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '86400s',
						'id:hk_events_internal' => '86400s',
						'id:hk_events_discovery' => '86400s',
						'id:hk_events_autoreg' => '86400s',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '86400s',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '86400s',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '3600s',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '86400s'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '86400s',
						'hk_events_internal' => '86400s',
						'hk_events_discovery' => '86400s',
						'hk_events_autoreg' => '86400s',
						'hk_services_mode' => 1,
						'hk_services' => '86400s',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '86400s',
						'hk_history_global' => 1,
						'hk_history' => '3600s',
						'hk_trends_global' => 1,
						'hk_trends' => '86400s'
					]
				]
			],
			// Minimal valid values in minutes.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '1440m',
						'id:hk_events_internal' => '1440m',
						'id:hk_events_discovery' => '1440m',
						'id:hk_events_autoreg' => '1440m',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '1440m',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '1440m',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '60m',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '1440m'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '1440m',
						'hk_events_internal' => '1440m',
						'hk_events_discovery' => '1440m',
						'hk_events_autoreg' => '1440m',
						'hk_services_mode' => 1,
						'hk_services' => '1440m',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '1440m',
						'hk_history_global' => 1,
						'hk_history' => '60m',
						'hk_trends_global' => 1,
						'hk_trends' => '1440m'
					]
				]
			],
			// Minimal valid values in hours.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '24h',
						'id:hk_events_internal' => '24h',
						'id:hk_events_discovery' => '24h',
						'id:hk_events_autoreg' => '24h',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '24h',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '24h',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '1h',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '24h'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '24h',
						'hk_events_internal' => '24h',
						'hk_events_discovery' => '24h',
						'hk_events_autoreg' => '24h',
						'hk_services_mode' => 1,
						'hk_services' => '24h',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '24h',
						'hk_history_global' => 1,
						'hk_history' => '1h',
						'hk_trends_global' => 1,
						'hk_trends' => '24h'
					]
				]
			],
			// Minimal valid values in days.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '1d',
						'id:hk_events_internal' => '1d',
						'id:hk_events_discovery' => '1d',
						'id:hk_events_autoreg' => '1d',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '1d',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '1d',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '1d'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '1d',
						'hk_events_internal' => '1d',
						'hk_events_discovery' => '1d',
						'hk_events_autoreg' => '1d',
						'hk_services_mode' => 1,
						'hk_services' => '1d',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '1d',
						'hk_trends_global' => 1,
						'hk_trends' => '1d'
					]
				]
			],
			// Maximal valid values in seconds without 's'.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '788400000',
						'id:hk_events_internal' => '788400000',
						'id:hk_events_discovery' => '788400000',
						'id:hk_events_autoreg' => '788400000',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '788400000',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '788400000',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '788400000',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '788400000'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => 788400000,
						'hk_events_internal' => 788400000,
						'hk_events_discovery' => 788400000,
						'hk_events_autoreg' => 788400000,
						'hk_services_mode' => 1,
						'hk_services' => 788400000,
						'hk_sessions_mode' => 1,
						'hk_sessions' => 788400000,
						'hk_history_global' => 1,
						'hk_history' => 788400000,
						'hk_trends_global' => 1,
						'hk_trends' => 788400000
					]
				]
			],
			// Maximal valid values in seconds with 's'.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '788400000s',
						'id:hk_events_internal' => '788400000s',
						'id:hk_events_discovery' => '788400000s',
						'id:hk_events_autoreg' => '788400000s',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '788400000s',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '788400000s',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '788400000s',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '788400000s'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '788400000s',
						'hk_events_internal' => '788400000s',
						'hk_events_discovery' => '788400000s',
						'hk_events_autoreg' => '788400000s',
						'hk_services_mode' => 1,
						'hk_services' => '788400000s',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '788400000s',
						'hk_history_global' => 1,
						'hk_history' => '788400000s',
						'hk_trends_global' => 1,
						'hk_trends' => '788400000s'
					]
				]
			],
			// Maximal valid values in minutes.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '13140000m',
						'id:hk_events_internal' => '13140000m',
						'id:hk_events_discovery' => '13140000m',
						'id:hk_events_autoreg' => '13140000m',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '13140000m',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '13140000m',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '13140000m',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '13140000m'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '13140000m',
						'hk_events_internal' => '13140000m',
						'hk_events_discovery' => '13140000m',
						'hk_events_autoreg' => '13140000m',
						'hk_services_mode' => 1,
						'hk_services' => '13140000m',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '13140000m',
						'hk_history_global' => 1,
						'hk_history' => '13140000m',
						'hk_trends_global' => 1,
						'hk_trends' => '13140000m'
					]
				]
			],
			// Maximal valid values in hours.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '219000h',
						'id:hk_events_internal' => '219000h',
						'id:hk_events_discovery' => '219000h',
						'id:hk_events_autoreg' => '219000h',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '219000h',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '219000h',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '219000h',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '219000h'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '219000h',
						'hk_events_internal' => '219000h',
						'hk_events_discovery' => '219000h',
						'hk_events_autoreg' => '219000h',
						'hk_services_mode' => 1,
						'hk_services' => '219000h',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '219000h',
						'hk_history_global' => 1,
						'hk_history' => '219000h',
						'hk_trends_global' => 1,
						'hk_trends' => '219000h'
					]
				]
			],
			// Maximal valid values in days.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '9125d',
						'id:hk_events_internal' => '9125d',
						'id:hk_events_discovery' => '9125d',
						'id:hk_events_autoreg' => '9125d',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '9125d',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '9125d',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '9125d',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '9125d'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '9125d',
						'hk_events_internal' => '9125d',
						'hk_events_discovery' => '9125d',
						'hk_events_autoreg' => '9125d',
						'hk_services_mode' => 1,
						'hk_services' => '9125d',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '9125d',
						'hk_history_global' => 1,
						'hk_history' => '9125d',
						'hk_trends_global' => 1,
						'hk_trends' => '9125d'
					]
				]
			],
			// Maximal valid values in weeks.
			[
				[
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '1303w',
						'id:hk_events_internal' => '1303w',
						'id:hk_events_discovery' => '1303w',
						'id:hk_events_autoreg' => '1303w',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '1303w',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '1303w',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '1303w',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '1303w'
					],
					'db' => [
						'hk_events_mode' => 1,
						'hk_events_trigger' => '1303w',
						'hk_events_internal' => '1303w',
						'hk_events_discovery' => '1303w',
						'hk_events_autoreg' => '1303w',
						'hk_services_mode' => 1,
						'hk_services' => '1303w',
						'hk_sessions_mode' => 1,
						'hk_sessions' => '1303w',
						'hk_history_global' => 1,
						'hk_history' => '1303w',
						'hk_trends_global' => 1,
						'hk_trends' => '1303w'
					]
				]
			],
			// Invalid zero values without 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0',
						'id:hk_events_internal' => '0',
						'id:hk_events_discovery' => '0',
						'id:hk_events_autoreg' => '0',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values with 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0s',
						'id:hk_events_internal' => '0s',
						'id:hk_events_discovery' => '0s',
						'id:hk_events_autoreg' => '0s',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0s',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0s'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0m',
						'id:hk_events_internal' => '0m',
						'id:hk_events_discovery' => '0m',
						'id:hk_events_autoreg' => '0m',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0m',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0m'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0h',
						'id:hk_events_internal' => '0h',
						'id:hk_events_discovery' => '0h',
						'id:hk_events_autoreg' => '0h',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0h',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0h'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values in days.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0d',
						'id:hk_events_internal' => '0d',
						'id:hk_events_discovery' => '0d',
						'id:hk_events_autoreg' => '0d',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0d',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0d'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0w',
						'id:hk_events_internal' => '0w',
						'id:hk_events_discovery' => '0w',
						'id:hk_events_autoreg' => '0w',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0w',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0w'
					],
					'details' => [
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid zero values in Months (Months are not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0M',
						'id:hk_events_internal' => '0M',
						'id:hk_events_discovery' => '0M',
						'id:hk_events_autoreg' => '0M',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0M',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0M',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0M',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0M'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid zero values in years (years are not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '0y',
						'id:hk_events_internal' => '0y',
						'id:hk_events_discovery' => '0y',
						'id:hk_events_autoreg' => '0y',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '0y',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '0y',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '0y',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '0y'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Minimal invalid values in seconds with 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '86399s',
						'id:hk_events_internal' => '86399s',
						'id:hk_events_discovery' => '86399s',
						'id:hk_events_autoreg' => '86399s',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '86399s',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '86399s',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '3599s',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '86399s'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Minimal invalid values in seconds without 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '86399',
						'id:hk_events_internal' => '86399',
						'id:hk_events_discovery' => '86399',
						'id:hk_events_autoreg' => '86399',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '86399',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '86399',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '3599',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '86399'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Minimal invalid values in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '1439m',
						'id:hk_events_internal' => '1439m',
						'id:hk_events_discovery' => '1439m',
						'id:hk_events_autoreg' => '1439m',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '1439m',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '1439m',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '59m',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '1439m'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Minimal invalid values in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '23h',
						'id:hk_events_internal' => '23h',
						'id:hk_events_discovery' => '23h',
						'id:hk_events_autoreg' => '23h',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '23h',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '23h',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '23h'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '99999999999999999999999999999999',
						'id:hk_events_internal' => '99999999999999999999999999999999',
						'id:hk_events_discovery' => '99999999999999999999999999999999',
						'id:hk_events_autoreg' => '99999999999999999999999999999999',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '99999999999999999999999999999999',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '99999999999999999999999999999999',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '99999999999999999999999999999999',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '99999999999999999999999999999999'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in seconds with 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '788400001s',
						'id:hk_events_internal' => '788400001s',
						'id:hk_events_discovery' => '788400001s',
						'id:hk_events_autoreg' => '788400001s',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '788400001s',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '788400001s',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '788400001s',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '788400001s'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in seconds without 's'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '788400001',
						'id:hk_events_internal' => '788400001',
						'id:hk_events_discovery' => '788400001',
						'id:hk_events_autoreg' => '788400001',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '788400001',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '788400001',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '788400001',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '788400001'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '13140001m',
						'id:hk_events_internal' => '13140001m',
						'id:hk_events_discovery' => '13140001m',
						'id:hk_events_autoreg' => '13140001m',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '13140001m',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '13140001m',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '13140001m',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '13140001m'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '219001h',
						'id:hk_events_internal' => '219001h',
						'id:hk_events_discovery' => '219001h',
						'id:hk_events_autoreg' => '219001h',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '219001h',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '219001h',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '219001h',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '219001h'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in days.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '9126d',
						'id:hk_events_internal' => '9126d',
						'id:hk_events_discovery' => '9126d',
						'id:hk_events_autoreg' => '9126d',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '9126d',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '9126d',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '9126d',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '9126d'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Maximal invalid values in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '1304w',
						'id:hk_events_internal' => '1304w',
						'id:hk_events_discovery' => '1304w',
						'id:hk_events_autoreg' => '1304w',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '1304w',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '1304w',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '1304w',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '1304w'
					],
					'details' => [
						'Incorrect value for field "hk_trends": value must be one of 0, 86400-788400000.',
						'Incorrect value for field "hk_history": value must be one of 0, 3600-788400000.',
						'Incorrect value for field "hk_sessions": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_services": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_autoreg": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_discovery": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_internal": value must be one of 86400-788400000.',
						'Incorrect value for field "hk_events_trigger": value must be one of 86400-788400000.'
					]
				]
			],
			// Invalid values in Months (Months are not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '301M',
						'id:hk_events_internal' => '301M',
						'id:hk_events_discovery' => '301M',
						'id:hk_events_autoreg' => '301M',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '301M',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '301M',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '301M',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '301M'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid values in years (years are not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '26y',
						'id:hk_events_internal' => '26y',
						'id:hk_events_discovery' => '26y',
						'id:hk_events_autoreg' => '26y',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '26y',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '26y',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '26y',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '26y'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid string values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => 'text',
						'id:hk_events_internal' => 'text',
						'id:hk_events_discovery' => 'text',
						'id:hk_events_autoreg' => 'text',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => 'text',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => 'text',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => 'text',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => 'text'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid special symbol values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '!@#$%^&*()_+',
						'id:hk_events_internal' => '!@#$%^&*()_+',
						'id:hk_events_discovery' => '!@#$%^&*()_+',
						'id:hk_events_autoreg' => '!@#$%^&*()_+',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '!@#$%^&*()_+',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '!@#$%^&*()_+',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '!@#$%^&*()_+',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '!@#$%^&*()_+'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid empty values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '',
						'id:hk_events_internal' => '',
						'id:hk_events_discovery' => '',
						'id:hk_events_autoreg' => '',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => ''
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			],
			// Invalid negative values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Events and alerts.
						'id:hk_events_mode' => true,
						'id:hk_events_trigger' => '-1',
						'id:hk_events_internal' => '-1',
						'id:hk_events_discovery' => '-1',
						'id:hk_events_autoreg' => '-1',
						// Services.
						'id:hk_services_mode' => true,
						'id:hk_services' => '-1',
						// User sessions.
						'id:hk_sessions_mode' => true,
						'id:hk_sessions' => '-1',
						// History.
						'id:hk_history_global' => true,
						'id:hk_history' => '-1',
						// Trends.
						'id:hk_trends_global' => true,
						'id:hk_trends' => '-1'
					],
					'details' => [
						'Incorrect value for field "hk_trends": a time unit is expected.',
						'Incorrect value for field "hk_history": a time unit is expected.',
						'Incorrect value for field "hk_sessions": a time unit is expected.',
						'Incorrect value for field "hk_services": a time unit is expected.',
						'Incorrect value for field "hk_events_autoreg": a time unit is expected.',
						'Incorrect value for field "hk_events_discovery": a time unit is expected.',
						'Incorrect value for field "hk_events_internal": a time unit is expected.',
						'Incorrect value for field "hk_events_trigger": a time unit is expected.'
					]
				]
			]
		];
	}

	/**
	 * Backup in needed because of DEV-1673, and can be removed after bug is fixed.
	 * @backup config
	 *
	 * @dataProvider getCheckFormData
	 */
	public function testFormAdministrationHousekeeping_CheckForm($data) {
		$this->executeCheckForm($data);
	}
}
