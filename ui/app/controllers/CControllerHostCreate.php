<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
		return parent::checkInputFields(self::getValidationFields());
	}

	protected function checkPermissions(): bool {
		$ret = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);

		if ($ret && $this->hasInput('clone_hostid')) {
			$hosts = API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('clone_hostid')
			]);

			if (!$hosts) {
				access_deny(ACCESS_DENY_OBJECT);
			}
		}

		return $ret;
	}

	protected function doAction(): void {
		$output = [];
		$host = array_filter([
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
		]);

		$this->getInputs($host, [
			'host', 'visiblename', 'description', 'status', 'proxy_hostid', 'ipmi_authtype', 'ipmi_privilege',
			'ipmi_username', 'ipmi_password', 'tls_subject', 'tls_issuer', 'tls_psk_identity', 'tls_psk',
			'inventory_mode'
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

		$hostids = API::Host()->create($host);

		if ($hostids !== false && $this->createValueMaps($hostids['hostids'][0], $this->getInput('valuemaps', []),
					$this->hasInput('full_clone') || $this->hasInput('clone')
				) && (!$full_clone || $this->copyFromCloneSourceHost($src_hostid, $hostids['hostids'][0]))) {
			$messages = get_and_clear_messages();
			$details = [];

			foreach ($messages as $message) {
				$details[] = $message['message'];
			}

			ob_start();
			uncheckTableRows('hosts');

			$output = [
				'message' => makeMessageBox(ZBX_STYLE_MSG_GOOD, $messages, _('Host added'), true, false)->toString(),
				'title' => _('Host added'),
				'details' => $details,
				'script_inline' => ob_get_clean()
			];
		}

		if (!$output) {
			if (($messages = getMessages()) !== null) {
				$output = ['errors' => $messages->toString()];
			}

			if ($hostids !== false) {
				API::Host()->delete([$hostids['hostids'][0]]);
			}
		}

		$response = $output
			? (new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			: new CControllerResponseFatal();

		$this->setResponse($response);
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
	 * @param array  $valuemaps   Submitted value maps.
	 * @param bool   $clone_mode  Whether to cleanup existing ids.
	 *
	 * @return bool
	 */
	private function createValueMaps(string $hostid, array $valuemaps, $clone_mode = false): bool {
		foreach($valuemaps as $key => $valuemap) {
			if ($clone_mode) {
				unset($valuemap['valuemapid']);
			}

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

		if (!copyItems($src_hostid, $hostid)) {
			return false;
		}

		// Copy triggers.
		$db_triggers = API::Trigger()->get([
			'output' => ['triggerid'],
			'hostids' => $src_hostid,
			'inherited' => false,
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL]
		]);

		if ($db_triggers && !copyTriggersToHosts(array_column($db_triggers, 'triggerid'), $hostid, $src_hostid)) {
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
