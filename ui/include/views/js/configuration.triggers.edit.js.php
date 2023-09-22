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

<script>
	const view = {
		form_name: null,
		context: null,
		parent_discoveryid: null,

		init({form_name, context, parent_discoveryid = null}) {
			this.form_name = form_name;
			this.context = context;
			this.is_discovery = parent_discoveryid !== null;

			$('#description')
				.on('input keydown paste', function() {
					$('#event_name').attr('placeholder', $(this).val());
				})
				.trigger('input');

			// Refresh field visibility on document load.
			this.changeRecoveryMode();

			$('input[name=recovery_mode]').change(() => view.changeRecoveryMode());
			$('input[name=correlation_mode]').change(() => view.changeCorrelationMode());

			let triggers_initialized = false;

			$('#tabs').on('tabscreate tabsactivate', (event, ui) => {
				const panel = (event.type === 'tabscreate') ? ui.panel : ui.newPanel;

				if (panel.attr('id') === 'triggersTab') {
					if (triggers_initialized) {
						return;
					}

					$('#triggersTab')
						.find('.<?= ZBX_STYLE_TEXTAREA_FLEXIBLE ?>')
						.textareaFlexible();

					triggers_initialized = true;
				}
			});
		},

		changeRecoveryMode() {
			const recovery_mode = $('input[name=recovery_mode]:checked').val();

			$('#expression_row').find('label').text(
				(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					? <?= json_encode(_('Problem expression')) ?>
					: <?= json_encode(_('Expression')) ?>
			);
			$('.recovery_expression_constructor_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>);
			$('#correlation_mode_row')
				.toggle(recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>
				);

			this.changeCorrelationMode();
		},

		changeCorrelationMode() {
			const recovery_mode = $('input[name=recovery_mode]:checked').val();
			const correlation_mode = $('input[name=correlation_mode]:checked').val();

			$('#correlation_tag_row')
				.toggle((recovery_mode == <?= ZBX_RECOVERY_MODE_EXPRESSION ?>
					|| recovery_mode == <?= ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION ?>)
					&& correlation_mode == <?= ZBX_TRIGGER_CORRELATION_TAG ?>
				);
		},

		/**
		 * @see init.js add.popup event
		 */
		addPopupValues(data) {
			if (!('object' in data) || data.object !== 'deptrigger') {
				return false;
			}

			for (let i = 0; i < data.values.length; i++) {
				create_var(this.form_name, 'new_dependency[' + i + ']', data.values[i].triggerid, false);
			}

			create_var(this.form_name, 'add_dependency', 1, true);
		},

		removeDependency(triggerid) {
			jQuery('#dependency_' + triggerid).remove();
			jQuery('#dependencies_' + triggerid).remove();
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
				this.events.elementSuccess.bind(this, this.context, this.is_discovery), {once: true}
			);
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			})
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
				this.events.elementSuccess.bind(this, this.context, this.is_discovery), {once: true}
			);
		},

		refresh() {
			const url = new Curl('');
			const form = document.getElementsByName(this.form_name)[0];
			const fields = getFormFields(form);

			post(url.getUrl(), fields);
		},

		events: {
			elementSuccess(context, discovery, e) {
				const data = e.detail;
				let curl = null;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if ('action' in data.success && data.success.action === 'delete') {
						curl = discovery ? new Curl('host_discovery.php') : new Curl('triggers.php')
						curl.setArgument('context', context);
					}
				}

				if (curl == null) {
					view.refresh();
				}
				else {
					location.href = curl.getUrl();
				}
			}
		}
	};
</script>
