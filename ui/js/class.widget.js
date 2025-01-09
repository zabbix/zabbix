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


/*
 * The base class of all dashboard widgets. Depending on widget needs, it can be instantiated directly or be extended.
 */
class CWidget extends CWidgetBase {

	/**
	 * Define initial state of the widget.
	 *
	 * Invoked on widget instantiation. No HTML or data manipulation must be done at this step.
	 *
	 * Possible widget state: WIDGET_STATE_INITIAL.
	 */
	onInitialize() {
	}

	/**
	 * Prepare widget for the first activation.
	 *
	 * An HTML structure can be created, but no data manipulation must be done at this step.
	 *
	 * Invoked once, before the first activation of the dashboard page.
	 *
	 * Possible widget state: WIDGET_STATE_INITIAL.
	 */
	onStart() {
	}

	/**
	 * Make the widget live! Activate custom event listeners.
	 *
	 * Widget update is requested automatically by invoking "promiseUpdate" method immediately and periodically later on
	 * until the widget is deactivated again (the dashboard page is switched away to display another dashboard page).
	 *
	 * Invoked on each activation of the dashboard page.
	 *
	 * Possible widget state: WIDGET_STATE_INACTIVE.
	 */
	onActivate() {
	}

	/**
	 * Stop any interactivity of the widget. Deactivate custom event listeners.
	 *
	 * Updating of the widget is automatically stopped at this step.
	 *
	 * Invoked on each deactivation of the dashboard page.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 */
	onDeactivate() {
	}

	/**
	 * Destroy the widget. Invoked once, when the widget or the dashboard page gets deleted.
	 *
	 * Possible widget state: WIDGET_STATE_INACTIVE.
	 */
	onDestroy() {
	}

	/**
	 * Take action when references to the widget have changed.
	 *
	 * @see CWidgetBase.isReferred
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 */
	onReferredUpdate() {
	}

	/**
	 * Set widget to editing mode. This is one-way action. Be aware that the widget may not be in the active state.
	 *
	 * Possible widget state: WIDGET_STATE_INITIAL, WIDGET_STATE_ACTIVE, WIDGET_STATE_INACTIVE.
	 */
	onEdit() {
	}

	/**
	 * Take whatever action is required on each resize event of the widget contents' container. Be aware that the widget
	 * may not be in the active state. For some widgets, the updating cycle will need to be restarted on resize event.
	 * This is done by checking that the widget is active and then invoking "_startUpdating" method. And if the widget
	 * is not active, it will still update as soon as it's activated.
	 *
	 * Possible widget state: WIDGET_STATE_INITIAL, WIDGET_STATE_ACTIVE, WIDGET_STATE_INACTIVE.
	 */
	onResize() {
	}

	/**
	 * Whether to display small vertical padding for the widget contents' container.
	 *
	 * @returns {boolean}
	 */
	hasPadding() {
		return this._view_mode !== ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER;
	}

	/**
	 * Feedback event callback.
	 *
	 * Invoked when the feedback is received from another widget listening to this one for a particular type of data.
	 * Invoked only if the feedback value is different from the broadcast one.
	 *
	 * Must return true to re-broadcast the feedback value or false to ignore the event.
	 * Feedbacks-aware widgets must generally re-broadcast the value.
	 *
	 * @param {string} type   Out data type, as specified in the manifest.json.
	 * @param {*}      value  Feedback value.
	 *
	 * @returns {boolean}  Whether to rebroadcast the value automatically.
	 */
	onFeedback({type, value}) {
		return false;
	}

	/**
	 * Check if the referred data is valid for running the next update.
	 *
	 * If not, the widget will skip running the update cycle (promiseUpdate). Instead, it will clear the contents
	 * (clearContents), enter the "Awaiting data" state and broadcast the default data to the listeners.
	 *
	 * By default, only the referred fields marked as required are checked for having non-default (non-empty) values.
	 *
	 * @returns {boolean}
	 */
	isFieldsReferredDataValid() {
		return this.isFieldsReferredDataRequirementFulfilled();
	}

