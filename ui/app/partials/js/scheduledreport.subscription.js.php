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
 * @var CPartial $this
 */
?>

<script>
	var row_num = 0;
	var userids = new Set();
	var usrgrpids = new Set();
	var allowed_edit = <?= json_encode($data['allowed_edit']) ?>;

	var ReportSubscription = class {

		constructor(data, edit = null) {
			this.data = data;

			this.row = document.createElement('tr');
			this.row.appendChild(this.createRecipientCell());
			this.row.appendChild(this.createCreatorCell());
			this.row.appendChild(this.createStatusCell());
			this.row.appendChild(this.createActionCell());

			this.render(edit);
			row_num++;
		}

		render(edit) {
			if (edit instanceof Element) {
				if (this.data.recipientid != this.data.old_recipientid) {
					if (this.data.recipient_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>) {
						userids
							.add(this.data.recipientid)
							.delete(this.data.old_recipientid);
					}
					else {
						usrgrpids
							.add(this.data.recipientid)
							.delete(this.data.old_recipientid);
					}
				}

				return edit.replaceWith(this.row);
			}

			return document
				.querySelector('#subscriptions-table tbody')
				.append(this.row);
		}

		createRecipientCell() {
			const cell = document.createElement('td');
			const icon = document.createElement('span');
			let recipient;

			if (this.data.recipient_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>) {
				icon.classList.add('<?= ZBX_STYLE_ICON_USER ?>');
				icon.setAttribute('title', <?= json_encode(_('User')) ?>);
				userids.add(this.data.recipientid);
			}
			else {
				icon.classList.add('<?= ZBX_STYLE_ICON_USER_GROUP ?>');
				icon.setAttribute('title', <?= json_encode(_('User group')) ?>);
				usrgrpids.add(this.data.recipientid);
			}

			if (allowed_edit) {
				recipient = document.createElement('a');
				recipient.href = 'javascript:void(0);';
				recipient.addEventListener('click', (event) => {
					const parameters = Object.assign(this.data, {
						edit: 1,
						old_recipientid: this.data.recipientid
					});

					if (this.data.recipient_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>) {
						parameters.exclude = recipient.parentNode.parentNode.querySelector('[name*=exclude]').value;
						parameters.userids = Array.from(userids);
					}
					else {
						parameters.usrgrpids = Array.from(usrgrpids);
					}

					PopUp('popup.scheduledreport.subscription.edit', parameters, {trigger_element: event.target});
				});
			}
			else {
				recipient = document.createElement('span');

				if (this.data.recipient_inaccessible) {
					recipient.classList.add('<?= ZBX_STYLE_GREY ?>');
				}
			}

			recipient.textContent = this.data.recipient_name;
			recipient.setAttribute('title', this.data.recipient_name);

			cell.appendChild(icon);
			cell.appendChild(recipient);
			cell.appendChild(this.createHiddenInput('[recipientid]', this.data.recipientid));
			cell.appendChild(this.createHiddenInput('[recipient_type]', this.data.recipient_type));
			cell.appendChild(this.createHiddenInput('[recipient_name]', this.data.recipient_name));
			cell.appendChild(this.createHiddenInput('[recipient_inaccessible]', this.data.recipient_inaccessible));

			return cell;
		}

		createCreatorCell() {
			const cell = document.createElement('td');
			const span = document.createElement('span');

			span.textContent = this.data.creator_name;
			span.setAttribute('title', this.data.creator_name);

			if (this.data.creator_type == <?= ZBX_REPORT_CREATOR_TYPE_RECIPIENT ?> || this.data.creator_inaccessible) {
				span.classList.add('<?= ZBX_STYLE_GREY ?>');
			}

			cell.appendChild(span);
			cell.appendChild(this.createHiddenInput('[creatorid]', this.data.creatorid));
			cell.appendChild(this.createHiddenInput('[creator_type]', this.data.creator_type));
			cell.appendChild(this.createHiddenInput('[creator_name]', this.data.creator_name));
			cell.appendChild(this.createHiddenInput('[creator_inaccessible]', this.data.creator_inaccessible));

			return cell;
		}

		createStatusCell() {
			const cell = document.createElement('td');

			if (this.data.recipient_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP ?>) {
				return cell;
			}

			let status;

			if (allowed_edit) {
				status = document.createElement('a');
				status.href = 'javascript:void(0);';
				status.classList.add('<?= ZBX_STYLE_LINK_ACTION ?>');
				status.addEventListener('click', (event) => {
					const input = status.parentNode.querySelector('[name*=exclude]');

					if (input.value == <?= ZBX_REPORT_EXCLUDE_USER_TRUE ?>) {
						status.textContent = <?= json_encode(_('Include')) ?>;
						status.classList.replace('<?= ZBX_STYLE_RED ?>', '<?= ZBX_STYLE_GREEN ?>');
						input.value = <?= ZBX_REPORT_EXCLUDE_USER_FALSE ?>
					}
					else {
						status.textContent = <?= json_encode(_('Exclude')) ?>;
						status.classList.replace('<?= ZBX_STYLE_GREEN ?>', '<?= ZBX_STYLE_RED ?>');
						input.value = <?= ZBX_REPORT_EXCLUDE_USER_TRUE ?>
					}
				});
			}
			else {
				status = document.createElement('span');
			}

			if (this.data.exclude == <?= ZBX_REPORT_EXCLUDE_USER_FALSE ?>) {
				status.textContent = <?= json_encode(_('Include')) ?>;
				status.classList.add('<?= ZBX_STYLE_GREEN ?>');
			}
			else {
				status.textContent = <?= json_encode(_('Exclude')) ?>;
				status.classList.add('<?= ZBX_STYLE_RED ?>');
			}

			cell.appendChild(status);
			cell.appendChild(this.createHiddenInput('[exclude]', this.data.exclude));

			return cell;
		}

		createActionCell() {
			const cell = document.createElement('td');
			const btn = document.createElement('button');

			btn.type = 'button';
			btn.classList.add('<?= ZBX_STYLE_BTN_LINK ?>');
			btn.textContent = <?= json_encode(_('Remove')) ?>;

			if (allowed_edit) {
				btn.addEventListener('click', () => {
					if (this.data.recipient_type == <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>) {
						userids.delete(this.data.recipientid);
					}
					else {
						usrgrpids.delete(this.data.recipientid);
					}

					this.row.remove();
				});
			}
			else {
				btn.setAttribute('disabled', 'disabled');
			}

			cell.appendChild(btn);

			return cell;
		}

		createHiddenInput(name, value) {
			const input = document.createElement('input');

			input.type = 'hidden';
			input.name = `subscriptions[${row_num}]${name}`;
			input.value = value;

			return input;
		}

		static initializeNewUserPopup() {
			const elem = document.querySelector('#subscriptions-table .js-add-user:not(:disabled)');

			if (!elem) {
				return;
			}

			elem.addEventListener('click', (event) => {
				PopUp('popup.scheduledreport.subscription.edit', {
					recipient_type: <?= ZBX_REPORT_RECIPIENT_TYPE_USER ?>,
					userids: Array.from(userids)
				}, {trigger_element: event.target});
			});
		}

		static initializeNewUserGroupPopup() {
			const elem = document.querySelector('#subscriptions-table .js-add-user-group:not(:disabled)');

			if (!elem) {
				return;
			}

			elem.addEventListener('click', (event) => {
				PopUp('popup.scheduledreport.subscription.edit', {
					recipient_type: <?= ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP ?>,
					usrgrpids: Array.from(usrgrpids)
				}, {trigger_element: event.target});
			});
		}
	}

	var subscriptions = <?= json_encode(array_values($data['subscriptions'])) ?>;

	subscriptions.forEach((subscription) => new ReportSubscription(subscription));

	if (allowed_edit) {
		ReportSubscription.initializeNewUserPopup();
		ReportSubscription.initializeNewUserGroupPopup();
	}
</script>
