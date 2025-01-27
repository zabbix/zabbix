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


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('trigger.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('trigger');
}

$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

$filter_column1 = (new CFormGrid())
	->addItem([
		new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'), 'filter_groupids__ms'),
		new CFormField((new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
			'data' => $data['filter_groupids_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'groupids',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
	])
	->addItem([
		(new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms')),
		new CFormField((new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
			'data' => $data['filter_hostids_ms'],
			'popup' => [
				'filter_preselect' => [
					'id' => 'filter_groupids_',
					'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
				],
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'hostids',
					'dstfld1' => 'filter_hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH))
	])
	->addItem([new CLabel(_('Name'), 'filter_name'),
		new CFormField((new CTextBox('filter_name', $data['filter_name']))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		)
	])
	->addItem([new CLabel(_('Severity'), 'filter_priority'),
		new CFormField((new CCheckBoxList('filter_priority'))
			->setOptions(CSeverityHelper::getSeverities())
			->setChecked($data['filter_priority'])
			->setColumns(3)
			->setVertical()
			->showTitles()
		)
	]);

if ($data['context'] === 'host') {
	$filter_column1->addItem([new CLabel(_('State'), 'filter_state'),
		new CFormField((new CRadioButtonList('filter_state', (int) $data['filter_state']))
			->addValue(_('All'), -1)
			->addValue(_('Normal'), TRIGGER_STATE_NORMAL)
			->addValue(_('Unknown'), TRIGGER_STATE_UNKNOWN)
			->setModern()
		)
	]);
}

$filter_column1->addItem([new CLabel(_('Status'), 'filter_status'),
	new CFormField((new CRadioButtonList('filter_status', (int) $data['filter_status']))
		->addValue(_('All'), -1)
		->addValue(triggerIndicator(TRIGGER_STATUS_ENABLED), TRIGGER_STATUS_ENABLED)
		->addValue(triggerIndicator(TRIGGER_STATUS_DISABLED), TRIGGER_STATUS_DISABLED)
		->setModern()
	)
]);

if ($data['context'] === 'host') {
	$filter_column1->addItem([new CLabel(_('Value'), 'filter_value'),
		new CFormField((new CRadioButtonList('filter_value', (int) $data['filter_value']))
			->addValue(_('All'), -1)
			->addValue(_('Ok'), TRIGGER_VALUE_FALSE)
			->addValue(_('Problem'), TRIGGER_VALUE_TRUE)
			->setModern()
		)
	]);
}

$filter_tags = $data['filter_tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter_evaltype'],
	'tags' => $filter_tags
]);

$filter_column2 = (new CFormGrid())
	->addItem([new CLabel(_('Tags')), new CFormField($filter_tags_table)])
	->addItem([new CLabel(_('Inherited'), 'filter_inherited'),
		new CFormField((new CRadioButtonList('filter_inherited', (int) $data['filter_inherited']))
			->addValue(_('All'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern())
	]);

if ($data['context'] === 'host') {
	$filter_column2->addItem([new CLabel(_('Discovered'), 'filter_discovered'),
		new CFormField((new CRadioButtonList('filter_discovered', (int) $data['filter_discovered']))
			->addValue(_('All'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern())
	]);
}

$filter_column2->addItem([new CLabel(_('With dependencies'), 'filter_dependent'),
	new CFormField((new CRadioButtonList('filter_dependent', (int) $data['filter_dependent']))
		->addValue(_('All'), -1)
		->addValue(_('Yes'), 1)
		->addValue(_('No'), 0)
		->setModern())
]);

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))
		->setArgument('action', 'trigger.list')
		->setArgument('context', $data['context']))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', 'trigger.list', 'filter_action')
	->addvar('context', $data['context'], 'filter_context')
	->addFilterTab(_('Filter'), [$filter_column1, $filter_column2]);

$html_page = (new CHtmlPage())
	->setTitle(_('Triggers'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_TRIGGERS_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATE_TRIGGERS_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['single_selected_hostid'] != 0
						? (new CSimpleButton(_('Create trigger')))
							->setId('js-create')
							->setAttribute('data-hostid', $data['single_selected_hostid'])
						: (new CButton('form',
							$data['context'] === 'host'
								? _('Create trigger (select host first)')
								: _('Create trigger (select template first)')
						))->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['single_selected_hostid'] != 0) {
	$html_page->setNavigation(getHostNavigation('triggers', $data['single_selected_hostid']));
}

$html_page->addItem($filter);

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'trigger.list')
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$triggers_form = (new CForm('post', $url))
	->setName('trigger_form')
	->addVar('context', $data['context'], 'form_context');

// create table
$triggers_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))
				->onClick("checkAll('".$triggers_form->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $data['sort'], $data['sortorder'], $url),
		$data['show_value_column'] ? _('Value') : null,
		($data['single_selected_hostid'] == 0)
			? ($data['context'] === 'host')
				? _('Host')
				: _('Template')
			: null,
		make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder'], $url),
		_('Operational data'),
		_('Expression'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		$data['show_info_column'] ? _('Info') : null,
		_('Tags')
	])
	->setPageNavigation($data['paging']);

$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression'],
	'context' => $data['context']
]);

foreach ($data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];

	// description
	$description = [];
	$description[] = makeTriggerTemplatePrefix($trigger['triggerid'], $data['parent_templates'],
		ZBX_FLAG_DISCOVERY_NORMAL, $data['allowed_ui_conf_templates']
	);

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

	if ($trigger['discoveryRule']) {
		$description[] = (new CLink(
			$trigger['discoveryRule']['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'trigger.prototype.list')
				->setArgument('parent_discoveryid', $trigger['discoveryRule']['itemid'])
				->setArgument('context', $data['context'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	$trigger_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'trigger.edit')
		->setArgument('triggerid', $triggerid)
		->setArgument('hostid', $data['single_selected_hostid'])
		->setArgument('context', $data['context'])
		->getUrl();

	$description[] = (new CLink($trigger['description'], $trigger_url))->addClass(ZBX_STYLE_WORDBREAK);

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$trigger_deps = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$dep_trigger = $data['dep_triggers'][$dependency['triggerid']];

			$dep_trigger_desc =
				implode(', ', array_column($dep_trigger['hosts'], 'name')).NAME_DELIMITER.$dep_trigger['description'];

			$dep_trigger_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'trigger.edit')
				->setArgument('triggerid', $dep_trigger['triggerid'])
				->setArgument('hostid', $data['single_selected_hostid'])
				->setArgument('context', $data['context'])
				->getUrl();

			$trigger_deps[] = (new CLink($dep_trigger_desc, $dep_trigger_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(triggerIndicatorStyle($dep_trigger['status']));

			$trigger_deps[] = BR();
		}
		array_pop($trigger_deps);

		$description = array_merge($description, [(new CDiv($trigger_deps))->addClass('dependencies')]);
	}

	$disable_source = $trigger['status'] == TRIGGER_STATUS_DISABLED && $trigger['triggerDiscovery']
		? $trigger['triggerDiscovery']['disable_source']
		: '';

	// info
	if ($data['show_info_column']) {
		$info_icons = [];
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && $trigger['error']) {
			$info_icons[] = makeErrorIcon((new CDiv($trigger['error']))->addClass(ZBX_STYLE_WORDBREAK));
		}

		if ($trigger['triggerDiscovery'] && $trigger['triggerDiscovery']['status'] == ZBX_LLD_STATUS_LOST) {
			$info_icons[] = getLldLostEntityIndicator(time(), $trigger['triggerDiscovery']['ts_delete'],
				$trigger['triggerDiscovery']['ts_disable'], $disable_source,
				$trigger['status'] == TRIGGER_STATUS_DISABLED, _('trigger')
			);
		}
	}

	// status
	$status = (new CLink(triggerIndicator($trigger['status'], $trigger['state'])))
		->setAttribute('data-triggerid', $triggerid)
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		->addClass(($trigger['status'] == TRIGGER_STATUS_DISABLED) ? 'js-enable-trigger' : 'js-disable-trigger');

	// hosts
	$hosts = null;

	if ($data['single_selected_hostid'] == 0) {
		foreach ($trigger['hosts'] as $hostid => $host) {
			if (!empty($hosts)) {
				$hosts[] = ', ';
			}

			$host_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
				->setArgument($data['context'] === 'host' ? 'hostid' : 'templateid', $host['hostid'])
				->getUrl();

			$hosts[] = in_array($host['hostid'], $data['editable_hosts'])
				? new CLink($host['name'], $host_url)
				: $host['name'];
		}

		$hosts = (new CCol($hosts))->addClass(ZBX_STYLE_WORDBREAK);
	}

	if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
		$expression = [
			_('Problem'), ': ', $trigger['expression'], BR(),
			_('Recovery'), ': ', $trigger['recovery_expression']
		];
	}
	else {
		$expression = $trigger['expression'];
	}

	$host = reset($trigger['hosts']);
	$trigger_value = ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)
		? (new CSpan(trigger_value2str($trigger['value'])))->addClass(
			($trigger['value'] == TRIGGER_VALUE_TRUE) ? ZBX_STYLE_PROBLEM_UNACK_FG : ZBX_STYLE_OK_UNACK_FG
		)
		: '';

	$disabled_by_lld = $disable_source == ZBX_DISABLE_SOURCE_LLD;

	$triggers_table->addRow([
		new CCheckBox('g_triggerid['.$triggerid.']', $triggerid),
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		$data['show_value_column'] ? $trigger_value : null,
		$hosts,
		(new CCol($description))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($trigger['opdata']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CDiv($expression))->addClass(ZBX_STYLE_WORDBREAK),
		(new CDiv([
			$status,
			$disabled_by_lld ? makeDescriptionIcon(_('Disabled automatically by an LLD rule.')) : null
		]))->addClass(ZBX_STYLE_NOWRAP),
		$data['show_info_column'] ? makeInformationList($info_icons) : null,
		$data['tags'][$triggerid]
	]);
}

// append table to form
$triggers_form->addItem([
	$triggers_table,
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.massenable' => [
				'content' => (new CSimpleButton(_('Enable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massenable-trigger')
			],
			'trigger.massdisable' => [
				'content' => (new CSimpleButton(_('Disable')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massdisable-trigger')
			],
			'trigger.masscopyto' => [
				'content' => (new CSimpleButton(_('Copy')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->setId('js-copy')
			],
			'trigger.massupdate' => [
				'content' => (new CSimpleButton(_('Mass update')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->setId('js-massupdate-trigger')
			],
			'trigger.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massdelete-trigger')
			]
		],
		'trigger'
	)
]);

$html_page
	->addItem($triggers_form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $data['checkbox_hash'],
		'checkbox_object' => 'g_triggerid',
		'context' => $data['context'],
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('trigger')],
		'form_name' => $triggers_form->getName()
	]).');
'))
	->setOnDocumentReady()
	->show();
