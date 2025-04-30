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
				'filter_preselect' => [
					'id' => 'filter_groupids_',
					'submit_as' => $data['context'] === 'host' ? 'groupid' : 'templategroupid'
				],
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
			->addValue(_('All'), -1)
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

$html_page = (new CHtmlPage())
	->setTitle(_('Web monitoring'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_HTTPCONF_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_HTTPCONF_LIST
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
	$html_page->setNavigation(getHostNavigation('web', $this->data['hostid']));
}

$html_page->addItem($filter);

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
	])
	->setPageNavigation($data['paging']);

$httpTestsLastData = $this->data['httpTestsLastData'];
$http_tests = $data['http_tests'];

$csrf_token = CCsrfTokenHelper::get('httpconf.php');

foreach ($http_tests as $httpTestId => $httpTest) {
	$name = [];
	$name[] = makeHttpTestTemplatePrefix($httpTestId, $data['parent_templates'], $data['allowed_ui_conf_templates']);
	$name[] = new CLink($httpTest['name'],
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

	$host_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', $data['context'] === 'host' ? 'host.edit' : 'template.edit')
		->setArgument($data['context'] === 'host' ? 'hostid' : 'templateid', $httpTest['hostid'])
		->getUrl();

	$host = $this->data['hostid'] == 0
		? new CLink($httpTest['hostname'], $host_url)
		: null;

	$httpTable->addRow([
		new CCheckBox('group_httptestid['.$httpTest['httptestid'].']', $httpTest['httptestid']),
		$host,
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
				->setArgument('backurl', $url)
				->getUrl()
		))
			->addCsrfToken($csrf_token)
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(httptest_status2style($httpTest['status'])),
		$data['tags'][$httpTest['httptestid']],
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

$button_list = [
	'httptest.massenable' => [
		'name' => _('Enable'),
		'confirm_singular' => _('Enable selected web scenario?'),
		'confirm_plural' => _('Enable selected web scenarios?'),
		'csrf_token' => $csrf_token
	],
	'httptest.massdisable' => [
		'name' => _('Disable'),
		'confirm_singular' => _('Disable selected web scenario?'),
		'confirm_plural' => _('Disable selected web scenarios?'),
		'csrf_token' => $csrf_token
	]
];

if ($data['context'] === 'host') {
	$button_list += [
		'httptest.massclearhistory' => [
			'name' => _('Clear history and trends'),
			'confirm_singular' => _('Clear history and trends of selected web scenario?'),
			'confirm_plural' => _('Clear history and trends of selected web scenarios?'),
			'csrf_token' => $csrf_token
		]
	];
}

$button_list += [
	'httptest.massdelete' => [
		'name' => _('Delete'),
		'confirm_singular' => _('Delete selected web scenario?'),
		'confirm_plural' => _('Delete selected web scenarios?'),
		'csrf_token' => $csrf_token
	]
];

// Append table to form.
$httpForm->addItem([$httpTable, new CActionButtonList('action', 'group_httptestid', $button_list, $data['hostid'])]);

$html_page
	->addItem($httpForm)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'checkbox_hash' => $data['hostid'],
		'form_name' => $httpForm->getName()
	]).');
'))
	->setOnDocumentReady()
	->show();
