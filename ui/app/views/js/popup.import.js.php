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

window.popup_import = new class {

	constructor() {
		this.overlay = null;
		this.dialogue = null;
		this.form = null;
	}

	init() {
		this.overlay = overlays_stack.getById('popup_import');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.warningListeners();
		this.advancedConfigurationListeners();
	}

	warningListeners() {
		const rules_images = document.getElementById('rules_images_updateExisting')

		if (rules_images) {
			rules_images.addEventListener('change', (e) => {
				this.updateWarning(e.target, <?= json_encode(_('Images for all maps will be updated!')) ?>)
			})
		}
	}

	advancedConfigurationListeners() {
		const advanced_configuration = document.getElementById('advanced_options');

		if (!advanced_configuration) {
			return;
		}

		advanced_configuration.addEventListener('change', () => {
			this.form.querySelectorAll('.js-advanced-configuration').forEach((e) => {
				return e.classList.toggle("display-none");
			});
		});

		document
			.getElementById('update_all')
			.addEventListener('change', () => { this.toggleCheckboxColumn('update')})

		document
			.getElementById('create_all')
			.addEventListener('change', () => { this.toggleCheckboxColumn('create')})

		document
			.getElementById('delete_all')
			.addEventListener('change', () => { this.toggleCheckboxColumn('delete')})

		this.form.addEventListener('change',  (e) => {
			if (e.target.classList.contains('js-delete')) {
				this.updateMainCheckbox('delete');
			}
			else if (e.target.classList.contains('js-create')) {
				this.updateMainCheckbox('create');
			}
			else if (e.target.classList.contains('js-update')) {
				this.updateMainCheckbox('update');
			}
		})
	}

	submitPopup() {
		if (document.getElementById('rules_preset').value === 'template') {
			return this.openImportComparePopup();
		}

		if (this.isDeleteMissingChecked()) {
			return this.confirmSubmit();
		}

		return this.submitImportPopup();
	}

	isDeleteMissingChecked() {
		return this.form.querySelectorAll('.js-delete:checked').length > 0;
	}

	confirmSubmit(compare_overlay) {
		const message = document.getElementById('rules_preset').value === 'template'
			? <?= json_encode(
				_('Any existing template entities not present in the import file will be deleted. Click OK to proceed.')
			) ?>
			: <?= json_encode(
				_('Any existing host entities not present in the import file will be deleted. Click OK to proceed.')
			) ?>;

		overlayDialogue({
			class: 'position-middle',
			content: document.createElement('span').innerText = message,
			buttons: [
				{
					title: <?= json_encode(_('OK')) ?>,
					focused: true,
					action: function() {
						if (compare_overlay !== undefined) {
							overlayDialogueDestroy(compare_overlay.dialogueid);
						}
						popup_import.submitImportPopup();
					}
				},
				{
					title: <?= json_encode(_('Cancel')) ?>,
					cancel: true,
					class: '<?= ZBX_STYLE_BTN_ALT ?>',
					action: function() {
						(compare_overlay || popup_import.overlay).unsetLoading();
						return true;
					}
				}
			]
		}, (compare_overlay || this.overlay).$btn_submit);
	}

	openImportComparePopup() {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup.import.compare');

		fetch(url.getUrl(), {
			method: 'post',
			body: new FormData(this.form)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogue({
					title: response.header,
					class: response.no_changes ? 'position-middle' : 'modal-popup modal-popup-fullscreen',
					dialogueid: 'popup_import_compare',
					content: response.body,
					buttons: response.buttons,
					script_inline: response.script_inline,
					debug: response.debug
				}, this.overlay.$btn_submit);
			})
			.catch((exception) => {
				document.getElementById('import_file').value = '';

				const msg_bad = this.dialogue.querySelector('.<?= ZBX_STYLE_MSG_BAD ?>');
				if (msg_bad) {
					msg_bad.remove();
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title);

				message_box.insertBefore(this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	submitImportPopup() {
		const url = new Curl('zabbix.php');
		url.setArgument('action', 'popup.import');

		this.overlay.setLoading();

		fetch(url.getUrl(), {
			method: 'post',
			body: new FormData(this.form)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				postMessageOk(response.success.title);

				if ('messages' in response.success) {
					postMessageDetails('success', response.success.messages);
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				location.href = location.href.split('#')[0];
			})
			.catch((exception) => {
				document.getElementById('import_file').value = '';

				const msg_bad = this.dialogue.querySelector('.<?= ZBX_STYLE_MSG_BAD ?>');
				if (msg_bad) {
					msg_bad.remove();
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title);

				message_box.insertBefore(this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	updateWarning(obj, content) {
		if (obj.checked) {
			overlayDialogue({
				class: 'position-middle',
				content: document.createElement('span').innerText = content,
				buttons: [
					{
						title: <?= json_encode(_('OK')) ?>,
						focused: true,
						action: function() {}
					},
					{
						title: <?= json_encode(_('Cancel')) ?>,
						cancel: true,
						class: '<?= ZBX_STYLE_BTN_ALT ?>',
						action: function() {
							obj.checked = false;
						}
					}
				]
			}, obj);
		}
	}

	updateMainCheckbox(action) {
		const all_checkbox = document.getElementById(action + '_all');
		all_checkbox.checked = true;

		this.form.querySelectorAll('.js-' + action).forEach(function (checkbox) {
			if (!checkbox.checked) {
				all_checkbox.checked = false;
			}
		})
	}

	toggleCheckboxColumn(action) {
		const check = document.getElementById(action + '_all').checked;

		this.form.querySelectorAll('.js-' + action).forEach(function (checkbox) {
			if (checkbox.checked !== check) {
				checkbox.checked = check;
			}
		});
	}
}

