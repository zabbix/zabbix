<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 */
?>

window.check_popup = new class {

	#overlay
	#dialogue
	#form_element
	#form

	init({rules}) {
		this.#overlay = overlays_stack.getById('discovery-check');
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this._loadViews();
		this.#form_element.style.display = '';
		this.#overlay.recoverFocus();
	}

	_loadViews() {
		new CViewSwitcher('type-select', 'change', <?= json_encode([
			SVC_SSH => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_LDAP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_SMTP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_FTP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_HTTP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_POP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_NNTP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_IMAP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_TCP => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_AGENT => [
				'dcheck_ports', 'dcheck_ports_label',
				'dcheck_key', 'dcheck_key_label'
			],
			SVC_SNMPv1 => [
				'dcheck_ports', 'dcheck_ports_label',
				'dcheck_snmp_community', 'dcheck_snmp_community_label',
				'dcheck_snmp_oid', 'dcheck_snmp_oid_label'
			],
			SVC_SNMPv2c => [
				'dcheck_ports', 'dcheck_ports_label',
				'dcheck_snmp_community', 'dcheck_snmp_community_label',
				'dcheck_snmp_oid', 'dcheck_snmp_oid_label'
			],
			SVC_ICMPPING => ['allow_redirect_field', 'allow_redirect_label'],
			SVC_SNMPv3 => [
				'dcheck_ports', 'dcheck_ports_label',
				'dcheck_snmp_oid', 'dcheck_snmp_oid_label',
				'dcheck_snmpv3_contextname', 'dcheck_snmpv3_contextname_label',
				'dcheck_snmpv3_securityname', 'dcheck_snmpv3_securityname_label',
				'dcheck_snmpv3_securitylevel', 'dcheck_snmpv3_securitylevel_label',
				'dcheck_snmpv3_authprotocol', 'dcheck_snmpv3_authprotocol_label',
				'dcheck_snmpv3_authpassphrase', 'dcheck_snmpv3_authpassphrase_label',
				'dcheck_snmpv3_privprotocol', 'dcheck_snmpv3_privprotocol_label',
				'dcheck_snmpv3_privpassphrase', 'dcheck_snmpv3_privpassphrase_label'
			],
			SVC_HTTPS => ['dcheck_ports', 'dcheck_ports_label'],
			SVC_TELNET => ['dcheck_ports', 'dcheck_ports_label']
		], JSON_THROW_ON_ERROR) ?>);

		let type = document.querySelector('#type-select');
		let snmpv3_securitylevel = document.querySelector('#snmpv3-securitylevel');

		snmpv3_securitylevel.onchange = () => {
			this._loadSecurityLevelView();
		};

		type.onchange = (e) => {
			if (e.target.value == <?= SVC_SNMPv3 ?>) {
				snmpv3_securitylevel.dispatchEvent(new Event('change'));
			}

			this._resetDCheckForm();
			this._setDCheckDefaultPort();
		}

		snmpv3_securitylevel.dispatchEvent(new Event('change'));
	}

	_loadSecurityLevelView() {
		new CViewSwitcher('snmpv3-securitylevel', 'change', <?= json_encode([
			ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => [],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => [
				'dcheck_snmpv3_authprotocol', 'dcheck_snmpv3_authprotocol_label',
				'dcheck_snmpv3_authpassphrase', 'dcheck_snmpv3_authpassphrase_label'
			],
			ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => [
				'dcheck_snmpv3_authprotocol', 'dcheck_snmpv3_authprotocol_label',
				'dcheck_snmpv3_authpassphrase', 'dcheck_snmpv3_authpassphrase_label',
				'dcheck_snmpv3_privprotocol', 'dcheck_snmpv3_privprotocol_label',
				'dcheck_snmpv3_privpassphrase', 'dcheck_snmpv3_privpassphrase_label'
			]
		], JSON_THROW_ON_ERROR) ?>);
	}

	submit() {
		this.#removePopupMessages();
		let fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();

					return;
				}

				this._post(zabbixUrl({action: 'discovery.check.check'}), fields);
			});
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

				if ('form_errors' in response) {
					this.#form.setErrors(response.form_errors, true, true);
					this.#form.renderErrors();

					return;
				}

				if (this.#overlay.$dialogue[0].isConnected) {
					this.#dialogue.dispatchEvent(new CustomEvent('check.submit', {detail: response}));
					overlayDialogueDestroy(this.#overlay.dialogueid);
				}
			})
			.catch((exception) => this.#ajaxExceptionHandler(exception))
			.finally(() => this.#overlay.unsetLoading());
	}

	#removePopupMessages() {
		for (const el of this.#form_element.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		const message_box = makeMessageBox('bad', messages, title)[0];

		this.#form_element.parentNode.insertBefore(message_box, this.#form_element);
	}

	/**
	 * Resets fields of the discovery check form to default values.
	 */
	_resetDCheckForm() {
		document.querySelector('#allow_redirect').checked = false;

		const elements = document.querySelectorAll(
			`#key_, #snmp_community, #snmp_oid, #snmpv3_contextname, #snmpv3_securityname, #snmpv3_authpassphrase,
			#snmpv3_privpassphrase`
		);

		elements.forEach((element) => {
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
		const default_ports = {
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
