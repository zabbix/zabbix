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

if (jQuery('#type').val() == '<?= SVC_SNMPv3 ?>') {
	jQuery('#type').trigger('change');
}

<?php return ob_get_clean(); ?>
