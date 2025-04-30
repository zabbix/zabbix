<?php
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


/**
 * @var CView $this
 */
?>

<script>
	const view = {
		checkbox_object: null,
		checkbox_hash: null,
		token: null,

		init({checkbox_hash, checkbox_object, context, token, form_name}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;
			this.token = token;
			this.form = document.forms[form_name];

			this.initEvents();
			this.initPopupListeners();
		},

		initEvents() {
			if (this.context === 'host') {
				document.getElementById('filter_state').addEventListener('change', e => this.updateFieldsVisibility());
				document.querySelector('.js-massexecute-item')
					.addEventListener('click', (e) => this.executeNow(e.target));
			}

			document.querySelectorAll('#filter_lifetime_type, #filter_enabled_lifetime_type').forEach(element => {
				element.addEventListener('change', () => this.updateLostResourcesFields());
			});

			this.updateLostResourcesFields();
		},

		updateFieldsVisibility() {
			const disabled = document.querySelector('[name="filter_state"]:checked').value != -1;

			document.querySelectorAll('[name="filter_status"]').forEach(radio => radio.disabled = disabled);
		},

		updateLostResourcesFields() {
			const lifetime_type = document.querySelector('[name="filter_lifetime_type"]:checked').value;
			const enabled_lifetime_type = document.querySelector('[name="filter_enabled_lifetime_type"]:checked').value;

			document.querySelectorAll('[name="filter_enabled_lifetime_type"]').forEach(radio =>
				radio.disabled = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>
			);

			document.getElementById('filter_lifetime').disabled = lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>;
			document.getElementById('filter_enabled_lifetime').disabled =
				enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
					|| lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;
		},

		executeNow(button) {
			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.execute');

			const data = {
				itemids: Object.keys(chkbxRange.getSelectedIds()),
				discovery_rule: 1
			}
			data[this.token[0]] = this.token[1];

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify(data)
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));

						const uncheckids = Object.keys(chkbxRange.getSelectedIds());
						uncheckTableRows('host_discovery_' + this.checkbox_hash, [], false);
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
						chkbxRange.update(this.checkbox_object);
					}
				})
				.catch(() => {
					const title = <?= json_encode(_('Unexpected server error.')) ?>;
					const message_box = makeMessageBox('bad', [], title)[0];

					clearMessages();
					addMessage(message_box);
				})
				.finally(() => {
					button.classList.remove('is-loading');

					// Deselect the "Execute now" button in both success and error cases, since there is no page reload.
					button.blur();
				});
		},

		initPopupListeners() {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT
				},
				callback: () => uncheckTableRows('host_discovery_' + view.checkbox_hash, [], false)
			});
		}
	};
</script>
