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
	'dstfrm'=>		[T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,		null],

	'media'=>		[T_ZBX_INT, O_OPT,	P_SYS,	null,			null],
	'mediatypeid'=>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({add})'],
	'sendto'=>		[null,		O_OPT,	null,	NOT_EMPTY,		'isset({add})'],
	'period' =>		[T_ZBX_TP,  O_OPT,  null,   null,  'isset({add})', _('When active')],
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
	$severity = 0;
	$_REQUEST['severity'] = getRequest('severity', []);
	foreach ($_REQUEST['severity'] as $id) {
		$severity |= 1 << $id;
	}

	echo '<script type="text/javascript">
			add_media('.CJs::encodeJson($_REQUEST['dstfrm']).','.
			CJs::encodeJson($_REQUEST['media']).','.
			CJs::encodeJson($_REQUEST['mediatypeid']).','.
			CJs::encodeJson($_REQUEST['sendto']).','.
			CJs::encodeJson($_REQUEST['period']).','.
			CJs::encodeJson(getRequest('active', MEDIA_STATUS_DISABLED)).','.
			$severity.');'.
			'</script>';
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

$mediatypes = API::MediaType()->get([
	'output' => ['type', 'description'],
	'preservekeys' => true
]);
CArrayHelper::sort($mediatypes, ['description']);

$media_types = [];
$default_mediatypeid = 0;
foreach ($mediatypes as $mtid => &$mediatype) {
	if ($default_mediatypeid == 0) {
		$default_mediatypeid = $mtid;
	}
	$media_types[$mtid] = $mediatype['type'];
	$mediatype = $mediatype['description'];
}
unset($mediatype);

$media = getRequest('media', -1);
$sendto = getRequest('sendto', '');
$mediatypeid = getRequest('mediatypeid', $default_mediatypeid);
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

// Create table of email addresses.
$email_send_to_table = (new CTable())
	->addClass('small-cell-padding')
	->setId('email_send_to');

$email_send_to = $sendto;
if (array_key_exists($mediatypeid, $media_types) && $media_types[$mediatypeid] == MEDIA_TYPE_EMAIL) {
	if (!is_array($email_send_to)) {
		$email_send_to = [$email_send_to];
	}

	$sendto = '';
}
else {
	$email_send_to = [''];
}

foreach ($email_send_to as $i => $email) {
	$input_field = (new CTextBox('sendto[]', $email))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
	$button = (new CButton('sendto_remove_'.$i, _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$email_send_to_table->addRow([$input_field, $button]);
}

$email_send_to_table->addRow([(new CButton('email_send_to_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addStyle('padding-top: 5px;')
	->addClass('element-table-add')]);

$frmMedia = (new CFormList(_('Media')))
	->addRow(_('Type'), new CComboBox('mediatypeid', $mediatypeid, null, $mediatypes))
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
	->addVar('dstfrm', $_REQUEST['dstfrm'])
	->addItem($mediaTab);

$widget = (new CWidget())
	->setTitle(_('Media'))
	->addItem($form)
	->show();

?>
<script type="text/x-jquery-tmpl" id="email_send_to_table_row">
<?php
echo (new CTag('tr', true, [
		(new CTag('td', true, (new CTextBox('sendto[]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))),
		(new CTag('td', true, (new CButton('sendto_remove_#{rowNum}', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
		)),
	]))
		->addClass('form_row')
		->toString();
?>
</script>
<script type="text/javascript">
jQuery(document).ready(function() {
	function switchSendToFields(mediatypeid) {
		var mediatypes_by_type = <?php echo json_encode($media_types); ?>;

		if (typeof mediatypes_by_type[mediatypeid] !== 'undefined'
				&& mediatypes_by_type[mediatypeid] == <?php echo MEDIA_TYPE_EMAIL; ?>) {
			jQuery('#mediatype_send_to').hide();
			jQuery('#mediatype_email_send_to').show();

			jQuery('#mediatype_send_to input').prop('disabled', true);
			jQuery('#mediatype_email_send_to input').prop('disabled', false);
		}
		else {
			jQuery('#mediatype_send_to').show();
			jQuery('#mediatype_email_send_to').hide();

			jQuery('#mediatype_send_to input').prop('disabled', false);
			jQuery('#mediatype_email_send_to input').prop('disabled', true);
		}
	}

	jQuery('#mediatypeid').on('change', function() {
		switchSendToFields(jQuery(this).val());
	});

	var email_send_to_table_tpl = new Template(jQuery('#email_send_to_table_row').html()),
		email_send_to_table = jQuery('#email_send_to');

	email_send_to_table
		.on('click', '.element-table-add', function() {
			var row = jQuery(this).closest('tr');
			row.before(email_send_to_table_tpl.evaluate({rowNum: email_send_to_table.find('tr').length}));
		})
		.on('click', '.element-table-remove', function() {
			jQuery(this).closest('tr').remove();
		});

	switchSendToFields(jQuery('#mediatypeid').val());
});
</script>
<?php
require_once dirname(__FILE__).'/include/page_footer.php';
