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

		$drules = API::DRule()->get([
			'output' => ['druleid', 'name'],
			'selectDHosts' => ['dhostid', 'status', 'lastup', 'lastdown'],
			'druleids' => (array_key_exists('filter_druleids', $this->data) && $this->data['filter_druleids'])
				? $this->data['filter_druleids']
				: null,
			'filter' => ['status' => DRULE_STATUS_ACTIVE],
			'preservekeys' => true
		]);

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
			'output' => ['macro', 'value', 'type'],
			'globalmacro' => true
		]);
		$macros = zbx_toHash($macros, 'macro');

		$dchecks = API::DCheck()->get([
			'output' => ['type', 'key_', 'allow_redirect'],
			'dserviceids' => array_keys($dservices),
			'preservekeys' => true
		]);

		// services
		$services = [];
		foreach ($dservices as $dservice) {
			$key_ = $dchecks[$dservice['dcheckid']]['key_'];
			if ($key_ !== '') {
				if (array_key_exists($key_, $macros)) {
					$key_ = CMacrosResolverGeneral::getMacroValue($macros[$key_]);
				}
				$key_ = ': '.$key_;
			}

			$allow_redirect = ($dchecks[$dservice['dcheckid']]['allow_redirect'] == 1)
				? ' "'._('allow redirect').'"'
				: '';

			$service_name = discovery_check_type2str($dchecks[$dservice['dcheckid']]['type']).
				discovery_port2str($dchecks[$dservice['dcheckid']]['type'], $dservice['port']).$key_.$allow_redirect;
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
			$header[] = (new CSpan($name))
				->addClass(ZBX_STYLE_TEXT_VERTICAL)
				->setTitle($name);
		}

		// create table
		$table = (new CTableInfo())->setHeader($header);

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
					$host_name = '';
					$hostid = '';
					$host_status = HOST_STATUS_NOT_MONITORED;
					$host = reset($dservices[$dservice['dserviceid']]['hosts']);

					if ($host) {
						$host_name = $host['name'];
						$hostid = $host['hostid'];
						$host_status = $host['status'];
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
							'host' => $host_name,
							'status' => $host_status,
							'hostid' => $hostid,
							'time' => $htime
						];
					}

					if ($dservice['status'] == DSVC_STATUS_DISABLED) {
						$class = ZBX_STYLE_INACTIVE_BG;
						$time = 'lastdown';
					}
					else {
						$class = null;
						$time = 'lastup';
					}

					$key_ = $dchecks[$dservice['dcheckid']]['key_'];
					if ($key_ !== '') {
						if (array_key_exists($key_, $macros)) {
							$key_ = CMacrosResolverGeneral::getMacroValue($macros[$key_]);
						}
						$key_ = NAME_DELIMITER.$key_;
					}

					$allow_redirect = ($dchecks[$dservice['dcheckid']]['allow_redirect'] == 1)
						? ' "'._('allow redirect').'"'
						: '';

					$service_name = discovery_check_type2str($dchecks[$dservice['dcheckid']]['type']).
						discovery_port2str($dchecks[$dservice['dcheckid']]['type'], $dservice['port']).$key_.
						$allow_redirect;

					$discovery_info[$dservice['ip']]['services'][$service_name] = [
						'class' => $class,
						'time' => $dservice[$time]
					];
				}
			}

			if ($discovery_info) {
				$col = new CCol([
					bold(
						(new CLinkAction($drule['name']))->setMenuPopup(CMenuPopupHelper::getDRule($drule['druleid']))
					),
					NBSP(),
					'('._n('%d device', '%d devices', count($discovery_info)).')'
				]);

				$col
					->addClass(ZBX_STYLE_WORDBREAK)
					->setColSpan(count($services) + 3);

				$table->addRow($col);
			}
			order_result($discovery_info, $sort_field, $sort_order);

			foreach ($discovery_info as $ip => $h_data) {
				$dns = ($h_data['dns'] === '') ? '' : ' ('.$h_data['dns'].')';
				$host = '';

				if (array_key_exists('host', $h_data)) {
					$host = $h_data['host'];

					if ($h_data['hostid'] !== '') {
						$host = (new CLinkAction($host))
							->addClass($h_data['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
							->setMenuPopup(CMenuPopupHelper::getHost($h_data['hostid']));
					}
				}

				$row = [
					($h_data['type'] === 'primary')
						? (new CSpan($ip.$dns))->addClass($h_data['class'])
						: new CSpan([NBSP(), NBSP(), $ip.$dns]),
					new CSpan($host),
					(new CSpan((($h_data['time'] == 0 || $h_data['type'] === 'slave')
						? ''
						: convertUnits(['value' => time() - $h_data['time'], 'units' => 'uptime'])))
					)
						->addClass($h_data['class'])
				];

				foreach ($services as $name => $foo) {
					$row[] = array_key_exists($name, $h_data['services'])
						? (new CCol(zbx_date2age($h_data['services'][$name]['time'])))
							->addClass($h_data['services'][$name]['class'])
						: '';
				}
				$table->addRow($row);
			}
		}

		return $this->getOutput($table, true, $this->data);
	}
}
