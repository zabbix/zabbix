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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = {
		checkbox_object: null,
		checkbox_hash: null,

		init({checkbox_hash, checkbox_object}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;

			// Disable the status filter when using the state filter.
			$('#filter_state')
				.on('change', function() {
					$('input[name=filter_status]').prop('disabled', $('input[name=filter_state]:checked').val() != -1);
				})
				.trigger('change');

			$('#filter-tags')
				.dynamicRows({template: '#filter-tag-row-tmpl'})
				.on('afteradd.dynamicRows', function() {
					const rows = this.querySelectorAll('.form_row');
					new CTagFilterItem(rows[rows.length - 1]);
				});

			// Init existing fields once loaded.
			document.querySelectorAll('#filter-tags .form_row').forEach(row => {
				new CTagFilterItem(row);
			});
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostDelete, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		massCheckNow(button) {
			button.classList.add('is-loading');

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'item.masscheck_now');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({itemids: Object.keys(chkbxRange.getSelectedIds())})
			})
				.then((response) => response.json())
				.then((response) => {
					clearMessages();

					/*
					 * Using postMessageError or postMessageOk would mean that those messages are stored in session
					 * messages and that would mean to reload the page and show them. Also postMessageError would be
					 * displayed right after header is loaded. Meaning message is not inside the page form like that is
					 * in postMessageOk case. Instead show message directly that comes from controller. Checkboxes
					 * use uncheckTableRows which only unsets checkboxes from session storage, but not physically
					 * deselects them. Another reason for need for page reload. Instead of page reload, manually
					 * deselect the checkboxes that were selected previously in session storage, but only in case of
					 * success message. In case of error message leave checkboxes checked.
					 */
					if ('error' in response) {
						addMessage(makeMessageBox('bad', [response.error.messages], response.error.title, true, true));
					}
					else if('success' in response) {
						addMessage(makeMessageBox('good', [], response.success.title, true, false));

						let uncheckids = chkbxRange.getSelectedIds();
						uncheckids = Object.keys(uncheckids);

						// This will only unset checkboxes from session storage, but not physically deselect them.
						uncheckTableRows('items_' + this.checkbox_hash, [], false);

						// Deselect the previous checkboxes.
						chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);

						// Reset the buttons in footer and update main checkbox.
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

		statusChange(button) {
			// Create the redirect URL.
			const item = JSON.parse(button.getAttribute('data-item'));

			const curl = new Curl('items.php', true);
			curl.setArgument('group_itemid[]', item.itemid);
			curl.setArgument('hostid', item.hostid);
			curl.setArgument('action', (item.status == <?= ITEM_STATUS_DISABLED ?>)
				? 'item.massenable'
				: 'item.massdisable'
			);
			curl.setArgument('context', new URLSearchParams(location.search).get('context'));

			// Actions that are affected by status change, should be also changed in checkbox session storage.
			const selected_ids = chkbxRange.getSelectedIds();
			let ids = {};

			for (const [id, attr] of Object.entries(selected_ids)) {
				if (id == item.itemid) {
					// Get allowed and affected actions.
					let allowed_actions = button.getAttribute('data-actions');

					if (allowed_actions === null) {
						allowed_actions = '';
					}

					const allowed_actions_list = allowed_actions.split(' ');

					// Compare affected actions and existing actions and then replace them if needed.
					if (attr !== null) {
						const existing_action_list = attr.split(' ');

						// First save the actions that are not affected by status change.
						let actions = existing_action_list.filter(action => !allowed_actions_list.includes(action));

						// Then add only affected actions.
						for (const action of allowed_actions_list) {
							if (item.status == <?= ITEM_STATUS_DISABLED ?>) {
								actions.push(action);
							}
						}

						ids[id] = actions.join(' ').trim();
					}
					else {
						// If there are no exising attributes for this checkbox, new ones should be added or removed.
						ids[id] = (item.status == <?= ITEM_STATUS_DISABLED ?>) ? allowed_actions : '';
					}
				}
				else {
					ids[id] = attr;
				}
			}

			// Store the new actions with same selected IDs in session storage.
			chkbxRange.saveSessionStorage(chkbxRange.pageGoName, ids);

			// Perform redirect to item form for the massenable or massdisable.
			location.href = curl.getUrl();
		},

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			},

			hostDelete(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				const curl = new Curl('zabbix.php', false);
				curl.setArgument('action', 'host.list');

				location.href = curl.getUrl();
			}
		}
	};
</script>
