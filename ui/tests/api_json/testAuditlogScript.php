<?php
/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup scripts
 */
class testAuditlogScript extends testAuditlogCommon {

	/**
	 * Webhook parameter id.
	 */
	protected static $parameterid;

	/**
	 * Created SSH script id.
	 */
	protected static $resourceid_ssh;

	/**
	 * Created Webhook script id.
	 */
	protected static $resourceid_webhook;

	/**
	 * Created script id.
	 */
	protected static $resourceid_script;

	/**
	 * Created script with authtype id.
	 */
	protected static $resourceid_authtype;

	public function testAuditlogScript_CreateSSH() {
		$create = $this->call('script.create', [
			[
				'name' => 'Created SSH script',
				'command' => 'Created command to run',
				'type' => 2,
				'scope' => 2,
				'menu_path' => 'created/menu/path',
				'authtype' => 1,
				'username' => 'created_user',
				'password' => 'created_password',
				'publickey' => 'created_public_key',
				'privatekey' => 'created_private_key',
				'port' => '12345',
				'host_access' => 3,
				'confirmation' => 'created_confirmation',
				'description' => 'created description',
				'groupid' => 1,
				'usrgrpid' => 7
			]
		]);

		self::$resourceid_ssh = $create['result']['scriptids'][0];

		$created = json_encode([
			'script.name' => ['add', 'Created SSH script'],
			'script.command' => ['add', 'Created command to run'],
			'script.type' => ['add', '2'],
			'script.scope' => ['add', '2'],
			'script.menu_path' => ['add', 'created/menu/path'],
			'script.authtype' => ['add', '1'],
			'script.username' => ['add', 'created_user'],
			'script.password' => ['add', '******'],
			'script.publickey' => ['add', 'created_public_key'],
			'script.privatekey' => ['add', 'created_private_key'],
			'script.port' => ['add', '12345'],
			'script.host_access' => ['add', '3'],
			'script.confirmation' => ['add', 'created_confirmation'],
			'script.description' => ['add', 'created description'],
			'script.groupid' => ['add', '1'],
			'script.usrgrpid' => ['add', '7'],
			'script.scriptid' => ['add', self::$resourceid_ssh]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_ssh);
	}

	/**
	 * @depends testAuditlogScript_CreateSSH
	 */
	public function testAuditlogScript_UpdateSSH() {
		$this->call('script.update', [
			[
				'scriptid' => self::$resourceid_ssh,
				'name' => 'Updated SSH script',
				'command' => 'Updated command to run',
				'scope' => 4,
				'type' => 3,
				'menu_path' => 'updated/menu/path',
				'username' => 'updated_user',
				'password' => 'updated_password',
				'port' => '65535',
				'groupid' => '4',
				'usrgrpid' => '8',
				'host_access' => 2,
				'confirmation' => 'updated_confirmation',
				'description' => 'updated description'
			]
		]);

		$updated = json_encode([
			'script.name' => ['update', 'Updated SSH script', 'Created SSH script'],
			'script.command' => ['update', 'Updated command to run', 'Created command to run'],
			'script.scope' => ['update', '4', '2'],
			'script.type' => ['update', '3', '2'],
			'script.menu_path' => ['update', 'updated/menu/path', 'created/menu/path'],
			'script.username' => ['update', 'updated_user', 'created_user'],
			'script.password' => ['update', '******', '******'],
			'script.port' => ['update', '65535', '12345'],
			'script.groupid' => ['update', '4', '1'],
			'script.usrgrpid' => ['update', '8', '7'],
			'script.host_access' => ['update', '2', '3'],
			'script.confirmation' => ['update', 'updated_confirmation', 'created_confirmation'],
			'script.description' => ['update', 'updated description', 'created description'],
			'script.authtype' => ['update', '0', '1'],
			'script.publickey' => ['update', '', 'created_public_key'],
			'script.privatekey' => ['update', '', 'created_private_key']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_ssh);
	}

	public function testAuditlogScript_CreateWebhook() {
		$create = $this->call('script.create', [
			[
				'name' => 'Created webhook script',
				'command' => 'Created command to run',
				'type' => 5,
				'timeout' => '40s',
				'parameters' => [
					[
						'name' => 'created_par_name',
						'value' => 'created_par_value'
					]
				]
			]
		]);

		self::$resourceid_webhook = $create['result']['scriptids'][0];

		self::$parameterid = CDBHelper::getRow('SELECT script_paramid FROM script_param WHERE scriptid='.
				zbx_dbstr(self::$resourceid_webhook)
		);

		$created = json_encode([
			'script.name' => ['add', 'Created webhook script'],
			'script.command' => ['add', 'Created command to run'],
			'script.timeout' => ['add', '40s'],
			'script.parameters['.self::$parameterid['script_paramid'].']'  => ['add'],
			'script.parameters['.self::$parameterid['script_paramid'].'].name'  => ['add', 'created_par_name'],
			'script.parameters['.self::$parameterid['script_paramid'].'].value'  => ['add', 'created_par_value'],
			'script.parameters['.self::$parameterid['script_paramid'].'].script_paramid'
					=> ['add', self::$parameterid['script_paramid']],
			'script.scriptid' => ['add', self::$resourceid_webhook]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_webhook);
	}

	/**
	 * @depends testAuditlogScript_CreateWebhook
	 */
	public function testAuditlogScript_UpdateWebhook() {
		$this->call('script.update', [
			[
				'scriptid' => self::$resourceid_webhook,
				'name' => 'Updated webhook script',
				'command' => 'Updated command to run',
				'timeout' => '35s',
				'parameters' => [
					[
						'name' => 'updated_par_name',
						'value' => 'updated_par_value'
					]
				]
			]
		]);

		$updated_parameterid = CDBHelper::getRow('SELECT script_paramid FROM script_param WHERE scriptid='.
				zbx_dbstr(self::$resourceid_webhook)
		);

		$updated = json_encode([
			'script.parameters['.self::$parameterid['script_paramid'].']'  => ['delete'],
			'script.parameters['.$updated_parameterid['script_paramid'].']'  => ['add'],
			'script.name' => ['update', 'Updated webhook script', 'Created webhook script'],
			'script.command' => ['update', 'Updated command to run', 'Created command to run'],
			'script.timeout' => ['update', '35s', '40s'],
			'script.parameters['.$updated_parameterid['script_paramid'].'].name'  => ['add', 'updated_par_name'],
			'script.parameters['.$updated_parameterid['script_paramid'].'].value'  => ['add', 'updated_par_value'],
			'script.parameters['.$updated_parameterid['script_paramid'].'].script_paramid'
					=> ['add', $updated_parameterid['script_paramid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_webhook);
	}

	public function testAuditlogScript_CreateScript() {
		$create = $this->call('script.create', [
			[
				'name' => 'Created script',
				'command' => 'Created command to run',
				'type' => 0,
				'execute_on' => 0
			]
		]);

		self::$resourceid_script = $create['result']['scriptids'][0];

		$created = json_encode([
			'script.name' => ['add', 'Created script'],
			'script.command' => ['add', 'Created command to run'],
			'script.type' => ['add', '0'],
			'script.execute_on' => ['add', '0'],
			'script.scriptid' => ['add', self::$resourceid_script]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_script);
	}

	/**
	 * @depends testAuditlogScript_CreateScript
	 */
	public function testAuditlogScript_UpdateScript() {
		$this->call('script.update', [
			[
				'scriptid' => self::$resourceid_script,
				'name' => 'Updated script',
				'execute_on' => 1
			]
		]);

		$updated = json_encode([
			'script.name' => ['update', 'Updated script', 'Created script'],
			'script.execute_on' => ['update', '1', '0']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_script);
	}

	public function testAuditlogScript_CreateAuthtype() {
		$create = $this->call('script.create', [
			[
				'name' => 'Created authtype script',
				'command' => 'Created command to run',
				'type' => 2,
				'authtype' => 0,
				'username' => 'created_user',
				'password' => 'created_password'
			]
		]);

		self::$resourceid_authtype = $create['result']['scriptids'][0];

		$created = json_encode([
			'script.name' => ['add', 'Created authtype script'],
			'script.command' => ['add', 'Created command to run'],
			'script.type' => ['add', '2'],
			'script.username' => ['add', 'created_user'],
			'script.password' => ['add', '******'],
			'script.scriptid' => ['add', self::$resourceid_authtype]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_authtype);
	}

	/**
	 * @depends testAuditlogScript_CreateAuthtype
	 */
	public function testAuditlogScript_UpdateAuthtype() {
		$this->call('script.update', [
			[
				'scriptid' => self::$resourceid_authtype,
				'name' => 'Updated authtype script',
				'authtype' => 1,
				'publickey' => 'update_public_key',
				'privatekey' => 'update_private_key'
			]
		]);

		$updated = json_encode([
			'script.name' => ['update', 'Updated authtype script', 'Created authtype script'],
			'script.authtype' => ['update', '1', '0'],
			'script.publickey' => ['update', 'update_public_key', ''],
			'script.privatekey' => ['update', 'update_private_key', '']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_authtype);
	}

	/**
	 * @depends testAuditlogScript_CreateSSH
	 */
	public function testAuditlogScript_Delete() {
		$this->call('script.delete', [self::$resourceid_ssh]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated SSH script', self::$resourceid_ssh);
	}
}
