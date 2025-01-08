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
 * Widget view modes: whether to display the header statically or on mouse hovering (configurable on the widget form).
 */

const ZBX_WIDGET_VIEW_MODE_NORMAL = 0;
const ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER = 1;

/*
 * Widget states, managed by the dashboard page.
 */

// Initial state of widget: the widget has never yet been displayed on the dashboard page.
const WIDGET_STATE_INITIAL = 'initial';

// Active state of widget: the widget is being displayed on the active dashboard page and is updating periodically.
const WIDGET_STATE_ACTIVE = 'active';

// Inactive state of widget: the widget has been active recently, but is currently hidden on an inactive dashboard page.
const WIDGET_STATE_INACTIVE = 'inactive';

// Destroyed state of widget: the widget has been deleted from the dashboard page.
const WIDGET_STATE_DESTROYED = 'destroyed';

/*
 * Events thrown by widgets to inform the dashboard page about user interaction with the widget, which may impact the
 * dashboard page and other widgets.
 */

// Widget edit event: informs the dashboard page to enter the editing mode.
const WIDGET_EVENT_EDIT = 'widget-edit';

// Widget actions event: informs the dashboard page to display the widget actions popup menu.
const WIDGET_EVENT_ACTIONS = 'widget-actions';

// Widget enter event: informs the dashboard page to focus the widget and un-focus other widgets.
const WIDGET_EVENT_ENTER = 'widget-enter';

// Widget leave event: informs the dashboard page to un-focus the widget.
const WIDGET_EVENT_LEAVE = 'widget-leave';

// Widget copy event: informs the dashboard page to copy the widget to the local storage.
const WIDGET_EVENT_COPY = 'widget-copy';

// Widget paste event: informs the dashboard page to paste the stored widget over the current one.
const WIDGET_EVENT_PASTE = 'widget-paste';

// Widget delete event: informs the dashboard page to delete the widget.
const WIDGET_EVENT_DELETE = 'widget-delete';

/*
 * The base class of all dashboard widgets. Depending on widget needs, it can be instantiated directly or be extended.
 */
class CWidgetBase {

	// Widget ready event: informs the dashboard page that the widget has been fully loaded (fired once).
	static EVENT_READY = 'widget-ready';

	// Require data source event: informs the dashboard page to load the referred foreign data source.
	static EVENT_REQUIRE_DATA_SOURCE = 'widget-require-data-source';

	static FOREIGN_REFERENCE_KEY = '_reference';

	#fields_references_accessors = null;

	#fields_referred_data = new Map();

	#fields_referred_data_updated = new Set();

	#fields_referred_data_subscriptions = [];

	#broadcast_cache = new Map();

	#feedback_cache = new Map();

	#ready_promise = null;

	#is_awaiting_data = false;

	#has_ever_updated = false;

	/**
	 * Widget constructor. Invoked by a dashboard page.
	 *
	 * @param {string}      type                Widget type ("id" field of the manifest.json).
	 * @param {string}      name                Widget name to display in the header.
	 * @param {number}      view_mode           One of ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER.
	 * @param {Object}      fields              Widget field values (widget configuration data).
	 *
	 * @param {Object}      defaults            Widget type defaults.
	 *        {string}      defaults.name         Default name to display in the header, if no custom name given.
	 *        {Object}      defaults.size         Optional. Default size to use when creating new widgets.
	 *        {number}      defaults.size.width   Default width.
	 *        {number}      defaults.size.height  Default height.
	 *        {string}      defaults.js_class     Optional. JavaScript class name.
	 *        {Object}      defaults.in           Optional. Fields able to receive data from the event hub.
	 *        {Array}       defaults.out          Optional. Fields able to broadcast data to the event hub.
	 *
	 * @param {string|null} widgetid            Widget ID stored in the database, or null for new widgets.
	 *
	 * @param {Object|null} pos                 Position and size of the widget (in dashboard coordinates).
	 *        {number}      pos.x               Horizontal position.
	 *        {number}      pos.y               Vertical position.
	 *        {number}      pos.width           Widget width.
	 *        {number}      pos.height          Widget height.
	 *
	 * @param {boolean}     is_new              Create a visual zoom effect when adding new widgets.
	 * @param {number}      rf_rate             Update cycle rate (refresh rate) in seconds. Supported values: 0 (no
	 *                                          refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 * @param {Object}      dashboard           Essential data of the dashboard object.
	 *        {string|null} dashboard.dashboardid  Dashboard ID.
	 *        {string|null} dashboard.templateid   Template ID (used for template and host dashboards).
	 *
	 * @param {Object}      dashboard_page      Essential data of the dashboard page object.
	 *        {string}      dashboard_page.unique_id  Run-time, unique ID of the dashboard page.
	 *
	 * @param {number}      cell_width          Dashboard page cell width in percentage.
	 * @param {number}      cell_height         Dashboard page cell height in pixels.
	 * @param {boolean}     is_editable         Whether to display the "Edit" button.
	 * @param {boolean}     is_edit_mode        Whether the widget is being created in the editing mode.
	 * @param {string|null} csrf_token          CSRF token for AJAX requests.
	 * @param {string}      unique_id           Run-time, unique ID of the widget.
	 */
	constructor({
		type,
		name,
		view_mode,
		fields,
		defaults,
		widgetid = null,
		pos = null,
		is_new,
		rf_rate,
		dashboard,
		dashboard_page,
		cell_width,
		cell_height,
		is_editable,
		is_edit_mode,
		csrf_token = null,
		unique_id
	}) {
		this._target = document.createElement('div');

		this._type = type;
		this._name = name;
		this._view_mode = view_mode;
		this._fields = fields;

		this._defaults = {
			name: defaults.name,
			size: defaults.size,
			js_class: defaults.js_class,
			in: 'in' in defaults ? {...defaults.in} : {},
			out: 'out' in defaults ? defaults.out : []
		};

		this._widgetid = widgetid;
		this._pos = pos;
		this._is_new = is_new;
		this._rf_rate = rf_rate;

		this._dashboard = {
			templateid: dashboard.templateid,
			dashboardid: dashboard.dashboardid
		};

		this._dashboard_page = {
			unique_id: dashboard_page.unique_id
		};

		this._cell_width = cell_width;
		this._cell_height = cell_height;
		this._is_editable = is_editable;
		this._is_edit_mode = is_edit_mode;
		this._csrf_token = csrf_token;
		this._unique_id = unique_id;

		this.#initialize();
	}

