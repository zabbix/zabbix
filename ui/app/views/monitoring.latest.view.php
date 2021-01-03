<?php
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
 * @var CView $this
 */

$this->addJsFile('multiselect.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.latest.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Latest data'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$widget->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'latest.view')))
		->setProfile('web.latest.filter')
		->setActiveTab($data['active_tab'])
		->addFormItem((new CVar('action', 'latest.view'))->removeId())
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
					(new CMultiSelect([
						'name' => 'filter_groupids[]',
						'object_name' => 'hostGroup',
						'data' => $data['multiselect_hostgroup_data'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groupids_',
								'real_hosts' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
					(new CMultiSelect([
						'name' => 'filter_hostids[]',
						'object_name' => 'hosts',
						'data' => $data['multiselect_host_data'],
						'popup' => [
							'filter_preselect_fields' => [
								'hostgroups' => 'filter_groupids_'
							],
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('Application'), [
					(new CTextBox('filter_application', $data['filter']['application']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('application_name', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
						->onClick('return PopUp("popup.generic", jQuery.extend('.
							json_encode([
								'srctbl' => 'applications',
								'srcfld1' => 'name',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_application',
								'real_hosts' => '1',
								'with_applications' => '1'
							]).', getFirstMultiselectValue("filter_hostids_")), null, this);'
						)
				]),
			(new CFormList())
				->addRow(_('Name'), (new CTextBox('filter_select', $data['filter']['select']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('Show items without data'),
					(new CCheckBox('filter_show_without_data'))
						->setChecked($data['filter']['show_without_data'] == 1)
						->setAttribute('disabled', $data['filter']['hostids'] ? null : 'disabled')
				)
				->addRow(_('Show details'),
					(new CCheckBox('filter_show_details'))->setChecked($data['filter']['show_details'] == 1)
				)
		])
	);
}

$widget->addItem(new CPartial('monitoring.latest.view.html', array_intersect_key($data, array_flip(['filter',
	'sort_field', 'sort_order', 'view_curl', 'paging', 'rows', 'hosts', 'applications', 'applications_size',
	'applications_index', 'items', 'history', 'collapsed_index', 'collapsed_all'
]))));

$widget->show();

// Initialize page refresh.
(new CScriptTag('latest_page.start();'))
	->setOnDocumentReady()
	->show();
