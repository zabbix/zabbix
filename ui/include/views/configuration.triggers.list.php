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


/**
 * @var CView $this
 * @var array $data
 */

require_once dirname(__FILE__).'/js/configuration.triggers.list.js.php';

$hg_ms_params = ($data['context'] === 'host') ? ['real_hosts' => 1] : ['templated_hosts' => 1];

$filter_column1 = (new CFormList())
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter_groupids_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'groupids',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => ($data['context'] === 'host') ? 'hosts' : 'templates',
			'data' => $data['filter_hostids_ms'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => ($data['context'] === 'host') ? 'hosts' : 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'hostids',
					'dstfld1' => 'filter_hostids_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Name'),
		(new CTextBox('filter_name', $data['filter_name']))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow(_('Severity'),
		(new CCheckBoxList('filter_priority'))
			->setOptions(CSeverityHelper::getSeverities())
			->setChecked($data['filter_priority'])
			->setColumns(3)
			->setVertical(true)
	);

if ($data['context'] === 'host') {
	$filter_column1->addRow(_('State'),
		(new CRadioButtonList('filter_state', (int) $data['filter_state']))
			->addValue(_('all'), -1)
			->addValue(_('Normal'), TRIGGER_STATE_NORMAL)
			->addValue(_('Unknown'), TRIGGER_STATE_UNKNOWN)
			->setModern(true)
	);
}

$filter_column1->addRow(_('Status'),
	(new CRadioButtonList('filter_status', (int) $data['filter_status']))
		->addValue(_('all'), -1)
		->addValue(triggerIndicator(TRIGGER_STATUS_ENABLED), TRIGGER_STATUS_ENABLED)
		->addValue(triggerIndicator(TRIGGER_STATUS_DISABLED), TRIGGER_STATUS_DISABLED)
		->setModern(true)
);

if ($data['context'] === 'host') {
	$filter_column1->addRow(_('Value'),
		(new CRadioButtonList('filter_value', (int) $data['filter_value']))
			->addValue(_('all'), -1)
			->addValue(_('Ok'), TRIGGER_VALUE_FALSE)
			->addValue(_('Problem'), TRIGGER_VALUE_TRUE)
			->setModern(true)
	);
}

$filter_tags = $data['filter_tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter_evaltype'],
	'tags' => $filter_tags
]);

