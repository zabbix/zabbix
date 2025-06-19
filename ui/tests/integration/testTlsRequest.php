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


require_once __DIR__.'/TlsCaseBase.php';
require_once __DIR__.'/../include/CIntegrationTest.php';

class testTlsRequest extends TlsCaseBase {
	public function configPairsSetupDataProvider() {
		// Tuples where first entry is in format for cert generation, second in format for Zabbix issuer/subject checks.

		// Simple case.
		yield [
			'issuer_ca' => ['/ST=CA/C=LV', 'C=LV,ST=CA'],
			'issuer_agent' => ['/ST=AGENT/C=LV', 'C=LV,ST=AGENT'],
			'issuer_server' => ['/ST=SERVER/C=LV', 'C=LV,ST=SERVER']
		];

		// DC attribute
		yield [
			'issuer_ca' => ['/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1'],
			'issuer_agent' => ['/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1'],
			'issuer_server' => ['/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1']
		];

		// DC attribute thrise
		/* yield [ */
		/* 	'issuer_ca' => ['/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3'], */
		/* 	'issuer_agent' => ['/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3'], */
		/* 	'issuer_server' => ['/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3'] */
		/* ]; */

		/* // "L" attribute */
		/* yield [ */
		/* 	'issuer_ca' => ['/L=1/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3,L=1'], */
		/* 	'issuer_agent' => ['/L=1/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3,L=1'], */
		/* 	'issuer_server' => ['/L=1/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3,L=1'] */
		/* ]; */

		/* // "O" attribute */
		/* yield [ */
		/* 	'issuer_ca' => ['/O=1/L=1/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3,L=1,O=1'], */
		/* 	'issuer_agent' => ['/O=1/L=1/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3,L=1,O=1'], */
		/* 	'issuer_server' => ['/O=1/L=1/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3,L=1,O=1'] */
		/* ]; */

		/* // "OU" attribute */
		/* yield [ */
		/* 	'issuer_ca' => ['/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3,L=1,O=1,OU=1'], */
		/* 	'issuer_agent' => ['/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3,L=1,O=1,OU=1'], */
		/* 	'issuer_server' => ['/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3,L=1,O=1,OU=1'] */
		/* ]; */

		/* // "UID" attribute */
		/* yield [ */
		/* 	'issuer_ca' => ['/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1'], */
		/* 	'issuer_agent' => ['/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1'], */
		/* 	'issuer_server' => ['/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1'] */
		/* ]; */

		/* // "CN" attribute */
		/* yield [ */
		/* 	'issuer_ca' => ['/CN=1/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=CA/C=LV', 'C=LV,ST=CA,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1,CN=1'], */
		/* 	'issuer_agent' => ['/CN=1/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=AGENT/C=LV', 'C=LV,ST=AGENT,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1,CN=1'], */
		/* 	'issuer_server' => ['/CN=1/UID=1/OU=1/O=1/L=1/DC=3/DC=2/DC=1/ST=SERVER/C=LV', 'C=LV,ST=SERVER,DC=1,DC=2,DC=3,L=1,O=1,OU=1,UID=1,CN=1'] */
		/* ]; */

		/* // Docs case. */
		/* yield [ */
		/* 	'issuer_ca' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA/DC=zabbix/DC=com/ST=CA', */
		/* 		'ST=CA,DC=com,DC=zabbix,O=Zabbix SIA,OU=Development group,CN=Signing CA' */
		/* 	], */
		/* 	'issuer_agent' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA/DC=zabbix/DC=com/ST=AGENT', */
		/* 		'ST=AGENT,DC=com,DC=zabbix,O=Zabbix SIA,OU=Development group,CN=Signing CA' */
		/* 	], */
		/* 	'issuer_server' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA/DC=zabbix/DC=com/ST=SERVER', */
		/* 		'ST=SERVER,DC=com,DC=zabbix,O=Zabbix SIA,OU=Development group,CN=Signing CA' */
		/* 	] */
		/* ]; */
		/* // Docs case with trailing space. */
		/* yield [ */
		/* 	'issuer_ca' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA\\ /DC=zabbix/DC=com/ST=CA', */
		/* 		'ST=CA,DC=com,DC=zabbix,O=Zabbix SIA\\ ,OU=Development group,CN=Signing CA' */
		/* 	], */
		/* 	'issuer_agent' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA/DC=zabbix/DC=com/ST=AGENT', */
		/* 		'ST=AGENT,DC=com,DC=zabbix,O=Zabbix SIA,OU=Development group,CN=Signing CA' */
		/* 	], */
		/* 	'issuer_server' => [ */
		/* 		'/CN=Signing CA/OU=Development group/O=Zabbix SIA/DC=zabbix/DC=com/ST=SERVER', */
		/* 		'ST=SERVER,DC=com,DC=zabbix,O=Zabbix SIA,OU=Development group,CN=Signing CA' */
		/* 	] */
		/* ]; */
	}

