<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
			this.#initActions();
		}

		#initActions() {
			document.querySelector('.js-create-proxy-group').addEventListener('click', () => this.#edit());

			const form = document.getElementById('proxy-group-list');

			form.addEventListener('click', (e) => {
				if (e.target.classList.contains('js-edit-proxy-group')) {
					this.#edit({proxy_groupid: e.target.dataset.proxy_groupid});
				}
				else if (e.target.classList.contains('js-edit-proxy')) {
					this.#editProxy(e.target.dataset.proxyid);
				}
			});

			form.querySelector('.js-massdelete-proxy-group').addEventListener('click', (e) => {
				this.#delete(e.target, Object.keys(chkbxRange.getSelectedIds()));
			});
		}

		#edit(parameters = {}) {
			const overlay = PopUp('popup.proxygroup.edit', parameters, {
				dialogueid: 'proxy-group-edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.#reload(e.detail.success));
		}

		#editProxy(proxyid) {
			const overlay = PopUp('popup.proxy.edit', {proxyid}, {
				dialogueid: 'proxy_edit',
				dialogue_class: 'modal-popup-static',
				prevent_navigation: true
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => this.#reload(e.detail.success));
		}

		#reload(success) {
			postMessageOk(success.title);

			if ('messages' in success) {
				postMessageDetails('success', success.messages);
			}

			uncheckTableRows('proxygroup');

			location.href = location.href;
		}

		#delete(target, proxy_groupids) {
			const confirmation = proxy_groupids.length > 1
				? <?= json_encode(_('Delete selected proxy groups?')) ?>
				: <?= json_encode(_('Delete selected proxy group?')) ?>;

			if (!window.confirm(confirmation)) {
				return;
			}

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'proxygroup.delete');

			this.#post(target, proxy_groupids, curl);
		}

		#post(target, proxy_groupids, curl) {
			curl.setArgument('<?= CCsrfTokenHelper::CSRF_TOKEN_NAME ?>',
				<?= json_encode(CCsrfTokenHelper::get('proxygroup'), JSON_THROW_ON_ERROR) ?>
			);

			target.classList.add('is-loading');

			return fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({proxy_groupids})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						if ('title' in response.error) {
							postMessageError(response.error.title);
						}

						postMessageDetails('error', response.error.messages);

						uncheckTableRows('proxygroup', response.keepids ?? []);
					}
					else if ('success' in response) {
						postMessageOk(response.success.title);

						if ('messages' in response.success) {
							postMessageDetails('success', response.success.messages);
						}

						uncheckTableRows('proxygroup');
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
