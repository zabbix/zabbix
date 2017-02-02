<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageTemplates extends CWebTest {

	public static function allTemplates() {
		return DBdata("select * from hosts where status in (".HOST_STATUS_TEMPLATE.')');
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_CheckLayout($template) {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->zbxTestCheckTitle('Configuration of templates');
		$this->zbxTestCheckHeader('Templates');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent(['Templates', 'Applications', 'Items', 'Triggers', 'Graphs', 'Screens', 'Discovery', 'Linked templates', 'Linked to']);

		$this->zbxTestTextPresent([$template['name']]);
		$this->zbxTestTextPresent(['Export', 'Delete', 'Delete and clear']);
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_SimpleUpdate($template) {
		$host = $template['host'];
		$name = $template['name'];

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts =
				'SELECT hostid,proxy_hostid,host,status,error,available,ipmi_authtype,ipmi_privilege,ipmi_username,'.
				'ipmi_password,ipmi_disable_until,ipmi_available,snmp_disable_until,snmp_available,maintenanceid,'.
				'maintenance_status,maintenance_type,maintenance_from,ipmi_errors_from,snmp_errors_from,ipmi_error,'.
				'snmp_error,jmx_disable_until,jmx_available,jmx_errors_from,jmx_error,'.
				'name,flags,templateid,description,tls_connect,tls_accept'.
			' FROM hosts'.
			' ORDER BY hostid';
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldHashItems = DBhash($sqlItems);
		$sqlTriggers = "select triggerid,expression,description,url,status,value,priority,comments,error,templateid,type,state,flags from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->zbxTestCheckTitle('Configuration of templates');

		$this->zbxTestTextPresent($name);
		$this->zbxTestClickLinkText($name);
		$this->zbxTestCheckHeader('Templates');
		$this->zbxTestTextPresent('All templates');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template updated');
		$this->zbxTestTextPresent($name);
		$this->zbxTestCheckHeader('Templates');

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertEquals($oldHashItems, DBhash($sqlItems));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

}
