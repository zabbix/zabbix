<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerDiscoveryEdit extends CController {

	/**
	 * @var mixed
	 */
	private $drule = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'druleid' =>	'db drules.druleid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY)) {
			return false;
		}

		if ($this->hasInput('druleid')) {
			$drules = API::DRule()->get([
				'output' => ['name', 'proxyid', 'iprange', 'delay', 'status', 'concurrency_max'],
				'druleids' => $this->getInput('druleid'),
				'selectDChecks' => [
					'type', 'key_', 'snmp_community', 'ports', 'snmpv3_securityname', 'snmpv3_securitylevel',
					'snmpv3_authpassphrase', 'snmpv3_privpassphrase', 'uniq', 'snmpv3_authprotocol',
					'snmpv3_privprotocol', 'snmpv3_contextname', 'host_source', 'name_source', 'allow_redirect'
				],
				'editable' => true
			]);

			if (!$drules) {
				return false;
			}

			$uniqueness_criteria = -1;
			$host_source = DB::getDefault('dchecks', 'host_source');
			$name_source = DB::getDefault('dchecks', 'name_source');

			$drule = $drules[0];

			if ($drule['dchecks']) {
				[['host_source' => $host_source, 'name_source' => $name_source]] = $drule['dchecks'];

				foreach ($drule['dchecks'] as $dcheck) {
					if ($dcheck['uniq']) {
						$uniqueness_criteria = $dcheck['dcheckid'];
					}

					if ($dcheck['host_source'] == ZBX_DISCOVERY_VALUE) {
						$host_source = '_'.$dcheck['dcheckid'];
					}

					if ($dcheck['name_source'] == ZBX_DISCOVERY_VALUE) {
						$name_source = '_'.$dcheck['dcheckid'];
					}
				}
			}

			$this->drule = $drule + compact('uniqueness_criteria', 'host_source', 'name_source');
			$this->drule['dchecks'] = array_map([$this, 'normalizeDiscoveryCheckFields'], $this->drule['dchecks']);
		}

		return true;
	}

	protected function doAction(): void {
		$this->drule += [
			'druleid' => null,
			'name' => DB::getDefault('drules', 'name'),
			'dchecks' => [],
			'iprange' => '192.168.0.1-254',
			'delay' => DB::getDefault('drules', 'delay'),
			'status' => DB::getDefault('drules', 'status'),
			'concurrency_max' => DB::getDefault('drules', 'concurrency_max'),
			'proxyid' => 0,
			'uniqueness_criteria' => -1,
			'host_source' => DB::getDefault('dchecks', 'host_source'),
			'name_source' => DB::getDefault('dchecks', 'name_source')
		];

		CArrayHelper::sort($this->drule['dchecks'], ['name']);
		$this->drule['dchecks'] = array_values($this->drule['dchecks']);

		$concurrency_max_type = ($this->drule['concurrency_max'] == ZBX_DISCOVERY_CHECKS_UNLIMITED
			|| $this->drule['concurrency_max'] == ZBX_DISCOVERY_CHECKS_ONE)
			? $this->drule['concurrency_max']
			: ZBX_DISCOVERY_CHECKS_CUSTOM;

		if ($concurrency_max_type != ZBX_DISCOVERY_CHECKS_CUSTOM) {
			$this->drule['concurrency_max'] = ZBX_DISCOVERY_CHECKS_UNLIMITED;
		}

		$data = [
			'drule' => $this->drule,
			'concurrency_max_type' => $concurrency_max_type,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$data['proxies'] = API::Proxy()->get([
			'output' => ['proxyid', 'name']
		]);
		CArrayHelper::sort($data['proxies'], ['name']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of discovery rules'));
		$this->setResponse($response);
	}

	/**
	 * @param array $db_dcheck
	 *
	 * @return array
	 */
	private function normalizeDiscoveryCheckFields(array $db_dcheck): array {
		$dcheck = [
			'type' => $db_dcheck['type'],
			'dcheckid' => $db_dcheck['dcheckid'],
			'ports' => $db_dcheck['ports'],
			'uniq' => array_key_exists('uniq', $db_dcheck) ? $db_dcheck['uniq'] : null,
			'host_source' => $db_dcheck['host_source'],
			'name_source' => $db_dcheck['name_source']
		];

		$dcheck['name'] = discovery_check2str(
			$db_dcheck['type'],
			array_key_exists('key_', $db_dcheck) ? $db_dcheck['key_'] : '',
			array_key_exists('ports', $db_dcheck) ? $db_dcheck['ports'] : '',
			array_key_exists('allow_redirect', $db_dcheck) ? $db_dcheck['allow_redirect'] : 0
		);

		switch($db_dcheck['type']) {
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
				$dcheck['snmp_community'] = $db_dcheck['snmp_community'];
			// break; is not missing here
			case SVC_AGENT:
				$dcheck['key_'] = $db_dcheck['key_'];
				break;
			case SVC_SNMPv3:
				$dcheck += [
					'key_' => $db_dcheck['key_'],
					'snmpv3_contextname' => $db_dcheck['snmpv3_contextname'],
					'snmpv3_securityname' => $db_dcheck['snmpv3_securityname'],
					'snmpv3_securitylevel' => $db_dcheck['snmpv3_securitylevel']
				];

				if ($db_dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV
					|| $db_dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$dcheck += [
						'snmpv3_authprotocol' => $db_dcheck['snmpv3_authprotocol'],
						'snmpv3_authpassphrase' => $db_dcheck['snmpv3_authpassphrase']
					];
				}

				if ($db_dcheck['snmpv3_securitylevel'] == ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV) {
					$dcheck += [
						'snmpv3_privprotocol' => $db_dcheck['snmpv3_privprotocol'],
						'snmpv3_privpassphrase' => $db_dcheck['snmpv3_privpassphrase']
					];
				}
				break;
			case SVC_ICMPPING:
				if ($db_dcheck['allow_redirect'] == 1) {
					$dcheck += [
						'allow_redirect' => $db_dcheck['allow_redirect']
					];
				}
				break;
		}

		return $dcheck;
	}
}
