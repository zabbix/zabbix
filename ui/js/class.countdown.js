/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CCountdown {

	#container;
	#countdown_template;
	#countdown_end_template;
	#expires_at;

	constructor(container, expires_at, countdown_html, countdown_end_html) {
		this.#container = container;
		this.#expires_at = expires_at;
		this.#countdown_template = new Template(countdown_html);
		this.#countdown_end_template = new Template(countdown_end_html);

		this.#updateMessage();
	}

	#updateMessage() {
		if (!this.#container) {
			return;
		}

		const expires_in = this.#expires_at - new CDate().getTime();

		if (expires_in <= 0) {
			this.#container.innerHTML = this.#countdown_end_template.evaluate();
		}
		else {
			this.#container.innerHTML = this.#countdown_template.evaluate({
				expires_in: new Date(expires_in).toISOString().slice(14, 19)
			});

			setTimeout(() => this.#updateMessage(), 200);
		}
	}

	destroy() {
		this.#container = null;
	}
}
