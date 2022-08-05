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
require_once dirname(__FILE__).'/js/configuration.httpconf.list.js.php';

$hg_ms_params = $data['context'] === 'host' ? ['with_hosts' => true] : ['with_templates' => true];

$filter_column_left = (new CFormList())
	->addRow(
		new CLabel($data['context'] === 'host' ? _('Host groups') : _('Template groups'), 'filter_groupids__ms'),
		(new CMultiSelect([
			'name' => 'filter_groupids[]',
			'object_name' => $data['context'] === 'host' ? 'hostGroup' : 'templateGroup',
			'data' => $data['filter']['groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'host_groups' : 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'filter_groupids_',
					'editable' => true,
					'enrich_parent_groups' => true
				] + $hg_ms_params
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
	)
	->addRow(
		(new CLabel(($data['context'] === 'host') ? _('Hosts') : _('Templates'), 'filter_hostids__ms')),
		(new CMultiSelect([
			'name' => 'filter_hostids[]',
			'object_name' => $data['context'] === 'host' ? 'hosts' : 'templates',
			'data' => $data['filter']['hosts'],
			'popup' => [
				'filter_preselect_fields' => $data['context'] === 'host'
					? ['hostgroups' => 'filter_groupids_']
					: ['templategroups' => 'filter_groupids_'],
				'parameters' => [
					'srctbl' => $data['context'] === 'host' ? 'hosts' : 'templates',
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
	);

$filter_column_right = (new CFormList())->addRow(_('Tags'),
	CTagFilterFieldHelper::getTagFilterField([
		'evaltype' => $data['filter']['evaltype'],
		'tags' => $data['filter']['tags']
	])
);

$filter = (new CFilter())
	->setResetUrl((new CUrl('httpconf.php'))->setArgument('context', $data['context']))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addvar('context', $data['context'])
	->addFilterTab(_('Filter'), [$filter_column_left, $filter_column_right]);

$widget = (new CWidget())
	->setTitle(_('Web monitoring'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::CONFIGURATION_HOST_HTTPCONF_LIST
		: CDocHelper::CONFIGURATION_TEMPLATES_HTTPCONF_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					$data['hostid'] != 0
						? new CRedirectButton(_('Create web scenario'),
							(new CUrl('httpconf.php'))
								->setArgument('form', 'create')
								->setArgument('hostid', $data['hostid'])
								->setArgument('context', $data['context'])
						)
						: (new CButton('form',
							$data['context'] === 'host'
								? _('Create web scenario (select host first)')
								: _('Create web scenario (select template first)')
						))->setEnabled(false)
				)
		))->setAttribute('aria-label', _('Content controls'))
	);

if (!empty($this->data['hostid'])) {
	$widget->setNavigation(getHostNavigation('web', $this->data['hostid']));
}

$widget->addItem($filter);

$url = (new CUrl('httpconf.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$httpForm = (new CForm('post', $url))
	->setName('scenarios')
	->addVar('hostid', $this->data['hostid']);

$httpTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_httptests'))->onClick("checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($data['hostid'] == 0)
			? make_sorting_header(($data['context'] === 'host') ? _('Host') : _('Template'), 'hostname', $data['sort'],
				$data['sortorder'], $url
			)
			: null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Number of steps'),
		_('Interval'),
		_('Attempts'),
		_('Authentication'),
		_('HTTP proxy'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		_('Tags'),
		($data['context'] === 'host') ? _('Info') : null
	]);

$httpTestsLastData = $this->data['httpTestsLastData'];
$http_tests = $data['http_tests'];

foreach ($http_tests as $httpTestId => $httpTest) {
	$name = [];
	$name[] = makeHttpTestTemplatePrefix($httpTestId, $data['parent_templates'], $data['allowed_ui_conf_templates']);
	$name[] = new CLink(CHtml::encode($httpTest['name']),
		(new CUrl('httpconf.php'))
			->setArgument('form', 'update')
			->setArgument('hostid', $httpTest['hostid'])
			->setArgument('httptestid', $httpTestId)
			->setArgument('context', $data['context'])
	);

	if ($data['context'] === 'host') {
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
		(new CLink(
			httptest_status2str($httpTest['status']),
			(new CUrl('httpconf.php'))
				->setArgument('group_httptestid[]', $httpTest['httptestid'])
				->setArgument('hostid', $httpTest['hostid'])
				->setArgument('action', ($httpTest['status'] == HTTPTEST_STATUS_DISABLED)
					? 'httptest.massenable'
					: 'httptest.massdisable'
				)
				->setArgument('context', $data['context'])
				->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(httptest_status2style($httpTest['status']))
			->addSID(),
		$data['tags'][$httpTest['httptestid']],
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

$button_list = [
	'httptest.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected web scenarios?')],
	'httptest.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected web scenarios?')]
];

if ($data['context'] === 'host') {
	$button_list += [
		'httptest.massclearhistory' => [
			'name' => _('Clear history'),
			'confirm' => _('Delete history of selected web scenarios?')
		]
	];
}

$button_list += [
	'httptest.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected web scenarios?')]
];

// Append table to form.
$httpForm->addItem([$httpTable, $data['paging'], new CActionButtonList('action', 'group_httptestid', $button_list,
	$data['hostid']
)]);

// Append form to widget.
$widget->addItem($httpForm);

$widget->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
