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


/**
 * @var CView $this
 */
?>

<script type="text/javascript">
	const view = new class {
		init() {
			document.getElementById('messages_enabled').addEventListener('click', () => {
				this._updateForm();
			});

			this._updateForm();
			this._handleAction();
		}

		_updateForm() {
			document
				.getElementById('notificationsTab')
				.querySelectorAll('input:not([name="messages[enabled]"]),button')
				.forEach((elem) => {
					elem.toggleAttribute('disabled', !document.getElementById('messages_enabled').checked);
				});
		}

		_handleAction() {
			document.querySelector('#notificationsTab').addEventListener('click', (e) => {
				if (e.target.classList.contains('js-test_sound')) {
					this._userSoundHandler(e.target.dataset.message_sounds);
				}
				else if (e.target.classList.contains('js-audio_stop')) {
					this._stopAudioHandler();
				}
			});
		}

		_userSoundHandler(message_sounds) {
			testUserSound(`messages_sounds.${message_sounds}`);
		}

		_stopAudioHandler() {
			AudioControl.stop();
		}
	}
</script>
