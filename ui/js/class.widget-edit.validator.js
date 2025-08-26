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
 * Widget editor component for widget configuration validation.
 */
class CWidgetEditValidator {

	#dashboard_data;

	#result_callbacks = new Map();

	#abort_controller = null;

	constructor({dashboard}) {
		this.#dashboard_data = dashboard.getData();
	}

	check({type, name, fields}) {
		if (this.#abort_controller !== null) {
			// Run during the next micro task.
			this.#abort_controller.abort();
		}

		this.#abort_controller = new AbortController();

		// Keep signal of current abort controller.
		const signal = this.#abort_controller.signal;

		const url = new URL('zabbix.php', location.href);

		url.searchParams.set('action', 'dashboard.widget.check');

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify({
				templateid: this.#dashboard_data.templateid ?? undefined,
				type,
				name,
				fields
			}),
			signal
		})
			.then(response => response.json())
			.then(response => {
				if ('error' in response) {
					throw {error: response.error};
				}

				this.#dispatchResult(response);
			})
			.catch(exception => {
				if (signal.aborted) {
					return;
				}

				this.#dispatchResult(
					typeof exception === 'object' && 'error' in exception
						? {error: exception.error}
						: {error: {title: t('Failed to update widget properties.')}}
				);
			})
			.finally(() => {
				if (!signal.aborted) {
					this.#abort_controller = null;
				}
			});
	}

	stop() {
		if (this.#abort_controller !== null) {
			this.#abort_controller.abort();
			this.#abort_controller = null;
		}
	}

	onResult({callback, priority, once = false}) {
		this.#result_callbacks.set(callback, {priority, once});
	}

	inProgress() {
		return this.#abort_controller !== null;
	}

	#dispatchResult(response) {
		const callbacks_data = [...this.#result_callbacks.entries()]
			.sort((a, b) => a[1].priority - b[1].priority);

		for (const [callback, {once}] of callbacks_data) {
			if (once) {
				this.#result_callbacks.delete(callback);
			}

			callback(response);
		}
	}
}
