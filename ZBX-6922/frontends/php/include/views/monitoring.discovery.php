<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


global $USER_DETAILS;

$discoveryWidget = new CWidget('hat_discovery');

// create header form
$discoveryHeaderForm = new CForm('get');
$discoveryHeaderForm->setName('slideHeaderForm');
$discoveryHeaderForm->addVar('fullscreen', $this->data['fullscreen']);
$discoveryWidget->addPageHeader(_('STATUS OF DISCOVERY'), get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen'])));

$discoveryRulesComboBox = $this->data['pageFilter']->getDiscoveryCB();

$discoveryHeaderForm->addItem(array(_('Discovery rule').SPACE, $discoveryRulesComboBox));
$discoveryWidget->addHeader(_('Discovery rules'), $discoveryHeaderForm);

// craete table
$discoveryTable = new CTableInfo(_('No discoveries defined.'));
$discoveryTable->makeVerticalRotation();

$header = array(
	is_show_all_nodes() ? new CCol(_('Node'), 'left') : null,
	make_sorting_header(_('Discovered device'), 'ip'),
	new CCol(_('Monitored host'), 'left'),
	new CCol(array(_('Uptime').'/', _('Downtime')), 'left')
);

foreach ($this->data['services'] as $name => $foo) {
	$header[] = new CCol($name, 'vertical_rotation');
}
$discoveryTable->setHeader($header, 'vertical_header');

foreach ($this->data['drules'] as $drule) {
	$discovery_info = array();

	$dhosts = $drule['dhosts'];
	foreach ($dhosts as $dhost) {
		if ($dhost['status'] == DHOST_STATUS_DISABLED) {
			$hclass = 'disabled';
			$htime = $dhost['lastdown'];
		}
		else {
			$hclass = 'enabled';
			$htime = $dhost['lastup'];
		}

		// $primary_ip stores the primary host ip of the dhost
		if (isset($primary_ip)) {
			unset($primary_ip);
		}

		$dservices = $this->data['dhosts'][$dhost['dhostid']]['dservices'];
		foreach ($dservices as $dservice) {
			$dservice = $this->data['dservices'][$dservice['dserviceid']];

			$hostName = '';

			$host = reset($this->data['dservices'][$dservice['dserviceid']]['hosts']);
			if (!is_null($host)) {
				$hostName = $host['name'];
			}

			if (isset($primary_ip)) {
				if ($primary_ip === $dservice['ip']) {
					$htype = 'primary';
				}
				else {
					$htype = 'slave';
				}
			}
			else {
				$primary_ip = $dservice['ip'];
				$htype = 'primary';
			}

			if (!isset($discovery_info[$dservice['ip']])) {
				$discovery_info[$dservice['ip']] = array(
					'ip' => $dservice['ip'],
					'dns' => $dservice['dns'],
					'type' => $htype,
					'class' => $hclass,
					'host' => $hostName,
					'time' => $htime,
					'druleid' => $dhost['druleid']
				);
			}

			$class = 'active';
			$time = 'lastup';
			if ($dservice['status'] == DSVC_STATUS_DISABLED) {
				$class = 'inactive';
				$time = 'lastdown';
			}

			$key_ = $dservice['key_'];
			if (!zbx_empty($key_)) {
				if (isset($this->data['macros'][$key_])) {
					$key_ = $this->data['macros'][$key_]['value'];
				}
				$key_ = ': '.$key_;
			}

			$serviceName = discovery_check_type2str($dservice['type']).discovery_port2str($dservice['type'], $dservice['port']).$key_;

			$discovery_info[$dservice['ip']]['services'][$serviceName] = array(
				'class' => $class,
				'time' => $dservice[$time]
			);
		}
	}

	if (empty($this->data['druleid']) && !empty($discovery_info)) {
		$col = new CCol(array(bold($drule['name']), SPACE.'('._n('%d device', '%d devices', count($discovery_info)).')'));
		$col->setColSpan(count($this->data['services']) + 3);

		$discoveryTable->addRow(array(get_node_name_by_elid($drule['druleid']), $col));
	}
	order_result($discovery_info, $this->data['sort'], $this->data['sortorder']);

	foreach ($discovery_info as $ip => $h_data) {
		$dns = $h_data['dns'] == '' ? '' : ' ('.$h_data['dns'].')';
		$row = array(
			get_node_name_by_elid($h_data['druleid']),
			$h_data['type'] == 'primary' ? new CSpan($ip.$dns, $h_data['class']) : new CSpan(SPACE.SPACE.$ip.$dns),
			new CSpan(empty($h_data['host']) ? '-' : $h_data['host']),
			new CSpan((($h_data['time'] == 0 || $h_data['type'] === 'slave')
				? ''
				: convert_units(time() - $h_data['time'], 'uptime')), $h_data['class'])
		);

		foreach ($this->data['services'] as $name => $foo) {
			$class = null;
			$time = SPACE;
			$hint = new CDiv(SPACE, $class);

			$hintTable = null;
			if (isset($h_data['services'][$name])) {
				$class = $h_data['services'][$name]['class'];
				$time = $h_data['services'][$name]['time'];

				$hintTable = new CTableInfo();
				$hintTable->setAttribute('style', 'width: auto;');

				if ($class == 'active') {
					$hintTable->setHeader(_('Uptime'));
				}
				elseif ($class == 'inactive') {
					$hintTable->setHeader(_('Downtime'));
				}
				$timeColumn = new CCol(zbx_date2age($h_data['services'][$name]['time']), $class);
				$hintTable->addRow($timeColumn);
			}
			$column = new CCol($hint, $class);
			if (!is_null($hintTable)) {
				$column->setHint($hintTable);
			}
			$row[] = $column;
		}
		$discoveryTable->addRow($row);
	}
}

$discoveryWidget->addItem($discoveryTable);
return $discoveryWidget;
