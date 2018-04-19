<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/../../include/triggers.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/../../include/users.inc.php';
require_once dirname(__FILE__).'/../../include/js.inc.php';
require_once dirname(__FILE__).'/../../include/discovery.inc.php';

function get_window_opener($frame, $field, $value) {
	if ($field === '') {
		return '';
	}

	return '
		try {'.
			"document.getElementById(".zbx_jsvalue($field).").value=".zbx_jsvalue($value)."; ".
		'} catch(e) {'.
			'throw("Error: Target not found")'.
		'}'."\n";
}

class CControllerPopupGeneric extends CController {
	private $popup_properties;
	private $allowed_item_types;
	private $source_table;

	protected function init() {
		$this->disableSIDvalidation();

		$this->allowed_item_types = [
			ITEM_TYPE_ZABBIX,
			ITEM_TYPE_ZABBIX_ACTIVE,
			ITEM_TYPE_SIMPLE,
			ITEM_TYPE_INTERNAL,
			ITEM_TYPE_AGGREGATE,
			ITEM_TYPE_SNMPTRAP,
			ITEM_TYPE_DB_MONITOR,
			ITEM_TYPE_JMX
		];

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
			'applications' => [
				'title' => _('Applications'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'applicationid,name',
				'form' => [
					'name' => 'applicationform',
					'id' => 'applications'
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
				'allowed_src_fields' => 'usergrpid,alias,fullname,userid',
				'form' => [
					'name' => 'userform',
					'id' => 'users'
				],
				'table_columns' => [
					_('Alias'),
					_x('Name', 'user first name'),
					_('Surname')
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
			'screens' => [
				'title' => _('Screens'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'screenid',
				'form' => [
					'name' => 'screenform',
					'id' => 'screens'
				],
				'table_columns' => [
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
					_('Type of information'),
					_('Status')
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
			'screens2' => [
				'title' => _('Screens'),
				'min_user_type' => USER_TYPE_ZABBIX_USER,
				'allowed_src_fields' => 'screenid,name',
				'table_columns' => [
					_('Name')
				]
			],
			'drules' => [
				'title' => _('Discovery rules'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'druleid,name',
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
			'scripts' => [
				'title' => _('Global scripts'),
				'min_user_type' => USER_TYPE_ZABBIX_ADMIN,
				'allowed_src_fields' => 'scriptid,name',
				'form' => [
					'name' => 'scriptform',
					'id' => 'scripts'
				],
				'table_columns' => [
					_('Name'),
					_('Execute on'),
					_('Commands')
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
			'dstfrm' =>						'string|fatal',
			'dstfld1' =>					'string|not_empty',
			'srctbl' =>						'string',
			'srcfld1' =>					'string|required|in '.$this->popup_properties[$this->source_table]['allowed_src_fields'],
			'groupid' =>					'db groups.groupid',
			'group' =>						'string',
			'hostid' =>						'db hosts.hostid',
			'host' =>						'string',
			'parent_discoveryid' =>			'db items.itemid',
			'screenid' =>					'db screens.screenid',
			'templates' =>					'string|not_empty',
			'host_templates' =>				'string|not_empty',
			'multiselect' =>				'in 1',
			'submit' =>						'string',
			'excludeids' =>					'array',
			'only_hostid' =>				'db hosts.hostid',
			'monitored_hosts' =>			'in 1',
			'templated_hosts' =>			'in 1',
			'real_hosts' =>					'in 1',
			'normal_only' =>				'in 1',
			'with_applications' =>			'in 1',
			'with_graphs' =>				'in 1',
			'with_items' =>					'in 1',
			'with_simple_graph_items' =>	'in 1',
			'with_triggers' =>				'in 1',
			'with_monitored_triggers' =>	'in 1',
			'with_webitems' =>	            'in 1',
			'itemtype' =>					'in '.implode(',', $this->allowed_item_types),
			'value_types' =>				'array',
			'numeric' =>					'in 1',
			'reference' =>					'string',
			'writeonly' =>					'in 1',
			'noempty' =>					'in 1',
			'submit_parent' =>				'in 1',
			'templateid' =>					'db hosts.hostid'
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
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		// Check minimum user type.
		if ($this->popup_properties[$this->getInput('srctbl')]['min_user_type'] > CWebUser::$data['type']) {
			return false;
		}

		// Check if requested element is accessible.
		if ($this->hasInput('only_hostid') && !isReadableHostTemplates([$this->getInput('only_hostid')])) {
			return false;
		}
		else {
			if ($this->hasInput('hostid') && !isReadableHostTemplates([$this->getInput('hostid')])) {
				return false;
			}
			if ($this->hasInput('groupid') && !isReadableHostGroups([$this->getInput('groupid')])) {
				return false;
			}
		}

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

	protected function doAction() {
		$excludeids = zbx_toHash($this->getInput('excludeids', []));
		$records = [];

		$value_types = null;

		if ($this->hasInput('value_types')) {
			$value_types = $this->getInput('value_types');
		}
		elseif ($this->hasInput('numeric')) {
			$value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64];
		}

		// Construct page filter.
		$groupids = null;
		if ($this->getInput('group', '') !== '') {
			$groups = API::HostGroup()->get([
				'output' => [],
				'filter' => [
					'name' => $this->getInput('group')
				],
				'preservekeys' => true
			]);

			if ($groups) {
				$groupids = array_keys($groups);
			}
		}

		if ($groupids === null) {
			$groupids = $this->hasInput('groupid') ? $this->getInput('groupid') : null;
		}

		$hostids = null;
		if ($this->getInput('host', '') !== '') {
			$hosts = API::HostGroup()->get([
				'output' => [],
				'filter' => [
					'name' => $this->getInput('host')
				],
				'preservekeys' => true
			]);

			if ($hosts) {
				$hostids = array_keys($hosts);
			}
		}

		if ($hostids === null) {
			$hostids = $this->hasInput('hostid') ? $this->getInput('hostid') : null;
		}

		$options = [
			'config' => ['select_latest' => true, 'deny_all' => true, 'popupDD' => true],
			'groups' => [],
			'hosts' => [],
			'groupid' => $groupids,
			'hostid' => $hostids
		];

		if ($this->hasInput('writeonly')) {
			$options['groups']['editable'] = true;
			$options['hosts']['editable'] = true;
		}

		$templated = null;

		if ($this->source_table == 'hosts') {
			$options['groups']['real_hosts'] = true;
			$templated = 0;
		}

		if ($this->hasInput('monitored_hosts')) {
			$options['groups']['monitored_hosts'] = true;
			$options['hosts']['monitored_hosts'] = true;
		}
		elseif ($this->hasInput('real_hosts')) {
			$options['groups']['real_hosts'] = true;
			$templated = 0;
		}
		elseif ($this->hasInput('templated_hosts')) {
			$options['hosts']['templated_hosts'] = true;
			$options['groups']['templated_hosts'] = true;
			$templated = 1;
		}
		else {
			$options['groups']['with_hosts_and_templates'] = true;
			$options['hosts']['templated_hosts'] = true;
		}

		if ($this->hasInput('with_applications')) {
			$options['groups']['with_applications'] = true;
			$options['hosts']['with_applications'] = true;
		}
		elseif ($this->hasInput('with_graphs')) {
			$options['groups']['with_graphs'] = true;
			$options['hosts']['with_graphs'] = true;
		}
		elseif ($this->hasInput('with_simple_graph_items')) {
			$options['groups']['with_simple_graph_items'] = true;
			$options['hosts']['with_simple_graph_items'] = true;
		}
		elseif ($this->hasInput('with_triggers')) {
			$options['groups']['with_triggers'] = true;
			$options['hosts']['with_triggers'] = true;
		}
		elseif ($this->hasInput('with_monitored_triggers')) {
			$options['groups']['with_monitored_triggers'] = true;
			$options['hosts']['with_monitored_triggers'] = true;
		}

		$page_filter = new CPageFilter($options);
		$groupids = $page_filter->groupids;

		// Get hostid.
		$hostid = null;
		if ($page_filter->hostsSelected) {
			if ($page_filter->hostid > 0) {
				$hostid = $page_filter->hostid;
			}
		}
		else {
			$hostid = 0;
		}

		// Gather options.
		$page_options = [
			'srctbl' => $this->source_table,
			'srcfld1' => $this->getInput('srcfld1', ''),
			'srcfld2' => $this->getInput('srcfld2', ''),
			'srcfld3' => $this->getInput('srcfld3', ''),
			'dstfld1' => $this->getInput('dstfld1', ''),
			'dstfld2' => $this->getInput('dstfld2', ''),
			'dstfld3' => $this->getInput('dstfld3', ''),
			'dstfrm' => $this->getInput('dstfrm', ''),
			'dstact' => $this->getInput('dstact', ''),
			'itemtype' => $this->getInput('itemtype', 0),
			'excludeids' => $excludeids,
			'multiselect' => $this->getInput('multiselect', 0),
			'parent_discoveryid' => $this->getInput('parent_discoveryid', 0),
			'reference' => $this->getInput('reference', $this->getInput('srcfld1', 'unknown'))
		];

		$page_options['parentid'] = $page_options['dstfld1'] !== '' ? zbx_jsvalue($page_options['dstfld1']) : 'null';

		if ($this->hasInput('only_hostid')) {
			$hostid = $this->getInput('only_hostid');

			$only_hosts = API::Host()->get([
				'hostids' => $hostid,
				'templated_hosts' => true,
				'output' => ['hostid', 'host', 'name'],
				'limit' => 1
			]);

			$page_options['only_hostid'] = $only_hosts[0];
		}

		if ($hostid != 0) {
			$page_options['hostid'] = $hostid;
		}

		$option_fields_binary = ['monitored_hosts', 'noempty', 'normal_only', 'numeric', 'real_hosts', 'submit_parent',
			'templated_hosts', 'with_applications', 'with_graphs', 'with_items', 'with_monitored_triggers',
			'with_simple_graph_items', 'with_triggers', 'with_webitems', 'writeonly'];
		foreach ($option_fields_binary as $field) {
			if ($this->hasInput($field)) {
				$page_options[$field] = true;
			}
		}

		$option_fields_value = ['host_templates', 'itemtype', 'screenid', 'templateid', 'value_types'];
		foreach ($option_fields_value as $field) {
			if ($this->hasInput($field)) {
				$page_options[$field] = $this->getInput($field);
			}
		}

		// Get data.
		switch ($this->source_table) {
			case 'usrgrp':
				$options = [
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::UserGroup()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'users':
				$options = [
					'output' => ['alias', 'name', 'surname', 'type', 'theme', 'lang'],
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::User()->get($options);
				CArrayHelper::sort($records, ['alias']);
				break;

			case 'templates':
				$options = [
					'output' => ['templateid', 'name'],
					'groupids' => $groupids,
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Template()->get($options);

				// Do not show itself.
				if (array_key_exists('templateid', $page_options)
						&& array_key_exists($page_options['templateid'], $records)) {
					unset($records[$page_options['templateid']]);
				}

				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['templateid' => 'id']);
				break;

			case 'hosts':
				$options = [
					'output' => ['hostid', 'name'],
					'groupids' => $groupids,
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Host()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['hostid' => 'id']);
				break;

			case 'host_templates':
				$options = [
					'output' => ['hostid', 'name'],
					'groupids' => $groupids,
					'templated_hosts' => true,
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Host()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['hostid' => 'id']);
				break;

			case 'host_groups':
				$options = [
					'output' => ['groupid', 'name'],
					'preservekeys' => true
				];

				if (array_key_exists('normal_only', $page_options)) {
					$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
				}

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::HostGroup()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['groupid' => 'id']);
				break;

			case 'help_items':
				$records = (new CHelpItems())->getByType($page_options['itemtype']);
				break;

			case 'triggers':
			case 'trigger_prototypes':
				$options = [
					'output' => ['triggerid', 'expression', 'description', 'status', 'priority', 'state'],
					'selectHosts' => ['name'],
					'selectDependencies' => ['triggerid', 'expression', 'description'],
					'expandDescription' => true,
					'preservekeys' => true
				];

				if ($this->source_table === 'trigger_prototypes') {
					if ($page_options['parent_discoveryid']) {
						$options['discoveryids'] = [$page_options['parent_discoveryid']];
					}
					else {
						$options['hostids'] = [$hostid];
					}

					if (array_key_exists('writeonly', $page_options)) {
						$options['editable'] = true;
					}

					if ($templated !== null) {
						$options['templated'] = $templated;
					}

					$records = API::TriggerPrototype()->get($options);
				}
				else {
					if ($hostid === null) {
						$options['groupids'] = $groupids;
					}
					else {
						$options['hostids'] = [$hostid];
					}

					if (array_key_exists('writeonly', $page_options)) {
						$options['editable'] = true;
					}

					if ($templated !== null) {
						$options['templated'] = $templated;
					}

					if (array_key_exists('with_monitored_triggers', $page_options)) {
						$options['monitored'] = true;
					}

					if (array_key_exists('normal_only', $page_options)) {
						$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
					}

					$records = API::Trigger()->get($options);
				}

				CArrayHelper::sort($records, ['description']);
				break;

			case 'items':
			case 'item_prototypes':
				$options = [
					'output' => ['itemid', 'hostid', 'name', 'key_', 'flags', 'type', 'value_type', 'status', 'state'],
					'selectHosts' => ['name'],
					'preservekeys' => true
				];

				if ($page_options['parent_discoveryid']) {
					$options['discoveryids'] = [$page_options['parent_discoveryid']];
				}
				else {
					$options['hostids'] = $hostid;
				}

				if ($templated == 1) {
					$options['templated'] = true;
				}

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				if ($value_types !== null) {
					$options['filter']['value_type'] = $value_types;
				}

				if ($this->source_table === 'item_prototypes') {
					$records = API::ItemPrototype()->get($options);
				}
				else {
					if (array_key_exists('with_webitems', $page_options)) {
						$options['webitems'] = true;
					}

					if (array_key_exists('normal_only', $page_options)) {
						$options['filter']['flags'] = ZBX_FLAG_DISCOVERY_NORMAL;
					}

					$records = API::Item()->get($options);
				}

				if ($excludeids) {
					foreach ($records as $item) {
						if (array_key_exists($item['itemid'], $excludeids)) {
							unset($records[$item['itemid']]);
						}
					}
				}

				$records = CMacrosResolverHelper::resolveItemNames($records);
				CArrayHelper::sort($records, ['name_expanded']);
				break;

			case 'applications':
				$options = [
					'output' => ['applicationid', 'name'],
					'hostids' => $hostid,
					'preservekeys' => true
				];
				if (is_null($hostid)) {
					$options['groupids'] = $groupids;
				}
				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}
				if (!is_null($templated)) {
					$options['templated'] = $templated;
				}

				$records = API::Application()->get($options);
				CArrayHelper::sort($records, ['name']);
				$records = CArrayHelper::renameObjectsKeys($records, ['applicationid' => 'id']);
				break;

			case 'graphs':
			case 'graph_prototypes':
				if ($page_filter->hostsSelected) {
					$options = [
						'output' => API_OUTPUT_EXTEND,
						'hostids' => $hostid,
						'selectHosts' => ['name'],
						'preservekeys' => true
					];

					if (array_key_exists('writeonly', $page_options)) {
						$options['editable'] = true;
					}
					if (!is_null($templated)) {
						$options['templated'] = $templated;
					}

					if ($this->source_table === 'graph_prototypes') {
						$records = API::GraphPrototype()->get($options);
					}
					else {
						$records = API::Graph()->get($options);
					}

					CArrayHelper::sort($records, ['name']);
				}
				else {
					$records = [];
				}
				break;

			case 'sysmaps':
				$options = [
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Map()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'screens':
				$options = [
					'output' => ['screenid', 'name'],
					'preservekeys' => true
				];
				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Screen()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;

			case 'screens2':
				require_once dirname(__FILE__).'/../../include/screens.inc.php';

				$options = [
					'output' => ['screenid', 'name'],
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Screen()->get($options);

				foreach ($records as $item) {
					if (array_key_exists('screenid', $page_options)
							&& check_screen_recursion($page_options['screenid'], $item['screenid'])) {
						unset($records[$item['screenid']]);
					}
				}

				CArrayHelper::sort($records, ['name']);
				break;

			case 'drules':
				$records = API::DRule()->get([
					'output' => ['druleid', 'name']
				]);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'dchecks':
				$records = API::DRule()->get([
					'selectDChecks' => ['dcheckid', 'type', 'key_', 'ports'],
					'output' => ['druleid', 'name']
				]);

				CArrayHelper::sort($records, ['name']);
				break;

			case 'proxies':
				$options = [
					'output' => ['proxyid', 'host'],
					'preservekeys' => true
				];

				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Proxy()->get($options);
				CArrayHelper::sort($records, ['host']);
				$records = CArrayHelper::renameObjectsKeys($records, ['proxyid' => 'id', 'host' => 'name']);
				break;

			case 'scripts':
				$options = [
					'output' => API_OUTPUT_EXTEND,
					'preservekeys' => true
				];
				if ($hostid === null) {
					$options['groupids'] = $groupids;
				}
				if (array_key_exists('writeonly', $page_options)) {
					$options['editable'] = true;
				}

				$records = API::Script()->get($options);
				CArrayHelper::sort($records, ['name']);
				break;
		}

		$data = [
			'title' => $this->popup_properties[$this->source_table]['title'],
			'popup_type' => $this->source_table,
			'page_filter' => $page_filter,
			'form' => array_key_exists('form', $this->popup_properties[$this->source_table])
				? $this->popup_properties[$this->source_table]['form']
				: null,
			'options' => $page_options,
			'multiselect' => $page_options['multiselect'],
			'table_columns' => $this->popup_properties[$this->source_table]['table_columns'],
			'table_records' => $records,
			'allowed_item_types' => $this->allowed_item_types
		];

		if ($this->source_table === 'triggers' || $this->source_table === 'trigger_prototypes') {
			$data['options']['config'] = select_config();
		}

		if (($messages = getMessages()) !== null) {
			$data['messages'] = $messages;
		}
		else {
			$data['messages'] = null;
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