	/**
	 * Define initial data. Invoked once, upon instantiation.
	 */
	#initialize() {
		this._css_classes = {
			actions: 'dashboard-grid-widget-actions',
			container: 'dashboard-grid-widget-container',
			contents: 'dashboard-grid-widget-contents',
			messages: 'dashboard-grid-widget-messages',
			body: 'dashboard-grid-widget-body',
			debug: 'dashboard-grid-widget-debug',
			focus: 'dashboard-grid-widget-focus',
			header: 'dashboard-grid-widget-header',
			hidden_header: 'dashboard-grid-widget-hidden-header',
			mask: 'dashboard-grid-widget-mask',
			root: 'dashboard-grid-widget',
			resize_handle: 'ui-resizable-handle'
		};

		this._state = WIDGET_STATE_INITIAL;

		this._contents_size	= {};
		this._update_timeout_id = null;
		this._update_interval_id = null;
		this._update_abort_controller = null;
		this._is_updating_paused = false;
		this._update_retry_sec = 3;
		this._show_preloader_asap = true;
		this._resizable_handles = [];
		this._hide_preloader_animation_frame = null;

		this._events = {};

		this.onInitialize();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onInitialize() {
	}

	/**
	 * Get current state.
	 *
	 * @returns {string}  WIDGET_STATE_INITIAL | WIDGET_STATE_INACTIVE | WIDGET_STATE_ACTIVE | WIDGET_STATE_DESTROYED.
	 */
	getState() {
		return this._state;
	}

	// Logical state control methods.

