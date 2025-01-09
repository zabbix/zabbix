<?php declare(strict_types = 0);
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
 * Controller for host creation.
 */
class CControllerHostCreate extends CControllerHostUpdateGeneral {

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationFields());

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add host'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)) {
			return false;
		}

		if ($this->hasInput('clone_hostid') && $this->hasInput('clone')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('clone_hostid')
			]);

			if (!$hosts) {
				return false;
			}
		}

		return true;
	}

	protected function doAction(): void {
		$result = false;

		try {
			DBstart();

			$host = [
				'status' => $this->getInput('status', HOST_STATUS_NOT_MONITORED),
				'monitored_by' => $this->getInput('monitored_by', ZBX_MONITORED_BY_SERVER),
				'groups' => $this->processHostGroups($this->getInput('groups', [])),
				'interfaces' => $this->processHostInterfaces($this->getInput('interfaces', [])),
				'tags' => $this->processTags($this->getInput('tags', [])),
				'templates' => $this->processTemplates([
					$this->getInput('add_templates', []), $this->getInput('templates', [])
				]),
				'macros' => $this->processUserMacros($this->getInput('macros', [])),
				'inventory' => ($this->getInput('inventory_mode', HOST_INVENTORY_DISABLED) != HOST_INVENTORY_DISABLED)
					? $this->getInput('host_inventory', [])
					: [],
				'tls_connect' => $this->getInput('tls_connect', HOST_ENCRYPTION_NONE),
				'tls_accept' => $this->getInput('tls_accept', HOST_ENCRYPTION_NONE)
			];

			if ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
				$host['proxyid'] = $this->getInput('proxyid', 0);
			}
			elseif ($host['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
				$host['proxy_groupid'] = $this->getInput('proxy_groupid', 0);
			}

			$this->getInputs($host, [
				'host', 'visiblename', 'description', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username',
				'ipmi_password', 'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk', 'inventory_mode'
			]);

			if ($host['tls_connect'] != HOST_ENCRYPTION_PSK && !($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
				unset($host['tls_psk'], $host['tls_psk_identity']);
			}

			if ($host['tls_connect'] != HOST_ENCRYPTION_CERTIFICATE
					&& !($host['tls_accept'] & HOST_ENCRYPTION_CERTIFICATE)) {
				unset($host['tls_issuer'], $host['tls_subject']);
			}

			$host = CArrayHelper::renameKeys($host, ['visiblename' => 'name']);

			$clone = $this->hasInput('clone');
			$src_hostid = $this->getInput('clone_hostid', 0);

			if ($clone && $src_hostid != 0) {
				$host = $this->extendHostCloneEncryption($host, $src_hostid);
			}

			$result = API::Host()->create($host);

			if ($result === false) {
				throw new Exception();
			}

			$host = ['hostid' => $result['hostids'][0]] + $host;

			if (!$this->createValueMaps($host['hostid'])
					|| ($clone && !$this->copyFromCloneSourceHost($src_hostid, $host))) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			$result = false;
			DBend(false);
		}

		$output = [];

		if ($result) {
			$success = ['title' => _('Host added')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add host'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	/**
	 * Copy write-only PSK fields values from source host to the new host. Used to clone host.
	 *
	 * @param array  $host                New host data to update.
	 * @param array  $host['tls_connect'] Type of connection to host.
	 * @param array  $host['tls_accept']  Type(s) of connection from host.
	 * @param string $src_hostid          ID of host to copy data from.
	 *
	 * @return array New host data with PSK, identity added (if applicable).
	 */
	private function extendHostCloneEncryption(array $host, string $src_hostid): array {
		if ($host['tls_connect'] == HOST_ENCRYPTION_PSK || ($host['tls_accept'] & HOST_ENCRYPTION_PSK)) {
			// Add values to PSK fields from cloned host.
			$clone_hosts = API::Host()->get([
				'output' => ['tls_psk_identity', 'tls_psk'],
				'hostids' => $src_hostid,
				'editable' => true
			]);

			if ($clone_hosts) {
				$host['tls_psk_identity'] = $this->getInput('tls_psk_identity', $clone_hosts[0]['tls_psk_identity']);
				$host['tls_psk'] = $this->getInput('tls_psk', $clone_hosts[0]['tls_psk']);
			}
		}

		return $host;
	}

	/**
	 * Create valuemaps.
	 *
	 * @param string $hostid      Target hostid.
	 *
	 * @return bool
	 */
	private function createValueMaps(string $hostid): bool {
		$valuemaps = $this->getInput('valuemaps', []);

		foreach ($valuemaps as $key => $valuemap) {
			unset($valuemap['valuemapid']);
			$valuemaps[$key] = $valuemap + ['hostid' => $hostid];
		}

		if ($valuemaps && !API::ValueMap()->create($valuemaps)) {
			return false;
		}

		return true;
	}

	/**
	 * Copy http tests, items, triggers, discovery rules and graphs from source host to target host.
	 *
	 * @param string $src_hostid
	 * @param array  $dst_host
	 *
	 * @return bool
	 */
	private function copyFromCloneSourceHost(string $src_hostid, array $dst_host): bool {
		// First copy web scenarios with web items, so that later regular items can use web item as their master item.
		return copyHttpTests($src_hostid, $dst_host['hostid'])
			&& CItemHelper::cloneHostItems($src_hostid, $dst_host)
			&& CTriggerHelper::cloneHostTriggers($src_hostid, $dst_host['hostid'])
			&& CGraphHelper::cloneHostGraphs($src_hostid, $dst_host['hostid'])
			&& CLldRuleHelper::cloneHostItems($src_hostid, $dst_host);
	}
}
