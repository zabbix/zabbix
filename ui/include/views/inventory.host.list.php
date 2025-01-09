<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
	->setTitle(_('Host inventory'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::INVENTORY_HOST_LIST));

// Make an inventory field dropdown.
$inventory_field_select = (new CSelect('filter_field'))
	->setValue($data['filter']['field'])
	->setFocusableElementId('label-field');

foreach ($data['host_inventories'] as $inventoryField) {
	$inventory_field_select->addOption(new CSelectOption($inventoryField['db_field'], $inventoryField['title']));
}

// filter
$html_page->addItem(
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
								'with_hosts' => true,
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
		make_sorting_header(_('Host'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Group'),
		make_sorting_header(_('Name'), 'pr_name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'pr_type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('OS'), 'pr_os', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Serial number A'), 'pr_serialno_a', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Tag'), 'pr_tag', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('MAC address A'), 'pr_macaddress_a', $data['sort'], $data['sortorder'], $url)
	])
	->setPageNavigation($data['paging']);

foreach ($data['hosts'] as $host) {
	$hostgroups = array_column($host['hostgroups'], 'name');
	natsort($hostgroups);

	$row = [
		(new CCol(
			(new CLink($host['name'], (new CUrl('hostinventories.php'))->setArgument('hostid', $host['hostid'])))
				->addClass($host['status'] == HOST_STATUS_NOT_MONITORED ? ZBX_STYLE_RED : null)
		))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(implode(', ', $hostgroups)))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['name'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['type'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['os'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['serialno_a'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['tag'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol(zbx_str2links($host['inventory']['macaddress_a'])))->addClass(ZBX_STYLE_WORDBREAK)
	];

	$table->addRow($row);
}

$html_page
	->addItem($table)
	->show();
