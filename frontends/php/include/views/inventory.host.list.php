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

$hostInventoryWidget = (new CWidget())
	->setTitle(_('Host inventory'))
	->setControls((new CForm('get'))
		->setAttribute('aria-label', _('Main filter'))
		->addItem((new CList())
			->addItem([
				new CLabel(_('Group'), 'groupid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$this->data['pageFilter']->getGroupsCB()
			])
		)
	);

// getting inventory fields to make a drop down
$inventoryFields = getHostInventories(true); // 'true' means list should be ordered by title
$inventoryFieldsComboBox = (new CComboBox('filter_field', $this->data['filterField']))
	->setAttribute('autofocus', 'autofocus');
foreach ($inventoryFields as $inventoryField) {
	$inventoryFieldsComboBox->addItem($inventoryField['db_field'], $inventoryField['title']);
}

// filter
$hostInventoryWidget->addItem(
	(new CFilter())
		->setProfile('web.hostinventories.filter', 0)
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Field'), [
				$inventoryFieldsComboBox,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CComboBox('filter_exact', $this->data['filterExact'], null, [
					0 => _('like'),
					1 => _('exactly')
				]),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CTextBox('filter_field_value', $this->data['filterFieldValue']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			])
		])
);

$table = (new CTableInfo())
	->setHeader([
		make_sorting_header(_('Host'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Group'),
		make_sorting_header(_('Name'), 'pr_name', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Type'), 'pr_type', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('OS'), 'pr_os', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Serial number A'), 'pr_serialno_a', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Tag'), 'pr_tag', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('MAC address A'), 'pr_macaddress_a', $this->data['sort'], $this->data['sortorder'])
	]);

foreach ($this->data['hosts'] as $host) {
	$hostGroups = [];
	foreach ($host['groups'] as $group) {
		$hostGroups[] = $group['name'];
	}
	natsort($hostGroups);
	$hostGroups = implode(', ', $hostGroups);

	$row = [
		(new CLink($host['name'], '?hostid='.$host['hostid'].url_param('groupid')))
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

$table = [$table, $this->data['paging']];
$hostInventoryWidget->addItem($table);

return $hostInventoryWidget;
