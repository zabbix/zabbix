<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Host prototypes'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CRedirectButton(_('Create host prototype'),
				(new CUrl('host_prototypes.php'))
					->setArgument('form', 'create')
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl()
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(
		get_header_host_table('hosts', $this->data['discovery_rule']['hostid'], $this->data['parent_discoveryid'])
	);

// create form
$itemForm = (new CForm())
	->setName('hosts')
	->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

$url = (new CUrl('host_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->getUrl();

// create table
$hostTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Templates'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url)
	]);

foreach ($this->data['hostPrototypes'] as $hostPrototype) {
	// name
	$name = [];
	$name[] = makeHostPrototypeTemplatePrefix($hostPrototype['hostid'], $data['parent_templates']);
	$name[] = new CLink(CHtml::encode($hostPrototype['name']),
		(new CUrl('host_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('hostid', $hostPrototype['hostid'])
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

			if (array_key_exists($template['templateid'], $data['writable_templates'])) {
				$caption[] = (new CLink($template['name'],
					'templates.php?form=update&templateid='.$template['templateid']
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
							'templates.php?form=update&templateid='.$tpl['templateid']
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
		'?group_hostid='.$hostPrototype['hostid'].
			'&parent_discoveryid='.$this->data['discovery_rule']['itemid'].
			'&action='.(($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED)
				? 'hostprototype.massenable'
				: 'hostprototype.massdisable'
			)
	))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($hostPrototype['status']))
		->addSID();

	$hostTable->addRow([
		new CCheckBox('group_hostid['.$hostPrototype['hostid'].']', $hostPrototype['hostid']),
		$name,
		$hostTemplates,
		$status
	]);
}

// append table to form
$itemForm->addItem([
	$hostTable,
	$this->data['paging'],
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
		$this->data['discovery_rule']['itemid']
	)
]);

// append form to widget
$widget->addItem($itemForm);

return $widget;
