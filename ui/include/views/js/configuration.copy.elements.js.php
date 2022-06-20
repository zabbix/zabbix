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

<script>
	const view = {
		form_name: null,

		init({form_name, copy_targetids}) {
			this.form_name = form_name;

			$('[name="copy_type"]').on('change', this.changeTargetType);

			this.changeTargetType(copy_targetids);
		},

		changeTargetType(data) {
			let $multiselect = $('<div>', {
					id: 'copy_targetids',
					class: 'multiselect',
					css: {
						width: '<?= ZBX_TEXTAREA_MEDIUM_WIDTH ?>px'
					},
					'aria-required': true
				}),
				helper_options = {
					id: 'copy_targetids',
					name: 'copy_targetids[]',
					data: data.length ? data : [],
					objectOptions: {
						editable: true
					},
					popup: {
						parameters: {
							dstfrm: view.form_name,
							dstfld1: 'copy_targetids',
							writeonly: 1,
							multiselect: 1
						}
					}
				};

			switch ($('#copy_type').find('input[name=copy_type]:checked').val()) {
				case '<?= COPY_TYPE_TO_TEMPLATE_GROUP ?>':
					helper_options.object_name = 'templateGroup';
					helper_options.popup.parameters.srctbl = 'template_groups';
					helper_options.popup.parameters.srcfld1 = 'groupid';
					break;

				case '<?= COPY_TYPE_TO_HOST_GROUP ?>':
					helper_options.object_name = 'hostGroup';
					helper_options.popup.parameters.srctbl = 'host_groups';
					helper_options.popup.parameters.srcfld1 = 'groupid';
					break;

				case '<?= COPY_TYPE_TO_HOST ?>':
					helper_options.object_name = 'hosts';
					helper_options.popup.parameters.srctbl = 'hosts';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					break;

				case '<?= COPY_TYPE_TO_TEMPLATE ?>':
					helper_options.object_name = 'templates';
					helper_options.popup.parameters.srctbl = 'templates';
					helper_options.popup.parameters.srcfld1 = 'hostid';
					helper_options.popup.parameters.srcfld2 = 'host';
					break;
			}

			$('#copy_targets').html($multiselect);

			$multiselect.multiSelectHelper(helper_options);
		},

		refresh() {
			const url = new Curl('', false);
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
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

		events: {
			hostSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				view.refresh();
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
