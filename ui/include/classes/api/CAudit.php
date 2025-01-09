<?php declare(strict_types = 0);
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


/**
 * Class to log audit records.
 */
class CAudit {

	/**
	 * Audit actions.
	 *
	 * @var int
	 */
	public const ACTION_ADD = 0;
	public const ACTION_UPDATE = 1;
	public const ACTION_DELETE = 2;
	public const ACTION_LOGOUT = 4;
	public const ACTION_EXECUTE = 7;
	public const ACTION_LOGIN_SUCCESS = 8;
	public const ACTION_LOGIN_FAILED = 9;
	public const ACTION_HISTORY_CLEAR = 10;
	public const ACTION_CONFIG_REFRESH = 11;
	public const ACTION_PUSH = 12;

	/**
	 * Audit resources.
	 *
	 * @var int
	 */
	public const RESOURCE_USER = 0;
	public const RESOURCE_MEDIA_TYPE = 3;
	public const RESOURCE_HOST = 4;
	public const RESOURCE_ACTION = 5;
	public const RESOURCE_GRAPH = 6;
	public const RESOURCE_USER_GROUP = 11;
	public const RESOURCE_TRIGGER = 13;
	public const RESOURCE_HOST_GROUP = 14;
	public const RESOURCE_ITEM = 15;
	public const RESOURCE_IMAGE = 16;
	public const RESOURCE_VALUE_MAP = 17;
	public const RESOURCE_IT_SERVICE = 18;
	public const RESOURCE_MAP = 19;
	public const RESOURCE_SCENARIO = 22;
	public const RESOURCE_DISCOVERY_RULE = 23;
	public const RESOURCE_SCRIPT = 25;
	public const RESOURCE_PROXY = 26;
	public const RESOURCE_MAINTENANCE = 27;
	public const RESOURCE_REGEXP = 28;
	public const RESOURCE_MACRO = 29;
	public const RESOURCE_TEMPLATE = 30;
	public const RESOURCE_TRIGGER_PROTOTYPE = 31;
	public const RESOURCE_ICON_MAP = 32;
	public const RESOURCE_DASHBOARD = 33;
	public const RESOURCE_CORRELATION = 34;
	public const RESOURCE_GRAPH_PROTOTYPE = 35;
	public const RESOURCE_ITEM_PROTOTYPE = 36;
	public const RESOURCE_HOST_PROTOTYPE = 37;
	public const RESOURCE_AUTOREGISTRATION = 38;
	public const RESOURCE_MODULE = 39;
	public const RESOURCE_SETTINGS = 40;
	public const RESOURCE_HOUSEKEEPING = 41;
	public const RESOURCE_AUTHENTICATION = 42;
	public const RESOURCE_TEMPLATE_DASHBOARD = 43;
	public const RESOURCE_USER_ROLE = 44;
	public const RESOURCE_AUTH_TOKEN = 45;
	public const RESOURCE_SCHEDULED_REPORT = 46;
	public const RESOURCE_HA_NODE = 47;
	public const RESOURCE_SLA = 48;
	public const RESOURCE_USERDIRECTORY = 49;
	public const RESOURCE_TEMPLATE_GROUP = 50;
	public const RESOURCE_CONNECTOR = 51;
	public const RESOURCE_LLD_RULE = 52;
	public const RESOURCE_HISTORY = 53;
	public const RESOURCE_MFA = 54;
	public const RESOURCE_PROXY_GROUP = 55;

	/**
	 * Audit details actions.
	 *
	 * @var string
	 */
	public const DETAILS_ACTION_ADD = 'add';
	public const DETAILS_ACTION_UPDATE = 'update';
	public const DETAILS_ACTION_DELETE = 'delete';

	/**
	 * Auditlog enabled value.
	 *
	 * @var int
	 */
	private const AUDITLOG_ENABLE = 1;

	/**
	 * Table names of audit resources.
	 * resource => table name
	 *
	 * @var array
	 */
	private const TABLE_NAMES = [
		self::RESOURCE_ACTION => 'actions',
		self::RESOURCE_AUTHENTICATION => 'config',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'config',
		self::RESOURCE_CONNECTOR => 'connector',
		self::RESOURCE_CORRELATION => 'correlation',
		self::RESOURCE_DASHBOARD => 'dashboard',
		self::RESOURCE_LLD_RULE => 'items',
		self::RESOURCE_HOST => 'hosts',
		self::RESOURCE_HOST_GROUP => 'hstgrp',
		self::RESOURCE_HOST_PROTOTYPE => 'hosts',
		self::RESOURCE_HOUSEKEEPING => 'config',
		self::RESOURCE_ICON_MAP => 'icon_map',
		self::RESOURCE_IMAGE => 'images',
		self::RESOURCE_ITEM => 'items',
		self::RESOURCE_ITEM_PROTOTYPE => 'items',
		self::RESOURCE_IT_SERVICE => 'services',
		self::RESOURCE_MACRO => 'globalmacro',
		self::RESOURCE_MAINTENANCE => 'maintenances',
		self::RESOURCE_MEDIA_TYPE => 'media_type',
		self::RESOURCE_MFA => 'mfa',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_PROXY => 'proxy',
		self::RESOURCE_PROXY_GROUP => 'proxy_group',
		self::RESOURCE_REGEXP => 'regexps',
		self::RESOURCE_SCENARIO => 'httptest',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_SCRIPT => 'scripts',
		self::RESOURCE_SETTINGS => 'config',
		self::RESOURCE_SLA => 'sla',
		self::RESOURCE_TEMPLATE => 'hosts',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'dashboard',
		self::RESOURCE_TEMPLATE_GROUP => 'hstgrp',
		self::RESOURCE_USER => 'users',
		self::RESOURCE_USERDIRECTORY => 'userdirectory',
		self::RESOURCE_USER_GROUP => 'usrgrp'
	];