$filter_column2 = (new CFormList())
	->addRow(_('Tags'), $filter_tags_table)
	->addRow(_('Inherited'),
		(new CRadioButtonList('filter_inherited', (int) $data['filter_inherited']))
			->addValue(_('all'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern(true)
	);

if ($data['context'] === 'host') {
	$filter_column2->addRow(_('Discovered'),
		(new CRadioButtonList('filter_discovered', (int) $data['filter_discovered']))
			->addValue(_('all'), -1)
			->addValue(_('Yes'), 1)
			->addValue(_('No'), 0)
			->setModern(true)
	);
}

$filter_column2->addRow(_('With dependencies'),
	(new CRadioButtonList('filter_dependent', (int) $data['filter_dependent']))
		->addValue(_('all'), -1)
		->addValue(_('Yes'), 1)
		->addValue(_('No'), 0)
		->setModern(true)
);

$filter = (new CFilter())
	->setResetUrl((new CUrl('triggers.php'))->setArgument('context', $data['context']))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addvar('context', $data['context'], 'filter_context')
	->addFilterTab(_('Filter'), [$filter_column1, $filter_column2]);

$widget = (new CWidget())
	->setTitle(_('Triggers'))
	->setControls(new CList([
		(new CTag('nav', true, ($data['single_selected_hostid'] != 0)
			? new CRedirectButton(_('Create trigger'), (new CUrl('triggers.php'))
				->setArgument('hostid', $data['single_selected_hostid'])
				->setArgument('form', 'create')
				->setArgument('context', $data['context'])
				->getUrl()
			)
			: (new CButton('form',
				($data['context'] === 'host')
					? _('Create trigger (select host first)')
					: _('Create trigger (select template first)')
			))->setEnabled(false)
		))->setAttribute('aria-label', _('Content controls'))
	]));

if ($data['single_selected_hostid'] != 0) {
	$widget->setNavigation(getHostNavigation('triggers', $data['single_selected_hostid']));
}

$widget->addItem($filter);

$url = (new CUrl('triggers.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$triggers_form = (new CForm('post', $url))
	->setName('triggersForm')
	->addVar('checkbox_hash', $data['checkbox_hash'])
	->addVar('context', $data['context'], 'form_context');

// create table
$triggers_table = (new CTableInfo())->setHeader([
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
]);

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
			CHtml::encode($trigger['discoveryRule']['name']),
			(new CUrl('trigger_prototypes.php'))
				->setArgument('parent_discoveryid', $trigger['discoveryRule']['itemid'])
				->setArgument('context', $data['context'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	$description[] = (new CLink(
		CHtml::encode($trigger['description']),
		(new CUrl('triggers.php'))
			->setArgument('form', 'update')
			->setArgument('triggerid', $triggerid)
			->setArgument('context', $data['context'])
	))->addClass(ZBX_STYLE_WORDWRAP);

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$trigger_deps = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$dep_trigger = $data['dep_triggers'][$dependency['triggerid']];

			$dep_trigger_desc = CHtml::encode(
				implode(', ', array_column($dep_trigger['hosts'], 'name')).NAME_DELIMITER.$dep_trigger['description']
			);

			$trigger_deps[] = (new CLink($dep_trigger_desc,
				(new CUrl('triggers.php'))
					->setArgument('form', 'update')
					->setArgument('triggerid', $dep_trigger['triggerid'])
					->setArgument('context', $data['context'])
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(triggerIndicatorStyle($dep_trigger['status']));

			$trigger_deps[] = BR();
		}
		array_pop($trigger_deps);

		$description = array_merge($description, [(new CDiv($trigger_deps))->addClass('dependencies')]);
	}

	// info
	if ($data['show_info_column']) {
		$info_icons = [];
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && $trigger['error']) {
			$info_icons[] = makeErrorIcon((new CDiv($trigger['error']))->addClass(ZBX_STYLE_WORDWRAP));
		}

		if (array_key_exists('ts_delete', $trigger['triggerDiscovery'])
				&& $trigger['triggerDiscovery']['ts_delete'] > 0) {
			$info_icons[] = getTriggerLifetimeIndicator(time(), $trigger['triggerDiscovery']['ts_delete']);
		}
	}

	// status
	$status = (new CLink(
		triggerIndicator($trigger['status'], $trigger['state']),
		(new CUrl('triggers.php'))
			->setArgument('g_triggerid', $triggerid)
			->setArgument('action', ($trigger['status'] == TRIGGER_STATUS_DISABLED)
				? 'trigger.massenable'
				: 'trigger.massdisable'
			)
			->setArgument('context', $data['context'])
			->getUrl()
		))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		->addSID();

	// hosts
	$hosts = null;
	if ($data['single_selected_hostid'] == 0) {
		foreach ($trigger['hosts'] as $hostid => $host) {
			if (!empty($hosts)) {
				$hosts[] = ', ';
			}
			$hosts[] = $host['name'];
		}
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

	$triggers_table->addRow([
		new CCheckBox('g_triggerid['.$triggerid.']', $triggerid),
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		$data['show_value_column'] ? $trigger_value : null,
		$hosts,
		$description,
		$trigger['opdata'],
		(new CDiv($expression))->addClass(ZBX_STYLE_WORDWRAP),
		$status,
		$data['show_info_column'] ? makeInformationList($info_icons) : null,
		$data['tags'][$triggerid]
	]);
}

// append table to form
$triggers_form->addItem([
	$triggers_table,
	$data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected triggers?')],
			'trigger.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected triggers?')],
			'trigger.masscopyto' => ['name' => _('Copy')],
			'popup.massupdate.trigger' => [
				'content' => (new CButton('', _('Mass update')))
					->onClick(
						"openMassupdatePopup('popup.massupdate.trigger', {}, {
							dialogue_class: 'modal-popup-static',
							trigger_element: this
						});"
					)
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id')
			],
			'trigger.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected triggers?')]
		],
		$data['checkbox_hash']
	)
]);

// append form to widget
$widget->addItem($triggers_form);

$widget->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
