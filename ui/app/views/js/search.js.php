<?php declare(strict_types = 0);
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
		original_url: null,

		init() {
			this.original_url = location.href;

			document.addEventListener('click', (e) => {
				const group_link = e.target.closest('a');

				if (group_link !== null) {
					if (group_link.classList.contains('js-edit-templategroup')) {
						e.preventDefault();
						this.editTemplateGroup({groupid: e.target.closest('a').dataset.groupid});
					}
					else if (group_link.classList.contains('js-edit-hostgroup')) {
						e.preventDefault();
						this.editHostGroup({groupid: e.target.closest('a').dataset.groupid});
					}
				}
			});
		},

		editHost(e, hostid) {
			e.preventDefault();
			const host_data = {hostid};

			this.openHostPopup(host_data);
		},

		openHostPopup(host_data) {
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.hostSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', this.original_url);
			}, {once: true});
		},

		editTemplateGroup(parameters = {}) {
			const overlay = PopUp('popup.templategroup.edit', parameters, {
				dialogueid: 'templategroup_edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.groupSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.groupSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', this.original_url);
			}, {once: true});
		},

		editHostGroup(parameters = {}) {
			const overlay = PopUp('popup.hostgroup.edit', parameters, {
				dialogueid: 'hostgroup_edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.groupSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.groupSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', this.original_url);
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

				location.href = location.href;
			},

			groupSuccess(e) {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				location.href = location.href;
			}
		}
	};
</script>
