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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

require_once dirname(__FILE__).'/js/configuration.host.prototype.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Host prototypes'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CRedirectButton(_('Create host prototype'),
				(new CUrl('host_prototypes.php'))
					->setArgument('form', 'create')
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->setArgument('context', $data['context'])
					->getUrl()
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(
		getHostNavigation('hosts', $this->data['discovery_rule']['hostid'], $this->data['parent_discoveryid'])
	);

$url = (new CUrl('host_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$itemForm = (new CForm('post', $url))
	->setName('hosts')
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

// create table
$hostTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Templates'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	]);

foreach ($this->data['hostPrototypes'] as $hostPrototype) {
	// name
	$name = [];
	$name[] = makeHostPrototypeTemplatePrefix($hostPrototype['hostid'], $data['parent_templates'],
		$data['allowed_ui_conf_templates']
	);
	$name[] = new CLink(CHtml::encode($hostPrototype['name']),
		(new CUrl('host_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('hostid', $hostPrototype['hostid'])
			->setArgument('context', $data['context'])
	);

	// template list
	if (empty($hostPrototype['templates'])) {
		$hostTemplates = '';
	}
	else {
		$hostTemplates = [];
		order_result($hostPrototype['templates'], 'name');

		foreach ($hostPrototype['templates'] as $template) {
			$caption = [];

			if ($data['allowed_ui_conf_templates']
					&& array_key_exists($template['templateid'], $data['writable_templates'])) {
				$caption[] = (new CLink($template['name'],
					(new CUrl('templates.php'))
						->setArgument('form', 'update')
						->setArgument('templateid', $template['templateid'])
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$caption[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
			}

			$linkedTemplates = $this->data['linkedTemplates'][$template['templateid']]['parentTemplates'];
			if ($linkedTemplates) {
				order_result($linkedTemplates, 'name');

				$caption[] = ' (';
				foreach ($linkedTemplates as $tpl) {
					if (array_key_exists($tpl['templateid'], $data['writable_templates'])) {
						$caption[] = (new CLink($tpl['name'],
							(new CUrl('templates.php'))
								->setArgument('form', 'update')
								->setArgument('templateid', $tpl['templateid'])
						))
							->addClass(ZBX_STYLE_LINK_ALT)
							->addClass(ZBX_STYLE_GREY);
					}
					else {
						$caption[] = (new CSpan($tpl['name']))->addClass(ZBX_STYLE_GREY);
					}

					$caption[] = ', ';
				}
				array_pop($caption);

				$caption[] = ')';
			}

			$hostTemplates[] = $caption;
			$hostTemplates[] = ', ';
		}

		if ($hostTemplates) {
			array_pop($hostTemplates);
		}
	}

	// status
	$status = (new CLink(
		($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED) ? _('No') : _('Yes'),
		(new CUrl('host_prototypes.php'))
			->setArgument('group_hostid', $hostPrototype['hostid'])
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('action', ($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED)
				? 'hostprototype.massenable'
				: 'hostprototype.massdisable'
			)
			->setArgument('context', $data['context'])
			->getUrl()
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($hostPrototype['status']))
		->addSID();

	$nodiscover = ($hostPrototype['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($nodiscover ? _('No') : _('Yes'),
			(new CUrl('host_prototypes.php'))
				->setArgument('hostid', $hostPrototype['hostid'])
				->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
				->setArgument('action', 'hostprototype.updatediscover')
				->setArgument('discover', $nodiscover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
				->setArgument('context', $data['context'])
				->getUrl()
		))
			->addSID()
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	$hostTable->addRow([
		new CCheckBox('group_hostid['.$hostPrototype['hostid'].']', $hostPrototype['hostid']),
		$name,
		$hostTemplates,
		$status,
		$discover,
		$data['tags'][$hostPrototype['hostid']]
	]);
}

// append table to form
$itemForm->addItem([
	$hostTable,
	$data['paging'],
	new CActionButtonList('action', 'group_hostid',
		[
			'hostprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Create hosts from selected prototypes as enabled?')
			],
			'hostprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Create hosts from selected prototypes as disabled?')
			],
			'hostprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected host prototypes?')
			]
		],
		$data['discovery_rule']['itemid']
	)
]);

// append form to widget
$widget->addItem($itemForm);

$widget->show();
