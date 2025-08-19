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


require_once __DIR__.'/../include/CLegacyWebTest.php';

/**
 * @backup hosts
 *
 * @onBefore prepareLLDPrototypeData
 */
class testUrlParameters extends CLegacyWebTest {

	const POPUP = 'zabbix.php?action=popup&popup=';

	protected static $ids; // Contains all required ids of created test data entities both for host and template.

	public function prepareLLDPrototypeData() {
		// Create host with LLD prototype.
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host with host prototype for URL parameters',
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for URL parameters check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		self::$ids['hostid'] = $response['hostids']['Host with host prototype for URL parameters'];
		self::$ids['host_lldid'] = $response['discoveryruleids']['Host with host prototype for URL parameters:drule'];

		self::$ids['host_lld_prototypeid'] = CDataHelper::call('discoveryruleprototype.create', [
			[
				'hostid' => self::$ids['hostid'],
				'ruleid' => self::$ids['host_lldid'],
				'name' => 'LLD rule prototype for documentation link test',
				'key_' => 'lld_rule_prototype[{#KEY},{#KEY2}]',
				'delay' => '30s',
				'type' => ITEM_TYPE_INTERNAL
			]
		])['itemids'][0];

		// Create template with LLD rule prototype.
		$template_response = CDataHelper::createTemplates([
			[
				'host' => 'Template with LLD prototype for URL parameters',
				'groups' => [['groupid' => 1]], // Templates
				'discoveryrules' => [
					[
						'name' => 'Drule for URL parameters check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);

		self::$ids['templateid'] = $template_response['templateids']['Template with LLD prototype for URL parameters'];
		self::$ids['template_lldid'] = $template_response['discoveryruleids']['Template with LLD prototype for URL parameters:drule'];

		self::$ids['template_lld_prototypeid'] = CDataHelper::call('discoveryruleprototype.create', [
			[
				'hostid' => self::$ids['templateid'],
				'ruleid' => self::$ids['template_lldid'],
				'name' => 'LLD rule prototype for URL parameters test',
				'key_' => 'lld_rule_prototype[{#KEY},{#KEY2}]',
				'delay' => '30s',
				'type' => ITEM_TYPE_INTERNAL
			]
		])['itemids'][0];
	}

	public static function data() {
		return [
			[
				[
					'title' => 'Host group edit',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'hostgroup.edit&groupid=4',
							'text_present' => 'Host groups'
						],
						[
							'url' => self::POPUP.'hostgroup.edit&groupid=9999999',
							'text_not_present' => 'Host groups',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'hostgroup.edit&groupid=abc',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "abc" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: abc',
								'popup: hostgroup.edit'
							]
						],
						[
							'url' => self::POPUP.'hostgroup.edit&groupid[]=1',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: array',
								'popup: hostgroup.edit'
							]
						],
						[
							'url' => self::POPUP.'hostgroup.edit&name[]=name',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for field "name": a character string is expected.',
								'Controller: popup',
								'action: popup',
								'name: array',
								'popup: hostgroup.edit'
							]
						],
						[
							'url' => self::POPUP.'hostgroup.edit&subgroups[]=1',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for "subgroups" field.',
								'Controller: popup',
								'action: popup',
								'subgroups: array',
								'popup: hostgroup.edit'
							]
						],
						[
							'url' => self::POPUP.'hostgroup.edit&groupid=',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid:',
								'popup: hostgroup.edit'
							]
						],
						[
							'url' => self::POPUP.'hostgroup.edit&groupid=-1',
							'text_not_present' => 'Host groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "-1" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: -1',
								'popup: hostgroup.edit'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Template group edit',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'templategroup.edit&groupid=1',
							'text_present' => 'Template groups'
						],
						[
							'url' => self::POPUP.'templategroup.edit&groupid=9999999',
							'text_not_present' => 'Template groups',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'templategroup.edit&groupid=abc',
							'text_not_present' => 'Template groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "abc" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: abc',
								'popup: templategroup.edit'
							]
						],
						[
							'url' => self::POPUP.'templategroup.edit&groupid=',
							'text_not_present' => 'Template groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid:',
								'popup: templategroup.edit'
							]
						],
						[
							'url' => self::POPUP.'templategroup.edit&groupid=-1',
							'text_not_present' => 'Template groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "-1" for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: -1',
								'popup: templategroup.edit'
							]
						],
						[
							'url' => self::POPUP.'templategroup.edit&groupid[]=1',
							'text_not_present' => 'Template groups',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for "groupid" field.',
								'Controller: popup',
								'action: popup',
								'groupid: array',
								'popup: templategroup.edit'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Host edit',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'host.edit&hostid=10084',
							'text_present' => 'Host'
						],
						[
							'url' => self::POPUP.'host.edit&hostid=9999999',
							'text_not_present' => 'Host',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [

						[
							'url' => self::POPUP.'host.edit&hostid=abc',
							'text_not_present' => 'Host',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "abc" for "hostid" field.',
								'Controller: popup',
								'action: popup',
								'hostid: abc',
								'popup: host.edit'
							]
						],
						[
							'url' => self::POPUP.'host.edit&hostid= ',
							'text_not_present' => 'Host',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "" for "hostid" field.',
								'Controller: popup',
								'action: popup',
								'hostid:',
								'popup: host.edit'
							]
						],
						[
							'url' => self::POPUP.'host.edit&hostid=-1',
							'text_not_present' => 'Host',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "-1" for "hostid" field.',
								'Controller: popup',
								'action: popup',
								'hostid: -1',
								'popup: host.edit'
							]
						],
						[
							'url' => self::POPUP.'host.edit&hostid[]=1',
							'text_not_present' => 'Host',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for "hostid" field.',
								'Controller: popup',
								'action: popup',
								'hostid: array',
								'popup: host.edit'
							]
						],
						[
							'url' => self::POPUP.'host.edit&hostid=',
							'text_not_present' => 'Host',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "" for "hostid" field.',
								'Controller: popup',
								'action: popup',
								'hostid:',
								'popup: host.edit'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'action.edit&eventsource=99999',
							'text_not_present' => 'Trigger actions',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "99999" for "eventsource" field.',
								'Controller: popup',
								'action: popup',
								'eventsource: 99999',
								'popup: action.edit'
							]
						],
						[
							'url' => self::POPUP.'action.edit&eventsource=abc',
							'text_not_present' => 'Trigger actions',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "abc" for "eventsource" field.',
								'Controller: popup',
								'action: popup',
								'eventsource: abc',
								'popup: action.edit'
							]
						],
						[
							'url' => self::POPUP.'action.edit&eventsource=-1',
							'text_not_present' => 'Trigger actions',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value "-1" for "eventsource" field.',
								'Controller: popup',
								'action: popup',
								'eventsource: -1',
								'popup: action.edit'
							]
						],
						[
							'url' => self::POPUP.'action.edit&eventsource[]=0',
							'text_not_present' => 'Trigger actions',
							'fatal_error' => true,
							'text_present' => [
								'Incorrect value for "eventsource" field.',
								'Controller: popup',
								'action: popup',
								'eventsource: array',
								'popup: action.edit'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Item edit',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'item.edit&context=template&itemid=46050',
							'text_present' => 'Item'
						],
						[
							'url' => self::POPUP.'item.edit&context=host&itemid=1',
							'text_not_present' => 'Item',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'item.edit&context=host&itemid=46050',
							'text_not_present' => 'Item',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Item prototype edit',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						// context=template.
						[
							// Acronis Cyber Protect Cloud MSP by HTTP -> Get devices: Acronis CPC: Device discovery -> Device [{#NAME}]:[{#ID}]: Agent enabled
							'url' => self::POPUP.'item.prototype.edit&context=template&itemid=59371&parent_discoveryid=59367',
							'text_present' => 'Item prototype'
						],
						[
							'url' => self::POPUP.'item.prototype.edit&context=template&itemid=1&parent_discoveryid=59367',
							'text_not_present' => 'Item prototype',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						],
						// context=host.
						[
							'url' => self::POPUP.'item.prototype.edit&item.prototype.edit&itemid=400610&parent_discoveryid=400590&context=host',
							'text_present' => 'Item prototype'
						],
						[
							'url' => self::POPUP.'item.prototype.edit&itemid=1&parent_discoveryid=400590&context=host',
							'text_not_present' => 'Item prototype',
							'access_denied' => true,
							'text_present' => [
								'You are logged in as "Admin". You have no permissions to access this page.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => self::POPUP.'item.prototype.edit&context=template&itemid=50085',
							'text_not_present' => 'Item prototype',
							'fatal_error' => true,
							'text_present' => [
								'Controller: popup',
								'action: popup',
								'context: template',
								'itemid: 50085',
								'popup: item.prototype.edit'
							]
						],
						[
							'url' => self::POPUP.'item.prototype.edit&item.prototype.edit&itemid=400610&context=host',
							'text_not_present' => 'Item',
							'fatal_error' => true,
							'text_present' => [
								'Controller: popup',
								'action: popup',
								'context: host',
								'item_prototype_edit:',
								'itemid: 400610',
								'popup: item.prototype.edit'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Configuration of discovery prototypes',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'substitute_ids' => true,
					'test_cases' => [
						// Context = template, validate itemid.
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid={template_lldid}',
							'text_present' => 'Discovery prototypes'
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid=1&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid=abc&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid=&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid=-1&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "itemid" field.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid[]={template_lld_prototypeid}'.
									'&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&parent_discoveryid={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is mandatory.'
							]
						],
						// Context = template, validate parent_discoveryid.
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid=1',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid=abc',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid=',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid=-1',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "parent_discoveryid" field.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}'.
									'&parent_discoveryid[]={template_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=template&itemid={template_lld_prototypeid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						// Context = host, validate itemid.
						[
							'url' => 'host_discovery_prototypes.php?form=update&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid={host_lldid}&context=host',
							'text_present' => 'Discovery prototypes'
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&itemid=1&parent_discoveryid={host_lldid}&context=host',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid=abc&parent_discoveryid={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid=&parent_discoveryid={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid=-1&parent_discoveryid={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "itemid" field.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid[]={host_lld_prototypeid}'.
									'&parent_discoveryid={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&parent_discoveryid={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemid" is mandatory.'
							]
						],
						// Context = host, validate parent_discoveryid.
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid=1',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid=abc',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid=',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not integer.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid=-1',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "parent_discoveryid" field.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}'.
									'&parent_discoveryid[]={host_lldid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "parent_discoveryid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'host_discovery_prototypes.php?form=update&context=host&itemid={host_lld_prototypeid}',
							'text_not_present' => 'Discovery prototypes',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Configuration of network maps',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'sysmap.php?sysmapid=1',
							'text_present' => 'Network maps'
						],
						[
							'url' => 'sysmap.php?sysmapid=9999999',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'sysmap.php?sysmapid=abc',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not integer.'
							]
						],
						[
							'url' => 'sysmap.php?sysmapid=',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not integer.'
							]
						],
						[
							'url' => 'sysmap.php?sysmapid=-1',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "sysmapid" field.'
							]
						],
						[
							'url' => 'sysmap.php?sysmapid[]=1',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'sysmap.php',
							'text_not_present' => 'Network maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is mandatory.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Details of web scenario',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'httpdetails.php?httptestid=94',
							'text_present' => 'Details of web scenario'
						],
						[
							'url' => 'httpdetails.php?httptestid=9999999',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'httpdetails.php?httptestid=abc',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "httptestid" is not integer.'
							]
						],
						[
							'url' => 'httpdetails.php?httptestid=',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "httptestid" is not integer.'
							]
						],
						[
							'url' => 'httpdetails.php?httptestid=-1',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Incorrect value "-1" for "httptestid" field.'
							]
						],
						[
							'url' => 'httpdetails.php?httptestid[]=1',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "httptestid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'httpdetails.php',
							'text_not_present' => 'Details of web scenario',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "httptestid" is mandatory.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Latest data',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=4&hostids[]=50009',
							'text_present' => 'Latest data'
						],
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=9999999&hostids[]=50009',
							'text_present' => 'Latest data'
						],
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=4&hostids[]=9999999',
							'text_present' => 'Latest data'
						],
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=abc&hostids[]=abc',
							'text_not_present' => 'Latest data',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "groupids" field.',
								'Incorrect value for "hostids" field.'
							]
						],
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=&hostids[]=',
							'text_not_present' => 'Latest data',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "groupids" field.',
								'Incorrect value for "hostids" field.'
							]
						],
						[
							'url' => 'zabbix.php?action=latest.view&groupids[]=-1&hostids[]=-1',
							'text_not_present' => 'Latest data',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "groupids" field.',
								'Incorrect value for "hostids" field.'
							]
						],
						[
							'url' => 'zabbix.php?action=latest.view',
							'text_present' => 'Latest data'
						],
						[
							'url' => 'zabbix.php?action[]=latest.view',
							'text_not_present' => 'Latest data',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for field "action": a character string is expected.'
							]
						]
					]
				]
			],
			[
				[
					'title' => '404 Not Found',
					'check_server_name' => false,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'events.php',
							'text_not_present' => 'Events',
							'text_present' => [
								'Not Found'
							]
						],
						[
							'url' => 'events.php?triggerid=13491',
							'text_not_present' => 'Events',
							'text_present' => [
								'Not Found'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Event details',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'tr_events.php?triggerid=99251&eventid=93',
							'text_present' => 'Event details'
						],
						[
							'url' => 'tr_events.php?triggerid=1&eventid=1',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'tr_events.php?triggerid[]=1&eventid[]=1',
							'text_not_present' => 'Event details',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "triggerid" is not correct: invalid data type.',
								'Field "eventid" is not correct: invalid data type.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Problems',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=problem.view',
							'text_present' => 'Problems'
						],
						[
							'url' => 'zabbix.php?action=problem.view&filter_triggerids[]=13491',
							'text_present' => 'Problems'
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => false,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=abc',
							'text_not_present' => 'Problems',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: problem.view'
								]
						],
						[
							'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=',
							'text_not_present' => 'Problems',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: problem.view'
							]
						],
						[
							'url' => 'zabbix.php?action=problem.view&triggerids%5B%5D=-1',
							'text_not_present' => 'Problems',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: problem.view'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Custom graphs',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=66666&filter_show=2&filter_set=1',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=99012&filter_hostids%5B%5D=66666&'.
									'filter_show=1&filter_set=1',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=50011&filter_hostids%5B%5D=66666&'.
							'filter_name=2_item&filter_show=0&filter_set=1',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=abc&filter_show=1&filter_set=1',
							'text_not_present' => 'Graphs',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "filter_hostids" field.'
							]
						],
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D=-1&filter_show=1&filter_set=1',
							'text_not_present' => 'Graphs',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "filter_hostids" field.'
							]
						],
						[
							'url' => 'zabbix.php?action=charts.view&filter_hostids=1&filter_show[]=1&filter_set[]=1',
							'text_not_present' => 'Graphs',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "filter_set" field.',
								'Incorrect value "1" for "filter_hostids" field.',
								'Incorrect value for "filter_show" field.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'History [refreshed every 30 sec.]',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'history.php?action=showgraph&itemids%5B%5D=66666',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'history.php?action=showgraph&itemids%5B%5D=',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemids" is not integer.'
							]
						],
						[
							'url' => 'history.php?action=showgraph&itemids=1',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemids" is not correct: an array is expected.'
							]
						],
						[
							'url' => 'history.php?action=showgraph&itemids%5B%5D=abc',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "itemids" is not integer.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Configuration of network maps',
					'check_server_name' => true,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'sysmaps.php?sysmapid=1&severity_min=0',
							'text_present' => 'Maps'
						],
						[
							'url' => 'sysmaps.php?sysmapid=9999999&severity_min=0',
							'text_not_present' => 'Maps',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=1&severity_min=6',
							'text_present' => [
								'Page received incorrect data',
								'Incorrect value "6" for "severity_min" field.'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=1&severity_min=-1',
							'text_present' => [
								'Page received incorrect data',
								'Incorrect value "-1" for "severity_min" field.'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=-1&severity_min=0',
							'text_not_present' => 'Maps',
							'text_present' => [
								'No permissions to referred object or it does not exist!'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=abc&severity_min=abc',
							'text_not_present' => 'Maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not integer.',
								'Field "severity_min" is not integer.'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=&severity_min=',
							'text_not_present' => 'Maps',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not integer.',
								'Field "severity_min" is not integer.'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid[]=1&severity_min=0',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "sysmapid" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'sysmaps.php?sysmapid=1&severity_min[]=0',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "severity_min" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'zabbix.php?action=map.view&sysmapid[]=1',
							'text_not_present' => 'Maps',
							'fatal_error' => true,
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "sysmapid" field.',
								'Controller: map.view'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Status of discovery',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=3&filter_set=1',
							'text_present' => 'Status of discovery'
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=3',
							'text_present' => 'Status of discovery'
						],
						[
							'url' => 'zabbix.php?action=discovery.view',
							'text_present' => 'Status of discovery'
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_rst=1',
							'text_present' => 'Status of discovery'
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => false,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action[]=dashboard.list',
							'text_not_present' => 'Dashboards',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for field "action": a character string is expected.'
							]
						],
						[
							'url' => 'zabbix.php?action[]=dashboard.view',
							'text_not_present' => 'Dashboards',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for field "action": a character string is expected.'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Fatal error, please report to the Zabbix team',
					'check_server_name' => false,
					'server_name_on_page' => false,
					'test_cases' => [
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=abc',
							'text_not_present' => 'Status of discovery',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: discovery.view'
							]
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids[]=-123',
							'text_not_present' => 'Status of discovery',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: discovery.view'
							]
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids=123',
							'text_not_present' => 'Status of discovery',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: discovery.view'
							]
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_druleids=',
							'text_not_present' => 'Status of discovery',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Controller: discovery.view'
							]
						],
						[
							'url' => 'zabbix.php?action=discovery.view&filter_rst[]=1',
							'text_not_present' => 'Status of discovery',
							'text_present' => [
								'Fatal error, please report to the Zabbix team',
								'Incorrect value for "filter_rst" field.',
								'Controller: discovery.view'
							]
						]
					]
				]
			],
			[
				[
					'title' => 'Host inventory overview',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'hostinventoriesoverview.php?groupby=&filter_set=1',
							'text_present' => 'Host inventory overview'
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groupby=alias&filter_set=1',
							'text_present' => 'Host inventory overview'
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=abc&filter_groupby=&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Field "filter_groups" is not integer.'
							]
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=&filter_groupby=&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Field "filter_groups" is not integer.'
							]
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=-1&filter_groupby=&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Incorrect value for "filter_groups" field.'
							]
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groups=1&filter_groupby[]=&filter_set[]=1',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "filter_set" is not correct: invalid data type.',
								'Field "filter_groups" is not correct: an array is expected.',
								'Field "filter_groupby" is not correct: invalid data type.'
							]
						],
						[
							'url' => 'hostinventoriesoverview.php?filter_groups%5B%5D=9999999&filter_groupby=&filter_set=1',
							'text_present' => 'Host inventory overview'
						],
						[
							'url' => 'hostinventoriesoverview.php',
							'text_present' => 'Host inventory overview'
						]
					]
				]
			],
			[
				[
					'title' => 'Host inventory',
					'check_server_name' => true,
					'server_name_on_page' => true,
					'test_cases' => [
						[
							'url' => 'hostinventories.php?filter_groups%5B%5D=4&filter_set=1',
							'text_present' => 'Host inventory'
						],
						[
							'url' => 'hostinventories.php?filter_groups%5B%5D=9999999&filter_set=1',
							'text_present' => [
								'text_present' => 'type here to search'
							]
						],
						[
							'url' => 'hostinventories.php?filter_groups%5B%5D=abc&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Field "filter_groups" is not integer.'
							]
						],
						[
							'url' => 'hostinventories.php?filter_groups%5B%5D=&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Field "filter_groups" is not integer.'
							]
						],
						[
							'url' => 'hostinventories.php?filter_groups%5B%5D=-1&filter_set=1',
							'text_present' => [
								'Page received incorrect data',
								'Incorrect value for "filter_groups" field.'
							]
						],
						[
							'url' => 'hostinventories.php?filter_groups=1&filter_set[]=1',
							'text_present' => [
								'Zabbix has received an incorrect request.',
								'Field "filter_set" is not correct: invalid data type.',
								'Field "filter_groups" is not correct: an array is expected.'
							]
						],
						[
							'url' => 'hostinventories.php',
							'text_present' => 'Host inventory'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider data
	 *
	 * @ignoreBrowserErrors
	 */
	public function testUrlParameters_UrlLoad($data) {
		foreach ($data['test_cases'] as $test_case) {
			if (CTestArrayHelper::get($data, 'substitute_ids')) {
				foreach (['host_lldid', 'host_lld_prototypeid', 'template_lldid', 'template_lld_prototypeid'] as $id) {
					if (strpos($test_case['url'], $id)) {
						$test_case['url'] = str_replace('{'.$id.'}', self::$ids[$id], $test_case['url']);
					}
				}
			}

			$this->page->login()->open($test_case['url'], $data['server_name_on_page'])->waitUntilReady();
			if (array_key_exists('fatal_error', $test_case)) {
				$this->zbxTestCheckTitle('Fatal error, please report to the Zabbix team', false);
			}
			elseif (array_key_exists('access_denied', $test_case)) {
				$this->zbxTestCheckTitle('Warning [refreshed every 30 sec.]', false);
			}
			else {
				$this->zbxTestCheckTitle($data['title'], $data['check_server_name']);
			}

			$this->zbxTestTextPresent($test_case['text_present']);

			if (isset($test_case['text_not_present'])) {
				$this->zbxTestHeaderNotPresent($test_case['text_not_present']);
			}
		}
	}
}
