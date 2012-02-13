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
		return $this->data['groups'];
	}

	public function getHosts() {
		$hostsData = $allGroups = array();

		foreach ($this->data['hosts'] as $host) {
			$allGroups += zbx_objectValues($host['groups'], 'name');

			$this->renameData($host, array('proxyid' => 'proxy_hostid'));

			foreach ($host['interfaces'] as $inum => $interface) {
				$this->renameData($host['interfaces'][$inum], array('default' => 'main'));
			}
			$host['interfaces'] = array_values($host['interfaces']);

			foreach ($host['templates'] as $tnum => $template) {
				$this->renameData($host['templates'][$tnum], array('name' => 'host'));
			}
			$host['templates'] = array_values($host['templates']);


			$host['interfaces'] = array_values($host['interfaces']);

			$hostsData[] = ArrayHelper::getByKeys($host, array('proxy_hostid', 'groups', 'templates', 'interfaces',
				'host', 'status', 'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'name'));
		}

		return $hostsData;
	}

	public function getApplications() {
		$applicationsData = array();

		foreach ($this->data['hosts'] as $host) {
			foreach ($host['applications'] as $application) {
				$applicationsData[$host['host']][$application['name']] = $application;
			}
		}

		return $applicationsData;
	}

	public function getItems() {
		$itemsData = array();

		foreach ($this->data['hosts'] as $host) {
			foreach ($host['items'] as $item) {
				$this->renameData($item, array('key' => 'key_', 'allowed_hosts' => 'trapper_hosts'));
				$itemsData[$host['host']][$item['key_']] = $item;
			}
		}

		return $itemsData;
	}

}