	/**
	 * ID field names of audit resources.
	 * resource => ID field name
	 *
	 * @var array
	 */
	private const ID_FIELD_NAMES = [
		self::RESOURCE_PROXY => 'proxyid',
		self::RESOURCE_TEMPLATE => 'templateid'
	];

	/**
	 * Name field names of audit resources.
	 * resource => name field
	 *
	 * @var array
	 */
	private const FIELD_NAMES = [
		self::RESOURCE_ACTION => 'name',
		self::RESOURCE_AUTHENTICATION => null,
		self::RESOURCE_AUTH_TOKEN => 'name',
		self::RESOURCE_AUTOREGISTRATION => null,
		self::RESOURCE_CONNECTOR => 'name',
		self::RESOURCE_CORRELATION => 'name',
		self::RESOURCE_DASHBOARD => 'name',
		self::RESOURCE_LLD_RULE => 'name',
		self::RESOURCE_HOST => 'host',
		self::RESOURCE_HOST_GROUP => 'name',
		self::RESOURCE_HOST_PROTOTYPE => 'host',
		self::RESOURCE_HOUSEKEEPING => null,
		self::RESOURCE_ICON_MAP => 'name',
		self::RESOURCE_IMAGE => 'name',
		self::RESOURCE_ITEM => 'name',
		self::RESOURCE_ITEM_PROTOTYPE => 'name',
		self::RESOURCE_IT_SERVICE => 'name',
		self::RESOURCE_MACRO => 'macro',
		self::RESOURCE_MAINTENANCE => 'name',
		self::RESOURCE_MEDIA_TYPE => 'name',
		self::RESOURCE_MFA => 'name',
		self::RESOURCE_MODULE => 'id',
		self::RESOURCE_PROXY => 'name',
		self::RESOURCE_PROXY_GROUP => 'name',
		self::RESOURCE_REGEXP => 'name',
		self::RESOURCE_SCENARIO => 'name',
		self::RESOURCE_SCHEDULED_REPORT => 'name',
		self::RESOURCE_SCRIPT => 'name',
		self::RESOURCE_SETTINGS => null,
		self::RESOURCE_SLA => 'name',
		self::RESOURCE_TEMPLATE => 'host',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'name',
		self::RESOURCE_TEMPLATE_GROUP => 'name',
		self::RESOURCE_USER => 'username',
		self::RESOURCE_USERDIRECTORY => 'name',
		self::RESOURCE_USER_GROUP => 'name'
	];

	/**
	 * API names of audit resources.
	 * resource => API name
	 *
	 * @var array
	 */
	private const API_NAMES = [
		self::RESOURCE_ACTION => 'action',
		self::RESOURCE_AUTHENTICATION => 'authentication',
		self::RESOURCE_AUTH_TOKEN => 'token',
		self::RESOURCE_AUTOREGISTRATION => 'autoregistration',
		self::RESOURCE_CONNECTOR => 'connector',
		self::RESOURCE_CORRELATION => 'correlation',
		self::RESOURCE_DASHBOARD => 'dashboard',
		self::RESOURCE_LLD_RULE => 'discoveryrule',
		self::RESOURCE_HOST => 'host',
		self::RESOURCE_HOST_GROUP => 'hostgroup',
		self::RESOURCE_HOST_PROTOTYPE => 'hostprototype',
		self::RESOURCE_HOUSEKEEPING => 'housekeeping',
		self::RESOURCE_ICON_MAP => 'iconmap',
		self::RESOURCE_IMAGE => 'image',
		self::RESOURCE_ITEM => 'item',
		self::RESOURCE_ITEM_PROTOTYPE => 'itemprototype',
		self::RESOURCE_IT_SERVICE => 'service',
		self::RESOURCE_MACRO => 'usermacro',
		self::RESOURCE_MAINTENANCE => 'maintenance',
		self::RESOURCE_MEDIA_TYPE => 'mediatype',
		self::RESOURCE_MFA => 'mfa',
		self::RESOURCE_MODULE => 'module',
		self::RESOURCE_PROXY => 'proxy',
		self::RESOURCE_PROXY_GROUP => 'proxygroup',
		self::RESOURCE_REGEXP => 'regexp',
		self::RESOURCE_SCHEDULED_REPORT => 'report',
		self::RESOURCE_SCRIPT => 'script',
		self::RESOURCE_SETTINGS => 'settings',
		self::RESOURCE_SLA => 'sla',
		self::RESOURCE_TEMPLATE => 'template',
		self::RESOURCE_TEMPLATE_DASHBOARD => 'templatedashboard',
		self::RESOURCE_TEMPLATE_GROUP => 'templategroup',
		self::RESOURCE_USER => 'user',
		self::RESOURCE_USERDIRECTORY => 'userdirectory',
		self::RESOURCE_USER_GROUP => 'usergroup'
	];

