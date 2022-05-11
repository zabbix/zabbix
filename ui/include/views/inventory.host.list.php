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

$widget = (new CWidget())->setTitle(_('Host inventory'));

// Make an inventory field dropdown.
$inventory_field_select = (new CSelect('filter_field'))
	->setValue($data['filter']['field'])
	->setFocusableElementId('label-field');

foreach ($data['host_inventories'] as $inventoryField) {
	$inventory_field_select->addOption(new CSelectOption($inventoryField['db_field'], $inventoryField['title']));
}

// filter
$widget->addItem(
	(new CFilter())
		->setResetUrl(new CUrl('hostinventories.php'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(
					(new CLabel(_('Host groups'), 'filter_groups__ms')),
					(new CMultiSelect([
						'name' => 'filter_groups[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groups'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groups_',
								'real_hosts' => 1,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
				->addRow(new CLabel(_('Field'), 'label-field'), [
					$inventory_field_select,
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CSelect('filter_exact'))
						->setValue($data['filter']['exact'])
						->addOptions(CSelect::createOptionsFromArray([
							0 => _('contains'),
							1 => _('equals')
						])),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CTextBox('filter_field_value', $data['filter']['fieldValue']))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				])
		])
);

// create table
$url = (new CUrl('hostinventories.php'))->getUrl();

$table = (new CTableInfo())
	->setHeader([
		make_sorting_header(_('Host'), 'name', $this->data['sort'], $this->data['sortorder'], $url),
		_('Group'),
		make_sorting_header(_('Name'), 'pr_name', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('Type'), 'pr_type', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('OS'), 'pr_os', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('Serial number A'), 'pr_serialno_a', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('Tag'), 'pr_tag', $this->data['sort'], $this->data['sortorder'], $url),
		make_sorting_header(_('MAC address A'), 'pr_macaddress_a', $this->data['sort'], $this->data['sortorder'], $url)
	]);

foreach ($this->data['hosts'] as $host) {
	$hostGroups = [];
	foreach ($host['groups'] as $group) {
		$hostGroups[] = $group['name'];
	}
	natsort($hostGroups);
	$hostGroups = implode(', ', $hostGroups);

	$row = [
		(new CLink($host['name'], (new CUrl('hostinventories.php'))->setArgument('hostid', $host['hostid'])))
			->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null),
		$hostGroups,
		zbx_str2links($host['inventory']['name']),
		zbx_str2links($host['inventory']['type']),
		zbx_str2links($host['inventory']['os']),
		zbx_str2links($host['inventory']['serialno_a']),
		zbx_str2links($host['inventory']['tag']),
		zbx_str2links($host['inventory']['macaddress_a'])
	];

	$table->addRow($row);
}

$widget->addItem([$table, $this->data['paging']]);

$widget->show();
