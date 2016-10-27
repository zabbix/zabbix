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


/**
 * A class to display discovery table as a screen element.
 */
class CScreenDiscovery extends CScreenBase {

	/**
	 * Data
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param array		$options['data']
	 */
	public function __construct(array $options = []) {
		parent::__construct($options);

		$this->data = $options['data'];
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'discovery';

		$sort_field = $this->data['sort'];
		$sort_order = $this->data['sortorder'];
		$druleid = $this->data['druleid'];

		$options = [
			'output' => ['druleid', 'name'],
			'selectDHosts' => ['dhostid', 'status', 'lastup', 'lastdown'],
			'filter' => ['status' => DRULE_STATUS_ACTIVE],
			'preservekeys' => true
		];

		if ($druleid != 0) {
			$options['druleids'] = [$druleid];
		}

		$drules = API::DRule()->get($options);

		order_result($drules, 'name');

		$dservices = API::DService()->get([
			'output' => ['dserviceid', 'port', 'status', 'lastup', 'lastdown', 'dcheckid', 'ip', 'dns'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'druleids' => array_keys($drules),
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limitSelects' => 1,
			'preservekeys' => true
		]);

		// user macros
		$macros = API::UserMacro()->get([
			'output' => ['macro', 'value'],
			'globalmacro' => true
		]);
		$macros = zbx_toHash($macros, 'macro');

		$dchecks = API::DCheck()->get([
			'output' => ['type', 'key_'],
			'dserviceids' => array_keys($dservices),
			'preservekeys' => true
		]);

		// services
		$services = [];
		foreach ($dservices as $dservice) {
			$key_ = $dchecks[$dservice['dcheckid']]['key_'];
			if ($key_ !== '') {
				if (array_key_exists($key_, $macros)) {
					$key_ = $macros[$key_]['value'];
				}
				$key_ = ': '.$key_;
			}
			$service_name = discovery_check_type2str($dchecks[$dservice['dcheckid']]['type']).
				discovery_port2str($dchecks[$dservice['dcheckid']]['type'], $dservice['port']).$key_;
			$services[$service_name] = 1;
		}
		ksort($services);

		$dhosts = API::DHost()->get([
			'output' => ['dhostid'],
			'selectDServices' => ['dserviceid'],
			'druleids' => array_keys($drules),
			'preservekeys' => true
		]);

		$header = [
			make_sorting_header(_('Discovered device'), 'ip', $sort_field, $sort_order,
				'zabbix.php?action=discovery.view'
			),
			_('Monitored host'),
			_('Uptime').'/'._('Downtime')
		];

		foreach ($services as $name => $foo) {
			$header[] = (new CColHeader($name))->addClass('vertical_rotation');
		}

		// create table
		$table = (new CTableInfo())
			->makeVerticalRotation()
			->setHeader($header);

		foreach ($drules as $drule) {
			$discovery_info = [];

			foreach ($drule['dhosts'] as $dhost) {
				if ($dhost['status'] == DHOST_STATUS_DISABLED) {
					$hclass = 'disabled';
					$htime = $dhost['lastdown'];
				}
				else {
					$hclass = 'enabled';
					$htime = $dhost['lastup'];
				}

				// $primary_ip stores the primary host ip of the dhost
				$primary_ip = '';

				foreach ($dhosts[$dhost['dhostid']]['dservices'] as $dservice) {
					$dservice = $dservices[$dservice['dserviceid']];

					$hostName = '';

					$host = reset($dservices[$dservice['dserviceid']]['hosts']);
					if (!is_null($host)) {
						$hostName = $host['name'];
					}

					if ($primary_ip !== '') {
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

					if (!array_key_exists($dservice['ip'], $discovery_info)) {
						$discovery_info[$dservice['ip']] = [
							'ip' => $dservice['ip'],
							'dns' => $dservice['dns'],
							'type' => $htype,
							'class' => $hclass,
							'host' => $hostName,
							'time' => $htime,
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

					$key_ = $dchecks[$dservice['dcheckid']]['key_'];
					if ($key_ !== '') {
						if (array_key_exists($key_, $macros)) {
							$key_ = $macros[$key_]['value'];
						}
						$key_ = NAME_DELIMITER.$key_;
					}

					$service_name = discovery_check_type2str($dchecks[$dservice['dcheckid']]['type']).
						discovery_port2str($dchecks[$dservice['dcheckid']]['type'], $dservice['port']).$key_;

					$discovery_info[$dservice['ip']]['services'][$service_name] = [
						'class' => $class,
						'time' => $dservice[$time]
					];
				}
			}

			if ($druleid == 0 && $discovery_info) {
				$col = new CCol(
					[bold($drule['name']), SPACE.'('._n('%d device', '%d devices', count($discovery_info)).')']
				);
				$col->setColSpan(count($services) + 3);

				$table->addRow($col);
			}
			order_result($discovery_info, $sort_field, $sort_order);

			foreach ($discovery_info as $ip => $h_data) {
				$dns = $h_data['dns'] == '' ? '' : ' ('.$h_data['dns'].')';
				$row = [
					$h_data['type'] == 'primary'
						? (new CSpan($ip.$dns))->addClass($h_data['class'])
						: new CSpan(SPACE.SPACE.$ip.$dns),
					new CSpan(array_key_exists('host', $h_data) ? $h_data['host'] : ''),
					(new CSpan((($h_data['time'] == 0 || $h_data['type'] === 'slave')
						? ''
						: convert_units(['value' => time() - $h_data['time'], 'units' => 'uptime'])))
					)
						->addClass($h_data['class'])
				];

				foreach ($services as $name => $foo) {
					$class = null;
					$time = SPACE;
					$hint = (new CDiv(SPACE))->addClass($class);

					$hint_table = null;
					if (array_key_exists($name, $h_data['services'])) {
						$class = $h_data['services'][$name]['class'];
						$time = $h_data['services'][$name]['time'];

						$hint_table = (new CTableInfo())->setAttribute('style', 'width: auto;');

						if ($class == ZBX_STYLE_ACTIVE_BG) {
							$hint_table->setHeader(_('Uptime'));
						}
						else {
							$hint_table->setHeader(_('Downtime'));
						}

						$hint_table->addRow(
							(new CCol(zbx_date2age($h_data['services'][$name]['time'])))->addClass($class)
						);
					}
					$column = (new CCol($hint))->addClass($class);
					if (!is_null($hint_table)) {
						$column->setHint($hint_table);
					}
					$row[] = $column;
				}
				$table->addRow($row);
			}
		}

		return $this->getOutput($table, true, $this->data);
	}
}
