<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$httpWidget = (new CWidget())->setTitle(_('Web monitoring'));

$createForm = (new CForm('get'))->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);

$controls = new CList();
$controls->addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()]);
$controls->addItem([SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()]);

if (empty($this->data['hostid'])) {
	$createButton = new CSubmit('form', _('Create web scenario (select host first)'));
	$createButton->setEnabled(false);
	$controls->addItem($createButton);
}
else {
	$controls->addItem(new CSubmit('form', _('Create web scenario')));
	$httpWidget->addItem(get_header_host_table('web', $this->data['hostid']));
}

$createForm->addItem($controls);
$httpWidget->setControls($createForm);

// create form
$httpForm = new CForm();
$httpForm->setName('scenarios');
$httpForm->addVar('hostid', $this->data['hostid']);

$httpTable = new CTableInfo();
$httpTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_httptests', null, "checkAll('".$httpForm->getName()."', 'all_httptests', 'group_httptestid');")))->
		addClass('cell-width'),
	($this->data['hostid'] == 0)
		? make_sorting_header(_('Host'), 'hostname', $this->data['sort'], $this->data['sortorder'])
		: null,
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Number of steps'),
	_('Update interval'),
	_('Retries'),
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
		$name[] = new CLink($template['name'], '?groupid=0&hostid='.$template['id'], ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY);
		$name[] = NAME_DELIMITER;
	}
	$name[] = new CLink($httpTest['name'], '?form=update'.'&httptestid='.$httpTestId.'&hostid='.$httpTest['hostid']);

	if ($this->data['showInfoColumn']) {
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

			$infoIcon = new CDiv(SPACE, 'status_icon iconerror');
			$infoIcon->setHint($errorMessage, ZBX_STYLE_RED);
		}
		else {
			$infoIcon = '';
		}
	}
	else {
		$infoIcon = null;
	}

	$httpTable->addRow([
		new CCheckBox('group_httptestid['.$httpTest['httptestid'].']', null, null, $httpTest['httptestid']),
		($this->data['hostid'] > 0) ? null : $httpTest['hostname'],
		$name,
		$httpTest['stepscnt'],
		convertUnitsS($httpTest['delay']),
		$httpTest['retries'],
		httptest_authentications($httpTest['authentication']),
		($httpTest['http_proxy'] !== '') ? _('Yes') : _('No'),
		($httpTest['applicationid'] != 0) ? $httpTest['application_name'] : '',
		new CLink(
			httptest_status2str($httpTest['status']),
			'?group_httptestid[]='.$httpTest['httptestid'].
				'&hostid='.$httpTest['hostid'].
				'&action='.($httpTest['status'] == HTTPTEST_STATUS_DISABLED
					? 'httptest.massenable'
					: 'httptest.massdisable'
				),
			ZBX_STYLE_LINK_ACTION.' '.httptest_status2style($httpTest['status'])
		),
		$infoIcon
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
$httpWidget->addItem($httpForm);

return $httpWidget;