	/**
	 * Promise to update the widget.
	 *
	 * Invoked immediately when a dashboard page is displayed, and periodically later on, until the dashboard page is
	 * switched away to display another dashboard page.
	 *
	 * The method must return a promise which must eventually become either resolved, if the update runs as expected, or
	 * rejected in case of unexpected errors (like network errors or unexpected server response). If the promise is
	 * rejected, the update cycle will be automatically restarted shortly.
	 *
	 * This method is not limited to invoking webserver requests. Any activity can be done to get the updated data.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 *
	 * @returns {Promise<any>}
	 */
	promiseUpdate() {
		const curl = new Curl('zabbix.php');

		curl.setArgument('action', `widget.${this._type}.view`);

		return fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(this.getUpdateRequestData()),
			signal: this._update_abort_controller.signal
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					this.processUpdateErrorResponse(response.error);

					return;
				}

				this.processUpdateResponse(response);
			});
	}

	/**
	 * Resolve as soon as the widget is fully rendered (ready for printing).
	 *
	 * The method is called once, immediately after the promiseUpdate is resolved.
	 * Custom implementation must also resolve the promise provided by the default implementation.
	 *
	 * @returns {Promise<any>}
	 */
	promiseReady() {
		return new Promise(resolve => {
			let incomplete = 0;

			const image_complete = () => {
				if (--incomplete === 0) {
					resolve();
				}
			};

			for (const img of this._body.querySelectorAll('img')) {
				if (!img.complete) {
					img.addEventListener('load', image_complete);
					img.addEventListener('error', image_complete);

					incomplete++;
				}
			}

			if (incomplete === 0) {
				// Wait until preloader icon is removed on animation frame.
				requestAnimationFrame(() => resolve());
			}
		});
	}

	/**
	 * Prepare server request data for updating the widget.
	 *
	 * Invoked by the default implementation of the "promiseUpdate" method only.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 *
	 * @returns {Object}
	 */
	getUpdateRequestData() {
		const fields_data = this.getFieldsData();

		return {
			templateid: this._dashboard.templateid ?? undefined,
			dashboardid: this._dashboard.dashboardid ?? undefined,
			widgetid: this._widgetid ?? undefined,
			name: this._name !== '' ? this._name : undefined,
			fields: Object.keys(fields_data).length > 0 ? fields_data : undefined,
			view_mode: this._view_mode,
			edit_mode: this._is_edit_mode ? 1 : 0,
			contents_width: this._contents_size.width,
			contents_height: this._contents_size.height
		};
	}

	/**
	 * Clear widget and display new contents if the update cycle has run successfully and without errors.
	 *
	 * The response object will contain all data returned by the controller for use in displaying new contents.
	 *
	 * Invoked by the default implementation of the "promiseUpdate" method only.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 *
	 * @param {Object}             response
	 *        {string}             response.name         Widget name to display in the header.
	 *        {string|undefined}   response.body         Widget body (HTML contents).
	 *        {string[]|undefined} response.messages     Error messages.
	 *
	 *        {Object[]|undefined} response.info         Info buttons to display in the widget header.
	 *        {string}             response.info[].icon
	 *        {string}             response.info[].hint
	 *
	 *        {string|undefined}   response.debug        Debug information.
	 */
	processUpdateResponse(response) {
		this._setHeaderName(response.name);

		this._updateMessages(response.messages);
		this._updateInfo(response.info);
		this._updateDebug(response.debug);

		this.setContents(response);
	}

	/**
	 * Display error message if the update cycle has run successfully, but returned a fatal error.
	 *
	 * Invoked by the default implementation of the "promiseUpdate" method only.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 *
	 * @param {Object}             error
	 *        {string|undefined}   error.title
	 *        {string[]|undefined} error.messages
	 */
	processUpdateErrorResponse(error) {
		this._updateMessages(error.messages, error.title);
	}

	/**
	 * Update widget body if the update cycle has run successfully and without errors.
	 *
	 * The response object will contain all data returned by the controller for use in displaying new contents.
	 *
	 * Invoked by the default implementation of the "processUpdateResponse" method only.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 *
	 * @param {Object} response
	 */
	setContents(response) {
		this._body.innerHTML = response.body ?? '';
	}

	/**
	 * Clear widget contents and cancel any asynchronous tasks related to updating the contents.
	 *
	 * Invoked prior to displaying specific view defined by the framework or user (by calling "setCoverMessage").
	 *
	 * Invoked by the default implementation of the "clearContents" method only.
	 *
	 * Possible widget state: WIDGET_STATE_ACTIVE.
	 */
	onClearContents() {
	}
}
