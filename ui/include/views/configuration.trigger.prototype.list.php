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
 */

require_once dirname(__FILE__).'/js/configuration.trigger.prototype.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Trigger prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::CONFIGURATION_HOST_TRIGGER_PROTOTYPE_LIST
		: CDocHelper::CONFIGURATION_TEMPLATES_TRIGGER_PROTOTYPE_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					new CRedirectButton(_('Create trigger prototype'),
						(new CUrl('trigger_prototypes.php'))
							->setArgument('parent_discoveryid', $data['parent_discoveryid'])
							->setArgument('form', 'create')
							->setArgument('context', $data['context'])
					)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));

$url = (new CUrl('trigger_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$triggersForm = (new CForm('post', $url))
	->setName('triggersForm')
	->addVar('parent_discoveryid', $data['parent_discoveryid'], 'form_parent_discoveryid')
	->addVar('context', $data['context'], 'form_context');

// create table
$triggersTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))->onClick("checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder'], $url),
		_('Operational data'),
		_('Expression'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	]);

$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression'],
	'context' => $data['context']
]);

foreach ($data['triggers'] as $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $data['parent_discoveryid'];

	// description
	$description = [];
	$description[] = makeTriggerTemplatePrefix($trigger['triggerid'], $data['parent_templates'],
		ZBX_FLAG_DISCOVERY_PROTOTYPE, $data['allowed_ui_conf_templates']
	);

	$description[] = new CLink(
		CHtml::encode($trigger['description']),
		(new CUrl('trigger_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('triggerid', $triggerid)
			->setArgument('context', $data['context'])
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
					(new CUrl('trigger_prototypes.php'))
						->setArgument('form', 'update')
						->setArgument('parent_discoveryid', $data['parent_discoveryid'])
						->setArgument('triggerid', $depTrigger['triggerid'])
						->setArgument('context', $data['context'])
				))->addClass(triggerIndicatorStyle($depTrigger['status']));
			}
			elseif ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$triggerDependencies[] = (new CLink(
					$depTriggerDescription,
					(new CUrl('triggers.php'))
						->setArgument('form', 'update')
						->setArgument('triggerid', $depTrigger['triggerid'])
						->setArgument('context', $data['context'])
				))->addClass(triggerIndicatorStyle($depTrigger['status']));
			}

			$triggerDependencies[] = BR();
		}
		array_pop($triggerDependencies);

		$description = array_merge($description, [(new CDiv($triggerDependencies))->addClass('dependencies')]);
	}

	// status
	$status = (new CLink(
		($trigger['status'] == TRIGGER_STATUS_DISABLED) ? _('No') : _('Yes'),
		(new CUrl('trigger_prototypes.php'))
			->setArgument('g_triggerid', $triggerid)
			->setArgument('parent_discoveryid', $data['parent_discoveryid'])
			->setArgument('action', ($trigger['status'] == TRIGGER_STATUS_DISABLED)
				? 'triggerprototype.massenable'
				: 'triggerprototype.massdisable'
			)
			->setArgument('context', $data['context'])
			->getUrl()
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status']))
		->addSID();


	$nodiscover = ($trigger['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($nodiscover ? _('No') : _('Yes'),
			(new CUrl('trigger_prototypes.php'))
				->setArgument('g_triggerid[]', $triggerid)
				->setArgument('parent_discoveryid', $data['parent_discoveryid'])
				->setArgument('action', $nodiscover
					? 'triggerprototype.discover.enable'
					: 'triggerprototype.discover.disable'
				)
				->setArgument('context', $data['context'])
				->setArgumentSID()
				->getUrl()
		))
			->addSID()
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

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
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		$description,
		$trigger['opdata'],
		(new CDiv($expression))->addClass(ZBX_STYLE_WORDWRAP),
		$status,
		$discover,
		$data['tags'][$triggerid]
	]);
}

// append table to form
$triggersForm->addItem([
	$triggersTable,
	$data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'triggerprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Create triggers from selected prototypes as enabled?')
			],
			'triggerprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Create triggers from selected prototypes as disabled?')
			],
			'popup.massupdate.triggerprototype' => [
				'content' => (new CButton('', _('Mass update')))
					->onClick(
						"openMassupdatePopup('popup.massupdate.triggerprototype', {}, {
							dialogue_class: 'modal-popup-static',
							trigger_element: this
						});"
					)
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id')
			],
			'triggerprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected trigger prototypes?')
			]
		],
		$this->data['parent_discoveryid']
	)
]);

// append form to widget
$widget->addItem($triggersForm);

$widget->show();
