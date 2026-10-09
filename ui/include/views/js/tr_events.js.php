<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
		init() {
			$.subscribe("acknowledge.create", function(event, response, overlay) {
				postMessageOk(response.success.title);
				location.href = location.href;
			});

			this._initActions();
		},

		_initActions() {
			document.querySelector('.js-correlation_edit')?.addEventListener('click', (e) =>
				this._editCorrelation(e.target)
			);
		},

		editItem(target, data) {
			const overlay = PopUp('item.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target,
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		editHost(hostid) {
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		editTrigger(trigger_data) {
			clearMessages();

			const overlay = PopUp('trigger.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		_editCorrelation(trigger) {
			const overlay = PopUp('correlation.edit', {correlationid: trigger.dataset.correlationid}, {
				dialogueid: 'correlation-form',
				dialogue_class: 'modal-popup-medium',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			});
		},

		openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.close', () => {
				history.replaceState({}, '', original_url);
			}, {once: true});
		},

		openTemplatePopup(template_data) {
			const overlay =  PopUp('template.edit', template_data, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		events: {
			elementSuccess(e) {
				let new_href = location.href;
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if (data.success.action === 'delete') {
						// If item or trigger is deleted redirect to problems page.
						let list_url = new Curl('zabbix.php');

						list_url.setArgument('action', 'problem.view');
						new_href = list_url.getUrl();
					}
				}

				location.href = new_href;
			}
		}
	};
</script>
