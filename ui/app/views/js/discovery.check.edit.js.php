<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

window.check_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('discovery-check');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this._loadViews();
		this.form.removeAttribute('style');
	}

	_loadViews() {
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
		], JSON_THROW_ON_ERROR) ?>);

		let type = document.querySelector('#type-select');
		let snmpv3_securitylevel = document.querySelector('#snmpv3-securitylevel');
		const that = this;

		snmpv3_securitylevel.onchange = function (e) {
			that._loadSecurityLevelView();
		}

		type.onchange = function (e) {
			if (e.target.value == <?= SVC_SNMPv3 ?>) {
				snmpv3_securitylevel.dispatchEvent(new Event('change'));
			}

			that._clearDCheckForm();
			that._setDCheckDefaultPort();
		}

		snmpv3_securitylevel.dispatchEvent(new Event('change'));
	}

	_loadSecurityLevelView() {
		new CViewSwitcher('snmpv3-securitylevel', 'change', <?= json_encode([
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => [],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => ['row_dcheck_snmpv3_authprotocol',
				'row_dcheck_snmpv3_authpassphrase'
			],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => [
				'row_dcheck_snmpv3_authprotocol', 'row_dcheck_snmpv3_authpassphrase',
				'row_dcheck_snmpv3_privprotocol', 'row_dcheck_snmpv3_privpassphrase'
			]
		], JSON_THROW_ON_ERROR) ?>);
	}

	/**
	 * Checks duplicate discovery checks.
	 */
	_hasDCheckDuplicates() {
		const dcheck = getFormFields(this.form);

		let results = [];
		let fields = [
			'dcheckid', 'type', 'ports', 'snmp_community', 'key_', 'snmpv3_contextname', 'snmpv3_securityname',
			'snmpv3_securitylevel', 'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol',
			'snmpv3_privpassphrase'
		];

		[...document.getElementById('dcheckList').getElementsByTagName('tr')].map(element => {
			let inputs = element.querySelectorAll('input');

			let result = [];
			for (const input of inputs) {
				for (let i = 0; i < fields.length; i++) {
					if (input.name.includes(fields[i])) {
						result[fields[i]] = input.value;
						break;
					}
				}
			}

			results.push(result);
		})

		const lookup = [
			{
				types: [
					<?= SVC_SSH ?>, <?= SVC_LDAP ?>, <?= SVC_SMTP ?>, <?= SVC_FTP ?>, <?= SVC_HTTP ?>, <?= SVC_POP ?>,
					<?= SVC_NNTP ?>, <?= SVC_IMAP ?>, <?= SVC_TCP ?>, <?= SVC_ICMPPING ?>, <?= SVC_HTTPS ?>,
					<?= SVC_TELNET ?>
				],
				keys: ['type', 'ports']
			},
			{
				types: [<?= SVC_AGENT ?>],
				keys: ['type', 'ports', 'key_']
			},
			{
				types: [<?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>],
				keys: ['type', 'ports', 'snmp_community', 'snmp_oid']
			},
			{
				types: [<?= SVC_SNMPv3 ?>],
				keys: [
					'type', 'ports', 'snmp_oid', 'snmpv3_contextname', 'snmpv3_securityname', 'snmpv3_securitylevel',
					'snmpv3_authprotocol', 'snmpv3_authpassphrase', 'snmpv3_privprotocol', 'snmpv3_privpassphrase'
				]
			}
		]

		return results.some(result => {
			if (!result.type) {
				return false
			}
			if (result.dcheckid === dcheck.dcheckid) {
				return false;
			}

			const check = lookup.find(entry => entry.types.includes(parseInt(result.type)))

			return Object.keys(result)
				.filter(key => check.keys.includes(key))
				.every(key => dcheck[key] === result[key])
		})
	}

	submit() {
		const curl = new Curl('zabbix.php');
		let fields = getFormFields(this.form);

		for (const element of this.form.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}

		if (this._hasDCheckDuplicates()) {
			this._addDuplicateMessage();
		}
		else {
			this._updateFields(fields);
			curl.setArgument('action', 'discovery.check.check');
			this._post(curl.getUrl(), fields);
		}
	}

	_post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.dialogue.dispatchEvent(new CustomEvent('check.submit', {detail: response}));
				overlayDialogueDestroy(this.overlay.dialogueid);
			})
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];
				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	_addDuplicateMessage() {
		const messageBox = makeMessageBox('bad', [<?= json_encode(_('Check already exists.')) ?>])[0];
		this.form.parentNode.insertBefore(messageBox, this.form);

		this.overlay.unsetLoading();
	}

	/**
	 * Updates form fields based on check type and trims string values.
	 */
	_updateFields(fields) {
		if (fields.type != <?= SVC_AGENT ?>) {
			for (const key in fields) {
				if (key == 'key_') {
					delete fields[key];
				}
			}
		}

		if (![<?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>].includes(parseInt(fields.type))) {
			for (const key in fields) {
				if (key == 'snmp_community') {
					delete fields[key];
				}
			}
		}

		if (![<?= SVC_SNMPv1 ?>, <?= SVC_SNMPv2c ?>, <?= SVC_SNMPv3 ?>].includes(parseInt(fields.type))) {
			for (const key in fields) {
				if (key == 'snmp_oid') {
					delete fields[key];
				}
			}
		}

		if (fields.type != <?= SVC_SNMPv3 ?>) {
			for (const key in fields) {
				if (key == 'snmpv3_privpassphrase') {
					delete fields[key];
				}
			}
		}

		if (fields.type == <?= SVC_SNMPv3 ?>) {
			let security_level = false;
			for (const key in fields) {

				if (key == 'snmpv3_securitylevel' && fields[key] != <?= ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV ?>) {
					security_level = true;
				}

				if (key == 'snmpv3_privpassphrase' && security_level) {
					delete fields[key];
				}
			}
		}

		for (let key in fields) {
			if (typeof fields[key] === 'string') {
				fields[key] = fields[key].trim();
			}
		}
	}

	/**
	 * Resets fields of the discovery check form to default values.
	 */
	_clearDCheckForm() {
		const elementsToClear = document.querySelectorAll(
			'#key_, #snmp_community, #snmp_oid, #snmpv3_contextname, #snmpv3_securityname, #snmpv3_authpassphrase, #snmpv3_privpassphrase'
		);
		elementsToClear.forEach(function(element) {
			element.value = '';
		});

		if (document.querySelector('#snmpv3-securitylevel')) {
			document.querySelector('#snmpv3-securitylevel').value = <?= ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV ?>;
		}

		if (document.querySelector('#snmpv3_authprotocol_0')) {
			document.querySelector('#snmpv3_authprotocol_0').checked = true;
		}

		if (document.querySelector('#snmpv3_privprotocol_0')) {
			document.querySelector('#snmpv3_privprotocol_0').checked = true;
		}
	}

	/**
	 * Set default discovery check port to input.
	 */
	_setDCheckDefaultPort() {
		document.querySelector('#ports').value = this._getDCheckDefaultPort(
			document.querySelector('#type-select').value
		);
	}

	/**
	 * Returns a default port number for the specified discovery check type.
	 *
	 * @param {string} dcheck_type Discovery check type.
	 *
	 * @returns {string}
	 */
	_getDCheckDefaultPort(dcheck_type) {
		var default_ports = {
			<?= SVC_SSH ?>: '22',
			<?= SVC_LDAP ?>: '389',
			<?= SVC_SMTP ?>: '25',
			<?= SVC_FTP ?>: '21',
			<?= SVC_HTTP ?>: '80',
			<?= SVC_POP ?>: '110',
			<?= SVC_NNTP ?>: '119',
			<?= SVC_IMAP ?>: '143',
			<?= SVC_AGENT ?>: '10050',
			<?= SVC_SNMPv1 ?>: '161',
			<?= SVC_SNMPv2c ?>: '161',
			<?= SVC_SNMPv3 ?>: '161',
			<?= SVC_HTTPS ?>: '443',
			<?= SVC_TELNET ?>: '23'
		};

		return default_ports.hasOwnProperty(dcheck_type) ? default_ports[dcheck_type] : '0';
	}
}
