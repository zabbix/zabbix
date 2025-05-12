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


class CTestListener extends CWidget {

	promiseUpdate() {
		if (this.getFieldsReferredData().size === 0) {
			if (this._body.innerHTML === '') {
				this.setCoverMessage({
					message: 'Widget not listening',
					description: 'Please connect to broadcasting widgets',
					icon: ZBX_ICON_WIDGET_AWAITING_DATA_LARGE
				});
			}

			return Promise.resolve();
		}

		if (this.hasEverUpdated()) {
			this.#updateBroadcasts();

			return Promise.resolve();
		}

		return super.promiseUpdate();
	}

	getUpdateRequestData() {
		const referred_fields = {};

		for (const [path, {descriptor}] of this.getFieldsReferredData()) {
			let source_label;

			switch (descriptor.sender_type) {
				case 'dashboard':
					source_label = 'DASHBOARD';
					break;

				case 'widget':
					source_label = ZABBIX.Dashboard.getSelectedDashboardPage().getWidget(descriptor.sender_unique_id)
						.getHeaderName();

					break;

				default:
					source_label = 'Unknown';
			}

			referred_fields[path] = source_label;
		}

		return {
			...super.getUpdateRequestData(),
			fields: undefined,
			referred_fields
		};
	}

	setContents(response) {
		super.setContents(response);

		this._body.addEventListener('click', e => {
			if (e.target.matches('.js-section .js-feedback')) {
				this.#onFeedbackClick(e.target);
			}
		});

		this._body.addEventListener('input', e => {
			if (e.target.matches('.js-buffer')) {
				this.#onBufferInput(e.target);
			}
		});

		this.#updateBroadcasts();
	}

	#updateBroadcasts() {
		const referred_data = this.getFieldsReferredData();

		const updates = [];

		for (const [path, {value}] of referred_data) {
			if (this.isFieldsReferredDataUpdated(path)) {
				const section = this._body.querySelector(`.js-section[data-name="${path}"]`);
				const buffer_1 = section.querySelector(`.js-buffer[data-queue="1"]`);
				const feedback_button_1 = section.querySelector(`.js-feedback[data-queue="1"]`);
				const buffer_2 = section.querySelector(`.js-buffer[data-queue="2"]`);
				const feedback_button_2 = section.querySelector(`.js-feedback[data-queue="2"]`);

				buffer_2.value = buffer_1.value;
				buffer_2.classList.toggle('has-errors', buffer_1.classList.contains('has-errors'));
				feedback_button_2.disabled = feedback_button_1.disabled;

				buffer_1.value = JSON.stringify(value);
				buffer_1.classList.remove('has-errors');
				feedback_button_1.disabled = false;

				updates.push({type: section.dataset.type, value});
			}
		}

		if (updates.length > 0) {
			const time = new Date().toTimeString().slice(0, 8);

			let text = '';

			for (const {type, value} of updates) {
				const time_or_pad = text === '' ? time : String().padStart(time.length);

				text += `${text !== '' ? '\n' : ''}${time_or_pad} | ${type} | ${JSON.stringify(value)}`;
			}

			const broadcasts_textarea = this._body.querySelector('textarea[name="broadcasts"]');

			broadcasts_textarea.value =
				`${text}${broadcasts_textarea.value !== '' ? '\n' : ''}${broadcasts_textarea.value}`;
		}
	}

	#onBufferInput(target) {
		const section = target.closest('.js-section');
		const feedback_button = section.querySelector(`.js-feedback[data-queue="${target.dataset.queue}"]`);

		try {
			JSON.parse(target.value);

			target.classList.remove('has-errors');
			feedback_button.disabled = false;
		}
		catch (exception) {
			target.classList.add('has-errors');
			feedback_button.disabled = true;
		}
	}

	#onFeedbackClick(target) {
		const section = target.closest('.js-section');
		const buffer = section.querySelector(`.js-buffer[data-queue="${target.dataset.queue}"]`);

		this.feedback({[section.dataset.name]: JSON.parse(buffer.value)});
	}

	hasPadding() {
		return false;
	}
}
