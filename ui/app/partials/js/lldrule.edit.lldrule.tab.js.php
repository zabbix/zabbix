<?php
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


/**
 * @var CPartial $this
 */
?>

var LldRuleEditLldRuleTab = class {
	#form_element;
	#form;
	#lldrule;
	#container;
	#field_switches;
	#host_interface_selector;
	#inherited_timeouts;
	#readonly;
	#telemetry_columns_config;
	#telemetry_function_labels;
	#telemetry_operator_labels;
	#telemetry_signal_type_metrics;

	constructor({
		form_element, form, container, field_switches, lldrule, interface_types, host_interfaces, readonly,
		inherited_timeouts,	telemetry_columns_config, telemetry_function_labels, telemetry_operator_labels,
		telemetry_signal_type_metrics
	}) {
		this.#form_element = form_element;
		this.#form = form;
		this.#lldrule = lldrule;
		this.#container = container;
		this.#field_switches = field_switches;
		this.#readonly = readonly;
		this.#inherited_timeouts = inherited_timeouts;
		this.#telemetry_columns_config = telemetry_columns_config;
		this.#telemetry_function_labels = telemetry_function_labels;
		this.#telemetry_operator_labels = telemetry_operator_labels;
		this.#telemetry_signal_type_metrics = telemetry_signal_type_metrics;

		if (!(lldrule.templated || lldrule.discovered)) {
			if (lldrule.parameters.length == 0) {
				lldrule.parameters.push({});
			}

			if (lldrule.query_fields.length == 0) {
				lldrule.query_fields.push({});
			}

			if (lldrule.headers.length == 0) {
				lldrule.headers.push({});
			}
		}

		if (!lldrule.discovered && lldrule.delay_flex.length == 0) {
			lldrule.delay_flex.push({});
		}

		$('#parameters-table').dynamicRows({
			template: '#parameter-row-tmpl',
			rows: lldrule.parameters,
			allow_empty: true
		});

		$('#query-fields-table').dynamicRows({
			template: '#query-field-row-tmpl',
			rows: lldrule.query_fields,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				selector_span: ':not(.error-container-row)',
				freeze_end: 1,
				enable_sorting: !(lldrule.templated || lldrule.discovered)
			}
		});

		jQuery('#headers-table').dynamicRows({
			template: '#item-header-row-tmpl',
			rows: lldrule.headers,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				selector_span: ':not(.error-container-row)',
				freeze_end: 1,
				enable_sorting: !(lldrule.templated || lldrule.discovered)
			}
		});

		jQuery('#delay-flex-table').dynamicRows({
			template: '#delay-flex-row-tmpl',
			rows: lldrule.delay_flex,
			allow_empty: true
		});

		this.#host_interface_selector = new HostInterfaceSelector({
			container: this.#container, interface_types, host_interfaces,
			type: lldrule.type
		});

		this.field = {
			type: this.#form_element.querySelector('[name="type"]'),
			signal_type: this.#form_element.querySelector('[name="signal_type"]'),
			metric_point_type: this.#form_element.querySelectorAll('[name="metric_point_type"]'),
			evaltype: this.#form_element.querySelector('[name="evaltype"]'),
			formula: this.#form_element.querySelector('[name="formula"]'),
			expression: this.#form_element.querySelector('#expression')
		};

		jQuery('#columns-table').dynamicRows({
			template: '#column-row-tmpl',
			rows: lldrule.columns,
			allow_empty: true,
			sortable: true,
			sortable_options: {
				target: 'tbody',
				selector_span: ':not(.error-container-row)',
				selector_handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
				freeze_end: 1,
				enable_sorting: !this.#readonly
			}
		}).on('afteradd.dynamicRows', (e) => {
			for (const select of e.target.querySelectorAll('.js-column')) {
				if (select.getOptions().length === 0) {
					this.#populateColumnSelect(select);
					this.#toggleAttributeKey(select);
				}
			}
		});

		this.#initEvents();
		this.#initTelemetryRows();
		this.#update();

		this.#container.querySelectorAll('#delay-flex-table tr.form_row').forEach(row => {
			this.#setVisibleCustomIntervalParameters(row);
		})
	}

	#initEvents() {
		new CViewSwitcher('allow_traps', 'change', this.#field_switches.for_traps);
		new CViewSwitcher('authtype', 'change', this.#field_switches.for_authtype);
		new CViewSwitcher('http_authtype', 'change', this.#field_switches.for_http_auth_type);
		new CViewSwitcher('type', 'change', this.#field_switches.for_type);

		const input_selectors = ['[name="authtype"]', '[name="custom_timeout"]', '[name="lifetime_type"]',
			'[name="enabled_lifetime_type"]', '[name="key"]', '[name="request_method"]', '[name="evaltype"]'
		];
		const inputs = this.#container.querySelectorAll(input_selectors.join(','));

		inputs.forEach(input => {
			input.addEventListener('change', () => this.#update());
		});

		this.#container.querySelector('[name="type"]').addEventListener('change', (e) => {
			this.#host_interface_selector.setType(parseInt(e.target.value));
			this.#container.dispatchEvent(new CustomEvent('update'));

			const inherited_timeout_input = this.#container.querySelector('[name="inherited_timeout"]');
			inherited_timeout_input.value = this.#inherited_timeouts[e.target.value] || '';
			const custom_timeout_input = this.#container.querySelector('[name="custom_timeout"]:checked');

			if (custom_timeout_input.value == <?= ZBX_ITEM_CUSTOM_TIMEOUT_DISABLED ?>) {
				this.#container.querySelector('[name="timeout"]').value = inherited_timeout_input.value;
			}

			this.#update();
		});

		this.#container.querySelector('[name="key"]').addEventListener('change', () =>
			this.#container.dispatchEvent(new CustomEvent('update'))
		);

		this.#container.querySelector('[name="snmp_oid"]').addEventListener('keyup', () =>
			this.#update()
		);

		this.#container.querySelector('.js-parseurl').addEventListener('click', (e) => this.#parseUrl(e.target));

		this.#container.querySelector('#delay-flex-table').addEventListener('click', e => {
			if (e.target.type === 'radio') {
				this.#setVisibleCustomIntervalParameters(e.target.closest('tr'))
			}
		});

		this.field.signal_type.addEventListener('change', this.#telemetrySignalChangeHandler.bind(this));

		for (const radio of this.field.metric_point_type) {
			radio.addEventListener('change', this.#telemetrySignalChangeHandler.bind(this));
		}

		for (const name of ['delay', 'lookback_limit', 'granularity']) {
			this.#form_element.querySelector(`[name="${name}"]`)
				.addEventListener('input', () => this.#updateTelemetryIndicators());
		}

		this.#form_element.querySelector('.js-add-aggregated-column')
			.addEventListener('click', (e) => this.#openAggregatedColumnModal(e.target));

		this.#form_element.querySelector('.js-add-condition')
			.addEventListener('click', (e) => this.#openConditionModal(e.target));

		this.#form_element.querySelector('#columns-table').addEventListener('change', (e) => {
			if (e.target.classList.contains('js-column')) {
				this.#toggleAttributeKey(e.target);
			}
		});

		for (const id of ['aggregated-columns-table', 'conditions-table']) {
			this.#form_element.querySelector(`#${id}`).addEventListener('click', (e) => {
				if (e.target.classList.contains('js-remove-row')) {
					const row = e.target.closest('tr');

					this.#removeRelatedErrorContainer(row);
					row.remove();

					this.#update();
				}
			});
		}

		this.#form_element.querySelector('#aggregated-columns-table').addEventListener('click', (e) => {
			if (e.target.classList.contains('js-edit-row')) {
				this.#openAggregatedColumnModal(e.target, e.target.closest('tr'));
			}
		});

		this.#form_element.querySelector('#conditions-table').addEventListener('click', (e) => {
			if (e.target.classList.contains('js-edit-row')) {
				this.#openConditionModal(e.target, e.target.closest('tr'));
			}
		});

		this.#container.querySelector('#delay-flex-table').addEventListener('input', e => {
			if (e.target.matches('[name$="[delay]"]')) {
				this.#updateTelemetryIndicators();
			}
		});

		jQuery('#query-fields-table')
			.on('tableupdate.dynamicRows', (e) => this.#updateSortOrder(e.target, 'query_fields'));
		jQuery('#headers-table').on('tableupdate.dynamicRows', (e) => this.#updateSortOrder(e.target, 'headers'));
		jQuery('#columns-table').on('tableupdate.dynamicRows', (e) => this.#updateSortOrder(e.target, 'columns'));
		jQuery('#delay-flex-table').on('tableupdate.dynamicRows', () => this.#updateTelemetryIndicators());
	}

	getContainer() {
		return this.#container;
	}

	#update() {
		const type = parseInt(this.#container.querySelector('[name="type"]').value);
		const key = this.#container.querySelector('[name="key"]').value
		const username_required = type == <?= ITEM_TYPE_SSH ?> || type == <?= ITEM_TYPE_TELNET ?>;
		const ipmi_sensor_required = type == <?= ITEM_TYPE_IPMI ?> && key !== 'ipmi.get';

		this.#container.querySelector('[name="username"]')
			[username_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.#container.querySelector('label[for="username"]').classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', username_required);
		this.#container.querySelector('[name="ipmi_sensor"]')
			[ipmi_sensor_required ? 'setAttribute' : 'removeAttribute']('aria-required', 'true');
		this.#container.querySelector('label[for="ipmi_sensor"]').classList
			.toggle('<?= ZBX_STYLE_FIELD_LABEL_ASTERISK ?>', ipmi_sensor_required);

		const lifetime_type = this.#container.querySelector('[name="lifetime_type"]:checked').value;
		const enabled_lifetime_type = this.#container.querySelector('[name="enabled_lifetime_type"]:checked').value;
		const delete_immediately = lifetime_type == <?= ZBX_LLD_DELETE_IMMEDIATELY ?>;

		this.#container.querySelector('#lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			lifetime_type != <?= ZBX_LLD_DELETE_AFTER ?>
		);
		this.#container.querySelector('#enabled_lifetime').classList.toggle('<?= ZBX_STYLE_DISPLAY_NONE ?>',
			delete_immediately || enabled_lifetime_type != <?= ZBX_LLD_DISABLE_AFTER ?>
		);

		let set_hidden = false;
		const toggle_field_ids = [];

		if (type == <?= ITEM_TYPE_SIMPLE ?>) {
			toggle_field_ids.push('js-item-timeout-label', 'js-item-timeout-field');
			set_hidden = key.substring(0, 8) === 'icmpping' || key.substring(0, 7) === 'vmware.';
		}
		else if (type == <?= ITEM_TYPE_ZABBIX_ACTIVE ?>) {
			toggle_field_ids.push('delay', 'js-item-delay-label', 'js-item-delay-field',
				'js-item-flex-intervals-label', 'js-item-flex-intervals-field'
			);
			set_hidden = key.substring(0, 8) === 'mqtt.get';
		}
		else if (type == <?= ITEM_TYPE_SNMP ?>) {
			toggle_field_ids.push('js-item-timeout-label', 'js-item-timeout-field');
			const snmp_oid = this.#container.querySelector('[name="snmp_oid"]').value;
			set_hidden = snmp_oid.substring(0, 4) !== 'get[' && snmp_oid.substring(0, 5) !== 'walk[';
		}

		const object_switcher = globalAllObjForViewSwitcher['type'];

		toggle_field_ids.forEach(id =>
			object_switcher[set_hidden ? 'hideObj' : 'showObj']({id})
		);

		this.#container.querySelectorAll('.js-item-disable-resources').forEach(el =>
			el.classList.toggle('<?=ZBX_STYLE_DISPLAY_NONE ?>', delete_immediately)
		);

		if (type == <?= ITEM_TYPE_HTTPAGENT ?>) {
			const request_method = this.#container.querySelector('[name="request_method"]').value;
			const is_retrieve_mode_read_only = request_method == <?= HTTPCHECK_REQUEST_HEAD ?>;

			this.#container.querySelectorAll('[name="retrieve_mode"]').forEach(radio => {
				if (is_retrieve_mode_read_only && radio.value == <?= HTTPTEST_STEP_RETRIEVE_MODE_HEADERS ?>) {
					radio.checked = true;
				}

				radio.readOnly = is_retrieve_mode_read_only || this.#readonly;
			})
		}

		const custom_timeout = this.#container.querySelector(['[name="custom_timeout"]:checked']).value;
		const inherited_hidden = custom_timeout == <?= ZBX_ITEM_CUSTOM_TIMEOUT_ENABLED; ?>;
		const timeout_inaccessible = this.#container.querySelector('#js-item-timeout-inaccessible');

		this.#container.querySelector('[name="timeout"]').classList
			.toggle(ZBX_STYLE_DISPLAY_NONE, !inherited_hidden);

		if (timeout_inaccessible !== null) {
			timeout_inaccessible.classList.toggle(ZBX_STYLE_DISPLAY_NONE, inherited_hidden);
		}
		else {
			this.#container.querySelector('[name="inherited_timeout"]').classList
				.toggle(ZBX_STYLE_DISPLAY_NONE, inherited_hidden);
		}

		this.#updateTelemetryVisibility();
	}

	#updateTelemetryVisibility() {
		if (this.field.type.value != <?= ITEM_TYPE_TELEMETRY_QUERY ?>) {
			return;
		}

		const is_metrics = this.field.signal_type.value == this.#telemetry_signal_type_metrics;
		const switcher = globalAllObjForViewSwitcher['type'];

		// "Metric points" is shown only for "Metrics".
		['js-item-metric-point-type-label', 'js-item-metric-point-type-field'].forEach(
			id => switcher[is_metrics ? 'showObj' : 'hideObj']({id})
		);

		// "Type of calculation" is shown only when there is more than one condition.
		const has_calculation = this.#form_element
			.querySelectorAll('#conditions-table tbody [data-row_index]').length > 1;

		if (!has_calculation) {
			this.field.evaltype.value = <?= CONDITION_EVAL_TYPE_AND_OR ?>;
		}

		['js-item-evaltype-label', 'js-item-evaltype-field'].forEach(
			id => switcher[has_calculation ? 'showObj' : 'hideObj']({id})
		);

		// The formula field is shown only for the "Custom expression" calculation type.
		const is_custom_expression = this.field.evaltype.value == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>;

		this.field.formula.style.display = is_custom_expression ? '' : 'none';
		this.field.expression.style.display = is_custom_expression ? 'none' : '';

		if (!is_custom_expression) {
			this.field.expression.innerHTML = getConditionFormula(this.#getTelemetryConditions(),
				+ this.field.evaltype.value
			);
		}

		this.#updateTelemetryIndicators();
	}

	#getTelemetryConditions() {
		const conditions = [];

		for (const row of this.#form_element.querySelectorAll('#conditions-table tbody [data-row_index]')) {
			const row_index = row.dataset.row_index;
			const formulaid = row.querySelector(`[name="conditions[${row_index}][formulaid]"]`).value;
			const column = row.querySelector(`[name="conditions[${row_index}][column]"]`).value;
			const attribute_key = row.querySelector(`[name="conditions[${row_index}][attribute_key]"]`).value;

			conditions.push({
				id: formulaid,
				type: `${column}.${attribute_key}`
			});
		}

		return conditions;
	}

	#updateTelemetryIndicators() {
		if (this.field.type.value != <?= ITEM_TYPE_TELEMETRY_QUERY ?>) {
			return;
		}

		const delays = this.#getDelays();
		const lookback_limit = timeUnitToSeconds(
			this.#form_element.querySelector('[name="lookback_limit"]').value.trim(), false
		);
		const granularity = timeUnitToSeconds(
			this.#form_element.querySelector('[name="granularity"]').value.trim(), false
		);

		const data_gaps = lookback_limit !== null && delays.some((delay) => lookback_limit < delay);
		const data_overlap = granularity !== null && delays.some((delay) => granularity > delay);

		this.#form_element.querySelector('.js-lookback-limit-warning').style.display = data_gaps ? '' : 'none';
		this.#form_element.querySelector('.js-granularity-warning').style.display = data_overlap ? '' : 'none';
	}

	#getDelays() {
		const delays = [];
		const delay = timeUnitToSeconds(this.#form_element.querySelector('[name="delay"]').value.trim(), false);

		if (delay > 0) {
			delays.push(delay);
		}

		for (const row of this.#form_element.querySelectorAll('#delay-flex-table .form_row')) {
			if (row.querySelector('[name$="[type]"]:checked').value != <?= ITEM_DELAY_FLEXIBLE ?>) {
				continue;
			}

			const flexible_delay = timeUnitToSeconds(row.querySelector('[name$="[delay]"]').value.trim(), false);

			if (flexible_delay > 0) {
				delays.push(flexible_delay);
			}
		}

		return delays;
	}

	#getTelemetryColumns() {
		const signal_type = this.field.signal_type.value;

		if (signal_type == this.#telemetry_signal_type_metrics) {
			const metric_point_type = this.#form_element.querySelector('[name="metric_point_type"]:checked').value;

			return this.#telemetry_columns_config.columns[signal_type][metric_point_type];
		}
		else {
			return this.#telemetry_columns_config.columns[signal_type];
		}
	}

	#populateColumnSelect(select) {
		const value = select.value;
		const names = this.#getTelemetryColumns();
		const is_unavailable = value !== null && value !== '' && !names.includes(value);
		const options = is_unavailable ? [...names, value].sort() : names;

		select.clearOptions();
		select.addOptions(options.map((name) => ({value: name, label: name})));

		if (value === null || value === '') {
			select.preselectHightlighted();

			return;
		}

		select.value = value;

		if (is_unavailable) {
			select.getOptionByValue(value).disabled = true;
		}
	}

	#toggleAttributeKey(select) {
		const is_complex = this.#telemetry_columns_config.complex.includes(select.value);
		const attribute_key = select.closest('tr').querySelector('.js-attribute-key');

		attribute_key.style.display = is_complex ? '' : 'none';

		if (!is_complex) {
			attribute_key.value = '';
		}
	}

	#telemetrySignalChangeHandler() {
		this.#refreshTelemetryColumns();
		this.#update();
		this.#form.validateChanges(['columns', 'aggregated_columns', 'conditions']);
	}

	#refreshTelemetryColumns() {
		for (const select of this.#form_element.querySelectorAll('#columns-table tbody .js-column')) {
			this.#populateColumnSelect(select);
			this.#toggleAttributeKey(select);
		}
	}

	#openAggregatedColumnModal(trigger, row = null) {
		const signal_type = this.field.signal_type.value;
		const metric_point_type = this.#form_element.querySelector('[name="metric_point_type"]:checked').value;

		const parameters = {
			signal_type: signal_type,
			metric_point_type: metric_point_type,
			existing_aliases: this.#getExistingAliases(row)
		};

		if (row !== null) {
			const row_index = row.dataset.row_index;

			parameters.edit = '1';
			parameters.row_index = row_index;
			parameters.column = row.querySelector(`[name="aggregated_columns[${row_index}][column]"]`).value;
			parameters.function = row.querySelector(`[name="aggregated_columns[${row_index}][function]"]`).value;
			parameters.percentile = row.querySelector(`[name="aggregated_columns[${row_index}][percentile]"]`).value;
			parameters.alias = row.querySelector(`[name="aggregated_columns[${row_index}][alias]"]`).value;
		}
		else {
			let row_index = 0;

			while (this.#form_element.querySelector(`#aggregated-columns-table [data-row_index="${row_index}"]`)
			!== null) {
				row_index++;
			}

			parameters.row_index = row_index;
		}

		const overlay = PopUp('popup.telemetry.aggregatedcolumn.edit', parameters, {
			dialogueid: 'telemetry-aggregated-column',
			dialogue_class: 'modal-popup-medium',
			trigger_element: trigger
		});

		overlay.$dialogue[0].addEventListener('telemetry_aggregated_column.submit',
			(e) => this.#addAggregatedColumnRow(e.detail)
		);
	}

	#getExistingAliases(exclude_row = null) {
		const rows = this.#form_element.querySelectorAll('#aggregated-columns-table tbody [data-row_index]');

		return [...rows]
		.filter((row) => row !== exclude_row)
		.map((row) => row.querySelector(`[name="aggregated_columns[${row.dataset.row_index}][alias]"]`).value);
	}

	#addAggregatedColumnRow(data) {
		const tbody = this.#form_element.querySelector('#aggregated-columns-table tbody');
		const percentile = data.percentile ?? '';
		let function_label = this.#telemetry_function_labels[data.function];

		if (data.function == <?= AGGREGATE_PERCENTILE ?>) {
			function_label += `(${data.column}, ${percentile})`;
		}
		else if (data.function != <?= AGGREGATE_COUNT ?>) {
			function_label += `(${data.column})`;
		}

		const source = document.getElementById('aggregated-column-row-tmpl').innerHTML;
		const html = new Template(source).evaluate({...data, function_label, percentile});
		const existing = tbody.querySelector(`[data-row_index="${data.row_index}"]`);

		if (existing !== null) {
			this.#removeRelatedErrorContainer(existing);

			existing.insertAdjacentHTML('afterend', html);
			existing.remove();
		}
		else {
			tbody.querySelector('.js-add-aggregated-column').closest('tr').insertAdjacentHTML('beforebegin', html);
		}
	}

	#removeRelatedErrorContainer(row) {
		const next_sibling = row.nextElementSibling;

		if (next_sibling !== null && next_sibling.classList.contains('error-container-row')) {
			next_sibling.remove();
		}
	}

	#openConditionModal(trigger, row = null) {
		const signal_type = this.field.signal_type.value;
		const metric_point_type = this.#form_element.querySelector('[name="metric_point_type"]:checked').value;

		const parameters = {
			signal_type: signal_type,
			metric_point_type: metric_point_type
		};

		if (row !== null) {
			const row_index = row.dataset.row_index;

			parameters.edit = '1';
			parameters.row_index = row_index;
			parameters.column = row.querySelector(`[name="conditions[${row_index}][column]"]`).value;
			parameters.attribute_key = row.querySelector(`[name="conditions[${row_index}][attribute_key]"]`).value;
			parameters.operator = row.querySelector(`[name="conditions[${row_index}][operator]"]`).value;
			parameters.value = row.querySelector(`[name="conditions[${row_index}][value]"]`).value;
		}
		else {
			let row_index = 0;

			while (this.#form_element.querySelector(`#conditions-table [data-row_index="${row_index}"]`) !== null) {
				row_index++;
			}

			parameters.row_index = row_index;
		}

		const overlay = PopUp('popup.telemetry.condition.edit', parameters, {
			dialogueid: 'telemetry-condition',
			dialogue_class: 'modal-popup-medium',
			trigger_element: trigger
		});

		overlay.$dialogue[0].addEventListener('telemetry_condition.submit', (e) => {
			this.#addConditionRow(e.detail);
			this.#update();
		});
	}

	#addConditionRow(data) {
		const config = this.#telemetry_columns_config;
		const tbody = this.#form_element.querySelector('#conditions-table tbody');
		const is_complex = config.complex.includes(data.column);
		const is_exists = data.operator == <?= CONDITION_OPERATOR_EXISTS ?>;
		const source = document.getElementById('condition-row-tmpl').innerHTML;
		const html = new Template(source).evaluate({
			...data,
			formulaid: data.formulaid ?? num2letter(data.row_index),
			attribute_key_name: is_complex ? data.attribute_key : '',
			operator_name: this.#telemetry_operator_labels[data.operator],
			value_name: is_exists ? '' : data.value
		});
		const existing = tbody.querySelector(`[data-row_index="${data.row_index}"]`);

		if (existing !== null) {
			this.#removeRelatedErrorContainer(existing);

			existing.insertAdjacentHTML('afterend', html);
			existing.remove();
		}
		else {
			tbody.querySelector('.js-add-condition').closest('tr').insertAdjacentHTML('beforebegin', html);
		}
	}

	#initTelemetryRows() {
		if (this.field.type.value != <?= ITEM_TYPE_TELEMETRY_QUERY ?>) {
			return;
		}

		for (const select of this.#form_element.querySelectorAll('#columns-table .js-column')) {
			this.#populateColumnSelect(select);
			this.#toggleAttributeKey(select);
		}

		this.#lldrule.aggregated_columns.forEach((column, row_index) => {
			this.#addAggregatedColumnRow({...column, row_index});
		});

		this.#lldrule.conditions.forEach((condition, row_index) => {
			this.#addConditionRow({...condition, row_index});
		});
	}

	#updateSortOrder(table, name_field) {
		table.querySelectorAll('.form_row').forEach((row, index) => {
			for (const field of row.querySelectorAll(`[name^="${name_field}["]`)) {
				field.name = field.name.replace(/\[\d+]/g, `[${index}]`);
			}
		});
	}

	#setVisibleCustomIntervalParameters(row) {
		const flexible = row.querySelector('[name$="[type]"]:checked').value == <?= ITEM_DELAY_FLEXIBLE ?>;

		row.querySelector('[name$="[delay]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[period]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, !flexible);
		row.querySelector('[name$="[schedule]"]').classList.toggle(ZBX_STYLE_DISPLAY_NONE, flexible);
	}

	#parseUrl(trigger_element) {
		const field = this.#container.querySelector('[name="url"]');
		const url = parseUrlString(field.value);

		if (url === false) {
			return this.#showErrorDialog(
				<?= json_encode(_('Failed to parse URL.').BR().BR()._('URL is not properly encoded.')) ?>,
				trigger_element
			);
		}

		const has_pairs = url.pairs.length != 0;

		if (has_pairs) {
			const dynamic_rows = jQuery('#query-fields-table').data('dynamicRows');

			dynamic_rows.addRows(url.pairs);
			dynamic_rows.removeRows(row => [].filter.call(
					row.querySelectorAll('[type="text"]'),
					input => input.value === ''
				).length == 2
			);
		}

		field.value = url.url;

		if (has_pairs) {
			this.#container.dispatchEvent(new CustomEvent('update'));
		}
	}

	#showErrorDialog(body, trigger_element) {
		const overlay = overlayDialogue({
			title: <?= json_encode(_('Error')) ?>,
			class: 'modal-popup',
			content: jQuery('<span>').html(body),
			buttons: [{
				title: <?= json_encode(_('Ok')) ?>,
				class: 'btn-alt',
				focused: true,
				action: function() {}
			}]
		}, {
			position: Overlay.prototype.POSITION_CENTER,
			trigger_element: jQuery(trigger_element)
		});

		// Display the close button after the screen reader announces the dialog title.
		overlay.$dialogue.$head.$close_button.show();
	}
};
