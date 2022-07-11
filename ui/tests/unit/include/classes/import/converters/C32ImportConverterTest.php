<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class C32ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[
					'templates' => [
						[
							'items' => [
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '30/1-5,08:00-12:00',
									'history' => 0,
									'trends' => 0
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '10',
									'multiplier' => 1,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_OCTAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 1,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_BOOLEAN,
									'formula' => '100',
									'multiplier' => 1,
									'delta' => 2,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '16',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								]
							],
							'discovery_rules' => [
								[
									'type' => '0',
									'delay' => 60,
									'delay_flex' => '30/1-5,08:00-12:00',
									'lifetime' => '{$LIFETIME}',
									'item_prototypes' => [
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '30/1-5,08:00-12:00',
											'history' => 0,
											'trends' => 0
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '10',
											'multiplier' => 1,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_OCTAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 1,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_BOOLEAN,
											'formula' => '100',
											'multiplier' => 1,
											'delta' => 2,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '16',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										]
									]
								]
							],
							'httptests' => [
								[
									'headers' => "Host:www.zabbix.com\nConnection:keep-alive\nPragma:no-cache",
									'variables' => "{var1}=value1\r\n\r\n\r\n{var2}=value2",
									'steps' => [
										[
											'headers' => "Host:internal.zabbix.com\n\n",
											'variables' => "{var3}=value3"
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => [
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '30/1-5,08:00-12:00',
									'history' => 0,
									'trends' => 0
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '10',
									'multiplier' => 1,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_OCTAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 1,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '0',
									'data_type' => ITEM_DATA_TYPE_BOOLEAN,
									'formula' => '100',
									'multiplier' => 1,
									'delta' => 2,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								],
								[
									'type' => '16',
									'data_type' => ITEM_DATA_TYPE_DECIMAL,
									'formula' => '1',
									'multiplier' => 0,
									'delta' => 0,
									'delay' => 60,
									'delay_flex' => '',
									'history' => 90,
									'trends' => 365
								]
							],
							'discovery_rules' => [
								[
									'type' => '0',
									'delay' => 60,
									'delay_flex' => '30/1-5,08:00-12:00',
									'lifetime' => '30',
									'item_prototypes' => [
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 0,
											'trends' => 0
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '10',
											'multiplier' => 1,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_OCTAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_HEXADECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 1,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '0',
											'data_type' => ITEM_DATA_TYPE_BOOLEAN,
											'formula' => '100',
											'multiplier' => 1,
											'delta' => 2,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										],
										[
											'type' => '16',
											'data_type' => ITEM_DATA_TYPE_DECIMAL,
											'formula' => '1',
											'multiplier' => 0,
											'delta' => 0,
											'delay' => 60,
											'delay_flex' => '',
											'history' => 90,
											'trends' => 365
										]
									]
								]
							],
							'httptests' => [
								[
									'headers' => '',
									'variables' => "{variable}=s00p3r$3c3t",
									'steps' => [
										[
											'headers' => "\r\n\n\r\r\r\nPragma:no-cache",
											'variables' => ''
										]
									]
								]
							]
						]
					]
				],
				[
					'templates' => [
						[
							'items' => [
								[
									'type' => '0',
									'delay' => '60;30/1-5,08:00-12:00',
									'history' => '0',
									'trends' => '0',
									'preprocessing' => '',
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '16',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => '',
									'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
									'master_item' => []
								]
							],
							'discovery_rules' => [
								[
									'type' => '0',
									'delay' => '60;30/1-5,08:00-12:00',
									'lifetime' => '{$LIFETIME}',
									'item_prototypes' => [
										[
											'type' => '0',
											'delay' => '60;30/1-5,08:00-12:00',
											'history' => '0',
											'trends' => '0',
											'preprocessing' => '',
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '16',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => '',
											'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
											'master_item_prototype' => []
										]
									],
									'jmx_endpoint' => ''
								]
							],
							'httptests' => [
								[
									'headers' => [
										[
											'name' => 'Host',
											'value' => 'www.zabbix.com'
										],
										[
											'name' => 'Connection',
											'value' => 'keep-alive'
										],
										[
											'name' => 'Pragma',
											'value' => 'no-cache'
										]
									],
									'variables' => [
										[
											'name' => '{var1}',
											'value' => 'value1'
										],
										[
											'name' => '{var2}',
											'value' => 'value2'
										]
									],
									'steps' => [
										[
											'headers' => [
												[
													'name' => 'Host',
													'value' => 'internal.zabbix.com'
												]
											],
											'variables' => [
												[
													'name' => '{var3}',
													'value' => 'value3'
												]
											],
											'query_fields' => []
										]
									]
								]
							]
						]
					],
					'hosts' => [
						[
							'items' => [
								[
									'type' => '0',
									'delay' => '60;30/1-5,08:00-12:00',
									'history' => '0',
									'trends' => '0',
									'preprocessing' => '',
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => (string) ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => (string) ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => []
								],
								[
									'type' => '16',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => '',
									'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
									'master_item' => []
								]
							],
							'discovery_rules' => [
								[
									'type' => '0',
									'delay' => '60;30/1-5,08:00-12:00',
									'lifetime' => '30d',
									'item_prototypes' => [
										[
											'type' => '0',
											'delay' => '60',
											'history' => '0',
											'trends' => '0',
											'preprocessing' => '',
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => (string) ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => (string) ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											],
											'jmx_endpoint' => '',
											'master_item_prototype' => []
										],
										[
											'type' => '16',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => '',
											'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
											'master_item_prototype' => []
										]
									],
									'jmx_endpoint' => ''
								]
							],
							'httptests' => [
								[
									'headers' => [],
									'variables' => [
										[
											'name' => '{variable}',
											'value' => 's00p3r$3c3t'
										]
									],
									'steps' => [
										[
											'headers' => [
												[
													'name' => 'Pragma',
													'value' => 'no-cache'
												]
											],
											'variables' => [],
											'query_fields' => []
										]
									]
								]
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderConvert
	 *
	 * @param $data
	 * @param $expected
	 */
	public function testConvert(array $data, array $expected) {
		$this->assertConvert($this->createExpectedResult($expected), $this->createSource($data));
	}

	protected function createSource(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '3.2',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '3.4',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertSame($expected, $result);
	}


	protected function createConverter() {
		return new C32ImportConverter();
	}

}
