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


require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/../../include/triggers.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/../../include/users.inc.php';
require_once dirname(__FILE__).'/../../include/js.inc.php';
require_once dirname(__FILE__).'/../../include/discovery.inc.php';

class CControllerPopupGeneric extends CController {

	/**
	 * Item types allowed to be used in 'help_items' dialog.
	 *
	 * @var array
	 */
	const ALLOWED_ITEM_TYPES = [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE, ITEM_TYPE_SNMPTRAP,
		ITEM_TYPE_INTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_IPMI, ITEM_TYPE_JMX
	];

	/**
	 * Popups having host group filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_HOST_GROUP_FILTER = ['hosts', 'host_templates'];

	/**
	 * Popups having template group filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_TEMPLATE_GROUP_FILTER = ['templates'];

	/**
	 * Popups having host filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_HOST_FILTER = ['items', 'item_prototypes', 'triggers', 'graphs', 'graph_prototypes'];

	/**
	 * Popups having template filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_TEMPLATE_FILTER = ['template_items', 'template_triggers'];

	/**
	 * General properties for supported dialog types.
	 *
	 * @var array
	 */
	private $popup_properties;

	/**
	 * Type of requested popup.
	 *
	 * @var string
	 */
	private $source_table;

	/**
	 * Host groups set in filter.
	 *
	 * @var array
	 */
	protected $groupids = [];

	/**
	 * Template groups set in filter.
	 *
	 * @var array
	 */
	protected $template_groupids = [];

	/**
	 * Hosts set in filter.
	 *
	 * @var array
	 */
	protected $hostids = [];

	/**
	 * Template set in filter.
	 *
	 * @var array
	 */
	protected $templateids = [];

	/**
	 * Either Host filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $host_preselect_required;

	/**
	 * Either template filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $template_preselect_required;

	/**
	 * Either Host group filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $group_preselect_required;

	/**
	 * Either template group filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $template_group_preselect_required;

	/**
	 * Set of disabled options.
	 *
	 * @var array
	 */
	protected $disableids = [];

	/**
	 * @var array
	 */
	private $page_options = [];

