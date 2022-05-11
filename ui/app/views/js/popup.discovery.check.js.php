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
?>

new CViewSwitcher('type-select', 'change', <?= json_encode([
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

var $type = jQuery('#type-select'),
	$snmpv3_securitylevel = jQuery('#snmpv3-securitylevel');

$type.on('change', function() {
	$snmpv3_securitylevel.off('change');

	if (jQuery(this).val() == <?= SVC_SNMPv3 ?>) {
		new CViewSwitcher('snmpv3-securitylevel', 'change', <?= json_encode([
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => [],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => ['row_dcheck_snmpv3_authprotocol',
				'row_dcheck_snmpv3_authpassphrase'
			],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => ['row_dcheck_snmpv3_authprotocol', 'row_dcheck_snmpv3_authpassphrase',
				'row_dcheck_snmpv3_privprotocol', 'row_dcheck_snmpv3_privpassphrase'
			]
		]) ?>);

		$snmpv3_securitylevel.on('change', function() {
			jQuery(window).trigger('resize');
		});
	}

	jQuery(window).trigger('resize');
});

if ($type.val() == <?= SVC_SNMPv3 ?>) {
	// Fires the change event to initialize CViewSwitcher.
	$type.trigger('change');

	// Now we can add the event to clear the form on type change.
	$type.on('change', function() {
		clearDCheckForm();
		setDCheckDefaultPort();
	});
}
else {
	$type.on('change', function() {
		clearDCheckForm();
		setDCheckDefaultPort();
	});
}

/**
 * Resets fields of the discovery check form to default values.
 */
function clearDCheckForm() {
	jQuery('#key_, #snmp_community, #snmp_oid, #snmpv3_contextname, #snmpv3_securityname, #snmpv3_authpassphrase, ' +
		'#snmpv3_privpassphrase').val('');
	jQuery('#snmpv3-securitylevel').val(<?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>);
	jQuery('#snmpv3_authprotocol_0, #snmpv3_privprotocol_0').prop('checked', true);
}
