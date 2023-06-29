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

<script>
	const view = new class {

		constructor() {
			this._registerEvents();
		}

		editHost({hostid}) {
			this._openHostPopup({hostid});
		}

		_openHostPopup(host_data) {
			const original_url = location.href;
			const overlay = PopUp('popup.host.edit', host_data, {
				dialogueid: 'host_edit',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.create', this.events.elementSuccess);
			overlay.$dialogue[0].addEventListener('dialogue.update', this.events.elementSuccess);
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.elementDelete);
			overlay.$dialogue[0].addEventListener('overlay.close', () => {
				history.replaceState({}, '', original_url);
			});
			overlay.$dialogue[0].addEventListener('edit.linked', (e) =>
				this._editTemplate({templateid:e.detail.templateid})
			);
		}

		_editTemplate(parameters) {
			const overlay = PopUp('template.edit', parameters, {
				dialogueid: 'templates-form',
				dialogue_class: 'modal-popup-large',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', this.events.elementSuccess, {once: true});
			overlay.$dialogue[0].addEventListener('dialogue.delete', this.events.elementDelete, {once: true});
			overlay.$dialogue[0].addEventListener('edit.linked', (e) => {
				this._editTemplate({templateid:e.detail.templateid})
			})
		}

		_registerEvents() {
			this.events = {
				elementSuccess(e) {
					const data = e.detail;

					if ('success' in data) {
						postMessageOk(data.success.title);

						if ('messages' in data.success) {
							postMessageDetails('success', data.success.messages);
						}
					}

					location.href = location.href;
				},

				elementDelete(e) {
					if ('success' in e.detail) {
						postMessageOk(e.detail.success.title);

						if ('messages' in e.detail.success) {
							postMessageDetails('success', e.detail.success.messages);
						}
					}

					location.href = new Curl('hostinventories.php').getUrl();
				}
			};
		}
	};
</script>
