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


class CTestBroadcaster extends CWidget {

	promiseUpdate() {
		if (this.hasEverUpdated()) {
			this.#updateView();

			return Promise.resolve();
		}

		return super.promiseUpdate();
	}

	setContents(response) {
		super.setContents(response);

		this._body.addEventListener('click', e => {
			if (e.target.matches('.js-section .js-objects button')) {
				this.#onObjectClick(e.target);
			}
			else if (e.target.matches('.js-broadcast')) {
				this.#onBroadcastClick(e.target);
			}
		});

		this._body.addEventListener('input', e => {
			if (e.target.matches('.js-buffer')) {
				this.#onBufferInput(e.target);
			}
		});

		this.#updateView();
	}

	#updateView() {
		const no_data_message = this._body.querySelector(`.${ZBX_STYLE_NO_DATA_MESSAGE}`);

		if (this.isReferred()) {
			if (no_data_message !== null) {
				no_data_message.remove();
			}

			this._body.querySelector('.js-view').style.display = '';

			for (const section of this._body.querySelectorAll('.js-section')) {
				section.style.display = this.isReferred(section.dataset.type) ? '' : 'none';
			}
		}
		else {
			this._body.querySelector('.js-view').style.display = 'none';

			if (no_data_message === null) {
				this.setCoverMessage({
					message: 'Widget not referred',
					description: 'Please connect widgets',
					icon: ZBX_ICON_WIDGET_AWAITING_DATA_LARGE
				});
			}
		}
	}

	onFeedback({type, value}) {
		if (this.hasEverUpdated()) {
			const time = new Date().toTimeString().slice(0, 8);

			const feedbacks_textarea = this._body.querySelector('textarea[name="feedbacks"]');

			let feedbacks = `${time} | ${type} | ${JSON.stringify(value)}`;

			if (feedbacks_textarea.value !== '') {
				feedbacks = `${feedbacks}\n${feedbacks_textarea.value}`;
			}

			feedbacks_textarea.value = feedbacks;
		}

		return false;
	}

	onReferredUpdate() {
		if (this.hasEverUpdated()) {
			this.#updateView();
		}
	}

	#onObjectClick(target) {
		const section = target.closest('.js-section');

		if (CTestBroadcaster.isTypeMultiple(section.dataset.type)) {
			if (target.dataset.type === 'default') {
				for (const button of section.querySelectorAll('.js-objects button')) {
					button.classList.toggle('on', button === target);
				}
			}
			else {
				section.querySelector('.js-objects button[data-type="default"]').classList.remove('on');
				target.classList.toggle('on');
			}
		}
		else {
			for (const button of section.querySelectorAll('.js-objects button')) {
				button.classList.toggle('on', button === target);
			}
		}

		if (target.dataset.type === 'default') {
			this.broadcast({[section.dataset.type]: CWidgetsData.getDefault(section.dataset.type)});
		}
		else {
			switch (section.dataset.type) {
				case CWidgetsData.DATA_TYPE_TIME_PERIOD:
					const button = section.querySelector('.js-objects button.on');

					this.broadcast({
						[section.dataset.type]: {
							from: button.dataset.from,
							from_ts: button.dataset.from_ts,
							to: button.dataset.to,
							to_ts: button.dataset.to_ts
						}
					});
					break;

				default:
					const broadcast_ids = [];

					for (const button of section.querySelectorAll('.js-objects button.on')) {
						broadcast_ids.push(button.dataset.id);
					}

					this.broadcast({[section.dataset.type]: broadcast_ids});
					break;
			}
		}

		section.querySelector('.js-buffer').classList.remove('has-errors');
		section.querySelector('.js-broadcast').disabled = false;
	}

	#onBufferInput(target) {
		const section = target.closest('.js-section');
		const broadcast_button = section.querySelector('.js-broadcast');

		for (const button of section.querySelectorAll('.js-objects button')) {
			button.classList.remove('on');
		}

		try {
			JSON.parse(target.value);

			target.classList.remove('has-errors');
			broadcast_button.disabled = false;
		}
		catch (exception) {
			target.classList.add('has-errors');
			broadcast_button.disabled = true;
		}
	}

	#onBroadcastClick(target) {
		const section = target.closest('.js-section');
		const buffer_input = section.querySelector('.js-buffer');

		this.broadcast({[section.dataset.type]: JSON.parse(buffer_input.value)});
	}

	broadcast(data) {
		super.broadcast(data);

		for (const [type, value] of Object.entries(data)) {
			this._body.querySelector(`.js-section[data-type="${type}"] .js-buffer`).value = JSON.stringify(value);
		}
	}

	hasPadding() {
		return false;
	}

	static isTypeMultiple(type) {
		switch (type) {
			case CWidgetsData.DATA_TYPE_HOST_GROUP_IDS:
			case CWidgetsData.DATA_TYPE_HOST_IDS:
			case CWidgetsData.DATA_TYPE_ITEM_IDS:
				return true;

			default:
				return false;
		}
	}
}
