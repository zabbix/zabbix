<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * @var CPartial $this
 */

$this->includeJsFile('common.filter.item.js.php');

$filter_column_left = (new CFormList())
	->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
		(new CMultiSelect([
			'multiple' => true,
			'name' => 'filter_groupids[]',
			'object_name' => 'hostGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'with_monitored_items' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'multiple' => true,
			'name' => 'filter_hostids[]',
			'object_name' => 'hosts',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'filter_groupids_'
				],
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_hostids_',
					'monitored_hosts' => true,
					'with_monitored_items' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	);

$filter_column_right = (new CFormList())
	->addRow(_('Tags'),
		CTagFilterFieldHelper::getTagFilterField([
			'evaltype' => $data['filter']['evaltype'],
			'tags' => $data['filter']['tags']
		])
	)
	->addRow(_('Show suppressed problems'),
		(new CCheckBox('show_suppressed'))->setChecked(
			$data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE
		)
	);

(new CFilter((new CUrl('overview.php'))->setArgument('type', SHOW_DATA)))
	->addVar('type', SHOW_DATA)
	->addFilterTab(_('Filter'), [$filter_column_left, $filter_column_right])
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->show();
