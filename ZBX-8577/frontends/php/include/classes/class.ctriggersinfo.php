<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

		$ok = $uncl = $info = $warn = $avg = $high = $dis = 0;

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
					switch ($row['priority']) {
						case TRIGGER_SEVERITY_NOT_CLASSIFIED:
							$uncl += $row['cnt'];
							break;
						case TRIGGER_SEVERITY_INFORMATION:
							$info += $row['cnt'];
							break;
						case TRIGGER_SEVERITY_WARNING:
							$warn += $row['cnt'];
							break;
						case TRIGGER_SEVERITY_AVERAGE:
							$avg += $row['cnt'];
							break;
						case TRIGGER_SEVERITY_HIGH:
							$high += $row['cnt'];
							break;
						case TRIGGER_SEVERITY_DISASTER:
							$dis += $row['cnt'];
							break;
					}
					break;
				case TRIGGER_VALUE_FALSE:
					$ok += $row['cnt'];
					break;
			}
		}

		if ($this->show_header) {
			$header_str = _('Triggers info').SPACE;

			if (!is_null($this->nodeid)) {
				$node = get_node_by_nodeid($this->nodeid);
				if ($node > 0) {
					$header_str .= '('.$node['name'].')'.SPACE;
				}
			}

			if ($this->groupid != 0) {
				$group = get_hostgroup_by_groupid($this->groupid);
				$header_str .= _('Group').SPACE.'&quot;'.$group['name'].'&quot;';
			}
			else {
				$header_str .= _('All groups');
			}

			$header = new CCol($header_str, 'header');
			if ($this->style == STYLE_HORIZONTAL) {
				$header->setColspan(8);
			}
			$this->addRow($header);
		}

		$trok = getSeverityCell(null, $ok.SPACE._('Ok'), true);
		$uncl = getSeverityCell(TRIGGER_SEVERITY_NOT_CLASSIFIED, $uncl.SPACE.getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED), !$uncl);
		$info = getSeverityCell(TRIGGER_SEVERITY_INFORMATION, $info.SPACE.getSeverityCaption(TRIGGER_SEVERITY_INFORMATION), !$info);
		$warn = getSeverityCell(TRIGGER_SEVERITY_WARNING, $warn.SPACE.getSeverityCaption(TRIGGER_SEVERITY_WARNING), !$warn);
		$avg = getSeverityCell(TRIGGER_SEVERITY_AVERAGE, $avg.SPACE.getSeverityCaption(TRIGGER_SEVERITY_AVERAGE), !$avg);
		$high = getSeverityCell(TRIGGER_SEVERITY_HIGH, $high.SPACE.getSeverityCaption(TRIGGER_SEVERITY_HIGH), !$high);
		$dis = getSeverityCell(TRIGGER_SEVERITY_DISASTER, $dis.SPACE.getSeverityCaption(TRIGGER_SEVERITY_DISASTER), !$dis);

		if (STYLE_HORIZONTAL == $this->style) {
			$this->addRow(array($trok, $uncl, $info, $warn, $avg, $high, $dis));
		}
		else {
			$this->addRow($trok);
			$this->addRow($uncl);
			$this->addRow($info);
			$this->addRow($warn);
			$this->addRow($avg);
			$this->addRow($high);
			$this->addRow($dis);
		}

		return parent::bodyToString();
	}
}
?>