	/**
	 * Create widget view (HTML objects). Invoked once, before the first activation of the dashboard page.
	 */
	start() {
		if (this._state !== WIDGET_STATE_INITIAL) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		this._makeView();

		if (this._pos !== null) {
			this.setPos(this._pos);
		}

		this.#registerEvents();

		this.#startDataExchange();

		this.onStart();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onStart() {
	}

	/**
	 * Start processing DOM events and start updating immediately. Invoked on each activation of the dashboard page.
	 */
	activate() {
		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_ACTIVE;

		this.#activateEvents();

		this.onActivate();

		this._startUpdating();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onActivate() {
	}

	/**
	 * Stop processing DOM events and stop updating immediately. Invoked on each deactivation of the dashboard page.
	 */
	deactivate() {
		if (this._state !== WIDGET_STATE_ACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_INACTIVE;

		if (this._is_new) {
			this._is_new = false;
			this._target.classList.remove('new-widget');
		}

		this._stopUpdating();

		this.onDeactivate();

		this.#deactivateEvents();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onDeactivate() {
	}

	/**
	 * Destroy the widget which has already been started.
	 *
	 * Invoked once, when the widget or the dashboard page gets deleted.
	 */
	destroy() {
		if (this._state === WIDGET_STATE_ACTIVE) {
			this.deactivate();
		}

		if (this._state !== WIDGET_STATE_INACTIVE) {
			throw new Error('Unsupported state change.');
		}

		this._state = WIDGET_STATE_DESTROYED;

		this.#stopDataExchange();

		this.onDestroy();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onDestroy() {
	}

	// Widget communication methods.

	/**
	 * Get broadcast types supported by widget.
	 *
	 * @returns {string[]}
	 */
	getBroadcastTypes() {
		const broadcast_types = [];

		for (const {type} of this._defaults.out) {
			broadcast_types.push(type);
		}

		return broadcast_types;
	}

	/**
	 * Broadcast data to dependent widgets.
	 *
	 * @param {Object} data  Object containing key-value pairs, like { _hostid: ["123"], _itemid: ["789"] }.
	 */
	broadcast(data) {
		const broadcast_types = this.getBroadcastTypes();

		for (const type of Object.keys(data)) {
			if (!broadcast_types.includes(type)) {
				throw new Error('Cannot broadcast data of undeclared type.');
			}
		}

		for (const [type, value] of Object.entries(data)) {
			ZABBIX.EventHub.publish(new CEventHubEvent({
				data: value,
				descriptor: {
					context: 'dashboard',
					sender_unique_id: this._unique_id,
					sender_type: 'widget',
					widget_type: this._type,
					event_type: 'broadcast',
					event_origin: this._unique_id,
					reference: this._fields.reference,
					type
				}
			}));

			this.#broadcast_cache.set(type, value);
		}
	}

	/**
	 * Check if data of specified type has been already broadcast.
	 *
	 * @param {string} type
	 *
	 * @returns {boolean}
	 */
	hasBroadcast(type) {
		const descriptor = {
			context: 'dashboard',
			sender_unique_id: this._unique_id,
			sender_type: 'widget',
			widget_type: this._type,
			event_type: 'broadcast',
			event_origin: this._unique_id,
			reference: this._fields.reference,
			type
		};

		return ZABBIX.EventHub.getData(descriptor) !== undefined;
	}

	/**
	 * Get default (empty) broadcast values.
	 *
	 * @returns {Object}
	 */
	getBroadcastDefaults() {
		const broadcast_defaults = {};

		for (const parameter of this._defaults.out) {
			broadcast_defaults[parameter.type] = CWidgetsData.getDefault(parameter.type);
		}

		return broadcast_defaults;
	}

	/**
	 * Send feedback data to the referred foreign data sources.
	 *
	 * @param {Object} data  An object consisting of {path: value} pairs.
	 */
	feedback(data) {
		const accessors = this.#getFieldsReferencesAccessors();

		for (const [path, value] of Object.entries(data)) {
			this.#feedback_cache.set(path, value);

			if (!accessors.has(path)) {
				continue;
			}

			const {reference, type} = CWidgetBase.parseTypedReference(accessors.get(path).getTypedReference());

			if (reference !== '') {
				ZABBIX.EventHub.publish(new CEventHubEvent({
					data: value,
					descriptor: {
						context: 'dashboard',
						sender_unique_id: this._unique_id,
						sender_type: 'widget',
						widget_type: this._type,
						event_type: 'feedback',
						event_origin: this._unique_id,
						reference,
						type
					}
				}));
			}
		}
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onFeedback({type, value}) {
		return false;
	}

	/**
	 * Require loading the specified foreign data source.
	 *
	 * @param {string} reference
	 * @param {string} type
	 */
	requireDataSource(reference, type) {
		this.fire(CWidgetBase.EVENT_REQUIRE_DATA_SOURCE, {reference, type});
	}

	/**
	 * Load and connect to the referred foreign data sources.
	 *
	 * Invoked before the first activation of the dashboard page.
	 */
	#startDataExchange() {
		for (const [path, accessor] of this.#getFieldsReferencesAccessors()) {
			const {reference, type} = CWidgetBase.parseTypedReference(accessor.getTypedReference());

			if (reference === '') {
				this.#fields_referred_data.set(path, {value: null, descriptor: null});
				this.#fields_referred_data_updated.add(path);

				this.#feedback_cache.delete(path);

				continue;
			}

			this.requireDataSource(reference, type);

			const broadcast_subscription = ZABBIX.EventHub.subscribe({
				require: {
					context: 'dashboard',
					event_type: 'broadcast',
					reference,
					type
				},
				callback: ({data, descriptor}) => {
					if (descriptor.event_origin === this._unique_id) {
						return;
					}

					this.#fields_referred_data.set(path, {value: data, descriptor});
					this.#fields_referred_data_updated.add(path);

					this.#feedback_cache.delete(path);

					if (this._state === WIDGET_STATE_ACTIVE) {
						this._startUpdating();
					}
				},
				accept_cached: true
			});

			this.#fields_referred_data_subscriptions.push(broadcast_subscription);
		}

		const broadcast_types = this.getBroadcastTypes();

		if (broadcast_types.length > 0) {
			for (const require_type of [CEventHubEvent.TYPE_SUBSCRIBE, CEventHubEvent.TYPE_UNSUBSCRIBE]) {
				const event_subscription = ZABBIX.EventHub.subscribe({
					require: {
						context: 'dashboard',
						event_type: 'broadcast',
						reference: this._fields.reference
					},
					require_type,
					callback: ({descriptor}) => {
						if (!('type' in descriptor) || !broadcast_types.includes(descriptor.type)) {
							return;
						}

						if (this._state === WIDGET_STATE_ACTIVE) {
							this.onReferredUpdate();
						}
					}
				});

				this.#fields_referred_data_subscriptions.push(event_subscription);
			}

			const feedback_subscription = ZABBIX.EventHub.subscribe({
				require: {
					context: 'dashboard',
					event_type: 'feedback',
					reference: this._fields.reference
				},
				callback: ({data, descriptor}) => {
					if (!('type' in descriptor) || !broadcast_types.includes(descriptor.type)) {
						return;
					}

					if (JSON.stringify(this.#broadcast_cache.get(descriptor.type)) !== JSON.stringify(data)) {
						this.#broadcast_cache.set(descriptor.type, data);

						if (this.onFeedback({type: descriptor.type, value: data})) {
							ZABBIX.EventHub.publish(new CEventHubEvent({
								data,
								descriptor: {
									context: 'dashboard',
									sender_unique_id: this._unique_id,
									sender_type: 'widget',
									widget_type: this._type,
									event_type: 'broadcast',
									event_origin: descriptor.event_origin,
									reference: this._fields.reference,
									type: descriptor.type
								}
							}));
						}
					}
				},
				accept_cached: true
			});

			this.#fields_referred_data_subscriptions.push(feedback_subscription);
		}
	}

	/**
	 * Disconnect from the referred foreign data sources, invalidate broadcast data.
	 *
	 * Invoked when the widget or the dashboard page gets deleted.
	 */
	#stopDataExchange() {
		ZABBIX.EventHub.invalidateData({
			context: 'dashboard',
			sender_unique_id: this._unique_id
		});

		ZABBIX.EventHub.unsubscribeAll(this.#fields_referred_data_subscriptions);

		this.#fields_referred_data.clear();
		this.#fields_referred_data_updated.clear();
		this.#fields_referred_data_subscriptions = [];

		this.#feedback_cache.clear();

		this.#resetFieldsReferencesAccessors();
	}

	/**
	 * Check if the referred data has been fully received from all foreign data sources.
	 *
	 * @returns {boolean}
	 */
	isFieldsReferredDataReady() {
		return this.#fields_referred_data.size === this.#getFieldsReferencesAccessors().size;
	}

	/**
	 * Get referred data and event descriptors received from foreign data sources.
	 *
	 * @returns {Map}
	 */
	getFieldsReferredData() {
		return this.#fields_referred_data;
	}

	/**
	 * Check whether the referred fields data has been updated since the last update cycle.
	 *
	 * @param   {string|null} path  Null will match any updated referred fields data.
	 *
	 * @returns {boolean}
	 */
	isFieldsReferredDataUpdated(path = null) {
		if (path === null) {
			return this.#fields_referred_data_updated.size > 0;
		}

		return this.#fields_referred_data_updated.has(path);
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	isFieldsReferredDataValid() {
	}

	/**
	 * Check if the referred fields marked as required are having non-default (non-empty) values.
	 *
	 * @returns {boolean}
	 */
	isFieldsReferredDataRequirementFulfilled() {
		const fields_referred_data = this.getFieldsReferredData();

		for (const [name, parameters] of Object.entries(this._defaults.in)) {
			if (!parameters.required || !fields_referred_data.has(name)) {
				continue;
			}

			if (CWidgetsData.isDefault(parameters.type, fields_referred_data.get(name).value)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get fields data with references replaced with the actual referred data received from foreign data sources.
	 *
	 * @returns {Object}
	 */
	getFieldsData() {
		const fields_data = JSON.parse(JSON.stringify(this._fields));

		const fields_data_update = new Map();

		for (const [path, {value}] of this.#fields_referred_data) {
			fields_data_update.set(path, value);
		}

		for (const [path, value] of this.#feedback_cache) {
			fields_data_update.set(path, value);
		}

		for (const [path, value] of fields_data_update) {
			let container = {fields_data};
			let key = 'fields_data';

			for (const step of path.split('/')) {
				container = container[key];
				key = step;
			}

			container[key] = value;
		}

		return fields_data;
	}

	/**
	 * Is this widget expected by other widgets to broadcast data of specified type.
	 *
	 * @param {string|null} type  Particular data type or null for any data type.
	 *
	 * @returns {boolean}
	 */
	isReferred(type = null) {
		if (!('reference' in this._fields)) {
			return false;
		}

		const require = {
			context: 'dashboard',
			event_type: 'broadcast',
			reference: this._fields.reference
		};

		if (type !== null) {
			require.type = type;
		}

		return ZABBIX.EventHub.hasSubscribers(require);
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onReferredUpdate() {
	}

	// External events management methods.

	/**
	 * Check whether the widget is in editing mode.
	 *
	 * @returns {boolean}
	 */
	isEditMode() {
		return this._is_edit_mode;
	}

	/**
	 * Set widget to editing mode. This is one-way action.
	 */
	setEditMode() {
		this._is_edit_mode = true;

		if (this._state === WIDGET_STATE_ACTIVE) {
			this._stopUpdating({do_abort: false});
		}

		this._target.classList.add('ui-draggable', 'ui-resizable');

		this.onEdit();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onEdit() {
	}

	/**
	 * Find whether the widget is currently entered (focused) my mouse or keyboard. Only one widget can be entered at a
	 * time.
	 *
	 * @returns {boolean}
	 */
	isEntered() {
		return this._target.classList.contains(this._css_classes.focus);
	}

	/**
	 * Enter (focus) the widget. Caused by mouse hovering or keyboard navigation. Only one widget can be entered at a
	 * time.
	 */
	enter() {
		if (this._is_edit_mode) {
			this._addResizeHandles();
		}

		this._target.classList.add(this._css_classes.focus);
	}

	/**
	 * Remove focus from the widget. Caused by mouse hovering or keyboard navigation.
	 */
	leave() {
		if (this._is_edit_mode) {
			this._removeResizeHandles();
		}

		if (this._header.contains(document.activeElement)) {
			document.activeElement.blur();
		}

		this._target.classList.remove(this._css_classes.focus);
	}

	/**
	 * Get number of header lines the widget displays when focused.
	 *
	 * @returns {number}
	 */
	getNumHeaderLines() {
		return this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER ? 1 : 0;
	}

	/**
	 * Is widget currently being resized?
	 *
	 * @returns {boolean}
	 */
	isResizing() {
		return this._target.classList.contains('ui-resizable-resizing');
	}

	/**
	 * Set widget resizing state.
	 *
	 * @param {boolean} is_resizing
	 */
	setResizing(is_resizing) {
		this._target.classList.toggle('ui-resizable-resizing', is_resizing);
	}

	/**
	 * Is widget currently being dragged?
	 *
	 * @returns {boolean}
	 */
	_isDragging() {
		return this._target.classList.contains('ui-draggable-dragging');
	}

	/**
	 * Set widget dragging state.
	 *
	 * @param {boolean} is_dragging
	 */
	setDragging(is_dragging) {
		this._target.classList.toggle('ui-draggable-dragging', is_dragging);
	}

	/**
	 * Are there context menus open or hints displayed for the widget?
	 *
	 * @returns {boolean}
	 */
	isUserInteracting() {
		return this._target
			.querySelectorAll('[data-expanded="true"], [aria-expanded="true"][aria-haspopup="true"]').length > 0;
	}

	/**
	 * Take whatever action is required on each resize event of the widget contents' container.
	 */
	resize() {
		this.onResize();
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onResize() {
	}

	// Data interface methods.

	/**
	 * Get the unique ID of the widget (runtime, dynamically generated).
	 *
	 * @returns {string}
	 */
	getUniqueId() {
		return this._unique_id;
	}

	/**
	 * Get the widget type ("id" field of the manifest.json).
	 *
	 * @returns {string}
	 */
	getType() {
		return this._type;
	}

	/**
	 * Get custom widget name (can be empty).
	 *
	 * @returns {string}
	 */
	getName() {
		return this._name;
	}

	/**
	 * Get widget name displayed in the header.
	 *
	 * @returns {string}
	 */
	getHeaderName() {
		return this._header.querySelector('h4').textContent;
	}

	/**
	 * Display the specified widget name in the header.
	 *
	 * @param {string} name
	 */
	_setHeaderName(name) {
		this._header.querySelector('h4').textContent = name;
	}

	// Data interface methods.

	/**
	 * Check if widget header is set to be always displayed or displayed only when the widget is entered (focused).
	 *
	 * @returns {number}  One of ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER, ZBX_WIDGET_VIEW_MODE_NORMAL.
	 */
	getViewMode() {
		return this._view_mode;
	}

	/**
	 * Get widget field values (widget configuration data).
	 *
	 * @returns {Object}
	 */
	getFields() {
		return this._fields;
	}

	/**
	 * Get accessors to the field objects containing references to the foreign data sources.
	 *
	 * @returns {Map}
	 */
	#getFieldsReferencesAccessors() {
		if (this.#fields_references_accessors === null) {
			this.#fields_references_accessors = CWidgetBase.getFieldsReferencesAccessors(this._fields);
		}

		return this.#fields_references_accessors;
	}

	/**
	 * Reset accessors to the field objects containing references to the foreign data sources.
	 *
	 * Invoked when the fields are updated.
	 */
	#resetFieldsReferencesAccessors() {
		this.#fields_references_accessors = null;
	}

	/**
	 * Get accessors to the field objects containing references to the foreign data sources.
	 *
	 * @param {Object} fields  Widget field values (widget configuration data).
	 *
	 * @returns {Object}
	 */
	static getFieldsReferencesAccessors(fields) {
		const accessors = new Map();

		let traverse = [{container: fields, path: []}];

		while (traverse.length > 0) {
			const traverse_next = [];

			for (const {container, path} of traverse) {
				for (const key in container) {
					if (typeof container[key] !== 'object' || container[key] === null) {
						continue;
					}

					if ('_reference' in container[key]) {
						accessors.set([...path, key].join('/'), {
							setTypedReference: typed_reference => container[key]._reference = typed_reference,
							getTypedReference: () => container[key]._reference
						});

						continue;
					}

					traverse_next.push({
						container: container[key],
						keys: Object.keys(container[key]),
						path: [...path, key]
					});
				}
			}

			traverse = traverse_next;
		}

		return accessors;
	}

	/**
	 * Parse typed reference (a reference to a foreign data source).
	 *
	 * @param {string} typed_reference
	 *
	 * @returns {{reference: string, type: string}}
	 */
	static parseTypedReference(typed_reference) {
		const separator_index = typed_reference.indexOf('.');

		if (separator_index === -1) {
			return {reference: '', type: ''};
		}

		return {
			reference: typed_reference.slice(0, separator_index),
			type: typed_reference.slice(separator_index + 1)
		};
	}

	/**
	 * Create typed reference (a reference to a foreign data source).
	 *
	 * @param {string} reference
	 * @param {string} type
	 *
	 * @returns {string}
	 */
	static createTypedReference({reference, type = ''}) {
		return type !== '' ? `${reference}.${type}` : reference;
	}

	/**
	 * Get widget ID.
	 *
	 * @returns {string|null}  Widget ID stored in the database, or null for new widgets.
	 */
	getWidgetId() {
		return this._widgetid;
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	hasPadding() {
	}

	/**
	 * Get update cycle rate (refresh rate) in seconds.
	 *
	 * @returns {number}  Supported values: 0 (no refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 */
	getRfRate() {
		return this._rf_rate;
	}

	/**
	 * Set update cycle rate (refresh rate) in seconds.
	 *
	 * @param {number} rf_rate  Supported values: 0 (no refresh), 10, 30, 60, 120, 600 or 900 seconds.
	 */
	_setRfRate(rf_rate) {
		this._rf_rate = rf_rate;

		if (this._widgetid !== null) {
			const curl = new Curl('zabbix.php');

			curl.setArgument('action', 'dashboard.widget.rfrate');
			curl.setArgument(CSRF_TOKEN_NAME, this._csrf_token);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/json'},
				body: JSON.stringify({widgetid: this._widgetid, rf_rate})
			})
				.then(response => response.json())
				.then(response => {
					if ('error' in response) {
						throw {error: response.error};
					}
				})
				.catch(exception => {
					console.log('Could not update widget refresh rate', exception);
				});
		}
	}

	/**
	 * Get widget data for purpose of copying the widget.
	 *
	 * @param {boolean} is_single_copy  Whether copying a single widget or copying a whole dashboard page.
	 *
	 * @returns {Object}
	 */
	getDataCopy({is_single_copy}) {
		const data = {
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: this._fields,
			pos: is_single_copy
				? {
					width: this._pos.width,
					height: this._pos.height
				}
				: this._pos,
			rf_rate: this._rf_rate
		};

		if (is_single_copy) {
			data.dashboard = {
				templateid: this._dashboard.templateid
			};
		}

		return data;
	}

	/**
	 * Get widget data for storing it in the database.
	 *
	 * @returns {Object}
	 */
	save() {
		return {
			widgetid: this._widgetid ?? undefined,
			pos: this._pos,
			type: this._type,
			name: this._name,
			view_mode: this._view_mode,
			fields: Object.keys(this._fields).length > 0 ? this._fields : undefined
		};
	}

	/**
	 * Get context menu to display when actions button is clicked.
	 *
	 * @param {boolean} can_copy_widget   Whether a widget is allowed to be copied?
	 * @param {boolean} can_paste_widget  Whether a copied widget is ready to be pasted over the current one.
	 *
	 * @returns {Object[]}
	 */
	getActionsContextMenu({can_copy_widget, can_paste_widget}) {
		let menu = [];
		let menu_actions = [];

		menu_actions.push({
			label: t('Copy'),
			disabled: can_copy_widget === false,
			clickCallback: () => this.fire(WIDGET_EVENT_COPY)
		});

		if (this._is_edit_mode) {
			menu_actions.push({
				label: t('Paste'),
				disabled: can_paste_widget === false,
				clickCallback: () => this.fire(WIDGET_EVENT_PASTE)
			});

			menu_actions.push({
				label: t('Delete'),
				clickCallback: () => this.fire(WIDGET_EVENT_DELETE)
			});
		}

		if (menu_actions.length) {
			menu.push({
				label: t('Actions'),
				items: menu_actions
			});
		}

		if (!this._is_edit_mode) {
			const refresh_interval_section = {
				label: t('Refresh interval'),
				items: []
			};

			const rf_rates = new Map([
				[0, t('No refresh')],
				[10, t('10 seconds')],
				[30, t('30 seconds')],
				[60, t('1 minute')],
				[120, t('2 minutes')],
				[600, t('10 minutes')],
				[900, t('15 minutes')]
			]);

			for (const [rf_rate, label] of rf_rates) {
				refresh_interval_section.items.push({
					label: label,
					selected: rf_rate === this._rf_rate,
					clickCallback: () => {
						this._setRfRate(rf_rate);

						if (this._state === WIDGET_STATE_ACTIVE) {
							if (this._rf_rate > 0) {
								this._startUpdating();
							}
							else {
								this._stopUpdating({do_abort: false});
							}
						}
					}
				});
			}

			menu.push(refresh_interval_section);
		}

		return menu;
	}

	// Content updating methods.

	/**
	 * Start updating the widget. Invoked on activation of the widget or when the update is required immediately.
	 *
	 * This method implements asynchronous delay, if delay_sec is set to zero.
	 *
	 * @param {number} delay_sec  Delay seconds before the update.
	 */
	_startUpdating({delay_sec = 0} = {}) {
		if (this._update_timeout_id !== null) {
			clearTimeout(this._update_timeout_id);
			this._update_timeout_id = null;
		}

		if (this._update_interval_id !== null) {
			clearInterval(this._update_interval_id);
			this._update_interval_id = null;
		}

		if (delay_sec >= 0) {
			this._update_timeout_id = setTimeout(() => {
				this._update_timeout_id = null;
				this._startUpdating({delay_sec: -1});
			}, delay_sec * 1000);

			return;
		}

		if (!this.isFieldsReferredDataReady()) {
			if (this._show_preloader_asap) {
				this._showPreloader();
			}
			else {
				this._schedulePreloader();
			}

			return;
		}

		if (!this._is_edit_mode && this._rf_rate > 0) {
			this._update_interval_id = setInterval(() => {
				this._update();
			}, this._rf_rate * 1000);
		}

		this._update();
	}

	/**
	 * Stop updating the widget. Invoked on deactivation of the widget or when the update is required to restart.
	 *
	 * @param {boolean} do_abort  Whether to abort the active update request.
	 */
	_stopUpdating({do_abort = true} = {}) {
		if (this._update_timeout_id !== null) {
			clearTimeout(this._update_timeout_id);
			this._update_timeout_id = null;
		}

		if (this._update_interval_id !== null) {
			clearInterval(this._update_interval_id);
			this._update_interval_id = null;
		}

		if (this._update_abort_controller === null) {
			this._hidePreloader();
		}

		if (do_abort && this._update_abort_controller !== null) {
			this._update_abort_controller.abort();
		}
	}

	/**
	 * Pause updating the widget whether the widget is active.
	 */
	_pauseUpdating() {
		this._is_updating_paused = true;
	}

	/**
	 * Resume updating the widget whether the widget is active.
	 */
	_resumeUpdating() {
		this._is_updating_paused = false;
	}

	/**
	 * Organize the update cycle of the widget.
	 */
	_update() {
		if (this._update_abort_controller !== null || this._is_updating_paused || this.isUserInteracting()) {
			this._startUpdating({delay_sec: 1});

			return;
		}

		if (!this.isFieldsReferredDataValid()) {
			if (!this.#is_awaiting_data) {
				this.#is_awaiting_data = true;

				this._hidePreloader();
				this._show_preloader_asap = true;

				this.clearContents();
				this.setCoverMessage({
					message: t('Awaiting data'),
					icon: ZBX_ICON_WIDGET_AWAITING_DATA_LARGE
				});

				this.broadcast(this.getBroadcastDefaults());

				if (this.#ready_promise === null) {
					this.#ready_promise = Promise.resolve();
					this.fire(CWidgetBase.EVENT_READY);
				}
			}

			return;
		}

		if (this.#is_awaiting_data) {
			this.#is_awaiting_data = false;
			this.clearContents();
		}

		this._contents_size = this._getContentsSize();

		this._update_abort_controller = new AbortController();

		if (this._show_preloader_asap) {
			this._showPreloader();
		}
		else {
			this._schedulePreloader();
		}

		Promise.resolve()
			.then(() => this.promiseUpdate())
			.then(() => {
				this._hidePreloader();
				this._show_preloader_asap = false;

				this.#fields_referred_data_updated.clear();

				for (const [type, value] of Object.entries(this.getBroadcastDefaults())) {
					if (!this.hasBroadcast(type)) {
						this.broadcast({[type]: value});
					}
				}

				if (this.#ready_promise === null) {
					this.#ready_promise = this.promiseReady();
					this.#ready_promise.then(() => {
						if (this._state !== WIDGET_STATE_DESTROYED) {
							this.fire(CWidgetBase.EVENT_READY);
						}
					});
				}

				this.#has_ever_updated = true;
			})
			.catch(exception => {
				if (this._update_abort_controller.signal.aborted) {
					this._hidePreloader();
				}
				else {
					console.log('Could not update widget', exception);

					this._startUpdating({delay_sec: this._update_retry_sec});
				}
			})
			.finally(() => {
				this._update_abort_controller = null;
			});
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	promiseUpdate() {
	}

	/**
	 * Check if widget has ever been updated. Returns true if "promiseUpdate" promise has once been resolved.
	 *
	 * @returns {boolean}
	 */
	hasEverUpdated() {
		return this.#has_ever_updated;
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	promiseReady() {
	}

	// Widget view methods.

	/**
	 * Get main HTML container of the widget.
	 *
	 * @returns {HTMLDivElement}
	 */
	getView() {
		return this._target;
	}

	/**
	 * Get CSS class name for the specified container or state.
	 *
	 * @param {string} name  Container or state name.
	 *
	 * @returns {string}
	 */
	getCssClass(name) {
		return this._css_classes[name];
	}

	/**
	 * Get position and size of the widget (in dashboard coordinates).
	 *
	 * @returns {{x: number, y: number, width: number, height: number}|null}
	 */
	getPos() {
		return this._pos;
	}

	/**
	 * Set size and position the widget on the dashboard page.
	 *
	 * @param {Object} pos         Position and size of the widget (in dashboard coordinates).
	 *        {number} pos.x       Horizontal position.
	 *        {number} pos.y       Vertical position.
	 *        {number} pos.width   Widget width.
	 *        {number} pos.height  Widget height.
	 *
	 * @param {boolean} is_managed  Whether physically setting the position and size is managed from the outside.
	 */
	setPos(pos, {is_managed = false} = {}) {
		this._pos = pos;

		if (!is_managed) {
			this._target.style.left = `${this._cell_width * this._pos.x}%`;
			this._target.style.top = `${this._cell_height * this._pos.y}px`;
			this._target.style.width = `${this._cell_width * this._pos.width}%`;
			this._target.style.height = `${this._cell_height * this._pos.height}px`;
		}
	}

	/**
	 * Calculate which of the four sides are affected by the resize handle.
	 *
	 * @param {HTMLElement} resize_handle  One of eight dots by which the widget can be resized in editing mode.
	 *
	 * @returns {{top: boolean, left: boolean, bottom: boolean, right: boolean}}
	 */
	getResizeHandleSides(resize_handle) {
		return {
			top: resize_handle.classList.contains('ui-resizable-nw')
				|| resize_handle.classList.contains('ui-resizable-n')
				|| resize_handle.classList.contains('ui-resizable-ne'),
			right: resize_handle.classList.contains('ui-resizable-ne')
				|| resize_handle.classList.contains('ui-resizable-e')
				|| resize_handle.classList.contains('ui-resizable-se'),
			bottom: resize_handle.classList.contains('ui-resizable-se')
				|| resize_handle.classList.contains('ui-resizable-s')
				|| resize_handle.classList.contains('ui-resizable-sw'),
			left: resize_handle.classList.contains('ui-resizable-sw')
				|| resize_handle.classList.contains('ui-resizable-w')
				|| resize_handle.classList.contains('ui-resizable-nw')
		};
	}

	/**
	 * Add eight resize handles to the widget by which the widget can be resized in editing mode. Invoked when the
	 * widget is entered (focused).
	 */
	_addResizeHandles() {
		this._resizable_handles = {};

		for (const direction of ['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw']) {
			const resizable_handle = document.createElement('div');

			resizable_handle.classList.add('ui-resizable-handle', `ui-resizable-${direction}`);

			if (['n', 'e', 's', 'w'].includes(direction)) {
				const ui_resize_dot = document.createElement('div');

				ui_resize_dot.classList.add('ui-resize-dot');
				resizable_handle.appendChild(ui_resize_dot);

				const ui_resizable_border = document.createElement('div');

				ui_resizable_border.classList.add(`ui-resizable-border-${direction}`);
				resizable_handle.appendChild(ui_resizable_border);
			}

			this._target.append(resizable_handle);
			this._resizable_handles[direction] = resizable_handle;
		}
	}

	/**
	 * Remove eight resize handles from the widget by which the widget can be resized in editing mode. Invoked when the
	 * widget is left (unfocused).
	 */
	_removeResizeHandles() {
		for (const resizable_handle of Object.values(this._resizable_handles)) {
			resizable_handle.remove();
		}

		this._resizable_handles = {};
	}

	/**
	 * Calculate viewport dimensions of the contents' container.
	 *
	 * @returns {{height: number, width: number}}
	 */
	_getContentsSize() {
		const computed_style = getComputedStyle(this._contents);

		const width = Math.floor(
			parseFloat(computed_style.width)
				- parseFloat(computed_style.paddingLeft) - parseFloat(computed_style.paddingRight)
				- parseFloat(computed_style.borderLeftWidth) - parseFloat(computed_style.borderRightWidth)
		);

		const height = Math.floor(
			parseFloat(computed_style.height)
				- parseFloat(computed_style.paddingTop) - parseFloat(computed_style.paddingBottom)
				- parseFloat(computed_style.borderTopWidth) - parseFloat(computed_style.borderBottomWidth)
		);

		return {width, height};
	}

	/**
	 * Update error messages.
	 *
	 * @param {string[]}    messages
	 * @param {string|null} title
	 */
	_updateMessages(messages = [], title = null) {
		this._messages.innerHTML = '';

		if (messages.length > 0 || title !== null) {
			const message_box = makeMessageBox('bad', messages, title)[0];

			this._messages.appendChild(message_box);
		}
	}

	/**
	 * Update info buttons in the widget header.
	 *
	 * @param {Object[]} info
	 *        {string}   info[].icon
	 *        {string}   info[].hint
	 */
	_updateInfo(info = []) {
		for (const li of this._actions.querySelectorAll('.widget-info-button')) {
			li.remove();
		}

		for (let i = info.length - 1; i >= 0; i--) {
			const li = document.createElement('li');

			li.classList.add('widget-info-button');

			const li_button = document.createElement('button');

			li_button.type = 'button';
			li_button.setAttribute('data-hintbox', '1');
			li_button.setAttribute('data-hintbox-static', '1');
			li_button.setAttribute('data-hintbox-ignore-position-change', '1');
			li_button.setAttribute('data-hintbox-contents', info[i].hint);
			li_button.classList.add(ZBX_STYLE_BTN_ICON, info[i].icon);
			li.appendChild(li_button);

			this._actions.prepend(li);
		}
	}

	/**
	 * Update debug information.
	 *
	 * @param {string} debug
	 */
	_updateDebug(debug = '') {
		this._debug.innerHTML = debug;
	}

	/**
	 * Clear widget contents, messages and debug info.
	 */
	clearContents() {
		this.onClearContents();

		this._updateMessages();
		this._updateDebug();
		this._body.innerHTML = '';
	}

	/**
	 * Stub method redefined in class.widget.js.
	 */
	onClearContents() {
	}

	/**
	 * Set cover message if standard view can't be displayed.
	 *
	 * @param {string}      message
	 * @param {string|null} description
	 * @param {string|null} icon
	 */
	setCoverMessage({message, description = null, icon = null} = {}) {
		const container = document.createElement('div');

		container.classList.add(ZBX_STYLE_NO_DATA_MESSAGE);
		container.innerText = message;

		if (icon !== null) {
			container.classList.add(icon);
		}

		if (description !== null) {
			const description_container = document.createElement('div');

			description_container.classList.add(ZBX_STYLE_NO_DATA_DESCRIPTION);
			description_container.innerText = description;

			container.appendChild(description_container);
		}

		this._body.appendChild(container);
	}

	/**
	 * Show data preloader immediately. Invoked on the first update cycle of the widget.
	 */
	_showPreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._contents.classList.add('is-loading');
		this._contents.classList.remove('is-loading-fadein', 'delayed-15s');
	}

	/**
	 * Hide data preloader.
	 */
	_hidePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			return;
		}

		this._hide_preloader_animation_frame = requestAnimationFrame(() => {
			this._contents.classList.remove('is-loading', 'is-loading-fadein', 'delayed-15s');
			this._hide_preloader_animation_frame = null;
		});
	}

	/**
	 * Schedule showing data preloader after 15 seconds. Invoked on a regular update cycle of the widget.
	 */
	_schedulePreloader() {
		// Fixed Safari 16 bug: removing preloader classes on animation frame to ensure removal of icons.

		if (this._hide_preloader_animation_frame !== null) {
			cancelAnimationFrame(this._hide_preloader_animation_frame);
			this._hide_preloader_animation_frame = null;
		}

		this._contents.classList.add('is-loading', 'is-loading-fadein', 'delayed-15s');
	}

	/**
	 * Create DOM structure for the widget. Invoked once, on widget start.
	 */
	_makeView() {
		this._container = document.createElement('div');
		this._container.classList.add(this._css_classes.container);

		this._header = document.createElement('div');
		this._header.classList.add(this._css_classes.header);

		const header_h4 = document.createElement('h4');

		header_h4.textContent = this._name !== '' ? this._name : this._defaults.name;
		this._header.appendChild(header_h4);

		this._actions = document.createElement('ul');
		this._actions.classList.add(this._css_classes.actions);

		if (this._is_editable) {
			this._button_edit = document.createElement('button');
			this._button_edit.type = 'button';
			this._button_edit.title = t('Edit')
			this._button_edit.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_COG_FILLED, 'js-widget-edit');

			const li = document.createElement('li');

			li.appendChild(this._button_edit);
			this._actions.appendChild(li);
		}

		this._button_actions = document.createElement('button');
		this._button_actions.type = 'button';
		this._button_actions.title = t('Actions');
		this._button_actions.setAttribute('aria-expanded', 'false');
		this._button_actions.setAttribute('aria-haspopup', 'true');
		this._button_actions.classList.add(ZBX_STYLE_BTN_ICON, ZBX_ICON_MORE, 'js-widget-action');

		const li = document.createElement('li');

		li.appendChild(this._button_actions);
		this._actions.appendChild(li);

		this._header.append(this._actions);

		this._container.appendChild(this._header);

		this._contents = document.createElement('div');
		this._contents.classList.add(this._css_classes.contents);
		this._contents.classList.add(`dashboard-widget-${this._type}`);
		this._contents.classList.toggle('no-padding', !this.hasPadding());

		this._messages = document.createElement('div');
		this._messages.classList.add(this._css_classes.messages);
		this._contents.appendChild(this._messages);

		this._body = document.createElement('div');
		this._body.classList.add(this._css_classes.body);
		this._contents.appendChild(this._body);

		this._debug = document.createElement('div');
		this._debug.classList.add(this._css_classes.debug);
		this._contents.appendChild(this._debug);

		this._container.appendChild(this._contents);

		this._target.appendChild(this._container);
		this._target.classList.add(this._css_classes.root);
		this._target.classList.toggle('ui-draggable', this._is_edit_mode);
		this._target.classList.toggle('ui-resizable', this._is_edit_mode);
		this._target.classList.toggle(this._css_classes.hidden_header,
			this._view_mode === ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER
		);
		this._target.classList.toggle('new-widget', this._is_new);

		this._target.style.minWidth = `${this._cell_width}%`;
		this._target.style.minHeight = `${this._cell_height}px`;
	}

	// Internal events management methods.

	/**
	 * Create event listeners. Invoked once, upon widget initialization.
	 */
	#registerEvents() {
		this._events = {
			actions: e => {
				this.fire(WIDGET_EVENT_ACTIONS, {mouse_event: e});
			},

			edit: () => {
				this.fire(WIDGET_EVENT_EDIT);
			},

			focusin: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			focusout: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			},

			enter: () => {
				this.fire(WIDGET_EVENT_ENTER);
			},

			leave: () => {
				this.fire(WIDGET_EVENT_LEAVE);
			}
		};
	}

	/**
	 * Activate event listeners. Invoked on each activation of the dashboard page.
	 */
	#activateEvents() {
		this._button_actions.addEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.addEventListener('click', this._events.edit);
		}

		this._target.addEventListener('mousemove', this._events.enter);
		this._target.addEventListener('mouseleave', this._events.leave);
		this._header.addEventListener('focusin', this._events.focusin);
		this._header.addEventListener('focusout', this._events.focusout);
	}

	/**
	 * Deactivate event listeners. Invoked on each deactivation of the dashboard page.
	 */
	#deactivateEvents() {
		this._button_actions.removeEventListener('click', this._events.actions);

		if (this._is_editable) {
			this._button_edit.removeEventListener('click', this._events.edit);
		}

		this._target.removeEventListener('mousemove', this._events.enter);
		this._target.removeEventListener('mouseleave', this._events.leave);
		this._header.removeEventListener('focusin', this._events.focusin);
		this._header.removeEventListener('focusout', this._events.focusout);
	}

	/**
	 * Attach event listener to widget events.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CWidgetBase}
	 */
	on(type, listener, options = false) {
		this._target.addEventListener(type, listener, options);

		return this;
	}

	/**
	 * Detach event listener from widget events.
	 *
	 * @param {string}       type
	 * @param {function}     listener
	 * @param {Object|false} options
	 *
	 * @returns {CWidgetBase}
	 */
	off(type, listener, options = false) {
		this._target.removeEventListener(type, listener, options);

		return this;
	}

	/**
	 * Dispatch widget event.
	 *
	 * @param {string} type
	 * @param {Object} detail
	 * @param {Object} options
	 *
	 * @returns {boolean}
	 */
	fire(type, detail = {}, options = {}) {
		return this._target.dispatchEvent(new CustomEvent(type, {...options, detail: {target: this, ...detail}}));
	}
}
