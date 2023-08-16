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

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script>
	const view = {
		init({checkbox_hash, checkbox_object, context}) {
			this.checkbox_hash = checkbox_hash;
			this.checkbox_object = checkbox_object;
			this.context = context;

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

			$('#filter_state')
				.on('change', function() {
					$('input[name=filter_status]').prop('disabled', $('input[name=filter_state]:checked').val() != -1);
				})
				.trigger('change');

			this._initActions();
		},

		_initActions() {
			document.querySelector('.js-copy').addEventListener('click', () => {
				const overlay = this.openCopyPopup();
				const dialogue = overlay.$dialogue[0];

				dialogue.addEventListener('dialogue.submit', (e) => {
					postMessageOk(e.detail.title);

					const uncheckids = Object.keys(chkbxRange.getSelectedIds());
					uncheckTableRows('triggers_' + this.checkbox_hash, [], false);
					chkbxRange.checkObjects(this.checkbox_object, uncheckids, false);
					chkbxRange.update(this.checkbox_object);

					if ('messages' in e.detail) {
						postMessageDetails('success', e.detail.messages);
					}

					location.href = location.href;
				});
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

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.events.elementSuccess.bind(this, this.context), {once: true}
			);
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		editTemplate(e, templateid) {
			e.preventDefault();
			const template_data = {templateid};

			this.openTemplatePopup(template_data);
		},

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit',
				this.events.elementSuccess.bind(this, this.context), {once: true}
			);
		},

		openCopyPopup() {
			const parameters = {
				triggerids: Object.keys(chkbxRange.getSelectedIds()),
				source: 'triggers'
			};

			const filter_hostids = document.getElementsByName('filter_hostids[]');

			if (filter_hostids.length == 1) {
				parameters.src_hostid = filter_hostids[0].value;
			}

			return PopUp('copy.edit', parameters, {
				dialogueid: 'copy',
				dialogue_class: 'modal-popup-static'
			});
		},

		events: {
			elementSuccess(context, e) {
				const data = e.detail;
				let curl = null;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if ('action' in data.success && data.success.action === 'delete') {
						curl = new Curl('triggers.php');
						curl.setArgument('context', context);
					}
				}

				uncheckTableRows('triggers_' + this.checkbox_hash, [], false);

				location.href = curl === null ? location.href : curl.getUrl();
			}
		}
	};
</script>
