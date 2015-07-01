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
	'dstfrm'=>		[T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		NULL],

	'media'=>		[T_ZBX_INT, O_OPT,	P_SYS,	NULL,			NULL],
	'mediatypeid'=>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({add})'],
	'sendto'=>		[T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'],
	'period'=>		[T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'],
	'active'=>		[T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({add})'],

	'severity'=>	[T_ZBX_INT, O_OPT,	NULL,	NOT_EMPTY,	NULL],
/* actions */
	'add'=>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL],
/* other */
	'form'=>		[T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL],
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
				$_REQUEST['active'].','.
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
$active = getRequest('active', 0);
$period = getRequest('period', ZBX_DEFAULT_INTERVAL);


$widget = (new CWidget())->setTitle(_('New media'));

$form = (new CForm())
	->addVar('media', $media)
	->addVar('dstfrm', $_REQUEST['dstfrm']);

$frmMedia = new CFormList(_('New media'));

$cmbType = new CComboBox('mediatypeid', $mediatypeid);

$types = DBfetchArrayAssoc(DBselect('SELECT mt.mediatypeid,mt.description FROM media_type mt'), 'mediatypeid');
CArrayHelper::sort($types, ['description']);

foreach ($types as $mediaTypeId => $type) {
	$cmbType->addItem($mediaTypeId, $type['description']);
}
$frmMedia->addRow(_('Type'), $cmbType);
$frmMedia->addRow(_('Send to'), (new CTextBox('sendto', $sendto))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
$frmMedia->addRow(_('When active'), (new CTextBox('period', $period))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));

$frm_row = [];

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$frm_row[] = [
		(new CCheckBox('severity['.$severity.']', $severity))->setChecked(str_in_array($severity, $severities)),
		getSeverityName($severity, $config)
	];
	$frm_row[] = BR();
}
$frmMedia->addRow(_('Use if severity'), $frm_row);

$cmbStat = new CComboBox('active', $active);
$cmbStat->addItem(0, _('Enabled'));
$cmbStat->addItem(1, _('Disabled'));
$frmMedia->addRow(_('Status'), $cmbStat);

$mediaTab = new CTabView();
$mediaTab->addTab('mediaTab', _('Media'), $frmMedia);

$mediaTab->setFooter(makeFormFooter(
	new CSubmit('add', ($media > -1) ? _('Update') : _('Add')),
	[
		new CButtonCancel(null, 'close_window();')
	]
));

$form->addItem($mediaTab);
$widget->addItem($form);
$widget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
