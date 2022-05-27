<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

		if ($this->hasInput('clone_hostid') && $this->hasInput('full_clone')) {
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
				'proxy_hostid' => $this->getInput('proxy_hostid', 0),
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

			$full_clone = $this->hasInput('full_clone');
			$src_hostid = $this->getInput('clone_hostid', '');

			if ($src_hostid) {
				$host = $this->extendHostCloneEncryption($host, $src_hostid);
			}

			$result = API::Host()->create($host);

			if ($result === false
					|| !$this->createValueMaps($result['hostids'][0])
					|| ($full_clone && !$this->copyFromCloneSourceHost($src_hostid, $result['hostids'][0]))) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
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
	 * @param string $src_hostid  Source hostid.
	 * @param string $hostid      Target hostid.
	 *
	 * @return bool
	 */
	private function copyFromCloneSourceHost(string $src_hostid, string $hostid): bool {
		// First copy web scenarios with web items, so that later regular items can use web item as their master item.
		if (!copyHttpTests($src_hostid, $hostid)) {
			return false;
		}

		if (!copyItems($src_hostid, $hostid, true)) {
			return false;
		}

		// Copy triggers.
		if (!copyTriggersToHosts([$hostid], $src_hostid)) {
			return false;
		}

		// Copy discovery rules.
		$db_discovery_rules = API::DiscoveryRule()->get([
			'output' => ['itemid'],
			'hostids' => $src_hostid,
			'inherited' => false
		]);

		if ($db_discovery_rules) {
			$copy_discovery_rules = API::DiscoveryRule()->copy([
				'discoveryids' => array_column($db_discovery_rules, 'itemid'),
				'hostids' => [$hostid]
			]);

			if (!$copy_discovery_rules) {
				return false;
			}
		}

		// Copy graphs.
		$db_graphs = API::Graph()->get([
			'output' => ['graphid'],
			'selectHosts' => ['hostid'],
			'selectItems' => ['type'],
			'hostids' => $src_hostid,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'inherited' => false
		]);

		foreach ($db_graphs as $db_graph) {
			if (count($db_graph['hosts']) > 1) {
				continue;
			}

			if (httpItemExists($db_graph['items'])) {
				continue;
			}

			if (!copyGraphToHost($db_graph['graphid'], $hostid)) {
				return false;
			}
		}

		return true;
	}
}
