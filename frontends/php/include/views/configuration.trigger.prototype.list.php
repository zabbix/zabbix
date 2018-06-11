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


$widget = (new CWidget())
	->setTitle(_('Trigger prototypes'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CRedirectButton(_('Create trigger prototype'),
				(new CUrl())
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->setArgument('form', 'create')
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$triggersTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))->onClick("checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Name'), 'description', $this->data['sort'], $this->data['sortorder']),
		_('Expression'),
		make_sorting_header(_('Create enabled'), 'status', $this->data['sort'], $this->data['sortorder'])
	]);

$this->data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($this->data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression']
]);

foreach ($this->data['triggers'] as $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $this->data['parent_discoveryid'];

	// description
	$description = [];

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = (new CSpan(_('Template')))->addClass(ZBX_STYLE_GREY);
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'],
				$real_host['hostid']
			);
			$description[] = (new CLink(
				CHtml::encode($real_host['name']),
				'trigger_prototypes.php?parent_discoveryid='.$tpl_disc_ruleid))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);

			$description[] = NAME_DELIMITER;
		}
	}

	$description[] = new CLink(
		CHtml::encode($trigger['description']),
		'trigger_prototypes.php?'.
			'form=update'.
			'&parent_discoveryid='.$this->data['parent_discoveryid'].
			'&triggerid='.$triggerid
	);

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$triggerDependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$depTrigger = $data['dependencyTriggers'][$dependency['triggerid']];

			$depTriggerDescription = CHtml::encode(
				implode(', ', zbx_objectValues($depTrigger['hosts'], 'name')).NAME_DELIMITER.$depTrigger['description']
			);

			if ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$triggerDependencies[] = (new CLink(
					$depTriggerDescription,
					'trigger_prototypes.php?form=update'.url_param('parent_discoveryid').
						'&triggerid='.$depTrigger['triggerid']))
					->addClass(triggerIndicatorStyle($depTrigger['status']));
			}
			elseif ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$triggerDependencies[] = (new CLink(
					$depTriggerDescription,
					'triggers.php?form=update&triggerid='.$depTrigger['triggerid']))
					->addClass(triggerIndicatorStyle($depTrigger['status']));
			}

			$triggerDependencies[] = BR();
		}
		array_pop($triggerDependencies);

		$description = array_merge($description, [(new CDiv($triggerDependencies))->addClass('dependencies')]);
	}

	// status
	$status = (new CLink(
		($trigger['status'] == TRIGGER_STATUS_DISABLED) ? _('No') : _('Yes'),
		'trigger_prototypes.php?'.
			'action='.(($trigger['status'] == TRIGGER_STATUS_DISABLED)
				? 'triggerprototype.massenable'
				: 'triggerprototype.massdisable'
			).
			'&g_triggerid='.$triggerid.
			'&parent_discoveryid='.$this->data['parent_discoveryid']
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status']))
		->addSID();

	if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
		$expression = [
			_('Problem'), ': ', $trigger['expression'], BR(),
			_('Recovery'), ': ', $trigger['recovery_expression']
		];
	}
	else {
		$expression = $trigger['expression'];
	}

	// checkbox
	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', $triggerid);

	$triggersTable->addRow([
		$checkBox,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$description,
		$expression,
		$status
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');

// append table to form
$triggersForm->addItem([
	$triggersTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'triggerprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Create triggers from selected prototypes as enabled?')
			],
			'triggerprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Create triggers from selected prototypes as disabled?')
			],
			'triggerprototype.massupdateform' => ['name' => _('Mass update')],
			'triggerprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected trigger prototypes?')
			],
		],
		$this->data['parent_discoveryid']
	)
]);

// append form to widget
$widget->addItem($triggersForm);

return $widget;
