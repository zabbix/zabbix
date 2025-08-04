<?php
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


require_once dirname(__FILE__).'/common/testAuditlogCommon.php';

/**
 * @backup connector
 */
class testAuditlogConnector extends testAuditlogCommon {

	/**
	 * Created connector ID.
	 */
	protected static $resourceid;

	/**
	 * Created connector ID with NTLM HTTP authentication.
	 */
	protected static $resourceid_ntlm;

	/**
	 * Created connector ID with Kerberos HTTP authentication.
	 */
	protected static $resourceid_kerberos;

	/**
	 * Created connector ID with Digest HTTP authentication.
	 */
	protected static $resourceid_digest;

	/**
	 * Created connector ID with Bearer HTTP authentication.
	 */
	protected static $resourceid_bearer;

	/**
	 * Created connector tag ID.
	 */
	protected static $connector_tagid;

	public function testAuditlogConnector_Create() {
		$create = $this->call('connector.create', [
			[
				'name' => 'Created controller',
				'data_type' => 1,
				'url' => 'created_url.com',
				'protocol' => 0,
				'max_records' => 100,
				'max_senders' => 55,
				'max_attempts' => 2,
				'timeout' => '33s',
				'http_proxy' => 'create_proxy_http',
				'authtype' => 4,
				'username' => 'created_username',
				'password' => 'created_password',
				'attempt_interval' => '2s',
				'status' => 0,
				'verify_peer' => 0,
				'verify_host' => 0,
				'ssl_cert_file' => 'created_ssl_cert_file_path',
				'ssl_key_file' => 'created_ssl_key_file_path',
				'ssl_key_password' => 'created_key_password',
				'description' => 'created description',
				'tags_evaltype' => 2,
				'tags' => [
					'tag' => 'created_connector',
					'operator' => 1,
					'value' => 'created_value'
				]
			]
		]);

		self::$resourceid = $create['result']['connectorids'][0];
		self::$connector_tagid = CDBHelper::getRow('SELECT connector_tagid FROM connector_tag WHERE connectorid='.
				zbx_dbstr(self::$resourceid)
		);

		$created = json_encode([
			'connector.name' => ['add', 'Created controller'],
			'connector.data_type' => ['add', '1'],
			'connector.url' => ['add', 'created_url.com'],
			'connector.max_records' => ['add', '100'],
			'connector.max_senders' => ['add', '55'],
			'connector.max_attempts' => ['add', '2'],
			'connector.timeout' => ['add', '33s'],
			'connector.http_proxy' => ['add', 'create_proxy_http'],
			'connector.authtype' => ['add', '4'],
			'connector.username' => ['add', 'created_username'],
			'connector.password' => ['add', '******'],
			'connector.attempt_interval' => ['add', '2s'],
			'connector.status' => ['add', '0'],
			'connector.verify_peer' => ['add', '0'],
			'connector.verify_host' => ['add', '0'],
			'connector.ssl_cert_file' => ['add', 'created_ssl_cert_file_path'],
			'connector.ssl_key_file' => ['add', 'created_ssl_key_file_path'],
			'connector.ssl_key_password' => ['add', '******'],
			'connector.description' => ['add', 'created description'],
			'connector.tags_evaltype' => ['add', '2'],
			'connector.tags['.self::$connector_tagid['connector_tagid'].']' => ['add'],
			'connector.tags['.self::$connector_tagid['connector_tagid'].'].tag' => ['add', 'created_connector'],
			'connector.tags['.self::$connector_tagid['connector_tagid'].'].operator' => ['add', '1'],
			'connector.tags['.self::$connector_tagid['connector_tagid'].'].value' => ['add', 'created_value'],
			'connector.tags['.self::$connector_tagid['connector_tagid'].'].connector_tagid'
					=> ['add', self::$connector_tagid['connector_tagid']],
			'connector.connectorid' => ['add', self::$resourceid]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid);
	}

	/**
	 * @depends testAuditlogConnector_Create
	 */
	public function testAuditlogConnector_Update() {
		$this->call('connector.update', [
			[
				'connectorid' => self::$resourceid,
				'name' => 'Updated controller',
				'data_type' => 0,
				'url' => 'updated_url.com',
				'protocol' => 0,
				'max_records' => 200,
				'max_senders' => 88,
				'max_attempts' => 3,
				'timeout' => '55s',
				'http_proxy' => 'updated_proxy_http',
				'authtype' => 3,
				'username' => 'updated_username',
				'password' => 'updated_password',
				'attempt_interval' => '4s',
				'status' => 1,
				'verify_peer' => 1,
				'verify_host' => 1,
				'ssl_cert_file' => 'updated_ssl_cert_file_path',
				'ssl_key_file' => 'updated_ssl_key_file_path',
				'ssl_key_password' => 'updated_key_password',
				'description' => 'updated description',
				'tags_evaltype' => 0,
				'tags' => [
					'tag' => 'updated_connector',
					'operator' => 3,
					'value' => 'updated_value'
				]
			]
		]);

		$updated_tagid = CDBHelper::getRow('SELECT connector_tagid FROM connector_tag WHERE connectorid='.
				zbx_dbstr(self::$resourceid)
		);

		$updated = json_encode([
			'connector.tags['.self::$connector_tagid['connector_tagid'].']' => ['delete'],
			'connector.tags['.$updated_tagid['connector_tagid'].']' => ['add'],
			'connector.name' => ['update', 'Updated controller', 'Created controller'],
			'connector.data_type' => ['update', '0', '1'],
			'connector.url' => ['update', 'updated_url.com', 'created_url.com'],
			'connector.max_records' => ['update', '200', '100'],
			'connector.max_senders' => ['update', '88', '55'],
			'connector.max_attempts' => ['update', '3', '2'],
			'connector.timeout' => ['update', '55s', '33s'],
			'connector.http_proxy' => ['update', 'updated_proxy_http', 'create_proxy_http'],
			'connector.authtype' => ['update', '3', '4'],
			'connector.username' => ['update', 'updated_username', 'created_username'],
			'connector.password' => ['update', '******', '******'],
			'connector.attempt_interval' => ['update', '4s', '2s'],
			'connector.status' => ['update', '1', '0'],
			'connector.verify_peer' => ['update', '1', '0'],
			'connector.verify_host' => ['update', '1', '0'],
			'connector.ssl_cert_file' => ['update', 'updated_ssl_cert_file_path', 'created_ssl_cert_file_path'],
			'connector.ssl_key_file' => ['update', 'updated_ssl_key_file_path', 'created_ssl_key_file_path'],
			'connector.ssl_key_password' => ['update', '******', '******'],
			'connector.description' => ['update', 'updated description', 'created description'],
			'connector.tags_evaltype' => ['update', '0', '2'],
			'connector.tags['.$updated_tagid['connector_tagid'].'].tag' => ['add', 'updated_connector'],
			'connector.tags['.$updated_tagid['connector_tagid'].'].operator' => ['add', '3'],
			'connector.tags['.$updated_tagid['connector_tagid'].'].value' => ['add', 'updated_value'],
			'connector.tags['.$updated_tagid['connector_tagid'].'].connector_tagid'
					=> ['add', $updated_tagid['connector_tagid']]
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid);
	}

	public function testAuditlogConnector_CreateNtlm() {
		$create = $this->call('connector.create', [
			[
				'name' => 'Created controller NTLM',
				'data_type' => 0,
				'url' => 'created_url.com',
				'authtype' => 2,
				'username' => 'created_username',
				'password' => 'created_password',
				'item_value_type' => 1
			]
		]);

		self::$resourceid_ntlm = $create['result']['connectorids'][0];

		$created = json_encode([
			'connector.name' => ['add', 'Created controller NTLM'],
			'connector.url' => ['add', 'created_url.com'],
			'connector.authtype' => ['add', '2'],
			'connector.username' => ['add', 'created_username'],
			'connector.password' => ['add', '******'],
			'connector.item_value_type' => ['add', '1'],
			'connector.connectorid' => ['add', self::$resourceid_ntlm]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_ntlm);
	}

	/**
	 * @depends testAuditlogConnector_CreateNtlm
	 */
	public function testAuditlogConnector_UpdateNtlm() {
		$this->call('connector.update', [
			[
				'connectorid' => self::$resourceid_ntlm,
				'name' => 'Updated controller NTLM',
				'username' => 'updated_username',
				'password' => 'updated_password',
				'item_value_type' => 4
			]
		]);

		$updated = json_encode([
			'connector.name' => ['update', 'Updated controller NTLM', 'Created controller NTLM'],
			'connector.username' => ['update', 'updated_username', 'created_username'],
			'connector.password' => ['update', '******', '******'],
			'connector.item_value_type' => ['update', '4', '1']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_ntlm);
	}

	public function testAuditlogConnector_CreateKerberos() {
		$create = $this->call('connector.create', [
			[
				'name' => 'Created controller Kerberos',
				'data_type' => 0,
				'url' => 'created_url.com',
				'authtype' => 3,
				'username' => 'created_username',
				'password' => 'created_password',
				'item_value_type' => 1
			]
		]);

		self::$resourceid_kerberos = $create['result']['connectorids'][0];

		$created = json_encode([
			'connector.name' => ['add', 'Created controller Kerberos'],
			'connector.url' => ['add', 'created_url.com'],
			'connector.authtype' => ['add', '3'],
			'connector.username' => ['add', 'created_username'],
			'connector.password' => ['add', '******'],
			'connector.item_value_type' => ['add', '1'],
			'connector.connectorid' => ['add', self::$resourceid_kerberos]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_kerberos);
	}

	/**
	 * @depends testAuditlogConnector_CreateKerberos
	 */
	public function testAuditlogConnector_UpdateKerberos() {
		$this->call('connector.update', [
			[
				'connectorid' => self::$resourceid_kerberos,
				'name' => 'Updated controller Kerberos',
				'username' => 'updated_username',
				'password' => 'updated_password'
			]
		]);

		$updated = json_encode([
			'connector.name' => ['update', 'Updated controller Kerberos', 'Created controller Kerberos'],
			'connector.username' => ['update', 'updated_username', 'created_username'],
			'connector.password' => ['update', '******', '******']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_kerberos);
	}

	public function testAuditlogConnector_CreateDigest() {
		$create = $this->call('connector.create', [
			[
				'name' => 'Created controller Digest',
				'data_type' => 0,
				'url' => 'created_url.com',
				'authtype' => 4,
				'username' => 'created_username',
				'password' => 'created_password',
				'item_value_type' => 1
			]
		]);

		self::$resourceid_digest = $create['result']['connectorids'][0];

		$created = json_encode([
			'connector.name' => ['add', 'Created controller Digest'],
			'connector.url' => ['add', 'created_url.com'],
			'connector.authtype' => ['add', '4'],
			'connector.username' => ['add', 'created_username'],
			'connector.password' => ['add', '******'],
			'connector.item_value_type' => ['add', '1'],
			'connector.connectorid' => ['add', self::$resourceid_digest]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_digest);
	}

	/**
	 * @depends testAuditlogConnector_CreateDigest
	 */
	public function testAuditlogConnector_UpdateDigest() {
		$this->call('connector.update', [
			[
				'connectorid' => self::$resourceid_digest,
				'name' => 'Updated controller Digest',
				'username' => 'updated_username',
				'password' => 'updated_password'
			]
		]);

		$updated = json_encode([
			'connector.name' => ['update', 'Updated controller Digest', 'Created controller Digest'],
			'connector.username' => ['update', 'updated_username', 'created_username'],
			'connector.password' => ['update', '******', '******']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_digest);
	}

	public function testAuditlogConnector_CreateBearer() {
		$create = $this->call('connector.create', [
			[
				'name' => 'Created controller Bearer',
				'data_type' => 0,
				'url' => 'created_url.com',
				'authtype' => 5,
				'token' => 'test_token',
				'item_value_type' => 1
			]
		]);

		self::$resourceid_bearer = $create['result']['connectorids'][0];

		$created = json_encode([
			'connector.name' => ['add', 'Created controller Bearer'],
			'connector.url' => ['add', 'created_url.com'],
			'connector.authtype' => ['add', '5'],
			'connector.token' => ['add', '******'],
			'connector.item_value_type' => ['add', '1'],
			'connector.connectorid' => ['add', self::$resourceid_bearer]
		]);

		$this->getAuditDetails('details', $this->add_actionid, $created, self::$resourceid_bearer);
	}

	/**
	 * @depends testAuditlogConnector_CreateBearer
	 */
	public function testAuditlogConnector_UpdateBearer() {
		$this->call('connector.update', [
			[
				'connectorid' => self::$resourceid_bearer,
				'name' => 'Updated controller Bearer',
				'token' => 'updated_test_token'
			]
		]);

		$updated = json_encode([
			'connector.name' => ['update', 'Updated controller Bearer', 'Created controller Bearer'],
			'connector.token' => ['update', '******', '******']
		]);

		$this->getAuditDetails('details', $this->update_actionid, $updated, self::$resourceid_bearer);
	}

	/**
	 * @depends testAuditlogConnector_Create
	 */
	public function testAuditlogConnector_Delete() {
		$this->call('connector.delete', [self::$resourceid]);
		$this->getAuditDetails('resourcename', $this->delete_actionid, 'Updated controller', self::$resourceid);
	}
}