	/**
	 * @dataProvider configPairsSetupDataProvider
	 */
	public function testTlsRequest_baseCase(array $issuer_ca, array $issuer_agent, array $issuer_server) {
		[
			'ca' => [$ca_key, $ca_crt],
			'server' => [$server_key, $server_crt],
			'agent' => [$agent_key, $agent_crt]
		] = self::generateCerts(
			ca_subject: $issuer_ca[0],
			server_subject: $issuer_server[0],
			agent_subject: $issuer_agent[0]
		);

		if (defined('PHPUNIT_BINARY_DIR')) {
			CShellExec::serverBin(PHPUNIT_BINARY_DIR.'zabbix_server');
			CShellExec::agentBin(PHPUNIT_BINARY_DIR.'zabbix_agent2');
		}
		else {
			CShellExec::serverBin('/home/mt/.cache/zsharp/builds/server/encrypt-php-to-server-process/sbin/zabbix_server');
			CShellExec::agentBin('/home/mt/.cache/zsharp/builds/agents/encrypt-php-to-server-process/sbin/zabbix_agent2');
		}

		[$ready] = CShellExec::server([
			'DBName' => 'encrypt-php-to-server-process',
			'DBUser' => 'mt',
			'Timeout' => 8,
			'TLSFrontendAccept' => 'cert',
			'TLSCAFile' => $ca_crt,
			'TLSCertFile' => $server_crt,
			'TLSKeyFile' => $server_key
		]);
		self::assertTrue($ready, 'Expected server be running.'.CShellExec::logs());

		[$ready] = CShellExec::agent([
			'TLSAccept' => 'cert',
			'TLSConnect' => 'cert',
			'TLSCAFile' => $ca_crt,
			'TLSCertFile' => $agent_crt,
			'TLSKeyFile' => $agent_key,
			'TLSServerCertIssuer' => $issuer_ca[1],
			'TLSServerCertSubject' => $issuer_server[1]
		]);
		self::assertTrue($ready, 'Expected agent be running.');

		$sid = self::sid('localhost:8888', 'Admin', 'zabbix');
		$zabbix_server = self::serverApi('localhost:10051', $ca_crt, $agent_key, $agent_crt, $sid);
		$result = $zabbix_server->testItem(self::testItemRequest($issuer_ca[1], $issuer_agent[1]), $sid);

		self::assertTrue(array_key_exists('data', $result), 'Expected minimal client to work.'.CShellExec::logs());
		self::assertTrue(array_key_exists('item', $result['data']), 'Expected minimal client to work.'.CShellExec::logs());
		$error = $result['data']['item']['error'] ?? '';
		self::assertTrue(array_key_exists('result', $result['data']['item']), 'Expected minimal client to work.'.PHP_EOL.$error.CShellExec::logs());

		// Once all this succeed, same must now succeed from current FE client with issuer checks using same values as
		// in agent configuration.

		$tls_config = [
			'ACTIVE' => '1',
			'CA_FILE' => $ca_crt,
			'KEY_FILE' => $agent_key,
			'CERT_FILE' => $agent_crt,
			'CERTIFICATE_ISSUER' =>  $issuer_ca[1],
			'CERTIFICATE_SUBJECT' => $issuer_server[1]
		];
		$zabbix_server = new CZabbixServer('localhost', '10051', 10, 10, 0, $tls_config);
		$result = $zabbix_server->testItem(self::testItemRequest($issuer_ca[1], $issuer_agent[1]), $sid);

		self::assertTrue(is_array($result), 'Expected native client to work.');
		self::assertTrue(array_key_exists('item', $result), 'Expected native client to work.');
		self::assertTrue(array_key_exists('result', $result['item']), 'Expected native client to work.');
	}

	private static function testItemRequest(string $issuer_ca, string $issuer_agent) {
		return [
			'options' =>  [
				'single' => false,
				'state' => 0
			],
			'item' => [
				'value_type' => '1',
				'type' => '0',
				'key' => 'agent.version',
				'timeout' => '3s',
				'steps' =>  [
					[
						'type' => '20',
						'error_handler' => '0',
						'error_handler_params' => '',
						'params' => '1d'
					]
				]
			],
			'host' =>  [
				'interface' => [
					'address' => '127.0.0.1',
					'port' => '10050',
					'type' => 0
				],
				'tls_connect' => '4',
				'tls_issuer' => $issuer_ca,
				'tls_subject' => $issuer_agent,
				'proxyid' => 0
			]
		];
	}
}
