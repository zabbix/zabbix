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

$this->addJsFile('gtlc.js');
$this->addJsFile('class.calendar.js');

$this->includeJsFile('reports.actionlog.list.js.php');

$filter_status_options = [];

foreach ($data['statuses'] as $value => $label) {
	$filter_status_options[] = [
		'label' => $label,
		'value' => $value,
		'checked' => in_array($value, $data['actionlog_statuses'])
	];
}

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', $data['action']))
	->setProfile($data['timeline']['profileIdx'])
	->addVar('action', $data['action'])
	->addTimeSelector($data['timeline']['from'], $data['timeline']['to'], true, 'web.actionlog.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(new CLabel(_('Recipients'), 'filter_userids__ms'), [
				(new CMultiSelect([
					'name' => 'filter_userids[]',
					'object_name' => 'users',
					'data' => $data['userids'],
					'placeholder' => '',
					'popup' => [
						'parameters' => [
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_userids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			])
			->addRow(new CLabel(_('Actions'), 'filter_actionids__ms'), [
				(new CMultiSelect([
					'name' => 'filter_actionids[]',
					'object_name' => 'actions',
					'data' => $data['actionids'],
					'placeholder' => '',
					'popup' => [
						'parameters' => [
							'srctbl' => 'actions',
							'srcfld1' => 'actionid',
							'srcfld2' => 'name',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_actionids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			])
			->addRow(new CLabel(_('Media types'), 'filter_mediatypeids__ms'), [
				(new CMultiSelect([
					'name' => 'filter_mediatypeids[]',
					'object_name' => 'media_types',
					'data' => $data['mediatypeids'],
					'placeholder' => '',
					'popup' => [
						'parameters' => [
							'srctbl' => 'media_types',
							'srcfld1' => 'mediatypeid',
							'srcfld2' => 'name',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_mediatypeids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			]),
		(new CFormList())
			->addRow(_('Status'),
				(new CCheckBoxList('filter_statuses'))
					->setId('filter_status')
					->setColumns(3)
					->setWidth(360)
					->setOptions($filter_status_options))
			->addRow(_('Search string'),
				(new CTextBox('filter_messages', $data['messages']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
	]);

$actionlog_list = (new CTableInfo())
	->setHeader([
		_('Time'),
		_('Action'),
		_('Media type'),
		_('Recipient'),
		_('Message'),
		_('Status'),
		_('Info')
	])
	->setPageNavigation($data['paging']);

foreach ($data['alerts'] as $alert) {
	$mediatype = array_pop($alert['mediatypes']);

	$message = $alert['alerttype'] == ALERT_TYPE_MESSAGE
		? [
			bold(_('Subject').':'),
			BR(),
			$alert['subject'],
			BR(),
			BR(),
			bold(_('Message').':'),
			BR(),
			zbx_nl2br($alert['message'])
		]
		: [
			bold(_('Command').':'),
			BR(),
			zbx_nl2br($alert['message'])
		];

	if ($alert['status'] == ALERT_STATUS_SENT) {
		$status = ($alert['alerttype'] == ALERT_TYPE_MESSAGE)
			? (new CSpan(_('Sent')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('Executed')))->addClass(ZBX_STYLE_GREEN);
	}
	elseif ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW) {
		$status = $alert['alerttype'] == ALERT_TYPE_MESSAGE
			? (new CSpan([
				_('In progress').':',
				BR(),
				_n('%1$s retry left', '%1$s retries left', $mediatype['maxattempts'] - $alert['retries'])
			]))->addClass(ZBX_STYLE_YELLOW)
			: (new CSpan(_('In progress')))->addClass(ZBX_STYLE_YELLOW);
	}
	else {
		$status = (new CSpan(_('Failed')))->addClass(ZBX_STYLE_RED);
	}

	$info_icons = [];
	if ($alert['error'] !== '') {
		$info_icons[] = makeErrorIcon($alert['error']);
	}

	$actionlog_list->addRow([
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
		(new CCol($data['actions'][$alert['actionid']]['name']))->addClass(ZBX_STYLE_WORDBREAK),
		$mediatype ? (new CCol($mediatype['name']))->addClass(ZBX_STYLE_WORDBREAK) : '',
		array_key_exists('userid', $alert) && $alert['userid']
			? (new CCol(
				makeEventDetailsTableUser($alert + ['action_type' => ZBX_EVENT_HISTORY_ALERT], $data['users'])
			))->addClass(ZBX_STYLE_WORDBREAK)
			: (new CCol(zbx_nl2br($alert['sendto'])))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($message))->addClass(ZBX_STYLE_WORDBREAK),
		$status,
		makeInformationList($info_icons)
	]);
}

(new CHtmlPage())
	->setTitle(_('Action log'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_ACTIONLOG_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CRedirectButton(_('Export to CSV'), (new CUrl())->setArgument('action', 'actionlog.csv')))
						->setId('export_csv')
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem(
		(new CForm('get'))
			->setName('auditForm')
			->addItem($actionlog_list)
	)
	->show();

(new CScriptTag('
	view.init('.json_encode($data['timeline'], JSON_THROW_ON_ERROR).');
'))
	->setOnDocumentReady()
	->show();
