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
 * SVGMap class.
 *
 * Implements vector map rendering functionality.
 */
class SVGMap {
	constructor(options) {
		this.layers = {};
		this.options = options;
		this.elements = {};
		this.shapes = {};
		this.links = {};
		this.duplicated_links = {};
		this.background = null;
		this.container = null;
		this.imageUrl = 'imgstore.php?iconid=';
		this.imageCache = new ImageCache();
		this.canvas = new SVGCanvas(options.canvas, true);
		if (this.options.show_timestamp === undefined) {
			this.options.show_timestamp = true;
		}
		this.rendered_promise = Promise.resolve();
		this.can_select_element = this.options.can_select_element || false;
		this.selected_element_id = options.selected_element_id || '';

		// Extra group for font styles.
		const container = this.canvas.add('g', {
			class: 'map-container',
			'font-family': SVGMap.FONTS[9],
			'font-size': '10px'
		});

		const layers_to_add = [
			// Background.
			{
				type: 'g',
				attributes: {
					class: 'map-background',
					fill: `#${options.theme.backgroundcolor}`
				}
			},
			// Grid.
			{
				type: 'g',
				attributes: {
					class: 'map-grid',
					stroke: `#${options.theme.gridcolor}`,
					fill: `#${options.theme.gridcolor}`,
					'stroke-width': '1',
					'stroke-dasharray': '4,4',
					'shape-rendering': 'crispEdges'
				}
			},
			// Custom shapes.
			{
				type: 'g',
				attributes: {
					class: 'map-shapes'
				}
			},
			// Elements (images, image labels, links, link labels, highlights, selections and markers).
			{
				type: 'g',
				attributes: {
					class: 'map-elements'
				}
			}
		];

		// Marks (timestamp and homepage).
		if (options.show_timestamp) {
			layers_to_add.push({
				type: 'g',
				attributes: {
					class: 'map-marks',
					fill: 'rgba(150, 150, 150, 0.75)',
					'font-size': '8px',
					'shape-rendering': 'crispEdges'
				},
				content: [
					{
						type: 'text',
						attributes: {
							class: 'map-timestamp',
							'text-anchor': 'end',
							x: options.canvas.width - 6,
							y: options.canvas.height - 6
						}
					}
				]
			});
		}

		const layers = container.add(layers_to_add);

		['background', 'grid', 'shapes', 'elements', 'marks']
			.forEach((attribute, index) => this.layers[attribute] = layers[index]);

		// Render goes first as it is needed for getBBox to work.
		if (this.options.container) {
			this.#render(this.options.container);
		}

		if (options.show_timestamp) {
			const elements = this.canvas.getElementsByAttributes({class: 'map-timestamp'});

			if (elements.length == 0) {
				throw 'timestamp element is missing';
			}
			else {
				this.timestamp = elements[0];
			}
		}

		this.update(this.options);
	}

	// Predefined list of fonts for maps.
	static FONTS = [
		'Georgia, serif',
		'"Palatino Linotype", "Book Antiqua", Palatino, serif',
		'"Times New Roman", Times, serif',
		'Arial, Helvetica, sans-serif',
		'"Arial Black", Gadget, sans-serif',
		'"Comic Sans MS", cursive, sans-serif',
		'Impact, Charcoal, sans-serif',
		'"Lucida Sans Unicode", "Lucida Grande", sans-serif',
		'Tahoma, Geneva, sans-serif',
		'"Trebuchet MS", Helvetica, sans-serif',
		'Verdana, Geneva, sans-serif',
		'"Courier New", Courier, monospace',
		'"Lucida Console", Monaco, monospace'
	];

	static LABEL_TYPE_LABEL = MAP_LABEL_TYPE_LABEL;
	static LABEL_TYPE_IP = MAP_LABEL_TYPE_IP;
	static LABEL_TYPE_NAME = MAP_LABEL_TYPE_NAME;
	static LABEL_TYPE_STATUS = MAP_LABEL_TYPE_STATUS;
	static LABEL_TYPE_NOTHING = MAP_LABEL_TYPE_NOTHING;
	static LABEL_TYPE_CUSTOM = MAP_LABEL_TYPE_CUSTOM;

	static EVENT_ELEMENT_SELECT = 'element.select';

	static BACKGROUND_SCALE_COVER = SYSMAP_BACKGROUND_SCALE_COVER;

	/**
	 * Get rendered promise.
	 *
	 * @return {Promise<void>}
	 */
	promiseRendered() {
		return this.rendered_promise;
	}

