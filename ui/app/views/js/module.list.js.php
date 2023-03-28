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

		init() {
			document.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-edit-module')) {
					this._edit({moduleid: e.target.dataset.moduleid});
				}
				else if (e.target.classList.contains('js-enable-module')) {
					this._enable(e.target, [e.target.dataset.moduleid], false);
				}
				else if (e.target.classList.contains('js-massenable-module')) {
					this._enable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
				else if (e.target.classList.contains('js-disable-module')) {
					this._disable(e.target, [e.target.dataset.moduleid], false);
				}
				else if (e.target.classList.contains('js-massdisable-module')) {
					this._disable(e.target, Object.keys(chkbxRange.getSelectedIds()));
				}
			});
		}

		_edit(parameters = {}) {
			const overlay = PopUp('module.edit', parameters, {
				dialogueid: 'module-edit',
				dialogue_class: 'modal-popup-medium',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				postMessageOk(e.detail.title);

				if ('messages' in e.detail) {
					postMessageDetails('success', e.detail.messages);
				}

				uncheckTableRows('modules');

				location.href = location.href;
			});
		}

		_enable(target, moduleids, mass_update = true) {
			if (mass_update) {
				const confirmation = moduleids.length > 1
					? <?= json_encode(_('Enable selected modules?')) ?>
					: <?= json_encode(_('Enable selected module?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'module.enable');

			this._post(target, moduleids, curl);
		}

		_disable(target, moduleids, mass_update = true) {
			if (mass_update) {
				const confirmation = moduleids.length > 1
					? <?= json_encode(_('Disable selected modules?')) ?>
					: <?= json_encode(_('Disable selected module?')) ?>;

				if (!window.confirm(confirmation)) {
					return;
				}
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'module.disable');

			this._post(target, moduleids, curl);
		}

		_post(target, moduleids, curl) {
			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('module')) ?>
			);

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({moduleids: moduleids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('modules', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('modules');
					}

					location.href = location.href;
				})
				.catch(() => {
					clearMessages();

					const message_box = makeMessageBox('bad', [<?= json_encode(_('Unexpected server error.')) ?>]);

					addMessage(message_box);
				})
				.finally(() => {
					target.classList.remove('is-loading');
				});
		}
	};
</script>
