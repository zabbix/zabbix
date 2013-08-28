<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


$hostInventoryWidget = new CWidget();

$r_form = new CForm('get');
$r_form->addItem(array(_('Group'), SPACE, $this->data['pageFilter']->getGroupsCB(true)));
$hostInventoryWidget->addPageHeader(_('HOST INVENTORIES'), SPACE);
$hostInventoryWidget->addHeader(_('Hosts'), $r_form);

// HOST INVENTORY FILTER {{{
if (hasRequest('filter_set')) {
	$filterField = getRequest('filter_field');
	$filterFieldValue = getRequest('filter_field_value');
	$filterExact = getRequest('filter_exact');
	CProfile::update('web.hostinventories.filter_field', $filterField, PROFILE_TYPE_STR);
	CProfile::update('web.hostinventories.filter_field_value', $filterFieldValue, PROFILE_TYPE_STR);
	CProfile::update('web.hostinventories.filter_exact', $filterExact, PROFILE_TYPE_INT);
}
else{
	$filterField = CProfile::get('web.hostinventories.filter_field');
	$filterFieldValue = CProfile::get('web.hostinventories.filter_field_value');
	$filterExact = CProfile::get('web.hostinventories.filter_exact');
}

$filter_table = new CTable('', 'filter');
// getting inventory fields to make a drop down
$inventoryFields = getHostInventories(true); // 'true' means list should be ordered by title
$inventoryFieldsComboBox = new CComboBox('filter_field', $filterField);
foreach($inventoryFields as $inventoryField){
	$inventoryFieldsComboBox->addItem(
		$inventoryField['db_field'],
		$inventoryField['title']
	);
}
$exactComboBox = new CComboBox('filter_exact', $filterExact);
$exactComboBox->addItem('0', _('like'));
$exactComboBox->addItem('1', _('exactly'));
$filter_table->addRow(array(
	array(
		array(bold(_('Field')), SPACE, $inventoryFieldsComboBox),
		array(
			$exactComboBox,
			new CTextBox('filter_field_value', $filterFieldValue, 20)
		),
	),
), 'host-inventories');

$filter = new CButton('filter', _('Filter'),
	"javascript: create_var('zbx_filter', 'filter_set', '1', true); chkbxRange.clearSelectedOnFilterChange();"
);
$filter->useJQueryStyle('main');

$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
$reset->useJQueryStyle();

$div_buttons = new CDiv(array($filter, SPACE, $reset));
$div_buttons->setAttribute('style', 'padding: 4px 0px;');

$footer_col = new CCol($div_buttons, 'controls');

$filter_table->addRow($footer_col);

$filter_form = new CForm('get');
$filter_form->setAttribute('name','zbx_filter');
$filter_form->setAttribute('id','zbx_filter');
$filter_form->addItem($filter_table);
$hostInventoryWidget->addFlicker($filter_form, CProfile::get('web.hostinventories.filter.state', 0));
$hostInventoryWidget->addHeaderRowNumber();

$table = new CTableInfo(_('No hosts defined.'));
$table->setHeader(array(
	is_show_all_nodes() ? make_sorting_header(_('Node'), 'hostid') : null,
	make_sorting_header(_('Host'), 'name'),
	_('Group'),
	make_sorting_header(_('Name'), 'pr_name'),
	make_sorting_header(_('Type'), 'pr_type'),
	make_sorting_header(_('OS'), 'pr_os'),
	make_sorting_header(_('Serial number A'), 'pr_serialno_a'),
	make_sorting_header(_('Tag'), 'pr_tag'),
	make_sorting_header(_('MAC address A'), 'pr_macaddress_a'))
);

$hosts = array();
$paging = getPagingLine($hosts);

if($this->data['pageFilter']->groupsSelected){
	// which inventory fields we will need for displaying
	$requiredInventoryFields = array(
		'name',
		'type',
		'os',
		'serialno_a',
		'tag',
		'macaddress_a'
	);

	// checking if correct inventory field is specified for filter
	$possibleInventoryFields = getHostInventories();
	$possibleInventoryFields = zbx_toHash($possibleInventoryFields, 'db_field');
	if(!empty($filterField)
			&& !empty($filterFieldValue)
			&& !isset($possibleInventoryFields[$filterField])){
		error(_s('Impossible to filter by inventory field "%s", which does not exist.', $filterField));
	}
	else{
		// if we are filtering by field, this field is also required
		if(!empty($filterField) && !empty($filterFieldValue)){
			$requiredInventoryFields[] = $filterField;
		}

		$options = array(
			'output' => array('hostid', 'name'),
			'selectInventory' => $requiredInventoryFields,
			'withInventory' => true,
			'selectGroups' => API_OUTPUT_EXTEND,
			'limit' => ($this->data['config']['search_limit'] + 1)
		);
		if($this->data['pageFilter']->groupid > 0)
			$options['groupids'] = $this->data['pageFilter']->groupid;

		$hosts = API::Host()->get($options);

		// copy some inventory fields to the uppers array level for sorting
		// and filter out hosts if we are using filter
		foreach($hosts as $num => $host){
			$hosts[$num]['pr_name'] = $host['inventory']['name'];
			$hosts[$num]['pr_type'] = $host['inventory']['type'];
			$hosts[$num]['pr_os'] = $host['inventory']['os'];
			$hosts[$num]['pr_serialno_a'] = $host['inventory']['serialno_a'];
			$hosts[$num]['pr_tag'] = $host['inventory']['tag'];
			$hosts[$num]['pr_macaddress_a'] = $host['inventory']['macaddress_a'];
			// if we are filtering by inventory field
			if(!empty($filterField) && !empty($filterFieldValue)){
				// must we filter exactly or using a substring (both are case insensitive)
				$match = $filterExact
					? zbx_strtolower($hosts[$num]['inventory'][$filterField]) === zbx_strtolower($filterFieldValue)
						: zbx_strpos(
						zbx_strtolower($hosts[$num]['inventory'][$filterField]),
						zbx_strtolower($filterFieldValue)
					) !== false;
				if(!$match){
					unset($hosts[$num]);
				}
			}
		}

		order_result($hosts, getPageSortField('name'), getPageSortOrder());
		$paging = getPagingLine($hosts);

		foreach($hosts as $host){
			$host_groups = array();
			foreach($host['groups'] as $group){
				$host_groups[] = $group['name'];
			}
			natsort($host_groups);
			$host_groups = implode(', ', $host_groups);

			$row = array(
				get_node_name_by_elid($host['hostid']),
				new CLink($host['name'],'?hostid='.$host['hostid'].url_param('groupid')),
				$host_groups,
				zbx_str2links($host['inventory']['name']),
				zbx_str2links($host['inventory']['type']),
				zbx_str2links($host['inventory']['os']),
				zbx_str2links($host['inventory']['serialno_a']),
				zbx_str2links($host['inventory']['tag']),
				zbx_str2links($host['inventory']['macaddress_a'])
			);

			$table->addRow($row);
		}
	}
}

$table = array($paging, $table, $paging);
$hostInventoryWidget->addItem($table);

return $hostInventoryWidget;
