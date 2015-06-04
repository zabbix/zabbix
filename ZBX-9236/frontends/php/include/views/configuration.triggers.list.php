<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$triggersWidget = (new CWidget('trigger-list'))->setTitle(_('Triggers'));

// append host summary to widget header
if ($this->data['hostid']) {
	$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid']));
}

// create new application button
$createForm = (new CForm('get'))->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);

$controls = new CList();
$controls->addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()]);
$controls->addItem([SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()]);

if (!$this->data['hostid']) {
	$createButton = new CSubmit('form', _('Create trigger (select host first)'));
	$createButton->setEnabled(false);
	$controls->addItem($createButton);
}
else {
	$controls->addItem(new CSubmit('form', _('Create trigger')));
}

$createForm->addItem($controls);
$triggersWidget->setControls($createForm);

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('hostid', $this->data['hostid']);

// create table
$triggersTable = new CTableInfo();
$triggersTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_triggers', null, "checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');")))->
		addClass('cell-width'),
	make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
	($this->data['hostid'] == 0) ? _('Host') : null,
	make_sorting_header(_('Name'), 'description', $this->data['sort'], $this->data['sortorder']),
	_('Expression'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
	$this->data['showInfoColumn'] ? _('Info') : null
]);

foreach ($this->data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];

	// description
	$description = [];

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
	$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
	$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = new CSpan(_('Host'), ZBX_STYLE_GREY);
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			$description[] = new CLink(
				CHtml::encode($real_host['name']),
				'triggers.php?hostid='.$real_host['hostid'],
				ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY
			);

			$description[] = NAME_DELIMITER;
		}
	}

	if ($trigger['discoveryRule']) {
		$description[] = new CLink(
			CHtml::encode($trigger['discoveryRule']['name']),
			'trigger_prototypes.php?parent_discoveryid='.$trigger['discoveryRule']['itemid'],
			ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_ORANGE
		);
		$description[] = NAME_DELIMITER.$trigger['description'];
	}
	else {
		$description[] = new CLink(
			CHtml::encode($trigger['description']),
			'triggers.php?form=update&hostid='.$this->data['hostid'].'&triggerid='.$triggerid
		);
	}

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$triggerDependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$depTrigger = $this->data['dependencyTriggers'][$dependency['triggerid']];

			$depTriggerDescription = CHtml::encode(
				implode(', ', zbx_objectValues($depTrigger['hosts'], 'name')).NAME_DELIMITER.$depTrigger['description']
			);

			if ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$triggerDependencies[] = new CLink(
					$depTriggerDescription,
					'triggers.php?form=update&triggerid='.$depTrigger['triggerid'],
					ZBX_STYLE_LINK_ALT.' '.triggerIndicatorStyle($depTrigger['status'])
				);
			}
			else {
				$triggerDependencies[] = $depTriggerDescription;
			}

			$triggerDependencies[] = BR();
		}
		array_pop($triggerDependencies);

		$description = array_merge($description, [new CDiv($triggerDependencies, 'dependencies')]);
	}


	// info
	if ($this->data['showInfoColumn']) {
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && $trigger['error']) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($trigger['error'], ZBX_STYLE_RED);
		}
		else {
			$info = '';
		}
	}
	else {
		$info = null;
	}

	// status
	$status = new CLink(
		triggerIndicator($trigger['status'], $trigger['state']),
		'triggers.php?'.
			'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
				? 'trigger.massenable'
				: 'trigger.massdisable'
			).
			'&hostid='.$this->data['hostid'].
			'&g_triggerid='.$triggerid,
		ZBX_STYLE_LINK_ACTION.' '.triggerIndicatorStyle($trigger['status'], $trigger['state'])
	);

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

	// checkbox
	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', null, null, $triggerid);
	$checkBox->setEnabled(empty($trigger['discoveryRule']));

	$triggersTable->addRow([
		$checkBox,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$hosts,
		$description,
		(new CCol(triggerExpression($trigger, true)))->addClass('trigger-expression'),
		$status,
		$info
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
			'trigger.masscopyto' =>  ['name' => _('Copy')],
			'trigger.massupdateform' => ['name' => _('Mass update')],
			'trigger.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected triggers?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
