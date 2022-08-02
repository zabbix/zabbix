<?php declare(strict_types=1);
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


class C34ImportConverterTest extends CImportConverterTest {

	public function dataProviderConvert() {
		return [
			[
				[
					'templates' => [
						[
							'items' => [
								[
									'type' => '0',
									'delay' => '60;30/1-5,08:00-12:00',
									'history' => '0',
									'trends' => '0',
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
											'type' => 1,
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
											'type' => 7,
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
											'type' => 8,
											'params' => ''
										],
										[
											'type' => 10,
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
											'type' => 6,
											'params' => ''
										],
										[
											'type' => 9,
											'params' => ''
										],
										[
											'type' => 1,
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
													'type' => 1,
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
													'type' => 7,
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
													'type' => 8,
													'params' => ''
												],
												[
													'type' => 10,
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
													'type' => 6,
													'params' => ''
												],
												[
													'type' => 9,
													'params' => ''
												],
												[
													'type' => 1,
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
											'type' => 1,
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
											'type' => 7,
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
											'type' => 8,
											'params' => ''
										],
										[
											'type' => 10,
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
											'type' => 6,
											'params' => ''
										],
										[
											'type' => 9,
											'params' => ''
										],
										[
											'type' => 1,
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
													'type' => 1,
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
													'type' => 7,
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
													'type' => 8,
													'params' => ''
												],
												[
													'type' => 10,
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
													'type' => 6,
													'params' => ''
												],
												[
													'type' => 9,
													'params' => ''
												],
												[
													'type' => 1,
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
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '16',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
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
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '16',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										]
									],
									'jmx_endpoint' => '',
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
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
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '10'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_OCT2DEC,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_HEX2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_SPEED,
											'params' => ''
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '0',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'preprocessing' => [
										[
											'type' => ZBX_PREPROC_BOOL2DEC,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_DELTA_VALUE,
											'params' => ''
										],
										[
											'type' => ZBX_PREPROC_MULTIPLIER,
											'params' => '100'
										]
									],
									'jmx_endpoint' => '',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
								],
								[
									'type' => '16',
									'delay' => '60',
									'history' => '90d',
									'trends' => '365d',
									'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
									'master_item' => [],
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'output_format' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
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
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '10'
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_OCT2DEC,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_HEX2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_SPEED,
													'params' => ''
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '0',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'preprocessing' => [
												[
													'type' => ZBX_PREPROC_BOOL2DEC,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_DELTA_VALUE,
													'params' => ''
												],
												[
													'type' => ZBX_PREPROC_MULTIPLIER,
													'params' => '100'
												]
											],
											'jmx_endpoint' => '',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										],
										[
											'type' => '16',
											'delay' => '60',
											'history' => '90d',
											'trends' => '365d',
											'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
											'master_item' => [],
											'timeout' => '3s',
											'url' => '',
											'posts' => '',
											'status_codes' => '200',
											'follow_redirects' => '1',
											'post_type' => '0',
											'http_proxy' => '',
											'retrieve_mode' => '0',
											'request_method' => '0',
											'output_format' => '0',
											'ssl_cert_file' => '',
											'ssl_key_file' => '',
											'ssl_key_password' => '',
											'verify_peer' => '0',
											'verify_host' => '0',
											'allow_traps' => '0',
											'query_fields' => [],
											'headers' => []
										]
									],
									'jmx_endpoint' => '',
									'timeout' => '3s',
									'url' => '',
									'posts' => '',
									'status_codes' => '200',
									'follow_redirects' => '1',
									'post_type' => '0',
									'http_proxy' => '',
									'retrieve_mode' => '0',
									'request_method' => '0',
									'ssl_cert_file' => '',
									'ssl_key_file' => '',
									'ssl_key_password' => '',
									'verify_peer' => '0',
									'verify_host' => '0',
									'allow_traps' => '0',
									'query_fields' => [],
									'headers' => []
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
				'version' => '3.4',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function createExpectedResult(array $data = []) {
		return [
			'zabbix_export' => array_merge([
				'version' => '4.0',
				'date' => '2014-11-19T12:19:00Z'
			], $data)
		];
	}

	protected function assertConvert(array $expected, array $source) {
		$result = $this->createConverter()->convert($source);
		$this->assertSame($expected, $result);
	}


	protected function createConverter() {
		return new C34ImportConverter();
	}

}
