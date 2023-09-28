<?php declare(strict_types = 0);
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

<script type="text/javascript">
	const view = {
		init() {
			$('z-select[name="severity_min"]').on('change', (e) => {
				document.forms['map.view'].submit();
			});
		},

		editItem(target, data) {
			const overlay = PopUp('item.edit', data, {
				dialogueid: 'item-edit',
				dialogue_class: 'modal-popup-large',
				trigger_element: target
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		editHost(hostid) {
			this.openHostPopup({hostid});
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

		editTemplate(parameters) {
			const overlay = PopUp('template.edit', parameters, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		editTrigger(trigger_data) {
			const overlay = PopUp('trigger.edit', trigger_data, {
				dialogueid: 'trigger-edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
		},

		events: {
			elementSuccess(e) {
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}
				}

				location.href = location.href;
			}
		}
	};
</script>
