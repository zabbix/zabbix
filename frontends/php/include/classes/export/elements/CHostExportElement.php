<?php

class CHostExportElement extends CNodeExportElement {

	public function __construct(array $host) {
		$host = ArrayHelper::getByKeys($host, array('proxy_hostid', 'host', 'status',
			'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'ipmi_disable_until', 'ipmi_available',
			'name'));
		parent::__construct('host', $host);
	}
}
