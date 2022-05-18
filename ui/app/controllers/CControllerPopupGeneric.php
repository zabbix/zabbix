<?php
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
	const ALLOWED_ITEM_TYPES = [ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL,
		ITEM_TYPE_IPMI, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_JMX
	];

	/**
	 * Popups having group filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_GROUP_FILTER = ['hosts', 'templates', 'host_templates'];

	/**
	 * Popups having host filter selector.
	 *
	 * @array
	 */
	const POPUPS_HAVING_HOST_FILTER = ['triggers', 'items', 'graphs', 'graph_prototypes',
		'item_prototypes'
	];

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
	 * Groups set in filter.
	 *
	 * @var array
	 */
	protected $groupids = [];

	/**
	 * Hosts set in filter.
	 *
	 * @var array
	 */
	protected $hostids = [];

	/**
	 * Either Host filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $host_preselect_required;

	/**
	 * Either Host group filter need to be filled to load results.
	 *
	 * @var bool
	 */
	protected $group_preselect_required;

	/**
	 * Set of disabled options.
	 *
	 * @var array
	 */
	protected $disableids = [];

	protected function init() {
		$this->disableSIDvalidation();

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
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
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
			'proxies' => [
				'title' => _('Proxies'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'proxyid,host',
				'form' => [
					'name' => 'proxiesform',
					'id' => 'proxies'
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
					_('Name'),
					_('Key'),
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
					_('Name')
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
					_('Name'),
					_('Key'),
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
			'group' =>								'string',
			'hostid' =>								'db hosts.hostid',
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
			'with_hosts_and_templates' =>			'in 1',
			'with_webitems' =>						'in 1',
			'with_inherited' =>						'in 1',
			'itemtype' =>							'in '.implode(',', self::ALLOWED_ITEM_TYPES),
			'value_types' =>						'array',
			'context' =>							'string|in host,template',
			'enabled_only' =>						'in 1',
			'disable_names' =>						'array',
			'numeric' =>							'in 1',
			'reference' =>							'string',
			'writeonly' =>							'in 1',
			'noempty' =>							'in 1',
			'submit_parent' =>						'in 1',
			'enrich_parent_groups' =>				'in 1',
			'filter_groupid_rst' =>					'in 1',
			'filter_hostid_rst' =>					'in 1',
			'user_type' =>							'in '.implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]),
			'hostids' => 							'array'
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

		if ($ret && $this->getInput('value_types', [])) {
			foreach ($this->getInput('value_types') as $value_type) {
				if (!is_numeric($value_type) || $value_type < 0 || $value_type > 15) {
					error(_s('Incorrect value "%1$s" for "%2$s" field.', $value_type, 'value_types'));
					$ret = false;
				}
			}
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
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

		if ($this->hasInput('writeonly')) {
			$group_options['editable'] = 1;
			$host_options['editable'] = 1;
		}

		if ($this->hasInput('with_items')) {
			$group_options['with_items'] = 1;
			$host_options['with_items'] = 1;
		}
		elseif ($this->hasInput('with_monitored_items')) {
			$group_options['with_monitored_items'] = 1;
			$host_options['with_monitored_items'] = 1;
		}

		if ($this->hasInput('with_httptests')) {
			$group_options['with_httptests'] = 1;
			$host_options['with_httptests'] = 1;
		}

		if ($this->source_table === 'hosts' && !$this->hasInput('templated_hosts')) {
			$group_options['real_hosts'] = 1;
		}
		elseif ($this->source_table === 'templates') {
			$host_options['templated_hosts'] = 1;
			$group_options['templated_hosts'] = 1;
		}

		if ($this->hasInput('monitored_hosts')) {
			$group_options['monitored_hosts'] = 1;
			$host_options['monitored_hosts'] = 1;
		}
		elseif ($this->hasInput('real_hosts')) {
			$group_options['real_hosts'] = 1;
			$host_options['real_hosts'] = 1;
		}
		elseif ($this->hasInput('templated_hosts')) {
			$host_options['templated_hosts'] = 1;
			$group_options['templated_hosts'] = 1;
		}
		elseif ($this->source_table !== 'templates' && $this->source_table !== 'host_templates') {
			$group_options['with_hosts_and_templates'] = 1;
		}

		if ($this->hasInput('groupid')) {
			$host_options['groupid'] = $this->getInput('groupid');
			$group_options['groupid'] = $this->getInput('groupid');
		}

		if ($this->hasInput('enrich_parent_groups') || $this->group_preselect_required) {
			$group_options['enrich_parent_groups'] = 1;
		}

		foreach (['with_graphs', 'with_graph_prototypes', 'with_simple_graph_items',
				'with_simple_graph_item_prototypes', 'with_triggers', 'with_monitored_triggers'] as $name) {
			if ($this->hasInput($name)) {
				$group_options[$name] = 1;
				$host_options[$name] = 1;
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

		// Host dropdown.
		if (in_array($this->source_table, self::POPUPS_HAVING_HOST_FILTER)
				&& ($this->source_table !== 'item_prototypes' || !$this->page_options['parent_discoveryid'])) {

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
				'disabled' => $this->hasInput('only_hostid'),
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

		return $filter;
	}

	/**
	 * Create an array of global options.
	 *
	 * @return array
	 */
	protected function getPageOptions(): array {
		$option_fields_binary = ['noempty', 'real_hosts', 'submit_parent', 'with_items', 'writeonly'];
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

		$page_options['parentid'] = ($page_options['dstfld1'] !== '')
			? zbx_jsvalue($page_options['dstfld1'])
			: 'null';

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

		if ($this->hostids) {
			CProfile::updateArray('web.popup.generic.filter_hostid', $this->hostids, PROFILE_TYPE_ID);
		}
		elseif ($this->hasInput('filter_hostid_rst')) {
			CProfile::delete('web.popup.generic.filter_hostid');
		}
		else {
			$this->hostids = CProfile::getArray('web.popup.generic.filter_hostid', []);
		}

		// Set popup options.
		$this->host_preselect_required = in_array($this->source_table, self::POPUPS_HAVING_HOST_FILTER);
		$this->group_preselect_required = in_array($this->source_table, self::POPUPS_HAVING_GROUP_FILTER)
			|| ($this->source_table === 'valuemaps' && !$this->hasInput('hostids'));
		$this->page_options = $this->getPageOptions();

		// Make control filters. Must be called before extending groupids.
		$filters = $this->makeFilters();

		// Select subgroups.
		$this->groupids = getSubGroups($this->groupids);

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
				|| ($this->group_preselect_required && !$this->groupids)),
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

			case 'items':
				foreach ($records as $itemid => $row) {
					$records[$row['name']] = ['itemid' => $row['name']] + $row;
					unset($records[$itemid]);
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

				$records = API::UserGroup()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'users':
				$options += [
					'output' => ['username', 'name', 'surname', 'type', 'theme', 'lang']
				];

				$records = API::User()->get($options);
				CArrayHelper::sort($records, ['username']);
				break;

			case 'templates':
				$options += [
					'output' => ['templateid', 'name'],
					'groupids' => $this->groupids ? $this->groupids : null
				];

				$records = (!$this->group_preselect_required || $this->groupids)
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
					'with_triggers' => $this->hasInput('with_triggers') ? true : null,
					'templated_hosts' => $this->hasInput('with_hosts_and_templates') ? true : null
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
					'with_triggers' => $this->hasInput('with_triggers') ? true : null
				];

				if (array_key_exists('real_hosts', $this->page_options)) {
					$options['real_hosts'] = 1;
				}
				elseif ($this->hasInput('templated_hosts')) {
					$options['templated_hosts'] = 1;
				}

				if ($this->hasInput('with_httptests')) {
					$options['with_httptests'] = 1;
				}

				if ($this->hasInput('with_hosts_and_templates')) {
					$options['with_hosts_and_templates'] = 1;
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
				$options += [
					'output' => ['itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status'],
					'selectHosts' => ['name'],
					'templated' => $this->hasInput('templated_hosts') ? true : null
				];

				if ($this->source_table === 'items') {
					$options['output'] = array_merge($options['output'], ['state']);
				}

				if ($this->page_options['parent_discoveryid']) {
					$options['discoveryids'] = [$this->page_options['parent_discoveryid']];
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
					if ($this->hasInput('with_webitems')) {
						$options['webitems'] = true;
					}

					if ($this->hasInput('normal_only')) {
						$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
					}

					$records = (!$this->host_preselect_required || $this->hostids)
						? API::Item()->get($options)
						: [];
				}

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
				$options += [
					'output' => API_OUTPUT_EXTEND
				];

				$records = API::Map()->get($options);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'drules':
				$records = API::DRule()->get([
					'output' => ['druleid', 'name'],
					'filter' => ['status' => DRULE_STATUS_ACTIVE],
					'preservekeys' => true
				]);

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['druleid' => 'id']);
				break;

			case 'dchecks':
				$records = API::DRule()->get([
					'selectDChecks' => ['dcheckid', 'type', 'key_', 'ports'],
					'output' => ['druleid', 'name']
				]);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'proxies':
				$options += [
					'output' => ['proxyid', 'host']
				];

				$records = API::Proxy()->get($options);
				CArrayHelper::sort($records, ['host']);
				$records = CArrayHelper::renameObjectsKeys($records, ['proxyid' => 'id', 'host' => 'name']);
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
				/**
				 * Show list of value maps with their mappings for defined hosts or templates.
				 *
				 * context  Define context for hostids value maps: host, template. Required together with "hostids".
				 * hostids  Array of host or template ids to get value maps from. Filter by groups will be displayed if
				 *          this parameter is not set;
				 */
				$records = [];

				if (($this->hasInput('hostids') && !$this->hasInput('context'))
						|| (!$this->hasInput('hostids') && !$this->groupids)) {
					break;
				}

				$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);

				if ($this->hasInput('hostids')) {
					$hostids = $this->getInput('hostids');
					$context = $this->getInput('context');

					if ($context === 'host') {
						$hosts = API::Host()->get([
							'output' => ['name'],
							'hostids' => $hostids,
							'preservekeys' => true
						]);
					}
					else {
						$hosts = API::Template()->get([
							'output' => ['name'],
							'templateids' => $hostids,
							'preservekeys' => true
						]);
					}
				}
				else {
					$hosts = API::Host()->get([
						'output' => ['name'],
						'groupids' => $this->groupids,
						'preservekeys' => true,
						'limit' => $limit
					]) + API::Template()->get([
						'output' => ['name'],
						'groupids' => $this->groupids,
						'preservekeys' => true,
						'limit' => $limit
					]);

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
		}

		return $records;
	}
}
