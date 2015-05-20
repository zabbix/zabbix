<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	public $style;
	private $groupid;
	private $hostid;

	public function __construct($groupid = null, $hostid = null, $style = STYLE_HORIZONTAL) {
		$this->style = null;

		parent::__construct(null, 'list-table');
		$this->setOrientation($style);
		$this->groupid = is_null($groupid) ? 0 : $groupid;
		$this->hostid = is_null($hostid) ? 0 : $hostid;
	}

	public function setOrientation($value) {
		$this->style = $value;
	}

	public function bodyToString() {
		$this->cleanItems();

		$config = select_config();

		// array of triggers (not classified, information, warning, average, high, disaster) in problem state
		$triggersProblemState = array();

		// number of triggers in OK state
		$triggersOkState = 0;

		$options = array(
			'monitored' => true,
			'skipDependent' => true,
			'output' => array('triggerid')
		);

		if ($this->hostid > 0) {
			$options['hostids'] = $this->hostid;
		}
		elseif ($this->groupid > 0) {
			$options['groupids'] = $this->groupid;
		}
		$triggers = API::Trigger()->get($options);
		$triggers = zbx_objectValues($triggers, 'triggerid');

		$db_priority = DBselect(
			'SELECT t.priority,t.value,count(DISTINCT t.triggerid) AS cnt'.
			' FROM triggers t'.
			' WHERE '.dbConditionInt('t.triggerid', $triggers).
			' GROUP BY t.priority,t.value'
		);
		while ($row = DBfetch($db_priority)) {
			switch ($row['value']) {
				case TRIGGER_VALUE_TRUE:
					if (!isset($triggersProblemState[$row['priority']])) {
						$triggersProblemState[$row['priority']] = 0;
					}

					$triggersProblemState[$row['priority']] += $row['cnt'];
					break;

				case TRIGGER_VALUE_FALSE:
					$triggersOkState += $row['cnt'];
			}
		}

		$severityCells = array(getSeverityCell(null, $config, $triggersOkState.SPACE._('Ok'), true));

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityCount = isset($triggersProblemState[$severity]) ? $triggersProblemState[$severity] : 0;

			$severityCells[] = getSeverityCell($severity,
				$config,
				$severityCount.SPACE.getSeverityName($severity, $config),
				!$severityCount
			);
		}

		if (STYLE_HORIZONTAL == $this->style) {
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
