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
?>
<?php
require_once dirname(__FILE__).'/../triggers.inc.php';

class CTriggersInfo extends CTable {

	public $style;
	public $show_header;
	private $nodeid;
	private $groupid;
	private $hostid;

	public function __construct($groupid = null, $hostid = null, $style = STYLE_HORIZONTAL) {
		$this->style = null;

		parent::__construct(null, 'triggers_info');
		$this->setOrientation($style);
		$this->show_header = true;
		$this->groupid = is_null($groupid) ? 0 : $groupid;
		$this->hostid = is_null($hostid) ? 0 : $hostid;
	}

	public function setOrientation($value) {
		if ($value != STYLE_HORIZONTAL && $value != STYLE_VERTICAL) {
			return $this->error('Incorrect value for SetOrientation ['.$value.']');
		}
		$this->style = $value;
	}

	public function hideHeader() {
		$this->show_header = false;
	}

	public function bodyToString() {
		$this->cleanItems();

		$okCount = 0;
		$notClassifiedCount = 0;
		$informationCount = 0;
		$warningCount = 0;
		$averageCount = 0;
		$highCount = 0;
		$disasterCount = 0;

		$options = array(
			'output' => array('triggerid', 'priority', 'value'),
			'monitored' => true,
			'skipDependent' => true
		);

		if ($this->hostid > 0) {
			$options['hostids'] = $this->hostid;
		}
		elseif ($this->groupid > 0) {
			$options['groupids'] = $this->groupid;
		}
		$triggers = API::Trigger()->get($options);

		foreach ($triggers as $trigger) {
			if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
				switch ($trigger['priority']) {
					case TRIGGER_SEVERITY_NOT_CLASSIFIED:
						$notClassifiedCount++;
						break;
					case TRIGGER_SEVERITY_INFORMATION:
						$informationCount++;
						break;
					case TRIGGER_SEVERITY_WARNING:
						$warningCount++;
						break;
					case TRIGGER_SEVERITY_AVERAGE:
						$averageCount++;
						break;
					case TRIGGER_SEVERITY_HIGH:
						$highCount++;
						break;
					case TRIGGER_SEVERITY_DISASTER:
						$disasterCount++;
						break;
				}
			}
			elseif ($trigger['value'] == TRIGGER_VALUE_FALSE) {
				$okCount++;
			}
		}

		if ($this->show_header) {
			$headerString = _('Triggers info').SPACE;

			if (!is_null($this->nodeid)) {
				$node = get_node_by_nodeid($this->nodeid);
				if ($node > 0) {
					$headerString .= '('.$node['name'].')'.SPACE;
				}
			}

			if ($this->groupid != 0) {
				$group = get_hostgroup_by_groupid($this->groupid);
				$headerString .= _('Group').SPACE.'&quot;'.$group['name'].'&quot;';
			}
			else {
				$headerString .= _('All groups');
			}

			$header = new CCol($headerString, 'header');
			if ($this->style == STYLE_HORIZONTAL) {
				$header->setColspan(8);
			}
			$this->addRow($header);
		}

		$okCount = getSeverityCell(null, $okCount.SPACE._('Ok'), true);
		$notClassifiedCount = getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED,
			$notClassifiedCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED), !$notClassifiedCount
		);
		$informationCount = getSeverityCell(TRIGGER_SEVERITY_INFORMATION,
			$informationCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_INFORMATION), !$informationCount
		);
		$warningCount = getSeverityCell(TRIGGER_SEVERITY_WARNING,
			$warningCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_WARNING), !$warningCount);
		$averageCount = getSeverityCell(TRIGGER_SEVERITY_AVERAGE,
			$averageCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_AVERAGE), !$averageCount
		);
		$highCount = getSeverityCell(TRIGGER_SEVERITY_HIGH,
			$highCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_HIGH), !$highCount
		);
		$disasterCount = getSeverityCell(TRIGGER_SEVERITY_DISASTER,
			$disasterCount.SPACE.getSeverityCaption(TRIGGER_SEVERITY_DISASTER), !$disasterCount
		);

		if (STYLE_HORIZONTAL == $this->style) {
			$this->addRow(array($okCount, $notClassifiedCount, $informationCount, $warningCount, $averageCount, $highCount, $disasterCount));
		}
		else {
			$this->addRow($okCount);
			$this->addRow($notClassifiedCount);
			$this->addRow($informationCount);
			$this->addRow($warningCount);
			$this->addRow($averageCount);
			$this->addRow($highCount);
			$this->addRow($disasterCount);
		}

		return parent::bodyToString();
	}
}
?>
