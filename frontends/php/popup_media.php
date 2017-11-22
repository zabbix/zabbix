<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

if (CWebUser::getType() < USER_TYPE_ZABBIX_ADMIN
		|| (CWebUser::isGuest() && CWebUser::getType() < USER_TYPE_SUPER_ADMIN)) {
	access_deny(ACCESS_DENY_PAGE);
}

define('ZBX_PAGE_NO_MENU', 1);
require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'dstfrm' =>			[T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null],
	'media' =>			[T_ZBX_INT, O_OPT,	P_SYS,	null,			null],
	'type' =>			[T_ZBX_STR, O_OPT,	null,	null,			null],
	'mediatypeid' =>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({add})'],
	'sendto' =>			[T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({add}) && {type} != '.MEDIA_TYPE_EMAIL,
		_('Send to')
	],
	'sendto_emails' =>	[T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		'isset({add}) && {type} == '.MEDIA_TYPE_EMAIL,
		_('Send to')
	],
	'period' =>			[T_ZBX_TP,  O_OPT,  null,   null,  'isset({add})', _('When active')],
	'active'=>			[T_ZBX_INT, O_OPT,	null,	IN([MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]), null],
	'severity' =>		[T_ZBX_INT, O_OPT,	null,	NOT_EMPTY,	null],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,	null,	null]
];
check_fields($fields);

$sendto = getRequest('sendto', '');
$sendto_emails = getRequest('sendto_emails', []);
$sendto_emails = is_array($sendto_emails) ? array_values($sendto_emails) : [];

if (getRequest('add') && getRequest('type') == MEDIA_TYPE_EMAIL) {
	$email_validator = new CEmailValidator();

	foreach ($sendto_emails as $email) {
		if (!$email_validator->validate($email)) {
			error($email_validator->getError());
			break;
		}
	}
}

insert_js_function('add_media');

$has_error_msgs = hasErrorMesssages();
if ($has_error_msgs) {
	show_messages(false, null, _('Page received incorrect data'));
}

if (getRequest('add')) {
	$severity = 0;
	$_REQUEST['severity'] = getRequest('severity', []);
	foreach ($_REQUEST['severity'] as $id) {
		$severity |= 1 << $id;
	}

	if (!$has_error_msgs) {
		echo '<script type="text/javascript">
				add_media('.CJs::encodeJson(getRequest('dstfrm')).','.
					CJs::encodeJson(getRequest('media')).','.
					CJs::encodeJson(getRequest('mediatypeid')).','.
					CJs::encodeJson((getRequest('type') == MEDIA_TYPE_EMAIL) ? $sendto_emails : $sendto).','.
					CJs::encodeJson(getRequest('period')).','.
					CJs::encodeJson(getRequest('active', MEDIA_STATUS_DISABLED)).','.
					$severity.
				');'.
			'</script>';
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
$active = getRequest('active', MEDIA_STATUS_ACTIVE);
$period = getRequest('period', ZBX_DEFAULT_INTERVAL);

$frm_row = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
	$frm_row->addItem(
		(new CCheckBox('severity['.$severity.']', $severity))
			->setLabel(getSeverityName($severity, $config))
			->setChecked(str_in_array($severity, $severities))
	);
}

$db_mediatypes = API::MediaType()->get([
	'output' => ['type', 'description'],
	'preservekeys' => true
]);
CArrayHelper::sort($db_mediatypes, ['description']);

$mediatypes = [];

foreach ($db_mediatypes as $mediatypeid => &$db_mediatype) {
	$mediatypes[$mediatypeid] = $db_mediatype['type'];
	$db_mediatype = $db_mediatype['description'];
}
unset($db_mediatype);

// Create table of email addresses.
$email_send_to_table = (new CTable())->setId('email_send_to');

// Popuplate with one empty field.
if (!$sendto_emails) {
	$sendto_emails[] = '';
}

foreach ($sendto_emails as $i => $email) {
	$email_send_to_table->addRow([
		(new CTextBox('sendto_emails['.$i.']', $email))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CButton('sendto_emails['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
	], 'form_row');
}

// buttons
$email_send_to_table->setFooter(new CCol(
	(new CButton('email_send_to_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$frmMedia = (new CFormList(_('Media')))
	->addRow(_('Type'), new CComboBox('mediatypeid', getRequest('mediatypeid'), null, $db_mediatypes))
	->addRow(_('Send to'), (new CTextBox('sendto', $sendto, false, 100))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		'mediatype_send_to'
	)
	->addRow(_('Send to'), $email_send_to_table, 'mediatype_email_send_to')
	->addRow(_('When active'), (new CTextBox('period', $period, false, 1024))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))
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
	->addVar('dstfrm', getRequest('dstfrm'))
	->addVar('type', getRequest('type', ''))
	->addItem($mediaTab);

$widget = (new CWidget())
	->setTitle(_('Media'))
	->addItem($form)
	->show();
?>
<script type="text/x-jquery-tmpl" id="email_send_to_table_row">
	<?= (new CRow([
			(new CCol((new CTextBox('sendto_emails[#{rowNum}]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH))),
			(new CCol((new CButton('sendto_emails[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
			)),
		]))
			->addClass('form_row')
			->toString()
	?>
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#email_send_to').dynamicRows({
			template: '#email_send_to_table_row'
		});

		// Show/hide multiple "Send to" inputs and single "Send to" input and populate hidden "type" field.
		$('#mediatypeid')
			.on('change', function() {
				var mediatypes_by_type = <?= (new CJson())->encode($mediatypes) ?>,
					mediatypeid = $(this).val();

				$('#type').val(mediatypes_by_type[mediatypeid]);

				if (mediatypes_by_type[mediatypeid] == <?= MEDIA_TYPE_EMAIL ?>) {
					$('#mediatype_send_to').hide();
					$('#mediatype_email_send_to').show();
				}
				else {
					$('#mediatype_send_to').show();
					$('#mediatype_email_send_to').hide();
				}
			})
			.trigger('change');
	});
</script>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
