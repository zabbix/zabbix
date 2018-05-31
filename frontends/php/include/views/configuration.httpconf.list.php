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


$filter = (new CFilter('web.httpconf.filter.state'))
	->addColumn(
		(new CFormList())
			->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $this->data['filter_status']))
					->addValue(_('all'), -1)
					->addValue(httptest_status2str(HTTPTEST_STATUS_ACTIVE), HTTPTEST_STATUS_ACTIVE)
					->addValue(httptest_status2str(HTTPTEST_STATUS_DISABLED), HTTPTEST_STATUS_DISABLED)
					->setModern(true)
			)
	)
	->addNavigator();

$widget = (new CWidget())
	->setTitle(_('Web monitoring'))
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getGroupsCB()
				])
				->addItem([
					new CLabel(_('Host'), 'hostid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$this->data['pageFilter']->getHostsCB()
				])
			),
		(new CTag('nav', true, ($this->data['pageFilter']->hostid > 0)
			? new CRedirectButton(_('Create web scenario'), (new CUrl('httpconf.php'))
					->setArgument('form', 'create')
					->setArgument('groupid', $this->data['pageFilter']->groupid)
					->setArgument('hostid', $this->data['pageFilter']->hostid)
					->getUrl()
				)
			: (new CButton('form', _('Create web scenario (select host first)')))->setEnabled(false)
		))
			->setAttribute('aria-label', _('Content controls'))
	]));

if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('web', $this->data['hostid']));
}

$widget->addItem($filter);

// create form
$httpForm = (new CForm())
	->setName('scenarios')
	->addVar('hostid', $this->data['hostid']);

$httpTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_httptests'))->onClick("checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		($this->data['hostid'] == 0)
			? make_sorting_header(_('Host'), 'hostname', $this->data['sort'], $this->data['sortorder'])
			: null,
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Number of steps'),
		_('Interval'),
		_('Attempts'),
		_('Authentication'),
		_('HTTP proxy'),
		_('Application'),
		make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
		$this->data['showInfoColumn'] ? _('Info') : null
	]);

$httpTestsLastData = $this->data['httpTestsLastData'];
$httpTests = $this->data['httpTests'];

foreach ($httpTests as $httpTestId => $httpTest) {
	$name = [];
	if (isset($this->data['parentTemplates'][$httpTestId])) {
		$template = $this->data['parentTemplates'][$httpTestId];
		$name[] = (new CLink($template['name'], '?groupid=0&hostid='.$template['id']))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_GREY);
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($httpTest['name'], '?form=update'.'&httptestid='.$httpTestId.'&hostid='.$httpTest['hostid']);

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

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

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

return $widget;
