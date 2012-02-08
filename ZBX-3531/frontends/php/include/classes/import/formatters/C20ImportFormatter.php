<?php

class C20ImportFormatter extends CimportFormatter {


	public function getGroups() {
		return $this->data['zabbix_export']['groups'];
	}

	public function getHosts() {

	}

}