	/**
	 * Array of abstract paths that should be masked in audit details.
	 *
	 * @var array
	 */
	private const MASKED_PATHS = [
		self::RESOURCE_AUTH_TOKEN => ['paths' => ['token.token']],
		self::RESOURCE_AUTOREGISTRATION => [
			'paths' => ['autoregistration.tls_psk_identity', 'autoregistration.tls_psk']
		],
		self::RESOURCE_CONNECTOR => [
			[
				'paths' => ['connector.password'],
				'conditions' => [
					'authtype' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
						ZBX_HTTP_AUTH_DIGEST
					]
				]
			],
			[
				'paths' => ['connector.token'],
				'conditions' => ['authtype' => ZBX_HTTP_AUTH_BEARER]
			],
			['paths' => ['connector.ssl_key_password']]
		],
		self::RESOURCE_LLD_RULE => [
			[
				'paths' => ['discoveryrule.password'],
				'conditions' => [
					[
						'type' => [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
							ITEM_TYPE_JMX
						]
					],
					[
						'type' => ITEM_TYPE_HTTPAGENT,
						'authtype' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
							ZBX_HTTP_AUTH_DIGEST
						]
					]
				]
			],
			['paths' => ['discoveryrule.ssl_key_password'], 'conditions' => ['type' => ITEM_TYPE_HTTPAGENT]]
		],
		self::RESOURCE_HOST_PROTOTYPE => [
			'paths' => ['hostprototype.macros.value'],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		self::RESOURCE_ITEM => [
			[
				'paths' => ['item.password'],
				'conditions' => [
					[
						'type' => [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
							ITEM_TYPE_JMX
						]
					],
					[
						'type' => ITEM_TYPE_HTTPAGENT,
						'authtype' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
							ZBX_HTTP_AUTH_DIGEST
						]
					]
				]
			],
			['paths' => ['item.ssl_key_password'], 'conditions' => ['type' => ITEM_TYPE_HTTPAGENT]]
		],
		self::RESOURCE_ITEM_PROTOTYPE => [
			[
				'paths' => ['itemprototype.password'],
				'conditions' => [
					[
						'type' => [ITEM_TYPE_SIMPLE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
							ITEM_TYPE_JMX
						]
					],
					[
						'type' => ITEM_TYPE_HTTPAGENT,
						'authtype' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
							ZBX_HTTP_AUTH_DIGEST
						]
					]
				]
			],
			['paths' => ['itemprototype.ssl_key_password'], 'conditions' => ['type' => ITEM_TYPE_HTTPAGENT]]
		],
		self::RESOURCE_MACRO => [
			'paths' => ['usermacro.value'],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		self::RESOURCE_MEDIA_TYPE => [
			'paths' => ['mediatype.passwd'],
			'conditions' => [
				[
					'provider' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP, CMediatypeHelper::EMAIL_PROVIDER_GMAIL_RELAY,
						CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY
					],
					'smtp_authentication' => SMTP_AUTHENTICATION_NORMAL
				],
				[
					'provider' => [CMediatypeHelper::EMAIL_PROVIDER_GMAIL, CMediatypeHelper::EMAIL_PROVIDER_OFFICE365]
				]
			]
		],
		self::RESOURCE_MFA => ['paths' => ['mfa.client_secret']],
		self::RESOURCE_PROXY => ['paths' => ['proxy.tls_psk_identity', 'proxy.tls_psk']],
		self::RESOURCE_SCRIPT => ['paths' => ['script.password']],
		self::RESOURCE_TEMPLATE => [
			'paths' => ['template.macros.value'],
			'conditions' => ['type' => ZBX_MACRO_TYPE_SECRET]
		],
		self::RESOURCE_USER => ['paths' => ['user.passwd']],
		self::RESOURCE_USERDIRECTORY => ['paths' => ['userdirectory.bind_password']]
	];

	/**
	 * Table names of nested objects to check default values.
	 * abstract path => table name
	 *
	 * @var array
	 */
	private const NESTED_OBJECTS_TABLE_NAMES = [
		'action.filter' => 'actions',
		'action.filter.conditions' => 'conditions',
		'action.operations' => 'operations',
		'action.operations.opconditions' => 'opconditions',
		'action.operations.opmessage' => 'opmessage',
		'action.operations.opmessage_grp' => 'opmessage_grp',
		'action.operations.opmessage_usr' => 'opmessage_usr',
		'action.operations.opcommand' => 'opcommand',
		'action.operations.opcommand_grp' => 'opcommand_grp',
		'action.operations.opcommand_hst' => 'opcommand_hst',
		'action.operations.opgroup' => 'opgroup',
		'action.operations.optemplate' => 'optemplate',
		'action.operations.opinventory' => 'opinventory',
		'action.operations.optag' => 'optag',
		'action.recovery_operations' => 'operations',
		'action.recovery_operations.opmessage' => 'opmessage',
		'action.recovery_operations.opmessage_grp' => 'opmessage_grp',
		'action.recovery_operations.opmessage_usr' => 'opmessage_usr',
		'action.recovery_operations.opcommand' => 'opcommand',
		'action.recovery_operations.opcommand_grp' => 'opcommand_grp',
		'action.recovery_operations.opcommand_hst' => 'opcommand_hst',
		'action.update_operations' => 'operations',
		'action.update_operations.opmessage' => 'opmessage',
		'action.update_operations.opmessage_grp' => 'opmessage_grp',
		'action.update_operations.opmessage_usr' => 'opmessage_usr',
		'action.update_operations.opcommand' => 'opcommand',
		'action.update_operations.opcommand_grp' => 'opcommand_grp',
		'action.update_operations.opcommand_hst' => 'opcommand_hst',
		'connector.tags' => 'connector_tag',
		'correlation.filter' => 'correlation',
		'correlation.filter.conditions' => 'corr_condition',
		'correlation.operations' => 'corr_operation',
		'dashboard.users' => 'dashboard_user',
		'dashboard.userGroups' => 'dashboard_usrgrp',
		'dashboard.pages' => 'dashboard_page',
		'dashboard.pages.widgets' => 'widget',
		'dashboard.pages.widgets.fields' => 'widget_field',
		'discoveryrule.filter' => 'items',
		'discoveryrule.filter.conditions' => 'item_condition',
		'discoveryrule.lld_macro_paths' => 'lld_macro_path',
		'discoveryrule.overrides' => 'lld_override',
		'discoveryrule.overrides.filter' => 'lld_override',
		'discoveryrule.overrides.filter.conditions' => 'lld_override_condition',
		'discoveryrule.overrides.operations' => 'lld_override_operation',
		'discoveryrule.overrides.operations.opdiscover' => 'lld_override_opdiscover',
		'discoveryrule.overrides.operations.ophistory' => 'lld_override_ophistory',
		'discoveryrule.overrides.operations.opinventory' => 'lld_override_opinventory',
		'discoveryrule.overrides.operations.opperiod' => 'lld_override_opperiod',
		'discoveryrule.overrides.operations.opseverity' => 'lld_override_opseverity',
		'discoveryrule.overrides.operations.opstatus' => 'lld_override_opstatus',
		'discoveryrule.overrides.operations.optag' => 'lld_override_optag',
		'discoveryrule.overrides.operations.optemplate' => 'lld_override_optemplate',
		'discoveryrule.overrides.operations.optrends' => 'lld_override_optrends',
		'discoveryrule.parameters' => 'item_parameter',
		'discoveryrule.preprocessing' => 'item_preproc',
		'hostgroup.hosts' => 'hosts_groups',
		'hostprototype.groupLinks' => 'group_prototype',
		'hostprototype.groupPrototypes' => 'group_prototype',
		'hostprototype.interfaces' => 'interface',
		'hostprototype.interfaces.details' => 'interface_snmp',
		'hostprototype.macros' => 'hostmacro',
		'hostprototype.tags' => 'host_tag',
		'hostprototype.templates' => 'hosts_templates',
		'iconmap.mappings' => 'icon_mapping',
		'item.parameters' => 'item_parameter',
		'item.preprocessing' => 'item_preproc',
		'item.tags' => 'item_tag',
		'itemprototype.parameters' => 'item_parameter',
		'itemprototype.preprocessing' => 'item_preproc',
		'itemprototype.tags' => 'item_tag',
		'maintenance.groups' => 'maintenances_groups',
		'maintenance.hosts' => 'maintenances_hosts',
		'maintenance.tags' => 'maintenance_tag',
		'maintenance.timeperiods' => 'timeperiods',
		'mediatype.message_templates' => 'media_type_message',
		'mediatype.parameters' => 'media_type_param',
		'proxy.hosts' => 'hosts',
		'proxy.interface' => 'interface',
		'regexp.expressions' => 'expressions',
		'report.users' => 'report_user',
		'report.user_groups' => 'report_usrgrp',
		'service.children' => 'services_links',
		'service.parents' => 'services_links',
		'service.problem_tags' => 'service_problem_tag',
		'service.status_rules' => 'service_status_rule',
		'service.tags' => 'service_tag',
		'sla.service_tags' => 'sla_service_tag',
		'sla.schedule' => 'sla_schedule',
		'sla.excluded_downtimes' => 'sla_excluded_downtime',
		'script.parameters' => 'script_param',
		'template.groups' => 'hosts_groups',
		'template.macros' => 'hostmacro',
		'template.tags' => 'host_tag',
		'template.templates' => 'hosts_templates',
		'template.templates_clear' => 'hosts_templates',
		'templatedashboard.pages' => 'dashboard_page',
		'templatedashboard.pages.widgets' => 'widget',
		'templatedashboard.pages.widgets.fields' => 'widget_field',
		'templategroup.templates' => 'hosts_groups',
		'user.medias' => 'media',
		'user.usrgrps' => 'users_groups',
		'userdirectory.provision_media' => 'userdirectory_media',
		'userdirectory.provision_groups' => 'userdirectory_idpgroup',
		'userdirectory.provision_groups.user_groups' => 'userdirectory_usrgrp',
		'usergroup.hostgroup_rights' => 'rights',
		'usergroup.templategroup_rights' => 'rights',
		'usergroup.tag_filters' => 'tag_filter',
		'usergroup.users' => 'users_groups'
	];

	/**
	 * ID field names of nested objects that stored in a parent object properties containing an array of nested objects.
	 * abstract path => id field name
	 *
	 * @var array
	 */
	private const NESTED_OBJECTS_ID_FIELD_NAMES = [
		'action.filter.conditions' => 'conditionid',
		'action.operations' => 'operationid',
		'action.operations.opconditions' => 'opconditionid',
		'action.operations.opmessage_grp' => 'opmessage_grpid',
		'action.operations.opmessage_usr' => 'opmessage_usrid',
		'action.operations.opcommand_grp' => 'opcommand_grpid',
		'action.operations.opcommand_hst' => 'opcommand_hstid',
		'action.operations.opgroup' => 'opgroupid',
		'action.operations.optemplate' => 'optemplateid',
		'action.operations.optag' => 'optagid',
		'action.recovery_operations' => 'operationid',
		'action.recovery_operations.opmessage_grp' => 'opmessage_grpid',
		'action.recovery_operations.opmessage_usr' => 'opmessage_usrid',
		'action.recovery_operations.opcommand_grp' => 'opcommand_grpid',
		'action.recovery_operations.opcommand_hst' => 'opcommand_hstid',
		'action.update_operations' => 'operationid',
		'action.update_operations.opmessage_grp' => 'opmessage_grpid',
		'action.update_operations.opmessage_usr' => 'opmessage_usrid',
		'action.update_operations.opcommand_grp' => 'opcommand_grpid',
		'action.update_operations.opcommand_hst' => 'opcommand_hstid',
		'connector.tags' => 'connector_tagid',
		'correlation.filter.conditions' => 'corr_conditionid',
		'correlation.operations' => 'corr_operationid',
		'dashboard.users' => 'dashboard_userid',
		'dashboard.userGroups' => 'dashboard_usrgrpid',
		'dashboard.pages' => 'dashboard_pageid',
		'dashboard.pages.widgets' => 'widgetid',
		'dashboard.pages.widgets.fields' => 'widget_fieldid',
		'discoveryrule.filter.conditions' => 'item_conditionid',
		'discoveryrule.headers' => 'sortorder',
		'discoveryrule.lld_macro_paths' => 'lld_macro_pathid',
		'discoveryrule.overrides' => 'lld_overrideid',
		'discoveryrule.overrides.filter.conditions' => 'lld_override_conditionid',
		'discoveryrule.overrides.operations' => 'lld_override_operationid',
		'discoveryrule.overrides.operations.optag' => 'lld_override_optagid',
		'discoveryrule.overrides.operations.optemplate' => 'lld_override_optemplateid',
		'discoveryrule.parameters' => 'item_parameterid',
		'discoveryrule.preprocessing' => 'item_preprocid',
		'discoveryrule.query_fields' => 'sortorder',
		'hostgroup.hosts' => 'hostgroupid',
		'hostprototype.groupLinks' => 'group_prototypeid',
		'hostprototype.groupPrototypes' => 'group_prototypeid',
		'hostprototype.interfaces' => 'interfaceid',
		'hostprototype.macros' => 'hostmacroid',
		'hostprototype.tags' => 'hosttagid',
		'hostprototype.templates' => 'hosttemplateid',
		'iconmap.mappings' => 'iconmappingid',
		'item.headers' => 'sortorder',
		'item.parameters' => 'item_parameterid',
		'item.preprocessing' => 'item_preprocid',
		'item.tags' => 'itemtagid',
		'item.query_fields' => 'sortorder',
		'itemprototype.headers' => 'sortorder',
		'itemprototype.parameters' => 'item_parameterid',
		'itemprototype.preprocessing' => 'item_preprocid',
		'itemprototype.tags' => 'itemtagid',
		'itemprototype.query_fields' => 'sortorder',
		'maintenance.groups' => 'maintenance_groupid',
		'maintenance.hosts' => 'maintenance_hostid',
		'maintenance.tags' => 'maintenancetagid',
		'maintenance.timeperiods' => 'timeperiodid',
		'mediatype.message_templates' => 'mediatype_messageid',
		'mediatype.parameters' => 'mediatype_paramid',
		'proxy.hosts' => 'hostid',
		'regexp.expressions' => 'expressionid',
		'report.users' => 'reportuserid',
		'report.user_groups' => 'reportusrgrpid',
		'script.parameters' => 'script_paramid',
		'service.children' => 'linkid',
		'service.parents' => 'linkid',
		'service.problem_tags' => 'service_problem_tagid',
		'service.status_rules' => 'service_status_ruleid',
		'service.tags' => 'servicetagid',
		'sla.service_tags' => 'sla_service_tagid',
		'sla.schedule' => 'sla_scheduleid',
		'sla.excluded_downtimes' => 'sla_excluded_downtimeid',
		'template.groups' => 'hostgroupid',
		'template.macros' => 'hostmacroid',
		'template.tags' => 'hosttagid',
		'template.templates' => 'hosttemplateid',
		'template.templates_clear' => 'hosttemplateid',
		'templatedashboard.pages' => 'dashboard_pageid',
		'templatedashboard.pages.widgets' => 'widgetid',
		'templatedashboard.pages.widgets.fields' => 'widget_fieldid',
		'templategroup.templates' => 'hostgroupid',
		'user.medias' => 'mediaid',
		'user.usrgrps' => 'id',
		'userdirectory.provision_media' => 'userdirectory_mediaid',
		'userdirectory.provision_groups' => 'userdirectory_idpgroupid',
		'userdirectory.provision_groups.user_groups' => 'userdirectory_usrgrpid',
		'usergroup.hostgroup_rights' => 'rightid',
		'usergroup.templategroup_rights' => 'rightid',
		'usergroup.tag_filters' => 'tag_filterid',
		'usergroup.users' => 'id'
	];

	/**
	 * Array of abstract paths that should be skipped in audit details.
	 *
	 * @var array
	 */
	private const SKIP_FIELDS = ['token.creator_userid', 'token.created_at'];

	/**
	 * Array of abstract paths that contain blob fields.
	 *
	 * @var array
	 */
	private const BLOB_FIELDS = ['image.image'];

	/**
	 * Array of abstract paths that can only contain a data to delete.
	 */
	private const DELETE_ONLY_FIELDS = ['template.templates_clear'];

	/**
	 * Add audit records.
	 *
	 * @param string|null $userid
	 * @param string      $ip
	 * @param string      $username
	 * @param int         $action      CAudit::ACTION_*
	 * @param int         $resource    CAudit::RESOURCE_*
	 * @param array       $objects
	 * @param array       $db_objects
	 */
	public static function log(?string $userid, string $ip, string $username, int $action, int $resource,
			array $objects, array $db_objects): void {
		if (!self::isAuditEnabled() && ($resource != self::RESOURCE_SETTINGS
					|| !array_key_exists(CSettingsHelper::AUDITLOG_ENABLED, current($objects)))) {
			return;
		}

		$auditlog = [];
		$clock = time();
		$ip = substr($ip, 0, DB::getFieldLength('auditlog', 'ip'));
		$recordsetid = self::getRecordSetId();

		switch ($action) {
			case self::ACTION_LOGOUT:
			case self::ACTION_LOGIN_SUCCESS:
			case self::ACTION_LOGIN_FAILED:
				$auditlog[] = [
					'userid' => $userid,
					'username' => $username,
					'clock' => $clock,
					'ip' => $ip,
					'action' => $action,
					'resourcetype' => $resource,
					'resourceid' => $userid,
					'resourcename' => '',
					'recordsetid' => $recordsetid,
					'details' => ''
				];
				break;

			default:
				$table_key = array_key_exists($resource, self::ID_FIELD_NAMES)
					? self::ID_FIELD_NAMES[$resource]
					: DB::getPk(self::TABLE_NAMES[$resource]);

				foreach ($objects as $object) {
					$resourceid = $object[$table_key];
					$db_object = ($action == self::ACTION_UPDATE) ? $db_objects[$resourceid] : [];
					$resource_name = self::getResourceName($resource, $action, $object, $db_object);

					$diff = self::handleObjectDiff($resource, $action, $object, $db_object);

					if ($action == self::ACTION_UPDATE && count($diff) === 0) {
						continue;
					}

					$auditlog[] = [
						'userid' => $userid,
						'username' => $username,
						'clock' => $clock,
						'ip' => $ip,
						'action' => $action,
						'resourcetype' => $resource,
						'resourceid' => $resourceid,
						'resourcename' => $resource_name,
						'recordsetid' => $recordsetid,
						'details' => (count($diff) == 0) ? '' : json_encode($diff)
					];
				}
		}

		DB::insertBatch('auditlog', $auditlog);
	}

	/**
	 * Return recordsetid. Generate recordsetid if it has not been generated yet.
	 *
	 * @return string
	 */
	private static function getRecordSetId(): string {
		static $recordsetid = null;

		if ($recordsetid === null) {
			$recordsetid = CCuid::generate();
		}

		return $recordsetid;
	}

	/**
	 * Check audit logging is enabled.
	 *
	 * @return bool
	 */
	private static function isAuditEnabled(): bool {
		return CSettingsHelper::getPublic(CSettingsHelper::AUDITLOG_ENABLED) == self::AUDITLOG_ENABLE;
	}

	/**
	 * Return resource name of logging object.
	 *
	 * @param int   $resource
	 * @param int   $action
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return string
	 */
	private static function getResourceName(int $resource, int $action, array $object, array $db_object): string {
		$field_name = self::FIELD_NAMES[$resource];
		$resource_name = ($field_name !== null)
			? (($action == self::ACTION_UPDATE)
				? $db_object[$field_name]
				: $object[$field_name])
			: '';

		if (mb_strlen($resource_name) > 255) {
			$resource_name = mb_substr($resource_name, 0, 252).'...';
		}

		return $resource_name;
	}

	/**
	 * Prepares the details for audit log.
	 *
	 * @param int   $resource
	 * @param int   $action
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return array
	 */
	private static function handleObjectDiff(int $resource, int $action, array $object, array $db_object): array {
		if (!in_array($action, [self::ACTION_ADD, self::ACTION_UPDATE])) {
			return [];
		}

		$api_name = self::API_NAMES[$resource];
		$details = self::convertKeysToPaths($api_name, $object);

		switch ($action) {
			case self::ACTION_ADD:
				return self::handleAdd($resource, $details);

			case self::ACTION_UPDATE:
				$db_details = self::convertKeysToPaths($api_name,
					self::intersectObjects($api_name, $db_object, $object)
				);

				return self::handleUpdate($resource, $details, $db_details);
		}
	}

	/**
	 * Computes the intersection of $db_object and $object using keys for comparison.
	 * Recursively removes $db_object properties if they are not present in $object.
	 *
	 * @param string $path
	 * @param array  $db_object
	 * @param array  $object
	 *
	 * @return array
	 */
	private static function intersectObjects(string $path, array $db_object, array $object): array {
		foreach ($db_object as $db_key => &$db_value) {
			if (is_string($db_key) && !array_key_exists($db_key, $object)) {
				unset($db_object[$db_key]);
				continue;
			}

			if (!is_array($db_value)) {
				continue;
			}

			$key = $db_key;
			$subpath = $path.'.'.$db_key;

			if (is_int($db_key)) {
				$key = null;

				$pk = self::NESTED_OBJECTS_ID_FIELD_NAMES[$path];

				foreach ($object as $i => $nested_object) {
					if (bccomp($nested_object[$pk], $db_key) == 0) {
						$key = $i;
						$subpath = $path;
						break;
					}
				}

				if ($key === null) {
					continue;
				}
			}

			$db_value = self::intersectObjects($subpath, $db_value, $object[$key]);
		}
		unset($db_value);

		return $db_object;
	}

	/**
	 * Checks by path, whether the value of the object should be masked.
	 *
	 * @param int    $resource
	 * @param string $path
	 * @param array  $object
	 *
	 * @return bool
	 */
	private static function isValueToMask(int $resource, string $path, array $object): bool {
		if (!array_key_exists($resource, self::MASKED_PATHS)) {
			return false;
		}

		$object_path = self::getLastObjectPath($path);
		$abstract_path = self::getAbstractPath($path);

		$rules = [];

		if (array_key_exists('paths', self::MASKED_PATHS[$resource])) {
			if (in_array($abstract_path, self::MASKED_PATHS[$resource]['paths'])) {
				$rules = self::MASKED_PATHS[$resource];
			}
		}
		else {
			foreach (self::MASKED_PATHS[$resource] as $_rules) {
				if (in_array($abstract_path, $_rules['paths'])) {
					$rules = $_rules;
					break;
				}
			}
		}

		if (!$rules) {
			return false;
		}

		if (!array_key_exists('conditions', $rules)) {
			return true;
		}

		$or_conditions = $rules['conditions'];

		if (!array_key_exists(0, $or_conditions)) {
			$or_conditions = [$or_conditions];
		}

		foreach ($or_conditions as $and_conditions) {
			$all_conditions = count($and_conditions);
			$true_conditions = 0;

			foreach ($and_conditions as $condition_key => $value) {
				$condition_path = $object_path.'.'.$condition_key;

				if (array_key_exists($condition_path, $object)) {
					$values = is_array($value) ? $value : [$value];

					if (in_array($object[$condition_path], $values)) {
						$true_conditions++;
					}
				}
			}

			if ($true_conditions == $all_conditions) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Converts the object properties to the one-dimensional array where the key is a path.
	 *
	 * @param string $path    Path to object or to array of objects.
	 * @param array  $object  The object or array of objects to convert.
	 *
	 * @return array
	 */
	private static function convertKeysToPaths(string $path, array $object): array {
		$result = [];

		$is_field_of_another_object = strpos($path, '.') !== false && !preg_match('/\[[0-9]+\]$/', $path);
		$is_array_of_objects = false;

		if ($is_field_of_another_object) {
			$abstract_path = self::getAbstractPath($path);
			$is_array_of_objects = array_key_exists($abstract_path, self::NESTED_OBJECTS_ID_FIELD_NAMES);

			if ($is_array_of_objects) {
				$id_field_name = self::NESTED_OBJECTS_ID_FIELD_NAMES[$abstract_path];
			}
		}

		if ($is_array_of_objects) {
			$objects = $object;

			foreach ($objects as $object) {
				$path_to_object = $path.'['.$object[$id_field_name].']';

				$result += self::convertKeysToPaths($path_to_object, $object);
			}
		}
		else {
			foreach ($object as $field => $value) {
				$path_to_field = $path.'.'.$field;

				if (in_array(self::getAbstractPath($path_to_field), self::SKIP_FIELDS)) {
					continue;
				}

				if (is_array($value)) {
					$result += self::convertKeysToPaths($path_to_field, $value);
				}
				else {
					$result[$path_to_field] = (string) $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Checks by path, whether the value is equal to default value from the database schema.
	 *
	 * @param int     $resource
	 * @param string  $path
	 * @param string  $value
	 *
	 * @return bool
	 */
	private static function isDefaultValue(int $resource, string $path, string $value): bool {
		$object_path = self::getLastObjectPath($path);
		$table_name = self::TABLE_NAMES[$resource];

		if ($object_path !== self::API_NAMES[$resource]) {
			$abstract_object_path = self::getAbstractPath($object_path);

			if (!array_key_exists($abstract_object_path, self::NESTED_OBJECTS_TABLE_NAMES)) {
				return false;
			}

			$table_name = self::NESTED_OBJECTS_TABLE_NAMES[self::getAbstractPath($object_path)];
		}

		$schema_fields = DB::getSchema($table_name)['fields'];
		$field_name = substr($path, strrpos($path, '.') + 1);

		if (!array_key_exists($field_name, $schema_fields)) {
			return false;
		}

		if ($schema_fields[$field_name]['type'] & DB::FIELD_TYPE_ID && $schema_fields[$field_name]['null']
				&& $value == 0) {
			return true;
		}

		if (!array_key_exists('default', $schema_fields[$field_name])) {
			return false;
		}

		return $value == $schema_fields[$field_name]['default'];
	}

	/**
	 * Checks whether a path is path to nested object property.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private static function isNestedObjectProperty(string $path): bool {
		return (count(explode('.', $path)) > 2);
	}

	/**
	 * Return the path to the parent property object from the passed path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private static function getLastObjectPath(string $path): string {
		return substr($path, 0, strrpos($path, '.'));
	}

	/**
	 * Return the abstract path (without indexes).
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private static function getAbstractPath(string $path): string {
		if (strpos($path, '[') !== false) {
			$path = preg_replace('/\[[0-9]+\]/', '', $path);
		}

		return $path;
	}

	/**
	 * Return the paths to nested object properties from the paths of passing object.
	 *
	 * @param array $object
	 *
	 * @return array
	 */
	private static function getNestedObjectsPaths(array $object): array {
		$paths = [];

		foreach ($object as $path => $foo) {
			if (!self::isNestedObjectProperty($path)) {
				continue;
			}

			$object_path = self::getLastObjectPath($path);

			if (!in_array($object_path, $paths)) {
				$paths[] = $object_path;
			}
		}

		return $paths;
	}

	/**
	 * Prepares the audit details for add action.
	 *
	 * @param int   $resource
	 * @param array $object
	 *
	 * @return array
	 */
	private static function handleAdd(int $resource, array $object): array {
		$result = [];

		foreach ($object as $path => $value) {
			if (self::isNestedObjectProperty($path)) {
				$result[self::getLastObjectPath($path)] = [self::DETAILS_ACTION_ADD];
			}

			if (self::isValueToMask($resource, $path, $object)) {
				$result[$path] = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
				continue;
			}

			if (self::isDefaultValue($resource, $path, $value)) {
				continue;
			}

			if (in_array(self::getAbstractPath($path), self::BLOB_FIELDS)) {
				$result[$path] = [self::DETAILS_ACTION_ADD];
				continue;
			}

			$result[$path] = [self::DETAILS_ACTION_ADD, $value];
		}

		return $result;
	}

	/**
	 * Prepares the audit details for update action.
	 *
	 * @param int   $resource
	 * @param array $object
	 * @param array $db_object
	 *
	 * @return array
	 */
	private static function handleUpdate(int $resource, array $object, array $db_object): array {
		$result = [];
		$nested_objects_paths = self::getNestedObjectsPaths($object);
		$db_nested_objects_paths = self::getNestedObjectsPaths($db_object);

		foreach ($db_nested_objects_paths as $path) {
			if (!in_array($path, $nested_objects_paths)) {
				$result[$path] = [self::DETAILS_ACTION_DELETE];
			}
		}

		foreach ($nested_objects_paths as $path) {
			if (!in_array($path, $db_nested_objects_paths)) {
				if (in_array(self::getAbstractPath($path), self::DELETE_ONLY_FIELDS)) {
					$result[$path] = [self::DETAILS_ACTION_DELETE];

					foreach ($object as $object_path => $value) {
						if (substr($object_path, 0, strlen($path)) === $path) {
							unset($object[$object_path]);
						}
					}
				}
				else {
					$result[$path] = [self::DETAILS_ACTION_ADD];
				}
			}
		}

		foreach ($object as $path => $value) {
			$is_value_to_mask = self::isValueToMask($resource, $path, $object);
			$db_value = array_key_exists($path, $db_object) ? $db_object[$path] : null;

			if ($db_value === null) {
				$is_value_to_mask = self::isValueToMask($resource, $path, $object);

				if ($is_value_to_mask) {
					$result[$path] = [self::DETAILS_ACTION_ADD, ZBX_SECRET_MASK];
					continue;
				}

				if (self::isDefaultValue($resource, $path, $value)) {
					continue;
				}

				if (in_array(self::getAbstractPath($path), self::BLOB_FIELDS)) {
					$result[$path] = [self::DETAILS_ACTION_ADD];
					continue;
				}

				$result[$path] = [self::DETAILS_ACTION_ADD, $value];
			}
			else {
				$is_db_value_to_mask = self::isValueToMask($resource, $path, $db_object);

				if ($value != $db_value || $is_value_to_mask || $is_db_value_to_mask) {
					if (self::isNestedObjectProperty($path)) {
						$result[self::getLastObjectPath($path)] = [self::DETAILS_ACTION_UPDATE];
					}

					if (in_array(self::getAbstractPath($path), self::BLOB_FIELDS)) {
						$result[$path] = [self::DETAILS_ACTION_UPDATE];
						continue;
					}

					$result[$path] = [
						self::DETAILS_ACTION_UPDATE,
						$is_value_to_mask ? ZBX_SECRET_MASK : $value,
						$is_db_value_to_mask ? ZBX_SECRET_MASK : $db_value
					];
				}
			}
		}

		return $result;
	}
}
