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
	const media_tab = new class {

		#userid;

		init({userid, medias}) {
			this.#userid = userid;

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
				else if (e.target.tagName === 'BUTTON' && e.target.classList.contains('js-status')) {
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
					mediatypeid: row.querySelector(`[name="medias[${row_index}][mediatypeid]"`).value,
					period: row.querySelector(`[name="medias[${row_index}][period]"`).value,
					severities: [],
					active: row.querySelector(`[name="medias[${row_index}][active]"`).value,
					provisioned: row.querySelector(`[name="medias[${row_index}][provisioned]"`).value
				};

				const severity = row.querySelector(`[name="medias[${row_index}][severity]"`).value;

				for (let i = <?= TRIGGER_SEVERITY_NOT_CLASSIFIED ?>; i < <?= TRIGGER_SEVERITY_COUNT ?>; i++) {
					if (severity & (1 << i)) {
						popup_params.severities.push(i);
					}
				}

				const mediaid_input = row.querySelector(`[name$="medias[${row_index}][mediaid]"`);
				if (mediaid_input !== null) {
					popup_params.mediaid = mediaid_input.value;
				}

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

			const overlay = PopUp('popup.media.edit', {...popup_params, userid: this.#userid}, {
				dialogueid: 'media-edit',
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

			if (!('mediaid' in media)) {
				row.querySelector(`[name="medias[${media.row_index}][mediaid]"`).remove();
			}

			if (media.mediatype_name === null) {
				const mediatype_name_span = row.querySelector('td:nth-child(1) span');
				mediatype_name_span.textContent = <?= json_encode(_('Unknown')) ?>;
				mediatype_name_span.classList.add('<?= ZBX_STYLE_DISABLED ?>');
				row.querySelector('button[data-hintbox]').remove();
				row.querySelector('.js-edit').disabled = true;
			}

			if (media.sendto_short.length > 50) {
				const hint = row.querySelector('td:nth-child(2) span');

				hint.setAttribute('data-hintbox-contents', escapeHtml(sendto_full));
				hint.setAttribute('data-hintbox', '1');
				hint.setAttribute('data-hintbox-static', '1');
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

			if (media.provisioned == <?= CUser::PROVISION_STATUS_YES ?>) {
				row.querySelector('.js-remove').setAttribute('disabled', 'disabled');
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
					span.dataset.hintboxContents += ' (' + <?= json_encode(_('on')) ?> + ')';
				}
				else {
					span.className = '<?= ZBX_STYLE_STATUS_DISABLED ?>';
					span.dataset.hintboxContents += ' (' + <?= json_encode(_('off')) ?> + ')';
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
			status_button.classList.replace('<?= ZBX_STYLE_COLOR_NEGATIVE ?>', '<?= ZBX_STYLE_COLOR_POSITIVE ?>');
			status_button.textContent = <?= json_encode(_('Enabled')) ?>;
		}

		#disableMedia(row) {
			const status_input = row.querySelector(`[name="medias[${row.dataset.row_index}][active]"`);
			const status_button = row.querySelector('.js-status');

			status_input.value = '1';
			status_button.classList.replace('<?= ZBX_STYLE_COLOR_POSITIVE ?>', '<?= ZBX_STYLE_COLOR_NEGATIVE ?>');
			status_button.textContent = <?= json_encode(_('Disabled')) ?>;
		}
	}
</script>
