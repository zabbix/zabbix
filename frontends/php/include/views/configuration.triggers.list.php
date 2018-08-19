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


require_once dirname(__FILE__).'/js/configuration.triggers.list.js.php';

if (!$data['hostid']) {
	$create_button = (new CSubmit('form', _('Create trigger (select host first)')))->setEnabled(false);
}
else {
	$create_button = new CSubmit('form', _('Create trigger'));
}

$filter_form = (new CFormList())
	->addRow(_('Severity'),
		new CSeverity([
			'name' => 'filter_priority',
			'value' => (int) $data['filter_priority'],
			'all' => true
		])
	)
	->addRow(_('State'),
		(new CRadioButtonList('filter_state', (int) $data['filter_state']))
			->addValue(_('all'), -1)
			->addValue(_('Normal'), TRIGGER_STATE_NORMAL)
			->addValue(_('Unknown'), TRIGGER_STATE_UNKNOWN)
			->setModern(true)
	)
	->addRow(_('Status'),
		(new CRadioButtonList('filter_status', (int) $data['filter_status']))
			->addValue(_('all'), -1)
			->addValue(triggerIndicator(TRIGGER_STATUS_ENABLED), TRIGGER_STATUS_ENABLED)
			->addValue(triggerIndicator(TRIGGER_STATUS_DISABLED), TRIGGER_STATUS_DISABLED)
			->setModern(true)
	);

if ($data['show_value_column']) {
	$filter_form->addRow(_('Value'),
		(new CRadioButtonList('filter_value', (int) $data['filter_value']))
			->addValue(_('all'), -1)
			->addValue(_('Ok'), TRIGGER_VALUE_FALSE)
			->addValue(_('Problem'), TRIGGER_VALUE_TRUE)
			->setModern(true)
	);
}

$filter = (new CFilter())
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [$filter_form]);

$widget = (new CWidget())
	->setTitle(_('Triggers'))
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Host'), 'hostid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getHostsCB()
				])
			),
		(new CTag('nav', true, ($data['hostid'] != 0)
			? new CRedirectButton(_('Create trigger'), (new CUrl('triggers.php'))
				->setArgument('groupid', $data['pageFilter']->groupid)
				->setArgument('hostid', $data['pageFilter']->hostid)
				->setArgument('form', 'create')
				->getUrl()
			)
			: (new CButton('form', _('Create trigger (select host first)')))->setEnabled(false)
		))->setAttribute('aria-label', _('Content controls'))
	]));

if ($data['hostid']) {
	$widget->addItem(get_header_host_table('triggers', $data['hostid']));
}

$widget->addItem($filter);

// create form
$triggers_form = (new CForm())
	->setName('triggersForm')
	->addVar('hostid', $data['hostid']);

// create table
$triggers_table = (new CTableInfo())->setHeader([
	(new CColHeader(
		(new CCheckBox('all_triggers'))
			->onClick("checkAll('".$triggers_form->getName()."', 'all_triggers', 'g_triggerid');")
	))->addClass(ZBX_STYLE_CELL_WIDTH),
	make_sorting_header(_('Severity'), 'priority', $data['sort'], $data['sortorder']),
	$data['show_value_column'] ? _('Value') : null,
	($data['hostid'] == 0) ? _('Host') : null,
	make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder']),
	_('Expression'),
	make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder']),
	$data['showInfoColumn'] ? _('Info') : null
]);

$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression']
]);

foreach ($data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];

	// description
	$description = [];

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

	if ($trigger['templateid'] > 0) {
		if (!isset($data['realHosts'][$triggerid])) {
			$description[] = (new CSpan(_('Host')))->addClass(ZBX_STYLE_GREY);
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			if (array_key_exists($real_host['hostid'], $data['writable_templates'])) {
				$description[] = (new CLink(CHtml::encode($real_host['name']),
					'triggers.php?hostid='.$real_host['hostid']
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$description[] = (new CSpan(CHtml::encode($real_host['name'])))->addClass(ZBX_STYLE_GREY);
			}

			$description[] = NAME_DELIMITER;
		}
	}

	if ($trigger['discoveryRule']) {
		$description[] = (new CLink(
			CHtml::encode($trigger['discoveryRule']['name']),
			'trigger_prototypes.php?parent_discoveryid='.$trigger['discoveryRule']['itemid']))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(
		CHtml::encode($trigger['description']),
		'triggers.php?form=update&hostid='.$data['hostid'].'&triggerid='.$triggerid
	);

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$triggerDependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$depTrigger = $data['dependencyTriggers'][$dependency['triggerid']];

			$depTriggerDescription = CHtml::encode(
				implode(', ', zbx_objectValues($depTrigger['hosts'], 'name')).NAME_DELIMITER.$depTrigger['description']
			);

			$triggerDependencies[] = (new CLink($depTriggerDescription,
				'triggers.php?form=update&triggerid='.$depTrigger['triggerid']
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(triggerIndicatorStyle($depTrigger['status']));

			$triggerDependencies[] = BR();
		}
		array_pop($triggerDependencies);

		$description = array_merge($description, [(new CDiv($triggerDependencies))->addClass('dependencies')]);
	}

	// info
	if ($data['showInfoColumn']) {
		$info_icons = [];
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && $trigger['error']) {
			$info_icons[] = makeErrorIcon($trigger['error']);
		}
	}

	// status
	$status = (new CLink(
		triggerIndicator($trigger['status'], $trigger['state']),
		'triggers.php?'.
			'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
				? 'trigger.massenable'
				: 'trigger.massdisable'
			).
			'&hostid='.$data['hostid'].
			'&g_triggerid='.$triggerid))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		->addSID();

	// hosts
	$hosts = null;
	if ($data['hostid'] == 0) {
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
		getSeverityCell($trigger['priority'], $data['config']),
		$data['show_value_column'] ? $trigger_value : null,
		$hosts,
		$description,
		$expression,
		$status,
		$data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

zbx_add_post_js('cookie.prefix = "'.$data['hostid'].'";');

// append table to form
$triggers_form->addItem([
	$triggers_table,
	$data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected triggers?')],
			'trigger.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected triggers?')],
			'trigger.masscopyto' => ['name' => _('Copy')],
			'trigger.massupdateform' => ['name' => _('Mass update')],
			'trigger.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected triggers?')]
		],
		$data['hostid']
	)
]);

// append form to widget
$widget->addItem($triggers_form);

return $widget;
