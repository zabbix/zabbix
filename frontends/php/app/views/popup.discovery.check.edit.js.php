<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


ob_start(); ?>

new CViewSwitcher('type', 'change', <?= CJs::encodeJson([
	SVC_SSH => ['row_dcheck_ports'],
	SVC_LDAP => ['row_dcheck_ports'],
	SVC_SMTP => ['row_dcheck_ports'],
	SVC_FTP => ['row_dcheck_ports'],
	SVC_HTTP => ['row_dcheck_ports'],
	SVC_POP => ['row_dcheck_ports'],
	SVC_NNTP => ['row_dcheck_ports'],
	SVC_IMAP => ['row_dcheck_ports'],
	SVC_TCP => ['row_dcheck_ports'],
	SVC_AGENT => ['row_dcheck_ports', 'row_dcheck_key'],
	SVC_SNMPv1 => ['row_dcheck_ports', 'row_dcheck_snmp_community', 'row_dcheck_snmp_oid'],
	SVC_SNMPv2c => ['row_dcheck_ports', 'row_dcheck_snmp_community', 'row_dcheck_snmp_oid'],
	SVC_ICMPPING => [],
	SVC_SNMPv3 => ['row_dcheck_ports', 'row_dcheck_snmp_oid', 'row_dcheck_snmpv3_contextname',
		'row_dcheck_snmpv3_securityname', 'row_dcheck_snmpv3_securitylevel', 'row_dcheck_snmpv3_authprotocol',
		'row_dcheck_snmpv3_authpassphrase', 'row_dcheck_snmpv3_privprotocol', 'row_dcheck_snmpv3_privpassphrase'
	],
	SVC_HTTPS => ['row_dcheck_ports'],
	SVC_TELNET => ['row_dcheck_ports']
]) ?>);

jQuery('#type').on('change', function() {
	var dcheck_type = jQuery(this).val();

	jQuery('#ports').val(getDCheckDefaultPort(dcheck_type));

	if (dcheck_type == '<?= SVC_SNMPv3 ?>') {
		new CViewSwitcher('snmpv3_securitylevel', 'change', <?= CJs::encodeJson([
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => [],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => ['row_dcheck_snmpv3_authprotocol',
				'row_dcheck_snmpv3_authpassphrase'
			],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => ['row_dcheck_snmpv3_authprotocol', 'row_dcheck_snmpv3_authpassphrase',
				'row_dcheck_snmpv3_privprotocol', 'row_dcheck_snmpv3_privpassphrase'
			]
		]) ?>);
	}
});

/**
 * Returns a default port number for the specified discovery check type.
 *
 * @param {string} dcheck_type  Discovery check type.
 *
 * @returns {string}
 */
function getDCheckDefaultPort(dcheck_type) {
	var default_ports = {
		<?= SVC_SSH ?>: '22',
		<?= SVC_LDAP ?>: '389',
		<?= SVC_SMTP ?>: '25',
		<?= SVC_FTP ?>:  '21',
		<?= SVC_HTTP ?>: '80',
		<?= SVC_POP ?>: '110',
		<?= SVC_NNTP ?>: '119',
		<?= SVC_IMAP ?>: '143',
		<?= SVC_AGENT ?>: '10050',
		<?= SVC_SNMPv1 ?>: '161',
		<?= SVC_SNMPv2c ?>: '161',
		<?= SVC_SNMPv3 ?>: '161',
		<?= SVC_HTTPS ?>: '443',
		<?= SVC_TELNET ?>: '23',
	};

	return (typeof default_ports[dcheck_type] !== 'undefined') ? default_ports[dcheck_type] : '0';
}

/**
 * Sends discovery check form data to the server for validation before adding it to the main form.
 *
 * @param {string} form_name  Form name that is sent to the server for validation.
 */
function submitDCheck(form_name) {
	var $form = jQuery(window.document.forms[form_name]),
		dialogueid = $form.closest("[data-dialogueid]").data('dialogueid');

	$form.trimValues(['#ports', '#key_', 'input[name^="snmp"]']);

	sendAjaxData('zabbix.php', {
		data: $form.serialize(),
		dataType: 'json',
		method: 'POST',
		success: function(response) {
			$form.parent().find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

			if (typeof response.errors !== 'undefined') {
				jQuery(response.errors).insertBefore($form);
			}
			else {
				addPopupValues([response.params]);
				overlayDialogueDestroy(dialogueid);
			}
		}
	});
}

<?php return ob_get_clean(); ?>