	protected function init() {
		$this->disableCsrfValidation();

		$this->popup_properties = [
			'hosts' => [
				'title' => _('Hosts'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'hostid,host',
				'form' => [
					'name' => 'hostform',
					'id' => 'hosts'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'templates' => [
				'title' => _('Templates'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'hostid,host',
				'form' => [
					'name' => 'templateform',
					'id' => 'templates'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'host_templates' => [
				'title' => _('Hosts'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'hostid,host',
				'form' => [
					'name' => 'hosttemplateform',
					'id' => 'hosts'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'host_groups' => [
				'title' => _('Host groups'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'groupid,name',
				'form' => [
					'name' => 'hostGroupsform',
					'id' => 'hostGroups'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'template_groups' => [
				'title' => _('Template groups'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'groupid,name',
				'form' => [
					'name' => 'templateGroupsform',
					'id' => 'templateGroups'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'proxies' => [
				'title' => _('Proxies'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'proxyid,name',
				'form' => [
					'name' => 'proxiesform',
					'id' => 'proxies'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'proxy_groups' => [
				'title' => _('Proxy groups'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'proxy_groupid,name',
				'form' => [
					'name' => 'proxy_groups_form',
					'id' => 'proxy-groups'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'triggers' => [
				'title' => _('Triggers'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'description,triggerid,expression',
				'form' => [
					'name' => 'triggerform',
					'id' => 'triggers'
				],
				'table_columns' => [
					_('Name'),
					_('Severity'),
					_('Status')
				]
			],
			'template_triggers' => [
				'title' => _('Triggers'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'description,triggerid,expression',
				'form' => [
					'name' => 'triggerform',
					'id' => 'triggers'
				],
				'table_columns' => [
					_('Name'),
					_('Severity'),
					_('Status')
				]
			],
			'trigger_prototypes' => [
				'title' => _('Trigger prototypes'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'description,triggerid,expression',
				'form' => [
					'name' => 'trigger_prototype_form',
					'id' => 'trigger_prototype'
				],
				'table_columns' => [
					_('Name'),
					_('Severity'),
					_('Status')
				]
			],
			'usrgrp' => [
				'title' => _('User groups'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'usrgrpid,name',
				'form' => [
					'name' => 'usrgrpform',
					'id' => 'usrgrps'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'users' => [
				'title' => _('Users'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'usergrpid,username,fullname,userid',
				'form' => [
					'name' => 'userform',
					'id' => 'users'
				],
				'table_columns' => [
					_('Username'),
					_x('Name', 'user first name'),
					_('Last name')
				]
			],
			'items' => [
				'title' => _('Items'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'itemid,name',
				'form' => [
					'name' => 'itemform',
					'id' => 'items'
				],
				'table_columns' => [
					(new CColHeader(_('Name')))->addStyle('width: 30%;'),
					(new CColHeader(_('Key')))->addStyle('width: 30%;'),
					_('Type'),
					_('Type of information'),
					_('Status')
				]
			],
			'template_items' => [
				'title' => _('Items'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'itemid,name',
				'form' => [
					'name' => 'itemform',
					'id' => 'items'
				],
				'table_columns' => [
					(new CColHeader(_('Name')))->addStyle('width: 30%;'),
					(new CColHeader(_('Key')))->addStyle('width: 30%;'),
					_('Type'),
					_('Type of information'),
					_('Status')
				]
			],
			'help_items' => [
				'title' => _('Standard items'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'key',
				'table_columns' => [
					_('Key'),
					_('Name'),
					''
				]
			],
			'graphs' => [
				'title' => _('Graphs'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'graphid,name',
				'form' => [
					'name' => 'graphform',
					'id' => 'graphs'
				],
				'table_columns' => [
					_('Name'),
					_('Graph type')
				]
			],
			'graph_prototypes' => [
				'title' => _('Graph prototypes'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'graphid,name',
				'form' => [
					'name' => 'graphform',
					'id' => 'graphs'
				],
				'table_columns' => [
					_('Name'),
					_('Graph type')
				]
			],
			'item_prototypes' => [
				'title' => _('Item prototypes'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'itemid,name,flags',
				'form' => [
					'name' => 'itemform',
					'id' => 'items'
				],
				'table_columns' => [
					(new CColHeader(_('Name')))->addStyle('width: 30%;'),
					(new CColHeader(_('Key')))->addStyle('width: 30%;'),
					_('Type'),
					_('Type of information')
				]
			],
			'sysmaps' => [
				'title' => _('Maps'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'sysmapid,name',
				'form' => [
					'name' => 'sysmapform',
					'id' => 'sysmaps'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'drules' => [
				'title' => _('Discovery rules'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'druleid,name',
				'form' => [
					'name' => 'druleform',
					'id' => 'drules'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'dchecks' => [
				'title' => _('Discovery checks'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'dcheckid,name',
				'table_columns' => [
					_('Name')
				]
			],
			'roles' => [
				'title' => _('User roles'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'roleid,name',
				'form' => [
					'name' => 'rolesform',
					'id' => 'roles'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'api_methods' => [
				'title' => _('API methods'),
				'min_user_type' => USER_TYPE_SUPER_ADMIN,
				'allowed_src_fields' => 'name',
				'form' => [
					'name' => 'apimethodform',
					'id' => 'apimethods'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'valuemap_names' => [
				'title' => _('Value mapping'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'valuemapid,name',
				'form' => [
					'name' => 'valuemapform',
					'id' => 'valuemaps'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'valuemaps' => [
				'title' => _('Value mapping'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'valuemapid,name',
				'form' => [
					'name' => 'valuemapform',
					'id' => 'valuemaps'
				],
				'table_columns' => [
					_('Name'),
					_('Mapping')
				]
			],
			'template_valuemaps' => [
				'title' => _('Value mapping'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'valuemapid,name',
				'form' => [
					'name' => 'valuemapform',
					'id' => 'valuemaps'
				],
				'table_columns' => [
					_('Name'),
					_('Mapping')
				]
			],
			'dashboard' => [
				'title' => _('Dashboards'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'dashboardid,name',
				'form' => [
					'name' => 'dashboardform',
					'id' => 'dashboards'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'sla' => [
				'title' => _('SLA'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'slaid,name',
				'form' => [
					'name' => 'slaform',
					'id' => 'sla'
				],
				'table_columns' => [
					_('Name')
				]
			],
			'actions' => [
				'title' => _('Actions'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'actionid,name',
				'form' => [
					'name' => 'actionform',
					'id' => 'actions'
				],
				'table_columns' => [
					_('Actions')
				]
			],
			'media_types' => [
				'title' => _('Media types'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'mediatypeid,name',
				'form' => [
					'name' => 'media_typeform',
					'id' => 'media_types'
				],
				'table_columns' => [
					_('Media type')
				]
			],
			'host_inventory' => [
				'title' => _('Inventory'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'id,name',
				'form' => [
					'name' => 'inventory_form',
					'id' => 'inventory_form'
				],
				'table_columns' => [
					_('Name')
				]
			]
		];
	}

	protected function checkInput() {
		// This must be done before standard validation.
		if (array_key_exists('srctbl', $_REQUEST) && array_key_exists($_REQUEST['srctbl'], $this->popup_properties)) {
			$this->source_table = $_REQUEST['srctbl'];
		}
		else {
			$this->setResponse(new CControllerResponseFatal());
			return false;
		}

		$fields = [
			'dstfrm' =>								'string|fatal',
			'dstfld1' =>							'string|not_empty',
			'srctbl' =>								'string',
			'srcfld1' =>							'string|required|in '.$this->popup_properties[$this->source_table]['allowed_src_fields'],
			'groupid' =>							'db hstgrp.groupid',
			'templategroupid' =>					'db hstgrp.groupid',
			'group' =>								'string',
			'templategroup' =>						'string',
			'hostid' =>								'db hosts.hostid',
			'templateid' =>							'db hosts.hostid',
			'host' =>								'string',
			'parent_discoveryid' =>					'db items.itemid',
			'templates' =>							'string|not_empty',
			'host_templates' =>						'string|not_empty',
			'multiselect' =>						'in 1',
			'patternselect' =>						'in 1',
			'submit' =>								'string',
			'excludeids' =>							'array',
			'disableids' =>							'array',
			'only_hostid' =>						'db hosts.hostid',
			'monitored_hosts' =>					'in 1',
			'templated_hosts' =>					'in 1',
			'real_hosts' =>							'in 1',
			'normal_only' =>						'in 1',
			'with_graphs' =>						'in 1',
			'with_graph_prototypes' =>				'in 1',
			'with_items' =>							'in 1',
			'with_simple_graph_items' =>			'in 1',
			'with_simple_graph_item_prototypes' =>	'in 1',
			'with_triggers' =>						'in 1',
			'with_monitored_triggers' =>			'in 1',
			'with_monitored_items' =>				'in 1',
			'with_httptests' => 					'in 1',
			'with_inherited' =>						'in 1',
			'itemtype' =>							'in '.implode(',', self::ALLOWED_ITEM_TYPES),
			'value_types' =>						'array',
			'context' =>							'string|in host,template,audit',
			'enabled_only' =>						'in 1',
			'disable_names' =>						'array',
			'numeric' =>							'in 1',
			'reference' =>							'string',
			'writeonly' =>							'in 1',
			'enrich_parent_groups' =>				'in 1',
			'filter_groupid_rst' =>					'in 1',
			'filter_hostid_rst' =>					'in 1',
			'filter_templateid_rst' =>				'in 1',
			'user_type' =>							'in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'group_status' =>						'in '.implode(',', [GROUP_STATUS_ENABLED, GROUP_STATUS_DISABLED]),
			'hostids' =>							'array',
			'host_pattern' =>						'array|not_empty',
			'host_pattern_wildcard_allowed' =>		'in 1',
			'host_pattern_multiple' =>				'in 1',
			'hide_host_filter' =>					'in 1',
			'resolve_macros' =>						'in 1',
			'exclude_provisioned' =>				'in 1'
		];

		// Set destination and source field validation roles.
		$dst_field_count = countRequest('dstfld');
		for ($i = 2; $dst_field_count >= $i; $i++) {
			$fields['dstfld'.$i] = 'string';
		}

		$src_field_count = countRequest('srcfld');
		for ($i = 2; $src_field_count >= $i; $i++) {
			$fields['srcfld'.$i] = 'in '.$this->popup_properties[$this->source_table]['allowed_src_fields'];
		}

		$ret = $this->validateInput($fields);

		// Set disabled options to property for ability to modify them in result fetching.
		if ($ret && $this->hasInput('disableids')) {
			$this->disableids = $this->getInput('disableids');
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		// Check minimum user type.
		if ($this->popup_properties[$this->getInput('srctbl')]['min_user_type'] > CWebUser::$data['type']) {
			return false;
		}

		// Check host group permissions.
		$group_options = [];

		if ($this->getInput('group', '') !== '') {
			$group_options['filter']['name'] = $this->getInput('group');
		}
		elseif ($this->hasInput('groupid')) {
			$group_options['groupids'] = $this->getInput('groupid');
		}

		if ($group_options) {
			$host_groups = API::HostGroup()->get(['output' => [], 'preservekeys' => true] + $group_options);

			if (!$host_groups) {
				return false;
			}

			$this->groupids = array_keys($host_groups);
		}

		// Check template group permissions.
		$templategroup_options = [];

		if ($this->getInput('templategroup', '') !== '') {
			$templategroup_options['filter']['name'] = $this->getInput('templategroup');
		}
		elseif ($this->hasInput('templategroupid')) {
			$templategroup_options['groupids'] = $this->getInput('templategroupid');
		}

		if ($templategroup_options) {
			$template_groups = API::TemplateGroup()->get([
				'output' => [],
				'preservekeys' => true
			] + $templategroup_options);

			if (!$template_groups) {
				return false;
			}

			$this->template_groupids = array_keys($template_groups);
		}

		// Check host permissions.
		$host_options = [];

		if ($this->hasInput('only_hostid')) {
			$host_options['templated_hosts'] = true;
			$host_options['hostids'] = $this->getInput('only_hostid');
		}
		elseif ($this->getInput('host', '') !== '') {
			$host_options['filter']['name'] = $this->getInput('host');
		}
		elseif ($this->hasInput('hostid')) {
			$host_options['hostids'] = $this->getInput('hostid');
		}

		if ($host_options) {
			$hosts = API::Host()->get([
				'output' => [],
				'templated_hosts' => true,
				'preservekeys' => true
			] + $host_options);

			if (!$hosts) {
				return false;
			}

			$this->hostids = array_keys($hosts);
		}

		// Check template permissions.
		$template_options = [];

		if ($this->hasInput('templateid')) {
			$template_options['templateids'] = $this->getInput('templateid');
		}

		if ($template_options) {
			$templates = API::Template()->get([
				'output' => [],
				'preservekeys' => true
			] + $template_options);

			if (!$templates) {
				return false;
			}

			$this->templateids = array_keys($templates);
		}

		// Check discovery rule permissions.
		if ($this->hasInput('parent_discoveryid')) {
			$lld_rules = API::DiscoveryRule()->get([
				'output' => [],
				'itemids' => $this->getInput('parent_discoveryid')
			]);

			if (!$lld_rules) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create array of multiselect filter options.
	 *
	 * @return array
	 */
	protected function makeFilters(): array {
		$filter = [];

		$group_options = [];
		$host_options = [];
		$templategroup_options = [];
		$template_options = [];

		if ($this->hasInput('writeonly')) {
			$group_options['editable'] = 1;
			$host_options['editable'] = 1;
			$templategroup_options['editable'] = 1;
			$template_options['editable'] = 1;
		}

		if ($this->hasInput('with_items')) {
			$group_options['with_items'] = 1;
			$host_options['with_items'] = 1;
			$templategroup_options['with_items'] = 1;
			$template_options['with_items'] = 1;
		}
		elseif ($this->hasInput('with_monitored_items')) {
			$group_options['with_monitored_items'] = 1;
			$host_options['with_monitored_items'] = 1;
		}

		if ($this->hasInput('with_httptests')) {
			$group_options['with_httptests'] = 1;
			$host_options['with_httptests'] = 1;
			$templategroup_options['with_httptests'] = 1;
		}

		if ($this->source_table === 'hosts' && !$this->hasInput('templated_hosts')) {
			$group_options['with_hosts'] = 1;
		}
		elseif ($this->source_table === 'templates') {
			$host_options['templated_hosts'] = 1;
			$templategroup_options['with_templates'] = 1;
		}

		if ($this->hasInput('monitored_hosts')) {
			$group_options['with_monitored_hosts'] = 1;
			$host_options['monitored_hosts'] = 1;
		}
		elseif ($this->hasInput('real_hosts')) {
			$group_options['with_hosts'] = 1;
			$host_options['real_hosts'] = 1;
		}
		elseif ($this->hasInput('templated_hosts')) {
			$host_options['templated_hosts'] = 1;
			$templategroup_options['with_templates'] = 1;
		}
		elseif ($this->source_table !== 'templates' && $this->source_table !== 'host_templates') {
			$group_options['with_hosts'] = 1;
		}

		if ($this->hasInput('groupid')) {
			$host_options['groupid'] = $this->getInput('groupid');
		}

		if ($this->hasInput('templategroupid')) {
			$template_options['groupid'] = $this->getInput('templategroupid');
		}

		if ($this->hasInput('enrich_parent_groups') || $this->group_preselect_required
				|| $this->template_group_preselect_required) {
			$group_options['enrich_parent_groups'] = 1;
			$templategroup_options['enrich_parent_groups'] = 1;
		}

		foreach (['with_graphs', 'with_graph_prototypes', 'with_simple_graph_items',
				'with_simple_graph_item_prototypes', 'with_triggers', 'with_monitored_triggers'] as $name) {
			if ($this->hasInput($name)) {
				$group_options[$name] = 1;
				$host_options[$name] = 1;
				$templategroup_options[$name] = 1;
				$template_options[$name] = 1;
				break;
			}
		}

		// Host group dropdown.
		if ($this->group_preselect_required
				&& ($this->source_table !== 'item_prototypes' || !$this->page_options['parent_discoveryid'])) {
			$groups = $this->groupids
				? API::HostGroup()->get([
					'output' => ['name', 'groupid'],
					'groupids' => $this->groupids
				])
				: [];

			$filter['groups'] = [
				'multiple' => false,
				'name' => 'popup_host_group',
				'object_name' => 'hostGroup',
				'data' => CArrayHelper::renameObjectsKeys($groups, ['groupid' => 'id']),
				'selectedLimit' => 1,
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfld1' => 'popup_host_group'
					] + $group_options
				],
				'add_post_js' => false
			];
		}

		// Template group dropdown.
		if ($this->template_group_preselect_required) {
			$groups = $this->template_groupids
				? API::TemplateGroup()->get([
					'output' => ['name', 'groupid'],
					'groupids' => $this->template_groupids
				])
				: [];

			$filter['templategroups'] = [
				'multiple' => false,
				'name' => 'popup_template_group',
				'object_name' => 'templateGroup',
				'data' => CArrayHelper::renameObjectsKeys($groups, ['groupid' => 'id']),
				'selectedLimit' => 1,
				'popup' => [
					'parameters' => [
						'srctbl' => 'template_groups',
						'srcfld1' => 'groupid',
						'dstfld1' => 'popup_template_group'
					] + $templategroup_options
				],
				'add_post_js' => false
			];
		}

		// Host dropdown.
		if (in_array($this->source_table, self::POPUPS_HAVING_HOST_FILTER)
				&& ($this->source_table !== 'item_prototypes' || !$this->page_options['parent_discoveryid'])
				&& !$this->hasInput('hide_host_filter')) {

			$src_name = 'hosts';
			if (!array_key_exists('monitored_hosts', $host_options)
					&& !array_key_exists('real_hosts', $host_options)
					&& !array_key_exists('templated_hosts', $host_options)) {
				$src_name = 'host_templates';
			}

			$hosts = $this->hostids
				? API::Host()->get([
					'output' => ['name', 'hostid'],
					'hostids' => $this->hostids
				])
				: [];

			$hosts = CArrayHelper::renameObjectsKeys($hosts, ['hostid' => 'id']);

			if (count($hosts) != count($this->hostids)) {
				$templates = $this->hostids
					? API::Template()->get([
						'output' => ['name', 'hostid'],
						'templateids' => $this->hostids
					])
					: [];

				$hosts += CArrayHelper::renameObjectsKeys($templates, ['templateid' => 'id']);
				unset($templates);
			}

			$this->hostids = array_column($hosts, 'id');
			$filter['hosts'] = [
				'multiple' => false,
				'name' => 'popup_host',
				'object_name' => $src_name,
				'data' => array_values($hosts),
				'selectedLimit' => 1,
				'readonly' => $this->hasInput('only_hostid'),
				'popup' => [
					'parameters' => [
						'srctbl' => $src_name,
						'srcfld1' => 'hostid',
						'dstfld1' => 'popup_host'
					] + $host_options
				],
				'add_post_js' => false
			];
		}

		// Template dropdown.
		if (in_array($this->source_table, self::POPUPS_HAVING_TEMPLATE_FILTER)) {
			$src_name = 'templates';

			$templates = $this->templateids
				? API::Template()->get([
					'output' => ['name', 'hostid'],
					'templateids' => $this->templateids
				])
				: [];

			$templates = CArrayHelper::renameObjectsKeys($templates, ['templateid' => 'id']);

			$this->templateids = array_column($templates, 'id');
			$filter['templates'] = [
				'multiple' => false,
				'name' => 'popup_template',
				'object_name' => $src_name,
				'data' => array_values($templates),
				'selectedLimit' => 1,
				'disabled' => $this->hasInput('only_hostid'),
				'popup' => [
					'parameters' => [
						'srctbl' => $src_name,
						'srcfld1' => 'hostid',
						'dstfld1' => 'popup_template'
					] + $template_options
				],
				'add_post_js' => false
			];
		}

		return $filter;
	}

	/**
	 * Create an array of global options.
	 *
	 * @return array
	 */
	protected function getPageOptions(): array {
		$option_fields_binary = ['real_hosts', 'with_items', 'writeonly'];
		$option_fields_value = ['host_templates'];

		$page_options = [
			'srcfld1' => $this->getInput('srcfld1', ''),
			'srcfld2' => $this->getInput('srcfld2', ''),
			'srcfld3' => $this->getInput('srcfld3', ''),
			'dstfld1' => $this->getInput('dstfld1', ''),
			'dstfld2' => $this->getInput('dstfld2', ''),
			'dstfld3' => $this->getInput('dstfld3', ''),
			'dstfrm' => $this->getInput('dstfrm', ''),
			'itemtype' => $this->getInput('itemtype', 0),
			'patternselect' => $this->getInput('patternselect', 0),
			'parent_discoveryid' => $this->getInput('parent_discoveryid', 0),
			'reference' => $this->getInput('reference', $this->getInput('srcfld1', 'unknown'))
		];

		$page_options['parentid'] = $page_options['dstfld1'] !== '' ? $page_options['dstfld1'] : null;

		foreach ($option_fields_binary as $field) {
			if ($this->hasInput($field)) {
				$page_options[$field] = true;
			}
		}

		foreach ($option_fields_value as $field) {
			if ($this->hasInput($field)) {
				$page_options[$field] = $this->getInput($field);
			}
		}

		return $page_options;
	}

	/**
	 * Main controller action.
	 */
	protected function doAction() {
		$popup = $this->getPopupProperties();

		// Update or read profile.
		if ($this->groupids) {
			CProfile::updateArray('web.popup.generic.filter_groupid', $this->groupids, PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('filter_groupid_rst')) {
			CProfile::delete('web.popup.generic.filter_groupid');
		}
		else {
			$this->groupids = CProfile::getArray('web.popup.generic.filter_groupid', []);
		}

		if ($this->template_groupids) {
			CProfile::updateArray( 'web.popup.generic.filter_templategroupid', $this->template_groupids,
				PROFILE_TYPE_ID
			);
		}
		elseif ($this->hasInput('filter_groupid_rst')) {
			CProfile::delete('web.popup.generic.filter_templategroupid');
		}
		else {
			$this->template_groupids = CProfile::getArray('web.popup.generic.filter_templategroupid', []);
		}

		if ($this->hostids) {
			CProfile::updateArray('web.popup.generic.filter_hostid', $this->hostids, PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('host_pattern')) {
			$host_pattern_multiple = $this->hasInput('host_pattern_multiple');
			$host_patterns = $host_pattern_multiple
				? $this->getInput('host_pattern')
				: [$this->getInput('host_pattern')];
			$host_pattern_wildcard_enabled = $this->hasInput('host_pattern_wildcard_allowed')
				&& !in_array('*', $host_patterns, true);

			$hosts = API::Host()->get([
				'output' => ['name'],
				'search' => [
					'name' => $host_pattern_wildcard_enabled ? $host_patterns : null
				],
				'searchWildcardsEnabled' => $host_pattern_wildcard_enabled,
				'searchByAny' => true,
				'preservekeys' => true
			]);

			CArrayHelper::sort($hosts, ['name']);

			$this->hostids = $hosts ? [array_key_first($hosts)] : [];

			CProfile::updateArray('web.popup.generic.filter_hostid', $this->hostids, PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('filter_hostid_rst')) {
			CProfile::delete('web.popup.generic.filter_hostid');
		}
		else {
			$this->hostids = CProfile::getArray('web.popup.generic.filter_hostid', []);
		}

		if ($this->templateids) {
			CProfile::updateArray('web.popup.generic.filter_templateid', $this->templateids, PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('filter_templateid_rst')) {
			CProfile::delete('web.popup.generic.filter_templateid');
		}
		else {
			$this->templateids = CProfile::getArray('web.popup.generic.filter_templateid', []);
		}

		// Set popup options.
		$this->host_preselect_required = in_array($this->source_table, self::POPUPS_HAVING_HOST_FILTER);
		$this->group_preselect_required = in_array($this->source_table, self::POPUPS_HAVING_HOST_GROUP_FILTER)
			|| ($this->source_table === 'valuemaps' && !$this->hasInput('hostids'));
		$this->template_group_preselect_required = in_array(
			$this->source_table,
			self::POPUPS_HAVING_TEMPLATE_GROUP_FILTER
		) || ($this->source_table === 'template_valuemaps' && !$this->hasInput('hostids'));
		$this->template_preselect_required = in_array($this->source_table, self::POPUPS_HAVING_TEMPLATE_FILTER);
		$this->page_options = $this->getPageOptions();

		// Make control filters. Must be called before extending groupids.
		$filters = $this->makeFilters();

		// Select subgroups.
		$this->groupids = getSubGroups($this->groupids);
		$this->template_groupids = getTemplateSubGroups($this->template_groupids);

		// Load results.
		$records = $this->fetchResults();
		$this->applyExcludedids($records);
		$this->applyDisableids($records);
		$this->transformRecordsForPatternSelector($records);

		$data = [
			'title' => $popup['title'],
			'popup_type' => $this->source_table,
			'filter' => $filters,
			'form' => array_key_exists('form', $popup) ? $popup['form'] : null,
			'options' => $this->page_options,
			'multiselect' => $this->getInput('multiselect', 0),
			'table_columns' => $popup['table_columns'],
			'table_records' => $records,
			'preselect_required' => (($this->host_preselect_required && !$this->hostids)
				|| ($this->group_preselect_required && !$this->groupids)
				|| ($this->template_group_preselect_required && !$this->template_groupids)
				|| ($this->template_preselect_required && !$this->templateids)),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (($messages = getMessages()) !== null) {
			$data['messages'] = $messages;
		}
		else {
			$data['messages'] = null;
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Customize and return popup properties.
	 *
	 * @return array
	 */
	protected function getPopupProperties(): array {
		$popup_properties = $this->popup_properties[$this->source_table];

		switch ($this->source_table) {
			case 'sla':
				if (!$this->hasInput('enabled_only')) {
					$popup_properties['table_columns'] = array_merge($popup_properties['table_columns'], [_('Status')]);
				}
				break;
		}

		return $popup_properties;
	}

	/**
	 * Unset records having IDs passed in 'excludeids'.
	 *
	 * @param array $records
	 */
	protected function applyExcludedids(array &$records) {
		$excludeids = $this->getInput('excludeids', []);

		foreach ($excludeids as $excludeid) {
			if (array_key_exists($excludeid, $records)) {
				unset($records[$excludeid]);
			}
		}
	}

	/**
	 * Mark records having IDs passed in 'disableids' as disabled.
	 *
	 * @param array $records
	 */
	protected function applyDisableids(array &$records) {
		foreach ($this->disableids as $disableid) {
			if (array_key_exists($disableid, $records)) {
				$records[$disableid]['_disabled'] = true;
			}
		}
	}

	/**
	 * Function transforms records to be usable in pattern selector.
	 *
	 * @param array $records
	 */
	protected function transformRecordsForPatternSelector(array &$records) {
		// Pattern selector uses names as ids so they need to be rewritten.
		if (!$this->page_options['patternselect']) {
			return;
		}

		switch ($this->source_table) {
			case 'hosts':
				foreach ($records as $hostid => $row) {
					$records[$row['name']] = [
						'host' => $row['name'],
						'name' => $row['name'],
						'id' => $row['name']
					];
					unset($records[$hostid]);
				}
				break;

			case 'graphs':
				foreach ($records as $graphid => $row) {
					$records[$row['name']] = [
						'name' => $row['name'],
						'graphid' => $row['name'],
						'graphtype' => $row['graphtype'],
						'hosts' => $row['hosts']
					];
					unset($records[$graphid]);
				}
				break;
		}
	}

	/**
	 * Load results from database.
	 *
	 * @return array
	 */
	protected function fetchResults(): array {
		// Construct API request.
		$options = [
			'editable' => $this->hasInput('writeonly'),
			'preservekeys' => true
		];

		$popups_support_templated_entries = ['triggers', 'trigger_prototypes', 'graphs', 'graph_prototypes'];

		if (in_array($this->source_table, $popups_support_templated_entries)) {
			if (!$this->hasInput('monitored_hosts') && $this->hasInput('real_hosts')) {
				$templated = false;
			}
			elseif ($this->hasInput('templated_hosts')) {
				$templated = true;
			}
			else {
				$templated = null;
			}

			$options['templated'] = $templated;
		}

		switch ($this->source_table) {
			case 'usrgrp':
				$options += [
					'output' => API_OUTPUT_EXTEND
				];

				if ($this->hasInput('group_status')) {
					$options['status'] = $this->getInput('group_status');
				}

				$records = API::UserGroup()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'users':
				$options += [
					'output' => ['userid', 'username', 'name', 'surname']
				];

				if ($this->hasInput('exclude_provisioned')) {
					$options['filter']['userdirectoryid'] = 0;
				}

				$records = API::User()->get($options);

				if ($this->hasInput('context')) {
					$records[0] = ['userid' => 0, 'username' => 'System', 'name' => '', 'surname' => ''];
				}

				CArrayHelper::sort($records, ['username']);
				break;

			case 'templates':
				$options += [
					'output' => ['templateid', 'name'],
					'groupids' => $this->template_groupids ? $this->template_groupids : null
				];
				$records = (!$this->template_group_preselect_required || $this->template_groupids)
					? API::Template()->get($options)
					: [];

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['templateid' => 'id']);
				break;

			case 'hosts':
				$options += [
					'output' => ['hostid', 'name'],
					'groupids' => $this->groupids ? $this->groupids : null,
					'real_hosts' => $this->hasInput('real_hosts') ? '1' : null,
					'with_httptests' => $this->hasInput('with_httptests') ? '1' : null,
					'with_items' => $this->hasInput('with_items') ? true : null,
					'with_triggers' => $this->hasInput('with_triggers') ? true : null
				];

				if ($this->hasInput('with_monitored_triggers')) {
					$options += [
						'with_monitored_triggers' => true
					];
				}

				if ($this->hasInput('with_monitored_items')) {
					$options += [
						'with_monitored_items' => true,
						'monitored_hosts' => true
					];
				}

				$records = (!$this->group_preselect_required || $this->groupids)
					? API::Host()->get($options)
					: [];

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['hostid' => 'id']);
				break;

			case 'host_templates':
				$options += [
					'output' => ['hostid', 'name'],
					'groupids' => $this->groupids ? $this->groupids : null,
					'templated_hosts' => true
				];

				$records = (!$this->group_preselect_required || $this->groupids)
					? API::Host()->get($options)
					: [];

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['hostid' => 'id']);
				break;

			case 'host_groups':
				$options += [
					'output' => ['groupid', 'name'],
					'with_triggers' => $this->hasInput('with_triggers')
				];

				if (array_key_exists('real_hosts', $this->page_options)) {
					$options['with_hosts'] = true;
				}

				if ($this->hasInput('with_httptests')) {
					$options['with_httptests'] = true;
				}

				if ($this->hasInput('with_items')) {
					$options['with_items'] = true;
				}

				if ($this->hasInput('with_monitored_triggers')) {
					$options['with_monitored_triggers'] = true;
				}

				if ($this->hasInput('normal_only')) {
					$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				}

				$records = API::HostGroup()->get($options);
				if ($this->hasInput('enrich_parent_groups')) {
					$records = enrichParentGroups($records);
				}

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['groupid' => 'id']);
				break;

			case 'template_groups':
				$options += [
					'output' => ['groupid', 'name'],
					'with_triggers' => $this->hasInput('with_triggers')
				];

				if ($this->hasInput('templated_hosts')) {
					$options['with_templates'] = true;
				}

				if ($this->hasInput('with_httptests')) {
					$options['with_httptests'] = true;
				}

				if ($this->hasInput('with_items')) {
					$options['with_items'] = true;
				}

				$records = API::TemplateGroup()->get($options);
				if ($this->hasInput('enrich_parent_groups')) {
					$records = enrichParentTemplateGroups($records);
				}

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['groupid' => 'id']);
				break;

			case 'help_items':
				$records = CItemData::getByType($this->page_options['itemtype']);
				break;

			case 'triggers':
				$options += [
					'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
					'selectHosts' => ['name'],
					'selectDependencies' => ['triggerid', 'expression', 'description'],
					'expandDescription' => true
				];

				if ($this->hostids) {
					$options['hostids'] = $this->hostids;
				}
				elseif ($this->groupids) {
					$options['groupids'] = $this->groupids;
				}

				if ($this->hasInput('with_monitored_triggers')) {
					$options['monitored'] = true;
				}

				if ($this->hasInput('normal_only')) {
					$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				}

				if (!$this->host_preselect_required || $this->hostids) {
					$records = API::Trigger()->get($options);
				}
				else {
					$records = [];
				}

				CArrayHelper::sort($records, ['description']);
				break;

			case 'template_triggers':
				$options += [
					'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
					'selectHosts' => ['name'],
					'selectItems' => ['status'],
					'selectDependencies' => ['triggerid', 'expression', 'description'],
					'expandDescription' => true
				];

				if ($this->templateids) {
					$options['templateids'] = $this->templateids;
				}
				elseif ($this->groupids) {
					$options['groupids'] = $this->groupids;
				}

				if (!$this->template_preselect_required || $this->templateids) {
					$records = API::Trigger()->get($options);

					if ($this->hasInput('with_monitored_triggers')) {
						foreach ($records as $id => $record) {
							foreach ($record['items'] as $item) {
								if ($item['status'] != ITEM_STATUS_ACTIVE) {
									unset($records[$id]);

									break;
								}
							}

							if ($record['status'] != TRIGGER_STATUS_ENABLED) {
								unset($records[$id]);
							}
						}

					}
				}
				else {
					$records = [];
				}

				CArrayHelper::sort($records, ['description']);
				break;

			case 'trigger_prototypes':
				$options += [
					'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
					'selectHosts' => ['name'],
					'selectDependencies' => ['triggerid', 'expression', 'description'],
					'expandDescription' => true
				];

				if ($this->page_options['parent_discoveryid']) {
					$options['discoveryids'] = [$this->page_options['parent_discoveryid']];
				}
				elseif ($this->hostids) {
					$options['hostids'] = $this->hostids;
				}

				$records = API::TriggerPrototype()->get($options);

				CArrayHelper::sort($records, ['description']);
				break;

			case 'items':
			case 'item_prototypes':
				$name_field = $this->source_table === 'items' && $this->getInput('resolve_macros', 0)
					? 'name_resolved'
					: 'name';

				$options += [
					'output' => ['itemid', $name_field, 'key_', 'flags', 'type', 'value_type', 'status'],
					'selectHosts' => ['name'],
					'templated' => $this->hasInput('templated_hosts') ? true : null
				];

				if ($this->source_table === 'items') {
					$options['output'][] = 'state';
				}

				if ($this->page_options['parent_discoveryid']) {
					$options['discoveryids'] = $this->page_options['parent_discoveryid'];
				}
				elseif ($this->hostids) {
					$options['hostids'] = $this->hostids;
				}

				$value_types = $this->hasInput('value_types')
					? $this->getInput('value_types')
					: ($this->hasInput('numeric') ? [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64] : null);

				if ($value_types !== null) {
					$options['filter']['value_type'] = $value_types;
				}

				if (array_key_exists('real_hosts', $this->page_options)) {
					$options['templated'] = false;
				}

				if ($this->source_table === 'item_prototypes') {
					$records = API::ItemPrototype()->get($options);
				}
				else {
					$records = [];

					if (!$this->host_preselect_required || $this->hostids) {
						if ($this->hasInput('normal_only')) {
							$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
						}

						$records = API::Item()->get($options + ['webitems' => true]);

						if ($this->getInput('resolve_macros', 0)) {
							$records = CArrayHelper::renameObjectsKeys($records, ['name_resolved' => 'name']);
						}
					}
				}

				CArrayHelper::sort($records, ['name']);
				break;

			case 'template_items':
				$options += [
					'output' => ['itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status'],
					'selectHosts' => ['name']
				];

				if ($this->page_options['parent_discoveryid']) {
					$options['discoveryids'] = $this->page_options['parent_discoveryid'];
				}
				elseif ($this->templateids) {
					$options['templateids'] = $this->templateids;
				}

				$records = !$this->template_preselect_required || $this->templateids
					? API::Item()->get($options + ['webitems' => true])
					: [];

				CArrayHelper::sort($records, ['name']);
				break;

			case 'graphs':
			case 'graph_prototypes':
				$options += [
					'output' => API_OUTPUT_EXTEND,
					'selectHosts' => ['hostid', 'name'],
					'hostids' => $this->hostids ? $this->hostids : null
				];

				if ($this->source_table === 'graph_prototypes') {
					$options['selectDiscoveryRule'] = ['hostid'];

					$records = (!$this->host_preselect_required || $this->hostids)
						? API::GraphPrototype()->get($options)
						: [];
				}
				else {
					$records = (!$this->host_preselect_required || $this->hostids)
						? API::Graph()->get($options)
						: [];
				}

				CArrayHelper::sort($records, ['name']);
				break;

			case 'sysmaps':
				$records = API::Map()->get([
					'output' => ['sysmapid', 'name'],
					'preservekeys' => true
				]);

				$records = CArrayHelper::renameObjectsKeys($records, ['sysmapid' => 'id']);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'drules':
				$filter = [];

				if ($this->getInput('enabled_only', 0)) {
					$filter['status'] = DRULE_STATUS_ACTIVE;
				}

				$records = API::DRule()->get([
					'output' => ['druleid', 'name'],
					'filter' => $filter,
					'preservekeys' => true
				]);

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['druleid' => 'id']);
				break;

			case 'dchecks':
				$records = API::DRule()->get([
					'selectDChecks' => ['dcheckid', 'type', 'key_', 'ports', 'allow_redirect'],
					'output' => ['druleid', 'name']
				]);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'proxies':
				$options += [
					'output' => ['proxyid', 'name']
				];

				$records = API::Proxy()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['proxyid' => 'id']);
				break;

			case 'proxy_groups':
				$options += [
					'output' => ['proxy_groupid', 'name']
				];

				$records = API::ProxyGroup()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['proxy_groupid' => 'id']);
				break;

			case 'roles':
				$options += [
					'output' => ['roleid', 'name'],
					'preservekeys' => true
				];

				$records = API::Role()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['roleid' => 'id']);
				break;

			case 'api_methods':
				$user_type = $this->getInput('user_type', USER_TYPE_ZABBIX_USER);
				$api_methods = CRoleHelper::getApiMethods($user_type);
				$api_mask_methods = CRoleHelper::getApiMaskMethods($user_type);
				$modified_disableids = [];

				foreach ($this->disableids as $disableid) {
					if (array_key_exists($disableid, $api_mask_methods)) {
						$modified_disableids = array_merge($modified_disableids, $api_mask_methods[$disableid]);
					}
					else if (!in_array($disableid, $modified_disableids)) {
						$modified_disableids[] = $disableid;
					}
				}

				$this->disableids = $modified_disableids;

				foreach ($api_methods as $api_method) {
					$records[$api_method] = ['id' => $api_method, 'name' => $api_method];
				}

				CArrayHelper::sort($records, ['name']);
				break;

			case 'valuemap_names':
				/**
				 * Show list of value maps with unique names for defined hosts or templates.
				 *
				 * hostids           (required) Array of host or template ids to get value maps from.
				 * context           (required) Define context for inherited value maps: host, template
				 * with_inherited    Include value maps from inherited templates.
				 */
				$records = [];
				$hostids = $this->getInput('hostids', []);
				$context = $this->getInput('context', '');

				if (!$hostids || $context === '') {
					break;
				}

				if ($this->hasInput('with_inherited')) {
					$hostids = CTemplateHelper::getParentTemplatesRecursive($hostids, $context);
				}

				$records = CArrayHelper::renameObjectsKeys(API::ValueMap()->get([
					'output' => ['valuemapid', 'name'],
					'hostids' => $hostids
				]), ['valuemapid' => 'id']);
				// Remove value maps with duplicate names.
				$records = array_column($records, null, 'name');
				$records = array_column($records, null, 'id');
				CArrayHelper::sort($records, ['name']);
				break;

			case 'valuemaps':
			case 'template_valuemaps':
				/**
				 * Show list of value maps with their mappings for defined hosts or templates.
				 *
				 * context  Define context for hostids value maps: host, template. Required.
				 * hostids  Array of host or template ids to get value maps from. Filter by groups will be displayed if
				 *          this parameter is not set.
				 */
				$records = [];
				$hostids = $this->getInput('hostids', []);
				$context = $this->getInput('context', '');

				if ($context === '' || (!$hostids && !$this->groupids && !$this->template_groupids)) {
					break;
				}

				$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

				$options = [
					'output' => ['name'],
					'preservekeys' => true
				];

				if ($hostids) {
					$hosts = $context === 'host'
						? API::Host()->get($options + ['hostids' => $hostids])
						: API::Template()->get($options + ['templateids' => $hostids]);
				}
				else {
					$options['limit'] = $limit;

					$hosts = $context === 'host'
						? API::Host()->get($options + ['groupids' => $this->groupids])
						: API::Template()->get($options + ['groupids' => $this->template_groupids]);

					$hostids = array_keys($hosts);
				}

				$db_valuemaps = API::ValueMap()->get([
					'output' => ['valuemapid', 'name', 'hostid'],
					'selectMappings' => ['type', 'value', 'newvalue'],
					'hostids' => $hostids,
					'limit' => $limit
				]);

				$disable_names = $this->getInput('disable_names', []);

				foreach ($db_valuemaps as $db_valuemap) {
					$valuemap = [
						'id' => $db_valuemap['valuemapid'],
						'hostname' => $hosts[$db_valuemap['hostid']]['name'],
						'name' => $db_valuemap['name'],
						'mappings' => array_values($db_valuemap['mappings']),
						'_disabled' => in_array($db_valuemap['name'], $disable_names)
					];

					$records[$db_valuemap['valuemapid']] = $valuemap;
				}

				$records = array_column($records, null, 'id');
				CArrayHelper::sort($records, ['name', 'hostname']);
				break;

			case 'dashboard':
				$options += [
					'output' => ['dashboardid', 'name']
				];

				$records = API::Dashboard()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['dashboardid' => 'id']);
				break;

			case 'sla':
				$options += $this->hasInput('enabled_only')
					? [
						'output' => ['slaid', 'name'],
						'filter' => [
							'status' => ZBX_SLA_STATUS_ENABLED
						]
					]
					: [
						'output' => ['slaid', 'name', 'status']
					];

				$records = API::Sla()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['slaid' => 'id']);
				break;

			case 'actions':
				$options += ['output' => ['name']];

				$records = API::Action()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'media_types':
				$options += ['output' => ['mediatypeid', 'name']];

				$records = API::MediaType()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'host_inventory':
				$records = [];

				foreach (getHostInventories(true) as $inventory_field) {
					$records[$inventory_field['nr']] = [
						'id' => $inventory_field['nr'],
						'name' => $inventory_field['title']
					];
				}
				break;
		}

		return $records;
	}
}
