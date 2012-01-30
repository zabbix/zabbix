<?php

class CHostExportElement extends CExportElement {

	public function __construct(array $host) {
		parent::__construct('host', $host);
	}

	protected function requiredFields() {
		return array('proxy_hostid', 'host', 'status',
			'ipmi_authtype', 'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'ipmi_disable_until', 'ipmi_available',
			'name');
	}

}
