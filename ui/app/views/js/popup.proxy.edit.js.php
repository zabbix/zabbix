<?php declare(strict_types = 1);
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
?>


window.proxy_edit_popup = new class {

	constructor() {
		this.proxyid = null;

		this.create_url = null;
		this.update_url = null;
		this.delete_url = null;

		this.overlay = null;
		this.dialogue = null;
		this.form = null;
		this.footer = null;

		this.display_change_psk = false;

		this.clone_proxyid = null;
	}

	init({proxyid, create_url, update_url, delete_url}) {
		this.proxyid = proxyid;

		this.create_url = create_url;
		this.update_url = update_url;
		this.delete_url = delete_url;

		this.overlay = overlays_stack.getById('proxy_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];

		this.display_change_psk =
			this.form.querySelector('#tls_connect input:checked').value == <?= HOST_ENCRYPTION_PSK ?>
				|| document.getElementById('tls_accept_psk').checked;

		document
			.getElementById('useip')
			.addEventListener('change', () => this.updateInterface());

		if (this.display_change_psk) {
			document
				.getElementById('tls-psk-change')
				.addEventListener('click', () => this.changePsk());

			for (const element of this.form.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
				element.style.display = 'none';
			}
		}

		for (const id of ['status', 'tls_connect', 'tls_accept_psk', 'tls_accept_certificate']) {
			document
				.getElementById(id)
				.addEventListener('change', () => this.update());
		}

		this.update();

		document.getElementById('proxy-form').style.display = '';
	}

	updateInterface() {
		if (this.form.querySelector('#useip input:checked').value == <?= INTERFACE_USE_IP ?>) {
			document.querySelector('.js-interface input[name="ip"]').setAttribute('aria-required', 'true');
			document.querySelector('.js-interface input[name="dns"]').removeAttribute('aria-required');
		}
		else {
			document.querySelector('.js-interface input[name="ip"]').removeAttribute('aria-required');
			document.querySelector('.js-interface input[name="dns"]').setAttribute('aria-required', 'true');
		}
	}

	changePsk() {
		for (const element of this.form.querySelectorAll('.js-tls-psk-change')) {
			element.remove();
		}

		for (const element of this.form.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
			element.style.display = '';
		}

		for (const id of ['tls_psk_identity', 'tls_psk']) {
			document.getElementById(id).disabled = false;
		}

		this.display_change_psk = false;
	}

	update() {
		const status_active = document.querySelector('#status input:checked').value == <?= HOST_STATUS_PROXY_ACTIVE ?>;

		for (const element of this.form.querySelectorAll('.js-interface')) {
			element.style.display = status_active ? 'none' : '';
		}

		for (const element of this.form.querySelectorAll('.js-proxy-address')) {
			element.style.display = status_active ? '' : 'none';
		}

		for (const element of this.form.querySelectorAll('#tls_connect input')) {
			element.disabled = status_active;
		}

		for (const id of ['tls_accept_none', 'tls_accept_psk', 'tls_accept_certificate']) {
			document.getElementById(id).disabled = !status_active;
		}

		const tls_connect = this.form.querySelector('#tls_connect input:checked').value;
		const tls_connect_psk = tls_connect == <?= HOST_ENCRYPTION_PSK ?>;
		const tls_connect_certificate = tls_connect == <?= HOST_ENCRYPTION_CERTIFICATE ?>;

		const tls_accept_psk = document.getElementById('tls_accept_psk').checked;
		const tls_accept_certificate = document.getElementById('tls_accept_certificate').checked;

		for (const element of this.form.querySelectorAll('.js-tls-issuer, .js-tls-subject')) {
			element.style.display = tls_connect_certificate || tls_accept_certificate ? '' : 'none';
		}

		for (const id of ['tls_issuer', 'tls_subject']) {
			document.getElementById(id).disabled =
				!(status_active && tls_accept_certificate || !status_active && tls_connect_certificate);
		}

		if (this.display_change_psk) {
			for (const id of ['tls_psk_identity', 'tls_psk']) {
				document.getElementById(id).disabled = true;
			}

			for (const element of this.form.querySelectorAll('.js-tls-psk-change')) {
				element.style.display = tls_connect_psk || tls_accept_psk ? '' : 'none';
			}

			document.getElementById('tls-psk-change').disabled =
				!(status_active && tls_accept_psk || !status_active && tls_connect_psk);
		}
		else {
			for (const element of this.form.querySelectorAll('.js-tls-psk-identity, .js-tls-psk')) {
				element.style.display = tls_connect_psk || tls_accept_psk ? '' : 'none';
			}

			for (const id of ['tls_psk_identity', 'tls_psk']) {
				document.getElementById(id).disabled =
					!(status_active && tls_accept_psk || !status_active && tls_connect_psk);
			}
		}
	}

	clone({title, buttons}) {
		this.clone_proxyid = this.proxyid;
		this.proxyid = null;

		this.overlay.unsetLoading();
		this.overlay.setProperties({title, buttons});
	}

	delete() {
		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.delete_url);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({proxyids: [this.proxyid]})
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {response_error: response.error};
				}

				if ('success' in response) {
					this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {
						detail: {
							title: response.success.title,
							messages: ('messages' in response.success) ? response.success.messages : null
						}
					}));
				}
			})
			.catch((error) => {
				this.overlay.unsetLoading();

				let title, messages;

				if (typeof error === 'object' && 'response_error' in error) {
					title = error.response_error.title;
					messages = error.response_error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
					messages = [];
				}

				const message_box = makeMessageBox('bad', messages, title, true, false)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
	}

	submit() {
		const fields = getFormFields(this.form);

		if (this.proxyid !== null) {
			fields.proxyid = this.proxyid;
			fields.update_psk = !this.display_change_psk;
		}
		else if (this.clone_proxyid !== null) {
			fields.clone_proxyid = this.clone_proxyid;
			fields.clone_psk = this.display_change_psk;
		}
		else {
			fields.clone_psk = false;
		}

		for (const name of ['host', 'ip', 'dns', 'port', 'proxy_address', 'description', 'tls_psk_identity', 'tls_psk',
				'tls_issuer', 'tls_subject']) {
			if (name in fields) {
				fields[name] = fields[name].trim();
			}
		}

		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const curl = new Curl(this.proxyid !== null ? this.update_url : this.create_url);

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(fields)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('errors' in response) {
					throw {html_string: response.errors};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {
					detail: {
						title: response.title,
						messages: ('messages' in response) ? response.messages : null
					}
				}));
			})
			.catch((error) => {
				let message_box;

				if (typeof error === 'object' && 'html_string' in error) {
					message_box =
						new DOMParser().parseFromString(error.html_string, 'text/html').body.firstElementChild;
				}
				else {
					const error = <?= json_encode(_('Unexpected server error.')) ?>;

					message_box = makeMessageBox('bad', [], error, true, false)[0];
				}

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
