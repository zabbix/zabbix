<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Media');
$page['file'] = 'popup_media.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

if (CWebUser::$data['alias'] == ZBX_GUEST_USER) {
	access_deny();
}

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'dstfrm'=>		[T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null],

	'media'=>		[T_ZBX_INT, O_OPT,	P_SYS,	null,			null],
	'mediatypeid'=>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({add})'],
	'sendto'=>		[T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({add})'],
	'period'=>		[T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({add})'],
	'active'=>		[T_ZBX_INT, O_OPT,	null,	IN([MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]), null],

	'severity'=>	[T_ZBX_INT, O_OPT,	null,	NOT_EMPTY,	null],
/* actions */
	'add'=>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
/* other */
	'form'=>		[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form_refresh'=>[T_ZBX_INT, O_OPT, null,	null,	null]
];

check_fields($fields);

insert_js_function('add_media');

if (isset($_REQUEST['add'])) {
	$validator = new CTimePeriodValidator();
	if ($validator->validate($_REQUEST['period'])) {
		$severity = 0;
		$_REQUEST['severity'] = getRequest('severity', []);
		foreach ($_REQUEST['severity'] as $id) {
			$severity |= 1 << $id;
		}

		echo '<script type="text/javascript">
				add_media("'.$_REQUEST['dstfrm'].'",'.
				$_REQUEST['media'].','.
				zbx_jsvalue($_REQUEST['mediatypeid']).','.
				CJs::encodeJson($_REQUEST['sendto']).',"'.
				$_REQUEST['period'].'",'.
				getRequest('active', MEDIA_STATUS_DISABLED).','.
				$severity.');'.
				'</script>';
	}
	else {
		error($validator->getError());
	}
}

$config = select_config();

$severityNames = [];
for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$severityNames[$severity] = getSeverityName($severity, $config);
}

if (isset($_REQUEST['media']) && !isset($_REQUEST['form_refresh'])) {
	$severityRequest = getRequest('severity', 63);

	$severities = [];
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		if ($severityRequest & (1 << $severity)) {
			$severities[$severity] = $severity;
		}
	}
}
else {
	$severities = getRequest('severity', array_keys($severityNames));
}

$media = getRequest('media', -1);
$sendto = getRequest('sendto', '');
$mediatypeid = getRequest('mediatypeid', 0);
$active = getRequest('active', MEDIA_STATUS_ACTIVE);
$period = getRequest('period', ZBX_DEFAULT_INTERVAL);

$mediatypes = API::MediaType()->get([
	'output' => ['description'],
	'preservekeys' => true
]);
CArrayHelper::sort($mediatypes, ['description']);

foreach ($mediatypes as &$mediatype) {
	$mediatype = $mediatype['description'];
}
unset($mediatype);

$frm_row = [];

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$frm_row[] = new CLabel([
		(new CCheckBox('severity['.$severity.']', $severity))->setChecked(str_in_array($severity, $severities)),
		getSeverityName($severity, $config)
	], 'severity['.$severity.']');
	$frm_row[] = BR();
}
array_pop($frm_row);

$frmMedia = (new CFormList(_('Media')))
	->addRow(_('Type'), new CComboBox('mediatypeid', $mediatypeid, null, $mediatypes))
	->addRow(_('Send to'), (new CTextBox('sendto', $sendto))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('When active'), (new CTextBox('period', $period))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
	->addRow(_('Use if severity'), $frm_row)
	->addRow(_('Enabled'), (new CCheckBox('active', MEDIA_STATUS_ACTIVE))->setChecked($active == MEDIA_STATUS_ACTIVE));

$mediaTab = (new CTabView())
	->addTab('mediaTab', _('Media'), $frmMedia)
	->setFooter(makeFormFooter(
		new CSubmit('add', ($media > -1) ? _('Update') : _('Add')),
		[
			new CButtonCancel(null, 'window.close();')
		]
	));

$form = (new CForm())
	->addVar('media', $media)
	->addVar('dstfrm', $_REQUEST['dstfrm'])
	->addItem($mediaTab);

$widget = (new CWidget())
	->setTitle(_('Media'))
	->addItem($form)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
