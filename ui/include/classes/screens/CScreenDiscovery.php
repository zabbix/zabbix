<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

		$drules = $this->getDRules();

		if (!$drules) {
			return $this->getOutput((new CTableInfo()), true, $this->data);
		}

		$dhosts = $this->getDHosts(array_keys($drules));
		$dservices = $this->getDServices(array_keys($drules));
		$dchecks = $this->getDChecks(array_keys($dservices));
		$macros = $this->getMacros();

		$prepared_services = $this->buildPreparedServices($dservices, $dchecks, $macros);

		$services = $this->buildServicesHeader($prepared_services);

		$table = (new CTableInfo())->setHeader($this->buildHeader($services));

		$drule_hosts = [];

		foreach ($dhosts as $dhost) {
			$drule_hosts[$dhost['druleid']][] = $dhost;
		}

		foreach ($drules as $druleid => $drule) {
			$discovery_info = $this->buildDiscoveryInfo(
				$drule_hosts[$druleid] ?? [],
				$prepared_services
			);

			if ($discovery_info) {
				$col = new CCol([
					bold((new CLinkAction($drule['name']))->setMenuPopup(CMenuPopupHelper::getDRule($druleid))),
					NBSP(),
					'('._n('%d device', '%d devices', count($discovery_info)).')'
				]);

				$col
					->addClass(ZBX_STYLE_WORDBREAK)
					->setColSpan(count($services) + 3);

				$table->addRow($col);
			}

			$discovery_info = $this->groupPrimaryAndSecondaryInterfaces($discovery_info);

			foreach ($discovery_info as $row_data) {
				$table->addRow($this->buildTableRow($row_data, $services));
			}
		}

		return $this->getOutput($table, true, $this->data);
	}

	private function getDRules(): array {
		$drules = API::DRule()->get([
			'output' => ['druleid', 'name'],
			'druleids' => array_key_exists('filter_druleids', $this->data) && $this->data['filter_druleids']
				? $this->data['filter_druleids']
				: null,
			'filter' => ['status' => DRULE_STATUS_ACTIVE],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			'preservekeys' => true
		]);

		order_result($drules, 'name');

		return $drules;
	}

	private function getDHosts(array $druleids): array {
		return API::DHost()->get([
			'output' => ['dhostid', 'druleid', 'status', 'lastup', 'lastdown'],
			'selectDServices' => ['dserviceid'],
			'druleids' => $druleids,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			'preservekeys' => true
		]);
	}

	private function getDServices(array $druleids): array {
		return API::DService()->get([
			'output' => ['dserviceid', 'port', 'status', 'lastup', 'lastdown', 'dcheckid', 'ip', 'dns'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'druleids' => $druleids,
			'limitSelects' => 1,
			'preservekeys' => true
		]);
	}

	private function getDChecks(array $dserviceids): array {
		return API::DCheck()->get([
			'output' => ['type', 'key_', 'allow_redirect'],
			'dserviceids' => $dserviceids,
			'preservekeys' => true
		]);
	}

	private function getMacros(): array {
		$macros = API::UserMacro()->get([
			'output' => ['macro', 'value', 'type'],
			'globalmacro' => true
		]);

		return zbx_toHash($macros, 'macro');
	}

	private function buildPreparedServices(array $dservices, array $dchecks, array $macros): array {
		$index = [];

		foreach ($dservices as $dserviceid => $dservice) {
			$dcheck = $dchecks[$dservice['dcheckid']];

			$host = reset($dservice['hosts']) ?: [];

			$index[$dserviceid] = [
				'dservice' => $dservice,
				'dcheck' => $dcheck,
				'host' => [
					'hostid' => $host['hostid'] ?? '',
					'name' => $host['name'] ?? '',
					'status' => $host['status'] ?? HOST_STATUS_NOT_MONITORED
				],
				'service_name' => $this->buildServiceName(
					$dservice,
					$dcheck,
					$macros
				),
				'ip_bin' => inet_pton($dservice['ip'])
			];
		}

		return $index;
	}

	private function buildServiceName(array $dservice, array $dcheck, array $macros): string {
		$key_ = $dcheck['key_'];

		if ($key_ !== '') {
			if (array_key_exists($key_, $macros)) {
				$key_ = CMacrosResolverGeneral::getMacroValue($macros[$key_]);
			}

			$key_ = NAME_DELIMITER.$key_;
		}

		$allow_redirect = $dcheck['allow_redirect'] == 1 ? ' "'._('allow redirect').'"' : '';

		return discovery_check_type2str($dcheck['type'])
			.discovery_port2str($dcheck['type'], $dservice['port'])
			.$key_
			.$allow_redirect;
	}

	private function buildServicesHeader(array $prepared_services): array {
		$services = [];

		foreach ($prepared_services as $service) {
			$services[] = $service['service_name'];
		}

		$services = array_unique($services);
		sort($services);

		return $services;
	}

	private function buildHeader(array $services): array {
		$header = [
			make_sorting_header(_('Discovered device'), 'ip', $this->data['sort'], $this->data['sortorder'],
				'zabbix.php?action=discovery.view'
			),
			_('Monitored host'),
			_('Uptime').'/'._('Downtime')
		];

		foreach ($services as $service_name) {
			$header[] = (new CVertical($service_name))->setTitle($service_name);
		}

		return $header;
	}

	private function buildDiscoveryInfo(array $dhosts, array $prepared_services): array {
		$discovery_info = [];

		foreach ($dhosts as $dhost) {
			$host_class = $dhost['status'] == DHOST_STATUS_DISABLED ? 'disabled' : 'enabled';
			$host_time = $dhost['status'] == DHOST_STATUS_DISABLED ? $dhost['lastdown'] : $dhost['lastup'];

			$dhost_services = [];

			foreach ($dhost['dservices'] as $ref) {
				$dhost_services[] = $prepared_services[$ref['dserviceid']];
			}

			usort($dhost_services, static function($a, $b) {
				return $a['ip_bin'] <=> $b['ip_bin'];
			});

			$primary_ip = $dhost_services[0]['dservice']['ip'] ?? null;

			foreach ($dhost_services as $service) {
				if ($service['host']['hostid'] !== '') {
					$primary_ip = $service['dservice']['ip'];
					break;
				}
			}

			foreach ($dhost_services as $service) {
				$dservice = $service['dservice'];

				$ip = $dservice['ip'];

				if (!isset($discovery_info[$ip])) {
					$discovery_info[$ip] = [
						'ip' => $ip,
						'dns' => $dservice['dns'],
						'type' => $ip === $primary_ip ? 'primary' : 'secondary',
						'class' => $host_class,
						'host' => $service['host']['name'],
						'status' => $service['host']['status'],
						'hostid' => $service['host']['hostid'],
						'dhostid' => $dhost['dhostid'],
						'time' => $host_time,
						'services' => []
					];
				}

				$is_disabled = $dservice['status'] == DSVC_STATUS_DISABLED;

				$discovery_info[$ip]['services'][$service['service_name']] = [
					'class' => $is_disabled ? ZBX_STYLE_INACTIVE_BG : null,
					'time' => $is_disabled ? $dservice['lastdown'] : $dservice['lastup']
				];
			}
		}

		return $discovery_info;
	}

	private function buildTableRow(array $h_data, array $services): array {
		$dns = $h_data['dns'] === '' ? '' : ' ('.$h_data['dns'].')';
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
			$h_data['type'] === 'primary'
				? (new CSpan($h_data['ip'].$dns))->addClass($h_data['class'])
				: (new CSpan([NBSP(), NBSP(), NBSP(), NBSP(), $h_data['ip'].$dns]))
				->addClass($h_data['class']),

			new CSpan($host),
			(new CSpan($h_data['time'] == 0 || $h_data['type'] === 'secondary'
				? ''
				: convertUnits(['value' => time() - $h_data['time'], 'units' => 'uptime'])
			))
				->addClass($h_data['class'])
		];

		foreach ($services as $service_name) {
			$row[] = array_key_exists($service_name, $h_data['services'])
				? (new CCol(zbx_date2age($h_data['services'][$service_name]['time'])))
					->addClass($h_data['services'][$service_name]['class'])
				: '';
		}

		return $row;
	}

	private function groupPrimaryAndSecondaryInterfaces(array $discovery_info): array {
		$groups = [];
		$primaries = [];

		foreach ($discovery_info as $ip => $host) {
			if ($host['type'] === 'primary') {
				$primaries[$ip] = $host;
			}
			else {
				$groups[$host['dhostid']][$ip] = $host;
			}
		}

		uasort($primaries, function($a, $b) {
			$cmp = inet_pton($a['ip']) <=> inet_pton($b['ip']);

			return $this->data['sortorder'] === ZBX_SORT_DOWN ? -$cmp : $cmp;
		});

		$sorted = [];

		foreach ($primaries as $primary_ip => $primary) {
			$sorted[$primary_ip] = $primary;

			if (!empty($groups[$primary['dhostid']])) {
				foreach ($groups[$primary['dhostid']] as $ip => $secondary) {
					unset($secondary['dhostid']);

					$sorted[$ip] = $secondary;
				}
			}
		}

		return $sorted;
	}
}
