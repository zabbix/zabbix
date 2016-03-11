<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$discoveryWidget = (new CWidget())->setTitle(_('Status of discovery'));

// create header form
$controls = (new CList())
	->addItem([_('Discovery rule'), SPACE, $data['pageFilter']->getDiscoveryCB()])
	->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]));

$discoveryWidget->setControls(
	(new CForm('get'))
		->setName('slideHeaderForm')
		->addVar('action', 'discovery.view')
		->addVar('fullscreen', $data['fullscreen'])
		->addItem($controls)
);

$header = [
	make_sorting_header(_('Discovered device'), 'ip', $data['sort'], $data['sortorder']),
	_('Monitored host'),
	_('Uptime').'/'._('Downtime')
];

foreach ($data['services'] as $name => $foo) {
	$header[] = (new CColHeader($name))->addClass('vertical_rotation');
}

// create table
$discoveryTable = (new CTableInfo())
	->makeVerticalRotation()
	->setHeader($header);

foreach ($data['drules'] as $drule) {
	$discovery_info = [];

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

		$dservices = $data['dhosts'][$dhost['dhostid']]['dservices'];
		foreach ($dservices as $dservice) {
			$dservice = $data['dservices'][$dservice['dserviceid']];

			$hostName = '';

			$host = reset($data['dservices'][$dservice['dserviceid']]['hosts']);
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
				$discovery_info[$dservice['ip']] = [
					'ip' => $dservice['ip'],
					'dns' => $dservice['dns'],
					'type' => $htype,
					'class' => $hclass,
					'host' => $hostName,
					'time' => $htime,
					'druleid' => $dhost['druleid']
				];
			}

			if ($dservice['status'] == DSVC_STATUS_DISABLED) {
				$class = ZBX_STYLE_INACTIVE_BG;
				$time = 'lastdown';
			}
			else {
				$class = ZBX_STYLE_ACTIVE_BG;
				$time = 'lastup';
			}

			$key_ = $dservice['key_'];
			if (!zbx_empty($key_)) {
				if (isset($data['macros'][$key_])) {
					$key_ = $data['macros'][$key_]['value'];
				}
				$key_ = NAME_DELIMITER.$key_;
			}

			$serviceName = discovery_check_type2str($dservice['type']).discovery_port2str($dservice['type'], $dservice['port']).$key_;

			$discovery_info[$dservice['ip']]['services'][$serviceName] = [
				'class' => $class,
				'time' => $dservice[$time]
			];
		}
	}

	if (empty($data['druleid']) && !empty($discovery_info)) {
		$col = new CCol([bold($drule['name']), SPACE.'('._n('%d device', '%d devices', count($discovery_info)).')']);
		$col->setColSpan(count($data['services']) + 3);

		$discoveryTable->addRow($col);
	}
	order_result($discovery_info, $data['sort'], $data['sortorder']);

	foreach ($discovery_info as $ip => $h_data) {
		$dns = $h_data['dns'] == '' ? '' : ' ('.$h_data['dns'].')';
		$row = [
			$h_data['type'] == 'primary'
				? (new CSpan($ip.$dns))->addClass($h_data['class'])
				: new CSpan(SPACE.SPACE.$ip.$dns),
			new CSpan(empty($h_data['host']) ? '' : $h_data['host']),
			(new CSpan((($h_data['time'] == 0 || $h_data['type'] === 'slave')
				? ''
				: convert_units(['value' => time() - $h_data['time'], 'units' => 'uptime'])))
			)
				->addClass($h_data['class'])
		];

		foreach ($data['services'] as $name => $foo) {
			$class = null;
			$time = SPACE;
			$hint = (new CDiv(SPACE))->addClass($class);

			$hintTable = null;
			if (isset($h_data['services'][$name])) {
				$class = $h_data['services'][$name]['class'];
				$time = $h_data['services'][$name]['time'];

				$hintTable = (new CTableInfo())->setAttribute('style', 'width: auto;');

				if ($class == ZBX_STYLE_ACTIVE_BG) {
					$hintTable->setHeader(_('Uptime'));
				}
				else {
					$hintTable->setHeader(_('Downtime'));
				}
				$timeColumn = (new CCol(zbx_date2age($h_data['services'][$name]['time'])))->addClass($class);
				$hintTable->addRow($timeColumn);
			}
			$column = (new CCol($hint))->addClass($class);
			if (!is_null($hintTable)) {
				$column->setHint($hintTable);
			}
			$row[] = $column;
		}
		$discoveryTable->addRow($row);
	}
}

$discoveryWidget->addItem($discoveryTable)->show();
