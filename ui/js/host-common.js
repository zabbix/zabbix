/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


const ZBX_STYLE_ZABBIX_HOST_POPUPEDIT = 'js-edit-host';
const ZBX_STYLE_ZABBIX_HOST_POPUPCREATE = 'js-create-host';
const PAGE_TYPE_JS = 'ajax';
const MESSAGE_TYPE_SUCCESS = 'success';

const host_popup = {
	/**
	 * General entry point to be called on pages that need host popup functionality.
	 */
	init() {
		this.initActionButtons();

		this.original_url = location.href;
	},

	/**
	 * Sets up listeners for elements marked to start host edit/create popup.
	 */
	initActionButtons() {
		document.addEventListener('click', (e) => {
			const node = e.target;

			if (node.classList.contains(ZBX_STYLE_ZABBIX_HOST_POPUPCREATE)) {
				const host_data = (typeof node.dataset.hostgroups !== 'undefined')
					? { groupids: JSON.parse(node.dataset.hostgroups) }
					: {},
					url = new Curl('zabbix.php', false);

				this.edit(host_data);
				url.setArgument('action', 'host.create');
				history.pushState({}, '', url.getUrl());
			}
			else if (node.classList.contains(ZBX_STYLE_ZABBIX_HOST_POPUPEDIT)) {
				let hostid = null;

				if (typeof node.hostid !== 'undefined' && typeof node.dataset.hostid !== 'undefined') {
					hostid = node.dataset.hostid;
				}
				else {
					hostid = new Curl(node.href).getArgument('hostid')
				}

				e.preventDefault();
				this.edit({hostid});
				history.pushState({}, '', node.getAttribute('href'));
			}
		}, {capture: true});
	},

	/**
	 * Sets up and opens host edit popup.
	 *
	 * @param {object} host_data                 Host data used to initialize host form.
	 * @param {object} host_data{hostid}         ID of host to edit.
	 * @param {object} host_data{groupids}       Host groups to pre-fill when creating new host.
	 */
	edit(host_data = {}) {
		host_data.output = PAGE_TYPE_JS;

		const overlay = PopUp('popup.host.edit', host_data, 'host_edit', document.activeElement);

		overlay.$dialogue.addClass('sticked-to-top')[0].addEventListener('dialogue.submit', (e) => {
			postMessageOk(e.detail.title);

			if (e.detail.messages !== null) {
				postMessageDetails('success', e.detail.messages);
			}
		});

		overlay.$dialogue[0].addEventListener('overlay.close', () => {
			history.replaceState({}, '', this.original_url);
		}, {once: true});
	}
};

/**
 * Handles host deletion from list, popup or fullscreen form.
 * @param {HTMLFormElement} host_form Host form if called from popup/fullscreen. Null assumes delete from list.
 * @param {HTMLInputElement} host_form{#hostid} Input expected to be present when passing form.
 * @returns {bool} Always false, to prevent button submit.
 */
function hosts_delete(host_form = null) {
	const curl = new Curl('zabbix.php');
	let ids = [],
		parent = null;

	if (host_form !== null) {
		ids = [host_form.querySelector('#hostid').value];
	}
	else {
		parent = document.querySelector('[name="hosts"]');
		ids = getFormFields(parent).ids;
	}

	curl.setArgument('action', 'host.massdelete');
	curl.setArgument('ids', ids);
	curl.setArgument('output', PAGE_TYPE_JS);

	fetch(curl.getUrl(), {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
		body: urlEncodeData(curl.getUrl())
	})
		.then((response) => handle_hostaction_response(response, host_form));

	return false;
}

/**
 * Show error/success messages from host actions, refreshes originator pages/lists on success.
 * @param {Promise} response Fetch promise.
 * @param {string|undefined} response{error} More "deep"/automated errors, from e.g. permissions, maintenance checks.
 * @param {string|undefined} response{errors} Controller-level failures, validation errors.
 * @param {string|undefined} response{script_inline} Additional JavaScript to inject into page.
 * @param {HTMLFormElement|JQueryElement|null} host_form Host form, if called from within, null for mass actions.
 */
async function handle_hostaction_response(response, host_form = null) {
	try {
		response = await response.text();
		response = JSON.parse(response)
	}
	catch (error) {
		response = {errors: $(response).find('output').removeClass('msg-global')};
	}

	const overlay = overlays_stack.end();

	if ('script_inline' in response) {
		jQuery('head').append(response.script_inline);
	}

	clearMessages();
	$('main').find('> .msg-good, > .msg-bad, > .msg-warning').not('.msg-global-footer').remove();

	if (typeof overlay !== 'undefined') {
		overlay.unsetLoading();
		overlay.$dialogue.find('.msg-bad, .msg-good').remove();
	}

	parent = (host_form !== null) ? host_form : document.querySelector('main');

	if ('error' in response) {
		alert(response.error);
	}
	else if ('errors' in response) {
		jQuery(response.errors).insertBefore(parent);
	}
	else {
		if (typeof overlay !== 'undefined') {
			// Original url/state restored after dialog close.
			overlayDialogueDestroy(overlay.dialogueid);
		}
		else {
			if (typeof host_popup.original_url === 'undefined') {
				let curl = new Curl('zabbix.php', false);

				curl.setArgument('action', 'host.list');
				host_popup.original_url = curl.getUrl();
			}

			history.replaceState({}, '', host_popup.original_url);
		}

		const filter_btn = document.querySelector('[name=filter_apply]');

		if (filter_btn !== null) {
			filter_btn.click();
			addMessage(response.message);
		}
		else {
			if ('details' in response) {
				postMessageDetails(MESSAGE_TYPE_SUCCESS, response.details);
			}

			postMessageOk(response.message_raw);
			location.replace(host_popup.original_url);
		}
	}
}
