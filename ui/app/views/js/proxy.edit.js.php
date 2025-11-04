<?php declare(strict_types = 0);
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
?>


window.proxy_edit_popup = new class {

	constructor() {
		this.clone_proxyid = null;
		this.form = null;
		this.form_element = null;
	}

	init({proxyid, rules}) {
		this.proxyid = proxyid;

		this.overlay = overlays_stack.getById('proxy.edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);
		this.footer = this.overlay.$dialogue.$footer[0];

		const return_url = new URL('zabbix.php', location.href);
		return_url.searchParams.set('action', 'proxy.list');
		ZABBIX.PopupManager.setReturnUrl(return_url.href);

		this.display_change_psk =
			this.form_element.querySelector('#tls_connect input:checked').value == <?= HOST_ENCRYPTION_PSK ?>
				|| document.getElementById('tls_accept_psk').checked;

		if (this.display_change_psk) {
			document
				.getElementById('tls-psk-change')
				.addEventListener('click', () => this._changePsk());

			for (const element of this.form_element.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
				element.style.display = 'none';
			}
		}

		jQuery('#proxy_groupid').on('change', () => this._update());

		for (const id of ['operating_mode', 'tls_connect', 'tls_accept_psk', 'tls_accept_certificate',
				'custom_timeouts']) {
			document
				.getElementById(id)
				.addEventListener('change', () => this._update());
		}

		this._update();

		document.getElementById('proxy-form').style.display = '';
		document.getElementById('name').focus();
	}

	_changePsk() {
		document.getElementById('update_psk').value = 1;

		for (const element of this.form_element.querySelectorAll('.js-tls-psk-change')) {
			element.remove();
		}

		for (const element of this.form_element.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
			element.style.display = '';
		}

		for (const id of ['tls_psk_identity', 'tls_psk']) {
			document.getElementById(id).disabled = false;
		}

		this.display_change_psk = false;
	}

	_update() {
		const $proxy_group = jQuery('#proxy_groupid').multiSelect('getData');

		for (const element of this.form_element.querySelectorAll('.js-local-address')) {
			element.style.display = $proxy_group.length ? '' : 'none';
		}

		const operating_mode_active =
			document.querySelector('#operating_mode input:checked').value == <?= PROXY_OPERATING_MODE_ACTIVE ?>;

		for (const element of this.form_element.querySelectorAll('.js-interface')) {
			element.style.display = operating_mode_active ? 'none' : '';
		}

		for (const element of this.form_element.querySelectorAll('.js-proxy-address')) {
			element.style.display = operating_mode_active ? '' : 'none';
		}

		for (const element of this.form_element.querySelectorAll('#tls_connect input')) {
			element.disabled = operating_mode_active;
		}

		for (const id of ['tls_accept_none', 'tls_accept_psk', 'tls_accept_certificate']) {
			document.getElementById(id).disabled = !operating_mode_active;
		}

		const tls_connect = this.form_element.querySelector('#tls_connect input:checked').value;
		const tls_connect_psk = tls_connect == <?= HOST_ENCRYPTION_PSK ?>;
		const tls_connect_certificate = tls_connect == <?= HOST_ENCRYPTION_CERTIFICATE ?>;

		const tls_accept_psk = document.getElementById('tls_accept_psk').checked;
		const tls_accept_certificate = document.getElementById('tls_accept_certificate').checked;

		for (const element of this.form_element.querySelectorAll('.js-tls-issuer, .js-tls-subject')) {
			element.style.display = tls_connect_certificate || tls_accept_certificate ? '' : 'none';
		}

		for (const id of ['tls_issuer', 'tls_subject']) {
			document.getElementById(id).disabled =
				!(operating_mode_active && tls_accept_certificate || !operating_mode_active && tls_connect_certificate);
		}

		if (this.display_change_psk) {
			document.getElementById('update_psk').value = 0;

			for (const id of ['tls_psk_identity', 'tls_psk']) {
				document.getElementById(id).disabled = true;
			}

			for (const element of this.form_element.querySelectorAll('.js-tls-psk-change')) {
				element.style.display = tls_connect_psk || tls_accept_psk ? '' : 'none';
			}

			document.getElementById('tls-psk-change').disabled =
				!(operating_mode_active && tls_accept_psk || !operating_mode_active && tls_connect_psk);
		}
		else {
			document.getElementById('update_psk').value = tls_connect_psk || tls_accept_psk ? 1 : 0;

			for (const element of this.form_element.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
				element.style.display = tls_connect_psk || tls_accept_psk ? '' : 'none';
			}

			for (const id of ['tls_psk_identity', 'tls_psk']) {
				document.getElementById(id).disabled =
					!(operating_mode_active && tls_accept_psk || !operating_mode_active && tls_connect_psk);
			}
		}

		const custom_timeouts_enabled = this.form_element
				.querySelector('#custom_timeouts input:checked').value == <?= ZBX_PROXY_CUSTOM_TIMEOUTS_ENABLED ?>;

		for (const id of ['timeout_zabbix_agent', 'timeout_simple_check', 'timeout_snmp_agent',
				'timeout_external_check', 'timeout_db_monitor', 'timeout_http_agent', 'timeout_ssh_agent',
				'timeout_telnet_agent', 'timeout_script', 'timeout_browser']) {
			document.getElementById(id).readOnly = !custom_timeouts_enabled;
		}
	}

	refreshConfig() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'proxy.config.refresh');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('proxy')) ?>);

		this._post(curl.getUrl(), {proxyids: [this.proxyid]}, true);
	}

	clone({title, buttons, rules}) {
		this.clone_proxyid = this.proxyid;
		this.proxyid = null;

		this.form.reload(rules);

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
		this.overlay.recoverFocus();
		this.overlay.containFocus();
	}

	delete() {
		const curl = new Curl('zabbix.php');
		curl.setArgument('action', 'proxy.delete');
		curl.setArgument(CSRF_TOKEN_NAME, <?= json_encode(CCsrfTokenHelper::get('proxy')) ?>);

		this._post(curl.getUrl(), {proxyids: [this.proxyid]});
	}

	submit() {
		const fields = this.form.getAllValues();

		if (this.proxyid !== null) {
			fields.proxyid = this.proxyid;
		}
		else if (this.clone_proxyid !== null) {
			fields.clone_proxyid = this.clone_proxyid;
		}

		const curl = new Curl('zabbix.php');
		curl.setArgument('action', this.proxyid !== null ? 'proxy.update' : 'proxy.create');

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				this._post(curl.getUrl(), fields);
			});
	}

	_post(url, data, keep_open = false) {
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
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();

					return;
				}

				if (keep_open) {
					const message_box = makeMessageBox('good', response.success.messages, response.success.title)[0];

					this.form_element.parentNode.querySelectorAll('.msg-good,.msg-bad,.msg-warning')
						.forEach(node => node.remove());
					this.form_element.parentNode.insertBefore(message_box, this.form_element);
				}
				else {
					const action = (new Curl(url)).getArgument('action');

					overlayDialogueDestroy(this.overlay.dialogueid);

					this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: {action, ...response}}));
				}
			})
			.catch((exception) => {
				for (const element of this.form_element.parentNode.children) {
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

				this.form_element.parentNode.insertBefore(message_box, this.form_element);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
