<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

$filter = (new CFilter(new CUrl('httpconf.php')))
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
							'enrich_parent_groups' => true
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
			->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('all'), -1)
					->addValue(httptest_status2str(HTTPTEST_STATUS_ACTIVE), HTTPTEST_STATUS_ACTIVE)
					->addValue(httptest_status2str(HTTPTEST_STATUS_DISABLED), HTTPTEST_STATUS_DISABLED)
					->setModern(true)
			)
	]);

$widget = (new CWidget())
	->setTitle(_('Web monitoring'))
	->setControls(
		(new CTag('nav', true, ($data['hostid'] > 0)
			? new CRedirectButton(_('Create web scenario'), (new CUrl('httpconf.php'))
				->setArgument('form', 'create')
				->setArgument('hostid', $data['hostid'])
				->getUrl()
			)
			: (new CButton('form', _('Create web scenario (select host first)')))->setEnabled(false)
		))->setAttribute('aria-label', _('Content controls'))
	);

if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('web', $this->data['hostid']));
}

$widget->addItem($filter);

// create form
$httpForm = (new CForm())
	->setName('scenarios')
	->addVar('hostid', $this->data['hostid']);

$url = (new CUrl('httpconf.php'))->getUrl();

$httpTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_httptests'))->onClick("checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($this->data['hostid'] == 0)
			? make_sorting_header(_('Host'), 'hostname', $data['sort'], $data['sortorder'], $url)
			: null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Number of steps'),
		_('Interval'),
		_('Attempts'),
		_('Authentication'),
		_('HTTP proxy'),
		_('Application'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		$this->data['showInfoColumn'] ? _('Info') : null
	]);

$httpTestsLastData = $this->data['httpTestsLastData'];
$httpTests = $this->data['httpTests'];

foreach ($httpTests as $httpTestId => $httpTest) {
	$name = [];
	$name[] = makeHttpTestTemplatePrefix($httpTestId, $data['parent_templates'], $data['allowed_ui_conf_templates']);
	$name[] = new CLink(CHtml::encode($httpTest['name']),
		(new CUrl('httpconf.php'))
			->setArgument('form', 'update')
			->setArgument('hostid', $httpTest['hostid'])
			->setArgument('httptestid', $httpTestId)
	);

	if ($this->data['showInfoColumn']) {
		$info_icons = [];
		if($httpTest['status'] == HTTPTEST_STATUS_ACTIVE && isset($httpTestsLastData[$httpTestId]) && $httpTestsLastData[$httpTestId]['lastfailedstep']) {
			$lastData = $httpTestsLastData[$httpTestId];

			$failedStep = $lastData['failedstep'];

			$errorMessage = $failedStep
				? _s(
					'Step "%1$s" [%2$s of %3$s] failed: %4$s',
					$failedStep['name'],
					$failedStep['no'],
					$httpTest['stepscnt'],
					($lastData['error'] === null) ? _('Unknown error') : $lastData['error']
				)
				: _s('Unknown step failed: %1$s', $lastData['error']);

			$info_icons[] = makeErrorIcon($errorMessage);
		}
	}

	$httpTable->addRow([
		new CCheckBox('group_httptestid['.$httpTest['httptestid'].']', $httpTest['httptestid']),
		($this->data['hostid'] > 0) ? null : $httpTest['hostname'],
		$name,
		$httpTest['stepscnt'],
		$httpTest['delay'],
		$httpTest['retries'],
		httptest_authentications($httpTest['authentication']),
		($httpTest['http_proxy'] !== '') ? _('Yes') : _('No'),
		($httpTest['applicationid'] != 0) ? $httpTest['application_name'] : '',
		(new CLink(
			httptest_status2str($httpTest['status']),
			'?group_httptestid[]='.$httpTest['httptestid'].
				'&hostid='.$httpTest['hostid'].
				'&action='.($httpTest['status'] == HTTPTEST_STATUS_DISABLED
					? 'httptest.massenable'
					: 'httptest.massdisable'
				)
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(httptest_status2style($httpTest['status']))
			->addSID(),
		$this->data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

// append table to form
$httpForm->addItem([
	$httpTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_httptestid',
		[
			'httptest.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected web scenarios?')],
			'httptest.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected web scenarios?')],
			'httptest.massclearhistory' => ['name' => _('Clear history'),
				'confirm' => _('Delete history of selected web scenarios?')
			],
			'httptest.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected web scenarios?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($httpForm);

$widget->show();
