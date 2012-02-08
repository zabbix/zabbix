<?php

class C20ImportFormatter extends CImportFormatter {

	protected function renameData(&$data, $fieldMap) {
		foreach ($data as $key => $value) {
			if (isset($fieldMap[$key])) {
				$data[$fieldMap[$key]] = $value;
				unset($data[$key]);
			}
		}
	}

	public function getGroups() {
		return $this->data['zabbix_export']['groups'];
	}

	public function getHosts() {
		$hostsData = $allGroups = array();
		$this->data['zabbix_export']['hosts'] = array_values($this->data['zabbix_export']['hosts']);

		foreach ($this->data['zabbix_export']['hosts'] as $host) {
			$allGroups += zbx_objectValues($host['groups'], 'name');

			$this->renameData($host, array('proxyid' => 'proxy_hostid'));

			foreach ($host['interfaces'] as $inum => $interface) {
				$this->renameData($host['interfaces'][$inum], array('default' => 'main'));
			}

			$this->renameData($host, array('proxyid' => 'proxy_hostid'));


			$host['interfaces'] = array_values($host['interfaces']);
			$hostsData[] = ArrayHelper::getByKeys($host, array('proxy_hostid', 'groups', 'interfaces',
				'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name'));
		}

		$allDbHosts = API::Host()->get(array(
			'filter' => array('host' => zbx_objectValues($this->data['zabbix_export']['hosts'], 'host')),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
		));
		$allDbHosts = zbx_toHash($allDbHosts, 'host');

		$allDbGroups = API::HostGroup()->get(array(
			'filter' => array('name' => array_unique($allGroups)),
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
		));
		$allDbGroups = zbx_toHash($allDbGroups, 'name');

		foreach ($hostsData as &$host) {
			foreach ($host['groups'] as $gnum => $group) {
				$host['groups'][$gnum] = $allDbGroups[$group['name']];
			}

			if (isset($allDbHosts[$host['host']])) {
				$host['hostid'] = $allDbHosts[$host['host']]['hostid'];
			}
		}
		unset($host);

		return $hostsData;
	}

}
