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

if (!$this->data['hostid']) {
	$create_button = (new CSubmit('form', _('Create trigger (select host first)')))->setEnabled(false);
}
else {
	$create_button = new CSubmit('form', _('Create trigger'));
}

$filter = (new CFilter())
	->setProfile('web.triggers.filter', 0)
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(
				_('Severity'),
				new CSeverity([
					'name' => 'filter_priority', 'value' => (int) $this->data['filter_priority'], 'all' => true
				])
			)
			->addRow(
				_('State'),
				(new CRadioButtonList('filter_state', (int) $this->data['filter_state']))
					->addValue(_('all'), -1)
					->addValue(_('Normal'), TRIGGER_STATE_NORMAL)
					->addValue(_('Unknown'), TRIGGER_STATE_UNKNOWN)
					->setModern(true)
			)
			->addRow(
				_('Status'),
				(new CRadioButtonList('filter_status', (int) $this->data['filter_status']))
					->addValue(_('all'), -1)
					->addValue(triggerIndicator(TRIGGER_STATUS_ENABLED), TRIGGER_STATUS_ENABLED)
					->addValue(triggerIndicator(TRIGGER_STATUS_DISABLED), TRIGGER_STATUS_DISABLED)
					->setModern(true)
			)
	]);

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
					$this->data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Host'), 'hostid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getHostsCB()
				])
			),
		(new CTag('nav', true, ($data['hostid'] != 0)
			? new CRedirectButton(_('Create trigger'), (new CUrl())
					->setArgument('groupid', $data['pageFilter']->groupid)
					->setArgument('hostid', $data['pageFilter']->hostid)
					->setArgument('form', 'create')
					->getUrl()
				)
			: (new CButton('form', _('Create trigger (select host first)')))->setEnabled(false)
		))
			->setAttribute('aria-label', _('Content controls'))
	]));

if ($this->data['hostid']) {
	$widget->addItem(get_header_host_table('triggers', $this->data['hostid']));
}

$widget->addItem($filter);

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('hostid', $this->data['hostid']);

// create table
$triggersTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))->onClick("checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
		($this->data['hostid'] == 0) ? _('Host') : null,
		make_sorting_header(_('Name'), 'description', $this->data['sort'], $this->data['sortorder']),
		_('Expression'),
		make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
		$this->data['showInfoColumn'] ? _('Info') : null
	]);

$this->data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($this->data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression']
]);

foreach ($this->data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];

	// description
	$description = [];

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = (new CSpan(_('Host')))->addClass(ZBX_STYLE_GREY);
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
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
		'triggers.php?form=update&hostid='.$this->data['hostid'].'&triggerid='.$triggerid
	);

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$triggerDependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$depTrigger = $this->data['dependencyTriggers'][$dependency['triggerid']];

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
	if ($this->data['showInfoColumn']) {
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
			'&hostid='.$this->data['hostid'].
			'&g_triggerid='.$triggerid))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		->addSID();

	// hosts
	$hosts = null;
	if ($this->data['hostid'] == 0) {
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

	$triggersTable->addRow([
		new CCheckBox('g_triggerid['.$triggerid.']', $triggerid),
		getSeverityCell($trigger['priority'], $this->data['config']),
		$hosts,
		$description,
		$expression,
		$status,
		$this->data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$triggersForm->addItem([
	$triggersTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected triggers?')],
			'trigger.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected triggers?')],
			'trigger.masscopyto' => ['name' => _('Copy')],
			'trigger.massupdateform' => ['name' => _('Mass update')],
			'trigger.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected triggers?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($triggersForm);

return $widget;
