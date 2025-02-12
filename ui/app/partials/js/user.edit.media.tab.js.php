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

?>

<script>
	const mediaView = new class {
		init({medias}) {
			this.#initMedias(medias);
			this.#initActions();
		}

		#initMedias(medias) {
			for (const media of medias) {
				this.#addMedia(media);
			}
		}

		#initActions() {
			document.querySelector('#mediaTab').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-add')) {
					this.#editMedia();
				}
				else if (e.target.classList.contains('js-edit')) {
					this.#editMedia(e.target.closest('tr'));
				}
				else if (e.target.classList.contains('js-remove')) {
					e.target.closest('tr').remove();
				}
				else if (e.target.classList.contains('js-status')) {
					this.#changeStatus(e.target.closest('tr'));
				}
			});
		}

		#editMedia(row = null) {
			let popup_params;

			if (row !== null) {
				const row_index = row.dataset.row_index;

				popup_params = {
					edit: 1,
					row_index,
					mediaid: row.querySelector(`[name="medias[${row_index}][mediaid]"`).value,
					mediatypeid: row.querySelector(`[name="medias[${row_index}][mediatypeid]"`).value,
					period: row.querySelector(`[name="medias[${row_index}][period]"`).value,
					severity: row.querySelector(`[name="medias[${row_index}][severity]"`).value,
					active: row.querySelector(`[name="medias[${row_index}][active]"`).value,
					provisioned: row.querySelector(`[name="medias[${row_index}][provisioned]"`).value,
					mediatype_name: row.querySelector(`[name="medias[${row_index}][mediatype_name]"`).value
				};

				const sendto_input = row.querySelector(`[name$="medias[${row_index}][sendto]"`);

				if (sendto_input === null) {
					const sendto_inputs = row.querySelectorAll(`[name="medias[${row_index}][sendto][]"`);

					popup_params.sendto_emails = Array.from(sendto_inputs).map((input) => input.value);
				}
				else {
					popup_params.sendto = sendto_input.value;
				}
			}
			else {
				let row_index = 0;

				while (document.querySelector(`#media-table [data-row_index="${row_index}"]`) !== null) {
					row_index++;
				}

				popup_params = {row_index};
			}

			const overlay = PopUp('popup.media', popup_params, {
				dialogueid: 'user-media-edit',
				dialogue_class: 'modal-popup-generic'
			});

			overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
				if (row !== null) {
					this.#updateMedia(row, e.detail);
				}
				else {
					this.#addMedia(e.detail);
				}
			});
		}

		#addMedia(media) {
			document
				.querySelector('#media-table tbody')
				.insertAdjacentHTML('beforeend', this.#evaluateTemplate(media));
		}

		#updateMedia(row, media) {
			row.insertAdjacentHTML('afterend', this.#evaluateTemplate(media));
			row.remove();
		}

		#evaluateTemplate(media) {
			let sendto_array = null;
			let sendto_full = '';

			if (Array.isArray(media.sendto)) {
				sendto_full = media.sendto.join(', ');
				sendto_array = media.sendto;
				delete(media.sendto);
			}
			else {
				sendto_full = media.sendto;
			}

			media.sendto_short = sendto_full.length > 50
				? `${sendto_full.substring(0, 50)}...`
				: sendto_full;

			const template = new Template(document.getElementById('media-row-tmpl').innerHTML);
			const row = template.evaluateToElement(media);

			if (media.mediaid == 0) {
				row.querySelector(`[name="medias[${media.row_index}][mediaid]"`).remove();
			}

			if (media.sendto_short.length <= 50) {
				row.querySelector('td:nth-child(2) span[data-hintbox]').dataset.hintbox = '0';
			}

			if (sendto_array !== null) {
				const sendto_input = row.querySelector(`[name="medias[${media.row_index}][sendto]"`);

				for (const [index, sendto] of Object.entries(sendto_array)) {
					const hInput = document.createElement('input');
					hInput.setAttribute('type', 'hidden');
					hInput.setAttribute('id', `medias_${media.row_index}_sendto_${index}`);
					hInput.setAttribute('name', `medias[${media.row_index}][sendto][]`);
					hInput.setAttribute('value', sendto);
					sendto_input.parentNode.insertBefore(hInput, sendto_input);
				}

				sendto_input.remove();
			}

			if (media.mediatype_status == <?= MEDIA_TYPE_STATUS_ACTIVE ?>) {
				if (media.active == <?= MEDIA_STATUS_ACTIVE ?>) {
					this.#enableMedia(row);
				}
				else {
					this.#disableMedia(row);
				}

				row.querySelector('button[data-hintbox]').remove();
				row.querySelector('.js-status + span').remove();
			}
			else {
				row.querySelector('.js-status').remove();
			}

			this.#evaluateSeverity(media, row);

			return row.outerHTML;
		}


		#evaluateSeverity(data, row) {
			const severities_span = row.querySelectorAll('.status-container span');
			let severity = <?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>;

			for (;severity < <?= TRIGGER_SEVERITY_COUNT ?>; severity++) {
				const media_active = (data.severity & (1 << severity)) !== 0;
				const span = severities_span[severity];

				if (media_active) {
					span.dataset.hintboxContents += ' (<?= json_encode(_('on')) ?>)';
				}
				else {
					span.className = '<?= ZBX_STYLE_STATUS_DISABLED ?>';
					span.dataset.hintboxContents += ' (<?= json_encode(_('off')) ?>)';
				}

				span.dataset.hintbox = '1';
			}
		}

		#changeStatus(row) {
			const status_input = row.querySelector(`[name="medias[${row.dataset.row_index}][active]"`);

			if (status_input.value === '1') {
				this.#enableMedia(row);
			}
			else {
				this.#disableMedia(row);
			}
		}

		#enableMedia(row) {
			const status_input = row.querySelector(`[name="medias[${row.dataset.row_index}][active]"`);
			const status_button = row.querySelector('.js-status');

			status_input.value = '0';
			status_button.classList.replace('<?= ZBX_STYLE_RED ?>', '<?= ZBX_STYLE_GREEN ?>');
			status_button.textContent = t('Enabled');
		}

		#disableMedia(row) {
			const status_input = row.querySelector(`[name="medias[${row.dataset.row_index}][active]"`);
			const status_button = row.querySelector('.js-status');

			status_input.value = '1';
			status_button.classList.replace('<?= ZBX_STYLE_GREEN ?>', '<?= ZBX_STYLE_RED ?>');
			status_button.textContent = t('Disabled');
		}
	}
</script>
