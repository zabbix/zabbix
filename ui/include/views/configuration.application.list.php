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
$filter = (new CFilter(new CUrl('applications.php')))
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
							'with_hosts_and_templates' => 1,
							'editable' => 1,
							'enrich_parent_groups' => 1
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(
				(new CLabel(_('Hosts'), 'filter_hosts__ms')),
				(new CMultiSelect([
					'name' => 'filter_hostids[]',
					'object_name' => 'host_templates',
					'data' => $data['filter']['hosts'],
					'popup' => [
						'filter_preselect_fields' => [
							'hostgroups' => 'filter_groups_'
						],
						'parameters' => [
							'srctbl' => 'host_templates',
							'srcfld1' => 'hostid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_hostids_',
							'editable' => 1
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
	]);

// create form
$form = (new CForm())->setName('application_form');

// create table
$applicationTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_applications'))
				->onClick("checkAll('".$form->getName()."', 'all_applications', 'applications');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($this->data['hostid'] > 0) ? null : _('Host'),
		make_sorting_header(_('Application'), 'name', $this->data['sort'], $this->data['sortorder'],
			(new CUrl('applications.php'))->getUrl()
		),
		_('Items'),
		$data['showInfoColumn'] ? _('Info') : null
	]);

$current_time = time();

foreach ($data['applications'] as $application) {
	$info_icons = [];

	// inherited app, display the template list
	if ($application['templateids']) {
		$name = makeApplicationTemplatePrefix($application['applicationid'], $data['parent_templates']);
		$name[] = $application['name'];
	}
	elseif ($application['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $application['discoveryRule']) {
		$name = [(new CLink(CHtml::encode($application['discoveryRule']['name']),
						'disc_prototypes.php?parent_discoveryid='.$application['discoveryRule']['itemid']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_ORANGE)
		];
		$name[] = NAME_DELIMITER.$application['name'];

		if ($application['applicationDiscovery']['ts_delete'] != 0) {
			$info_icons[] = getApplicationLifetimeIndicator(
				$current_time, $application['applicationDiscovery']['ts_delete']
			);
		}
	}
	else {
		$name = new CLink($application['name'],
			'applications.php?form=update&applicationid='.$application['applicationid'].
				'&hostid='.$application['hostid']
		);
	}

	$checkBox = new CCheckBox('applications['.$application['applicationid'].']', $application['applicationid']);
	$checkBox->setEnabled(!$application['discoveryRule']);

	$applicationTable->addRow([
		$checkBox,
		($this->data['hostid'] > 0) ? null : $application['host']['name'],
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(
				_('Items'),
				(new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$application['hostid']])
					->setArgument('filter_application', $application['name'])
			),
			CViewHelper::showNum(count($application['items']))
		],
		$data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

// append table to form
$form->addItem([
	$applicationTable,
	$this->data['paging'],
	new CActionButtonList('action', 'applications',
		[
			'application.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected applications?')],
			'application.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected applications?')],
			'application.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected applications?')]
		],
		$this->data['hostid']
	)
]);

// Make widget.
(new CWidget())
	->setTitle(_('Applications'))
	->setControls(
		(new CTag('nav', true, ($data['hostid'] == 0)
			? (new CButton('form', _('Create application (select host first)')))->setEnabled(false)
			: new CRedirectButton(_('Create application'), (new CUrl('applications.php'))
				->setArgument('form', 'create')
				->setArgument('hostid', $data['hostid'])
				->getUrl()
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(get_header_host_table('applications', $data['hostid']))
	->addItem($filter)
	->addItem($form)
	->show();
