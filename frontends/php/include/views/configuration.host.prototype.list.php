<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->setControls((new CForm('get'))
		->cleanItems()
		->addVar('parent_discoveryid', $this->data['parent_discoveryid'])
		->addItem((new CList())->addItem(new CSubmit('form', _('Create host prototype'))))
	)
	->addItem(
		get_header_host_table('hosts', $this->data['discovery_rule']['hostid'], $this->data['parent_discoveryid'])
	);

// create form
$itemForm = (new CForm())
	->setName('hosts')
	->addVar('parent_discoveryid', $this->data['parent_discoveryid']);

// create table
$hostTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Templates'),
		make_sorting_header(_('Create enabled'), 'status', $this->data['sort'], $this->data['sortorder'])
	]);

foreach ($this->data['hostPrototypes'] as $hostPrototype) {
	// name
	$name = [];
	if ($hostPrototype['templateid']) {
		$sourceTemplate = $hostPrototype['sourceTemplate'];
		$name[] = (new CLink($sourceTemplate['name'], '?parent_discoveryid='.$hostPrototype['sourceDiscoveryRuleId']))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_GREY);
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($hostPrototype['name'], '?form=update&parent_discoveryid='.$this->data['discovery_rule']['itemid'].'&hostid='.$hostPrototype['hostid']);

	// template list
	if (empty($hostPrototype['templates'])) {
		$hostTemplates = '';
	}
	else {
		$hostTemplates = [];
		order_result($hostPrototype['templates'], 'name');

		foreach ($hostPrototype['templates'] as $template) {

			$caption = [];
			$caption[] = (new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);

			$linkedTemplates = $this->data['linkedTemplates'][$template['templateid']]['parentTemplates'];
			if ($linkedTemplates) {
				order_result($linkedTemplates, 'name');

				$caption[] = ' (';
				foreach ($linkedTemplates as $tpl) {
					$caption[] = (new CLink($tpl['name'],'templates.php?form=update&templateid='.$tpl['templateid']))
						->addClass(ZBX_STYLE_LINK_ALT)
						->addClass(ZBX_STYLE_GREY);
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
		($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED) ? _('Yes') : _('No'),
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

zbx_add_post_js('cookie.prefix = "'.$this->data['discovery_rule']['itemid'].'";');

// append table to form
$itemForm->addItem([
	$hostTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_hostid',
		[
			'hostprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Enable selected host prototypes?')
			],
			'hostprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Disable selected host prototypes?')
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
