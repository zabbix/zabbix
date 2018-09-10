<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CTriggersInfo extends CTable {

	private $style = STYLE_HORIZONTAL;
	private $groupid;

	public function __construct($groupid) {
		parent::__construct();

		$this->addClass(ZBX_STYLE_LIST_TABLE);
		$this->groupid = $groupid;
	}

	public function setOrientation($value) {
		$this->style = $value;
		return $this;
	}

	protected function bodyToString() {
		$this->cleanItems();

		$config = select_config();

		// array of triggers (not classified, information, warning, average, high, disaster) in problem state
		$triggersProblemState = [];

		// number of triggers in OK state
		$triggersOkState = 0;

		$options = [
			'output' => ['triggerid', 'priority', 'value'],
			'monitored' => true,
			'skipDependent' => true
		];

		if ($this->groupid != 0) {
			$options['groupids'] = $this->groupid;
		}
		$triggers = API::Trigger()->get($options);

		foreach ($triggers as $trigger) {
			switch ($trigger['value']) {
				case TRIGGER_VALUE_TRUE:
					if (!array_key_exists($trigger['priority'], $triggersProblemState)) {
						$triggersProblemState[$trigger['priority']] = 0;
					}

					$triggersProblemState[$trigger['priority']]++;
					break;

				case TRIGGER_VALUE_FALSE:
					$triggersOkState++;
			}
		}

		$severityCells = [getSeverityCell(null, $config, $triggersOkState.SPACE._('Ok'), true)];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityCount = isset($triggersProblemState[$severity]) ? $triggersProblemState[$severity] : 0;

			$severityCells[] = getSeverityCell($severity,
				$config,
				$severityCount.SPACE.getSeverityName($severity, $config),
				!$severityCount
			);
		}

		if ($this->style == STYLE_HORIZONTAL) {
			$this->addRow($severityCells);
		}
		else {
			foreach ($severityCells as $severityCell) {
				$this->addRow($severityCell);
			}
		}

		return parent::bodyToString();
	}
}