	/**
	 * Get image URL.
	 *
	 * @param {number|string} id  Image ID.
	 *
	 * @return {string}           Image URL.
	 */
	getImageUrl(id) {
		return this.imageUrl + id;
	}

	/**
	 * Get image from image cache.
	 *
	 * @param {number|string} id  Image ID.
	 *
	 * @return {object|null}      Image object or null if image object is not present in cache.
	 */
	getImage(id) {
		if (id !== undefined && this.imageCache.images[id] !== undefined) {
			return this.imageCache.images[id];
		}

		return null;
	}

	/**
	 * Update background image.
	 *
	 * @param {object} options                   Options object.
	 * @param {string} options.background        Background image ID.
	 * @param {string} options.background_scale  Background scale type.
	 */
	#updateBackground({background, background_scale}) {
		if (background && background != 0) {
			const is_bg_changed = this.background === null || background !== this.options.background,
				is_bg_scale_changed = this.options.background_scale != background_scale;

			if (!is_bg_changed && !is_bg_scale_changed) {
				// Background was not changed.
				return;
			}

			const image = this.getImage(background);

			let width,
				height;

			width = image.naturalWidth;
			height = image.naturalHeight;

			if (background_scale == this.constructor.BACKGROUND_SCALE_COVER) {
				const canvas_aspect_ratio = this.options.canvas.width / this.options.canvas.height,
					image_aspect_ratio = width / height;

				if (image_aspect_ratio > canvas_aspect_ratio) {
					width = this.options.canvas.height * image_aspect_ratio;
					height = this.options.canvas.height;
				}
				else {
					width = this.options.canvas.width;
					height = this.options.canvas.width / image_aspect_ratio;
				}
			}

			if (!is_bg_changed && is_bg_scale_changed) {
				this.background.update({width, height});
			}
			else {
				const element = this.layers.background.add('image', {
					x: 0,
					y: 0,
					width,
					height,
					'xlink:href': this.getImageUrl(background)
				});

				if (this.background !== null) {
					this.background.replace(element);
				}
				else {
					this.background = element;
				}
			}
		}
		else if (this.background !== null) {
			this.background.remove();
		}
	}

	/**
	 * Set grid size.
	 *
	 * @param {number} size  Grid size. Setting grid size to 0 turns of the grid.
	 */
	setGrid(size) {
		this.layers.grid.clear();

		if (size == 0) {
			return;
		}

		for (let x = size; x < this.options.canvas.width; x += size) {
			this.layers.grid.add('line', {
				x1: x,
				y1: 0,
				x2: x,
				y2: this.options.canvas.height
			});

			this.layers.grid.add('text', {
				x: x + 3,
				y: 9 + 3,
				'stroke-width': 0
			}, x);
		}

		for (let y = size; y < this.options.canvas.height; y += size) {
			this.layers.grid.add('line', {
				x1: 0,
				y1: y,
				x2: this.options.canvas.width,
				y2: y
			});

			this.layers.grid.add('text', {
				x: 3,
				y: y + 12,
				'stroke-width': 0
			}, y);
		}

		this.layers.grid.add('text', {
			x: 2,
			y: 12,
			'stroke-width': 0
		}, 'Y X:');
	}

	/**
	 * Compare map object attributes to determine if attributes were changed.
	 *
	 * @param {object} source  Object to be compared.
	 * @param {object} target  Object to be compared with.
	 *
	 * @return {boolean}       True if objects attributes are different, false if object attributes are the same.
	 */
	isChanged(source, target) {
		if (typeof source !== 'object' || source === null) {
			return true;
		}

		const keys = Object.keys(target);

		for (let i = 0; i < keys.length; i++) {
			if (typeof target[keys[i]] === 'object') {
				if (this.isChanged(source[keys[i]], target[keys[i]])) {
					return true;
				}
			}
			else {
				if (target[keys[i]] !== source[keys[i]]) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Update map elements and links. First iterate through objects of specified type and check if they exist. If not,
	 * remove them completely. Then if objects still exist iterate through objects of specified type again create new
	 * class instance. After that iterate through elements and links adding the links to elements and calculate the
	 * coordinates for elements. Then process the elements and links - first add links and then elements.
	 *
	 * @param {object}  data         Object with elements, links, corresponding ID fields and class names.
	 * @param {boolean} incremental  Update method. If set to true, items are added to the existing set of map objects.
	 */
	updateElements(data, incremental) {
		const items = {},
			class_names = {},
			types = {},
			id_fields = {};

		Object.keys(data).forEach((key) => {
			items[key] = data[key].items;
			class_names[key] = data[key].class;
			types[key] = key;
			id_fields[key] = data[key].id_field;
		});

		if (incremental !== true) {
			for (const type in types) {
				Object.keys(this[type]).forEach((key) => {
					if (items[type].filter((item) => item[id_fields[type]] === key).length == 0) {
						this[type][key].remove();
						delete this[type][key];
					}
				});
			}
		}

		// Initialize classes.
		for (const type in types) {
			items[type].forEach((item) => {
				if (typeof this[type][item[id_fields[type]]] !== 'object') {
					this[type][item[id_fields[type]]] = new class_names[type](this, {});
				}
			});
		}

		const processed_links = [],
			processed_elements = [];

		// Calculate x, y and other necessary options in order to draw links. And add only matching links to elements.
		items.elements.forEach((elements_item) => {
			this.elements[elements_item.selementid].updateOptions(elements_item);

			elements_item.links = [];

			items.links.forEach((links_item) => {
				if ((elements_item.selementid_orig !== undefined
						&& (elements_item.selementid_orig === links_item.selementid1
								|| elements_item.selementid_orig === links_item.selementid2
								|| elements_item.selementid === links_item.selementid1
								|| elements_item.selementid === links_item.selementid2))
						|| (elements_item.selementid === links_item.selementid1
								|| elements_item.selementid === links_item.selementid2)) {
					elements_item.links.push(links_item);
				}
			});
		});

		// If elements have links, first draw links and then the elements. Otherwise just draw the element.
		items.elements.forEach((elements_item) => {
			if (elements_item.links.length > 0) {
				elements_item.links.forEach((links_item) => {
					const linkid = links_item.linkid;

					if (!processed_links.includes(linkid)) {
						this.links[linkid].update(links_item);
						processed_links.push(linkid);
					}
				});
			}

			const selementid = elements_item.selementid;

			if (!processed_elements.includes(selementid)) {
				this.elements[selementid].update(elements_item);
				processed_elements.push(selementid);
			}
		});
	}

	/**
	 * Update ordered map objects.
	 *
	 * @param {string}       type         Object type (name of SVGMap class attribute).
	 * @param {string}       id_field     Field used to identify objects.
	 * @param {SVGMapShape}  Class        Class used to create instance of a new object.
	 * @param {object}       items        Array of map objects.
	 * @param {boolean}      incremental  Update method. If set to true, items are added to the existing set of map
	 *                                    objects.
	 */
	updateItems(type, id_field, Class, items, incremental) {
		if (incremental !== true) {
			Object.keys(this[type]).forEach((key) => {
				if (items.filter((item) => item[id_field] === key).length == 0) {
					this[type][key].remove();
					delete this[type][key];
				}
			});
		}

		items.forEach((item) => {
			if (typeof this[type][item[id_field]] !== 'object') {
				this[type][item[id_field]] = new Class(this, {});
			}

			this[type][item[id_field]].update(item);
		});
	}

	/**
	 * Update map objects based on specified options.
	 *
	 * @param {object}  options      Map options.
	 * @param {boolean} incremental  Update method. If set to true, items are added to the existing set of map objects.
	 */
	update(options, incremental) {
		let invalidate = false;

		// Check for element and link changes only on map or widget refresh. Similar to edit mode when re-ordering.
		if (options.caller !== undefined) {
			/*
			 * Adding new or removing existing elements means that display the order of elements have changed. This
			 * includes highlights, links and selections. Changing an element type to a "host group elements" type can
			 * also trigger this. If element count is the same check only if order (zindex) has changed for element. If
			 * so, draw elements again. Other element properties like coordinates, image URL, width or height are
			 * checked later and complete redrawing is not necessary.
			 */
			invalidate = options.elements.length != this.options.elements.length;

			if (!invalidate) {
				const existing_elements = new Map(
					this.options.elements.map((element) => [element.selementid, element])
				);

				for (const element of options.elements) {
					const ex = existing_elements.get(element.selementid);

					if (!ex || ex.zindex != element.zindex) {
						invalidate = true;

						break;
					}
				}
			}

			/*
			 * Check changes in link properties like show label always or auto hide. That generates changes in
			 * "options.duplicated_links". Usually enough with just checking if duplicated link count has changed. If a
			 * different node is connected, it can be checked using changes in "options.links".
			 */
			if (!invalidate) {
				invalidate = options.duplicated_links.length != this.options.duplicated_links.length;
			}

			/*
			 * If no changes in element properties or duplicated links, check if links are added or removed. This also
			 * should trigger a complete element redrawing. If link count is the same, check if selement IDs have
			 * changed for same link. Meaning that a different node could be connected. In that case also draw elements
			 * again. Other link properties like color, draw style are checked later and can be applied in real time and
			 * redrawing is not necessary.
			 */
			if (!invalidate) {
				invalidate = options.links.length != this.options.links.length;

				if (!invalidate) {
					const existing_links = new Map(this.options.links.map((link) => [link.linkid, link]));

					for (const link of options.links) {
						const ex = existing_links.get(link.linkid);

						if (!ex) {
							invalidate = true;

							break;
						}

						const [a1, a2] = [link.selementid1, link.selementid2].sort();
						const [b1, b2] = [ex.selementid1, ex.selementid2].sort();

						if (a1 !== b1 || a2 !== b2) {
							invalidate = true;

							break;
						}
					}
				}
			}

			if (invalidate) {
				this.invalidate('selements');
			}
		}

		// Sort map elements and shapes.
		['elements', 'shapes'].forEach((key) => {
			options[key] = (options[key] ?? []).sort((a, b) => a.zindex - b.zindex);
		});

		this.options.label_location = options.label_location;

		// Collect the list of images.
		const images = {};

		Object.keys(options.elements).forEach((key) => {
			const element = options.elements[key];

			if (element.icon !== undefined) {
				images[element.icon] = this.getImageUrl(element.icon);
			}
		});

		if (options.background && options.background != 0) {
			images[options.background] = this.getImageUrl(options.background);
		}

		// Resize the canvas and move marks.
		if (options.canvas !== undefined && options.canvas.width !== undefined && options.canvas.height !== undefined
				&& this.canvas.resize(options.canvas.width, options.canvas.height)) {
			this.options.canvas = options.canvas;

			if (this.container !== null) {
				this.container.style.width = `${options.canvas.width}px`;
				this.container.style.height = `${options.canvas.height}px`;
			}

			if (options.show_timestamp) {
				this.timestamp.update({
					x: options.canvas.width,
					y: options.canvas.height - 6
				});
			}
		}

		this.rendered_promise = new Promise((resolve) => {
			// Images are preloaded before update.
			this.imageCache.preload(images, () => {
				try {
					/*
					 * Shapes must be drawn first as host group element links depend on shape around it and its
					 * positioning.
					 */
					this.updateItems('shapes', 'sysmap_shapeid', SVGMapShape, options.shapes, incremental);

					this.updateElements({
						elements: {
							class: SVGMapElement,
							id_field: 'selementid',
							items: options.elements
						},
						links: {
							class: SVGMapLink,
							id_field: 'linkid',
							items: options.links.concat(options.duplicated_links)
						}},
						incremental
					);

					this.#updateBackground(options);
				}
				catch(exception) {
					resolve();

					throw exception;
				}

				this.options = SVGElement.mergeAttributes(this.options, options);

				const readiness = [],
					container = typeof this.options.container !== 'object'
						? document.querySelector(this.options.container)
						: this.options.container;

				container.querySelectorAll('image').forEach((image) => {
					readiness.push(new Promise((resolve) => image.addEventListener('load', resolve)));
				});

				resolve(Promise
					.all(readiness)
					.then(this.select(this.selected_element_id))
				);
			});
		});

		// Timestamp (date on map) is updated.
		if (options.show_timestamp && options.timestamp !== undefined) {
			this.timestamp.element.textContent = options.timestamp;
		}
	}

	/**
	 * Invalidate items based on type.
	 *
	 * @param {string} type  Object type (name of SVGMap class attribute).
	 */
	invalidate(type) {
		switch (type) {
			case 'shapes':
				Object.keys(this[type]).forEach((key) => {
					this[type][key].options = {};
					this[type][key].element.invalidate();
				});
				break;

			case 'selements':
				Object.values(this.elements).forEach((element) => {
					element.image.invalidate();
					['label', 'markers'].forEach((key) => element.removeItem(key));

					['highlight', 'selection'].forEach((key) => {
						if (element[key] !== null) {
							element[key].invalidate();
						}
					});
				});

				Object.values(this.links).forEach((element) => {
					element.remove();
					element.options = {};
				});
				break;
		}
	}

	/**
	 * Render map within container.
	 *
	 * @param {mixed} container  DOM element or jQuery selector.
	 */
	#render(container) {
		if (typeof container === 'string') {
			container = jQuery(container)[0];
		}
		this.canvas.render(container);
		this.container = container;
	}

	/**
	 * Map element selection by element ID.
	 *
	 * @param {string} selected_element_id
	 */
	select(selected_element_id) {
		for (const element_id in this.elements) {
			this.elements[element_id].toggleSelection(element_id == selected_element_id);
		}
	}
}
