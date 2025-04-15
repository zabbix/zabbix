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
 * Creates a new Shape.
 *
 * @class represents shape (static) element.
 *
 * @property {object} sysmap   Reference to Map object
 * @property {object} data     Shape values from DB.
 * @property {string} id       Shape ID (shapeid).
 *
 * @param {object} sysmap  Map object.
 * @param {object} data    Shape data from DB.
 */
class Shape {
	constructor(sysmap, data) {
		const default_data = {
			type: SVGMapShape.TYPE_RECTANGLE,
			x: 10,
			y: 10,
			width: 50,
			height: 50,
			border_color: '000000',
			background_color: '',
			border_width: SYSMAP_SHAPE_BORDER_WIDTH_DEFAULT,
			// Helvetica
			font: 9,
			font_size: 11,
			font_color: '000000',
			text_valign: 0,
			text_halign: 0,
			text: '',
			border_type: SVGMapShape.BORDER_TYPE_SOLID
		};

		this.sysmap = sysmap;

		if (!data) {
			data = default_data;

			// Generate unique sysmap_shapeid.
			data.sysmap_shapeid = getUniqueId();
			data.zindex = Object.values(sysmap.shapes).reduce((max, el) => Math.max(max, el.data.zindex), -1) + 1;
		}
		else {
			Object.keys(default_data).forEach((field) => {
				if (data[field] === undefined) {
					data[field] = default_data[field];
				}
			});
		}

		this.data = data;
		this.id = this.data.sysmap_shapeid;
		this.expanded = this.data.expanded;
		delete this.data.expanded;

		// Assign by reference.
		this.sysmap.data.shapes[this.id] = this.data;

		// Create dom.
		this.domNode = $('<div>', {
				style: 'position: absolute; z-index: 1; background: url("data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7") 0 0 repeat',
			})
			.appendTo(this.sysmap.container)
			.addClass('cursor-pointer sysmap_shape')
			.attr('data-id', this.id)
			.attr('data-type', 'shapes');

		this.makeDraggable(true);
		this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

		const dimensions = this.getDimensions();

		Object.assign(this.domNode[0].style, {
			top: `${dimensions.y}px`,
			left: `${dimensions.x}px`,
			width: `${dimensions.width}px`,
			height: `${dimensions.height}px`
		});
	}

	/**
	 * Updates values in property data.
	 *
	 * @param {object} data
	 */
	update(data) {
		const invalidate = (data.type != SVGMapShape.TYPE_LINE && data.text !== undefined
				&& this.data.text !== data.text);

		if (data.type !== undefined && /^[0-9]+$/.test(this.data.sysmap_shapeid) === true
				&& (data.type == SVGMapShape.TYPE_LINE) != (this.data.type == SVGMapShape.TYPE_LINE)) {
			delete data.sysmap_shapeid;
			this.data.sysmap_shapeid = getUniqueId();
		}

		Object.assign(this.data, data);

		['x', 'y', 'width', 'height'].forEach((name) => this.data[name] = parseInt(this.data[name], 10));

		const dimensions = this.getDimensions();

		Object.assign(this.domNode[0].style, {
			width: `${dimensions.width}px`,
			height: `${dimensions.height}px`
		});

		this.makeDraggable(true);
		this.makeResizable(this.data.type != SVGMapShape.TYPE_LINE);

		this.align(false);
		this.sysmap.afterMove(this);

		Object.assign(this.data, data);

		this.sysmap[invalidate ? 'expandMacros' : 'updateImage'](this);
	}

	/**
	 * Return label based on map constructor configuration.
	 *
	 * @param {boolean} expand
	 *
	 * @return {string}  Return label with expanded macros.
	 */
	getLabel(expand) {
		let label = this.data.text;

		if (expand === undefined) {
			expand = true;
		}

		if (expand && typeof(this.expanded) === 'string' && this.sysmap.data.expand_macros == SYSMAP_EXPAND_MACROS_ON) {
			label = this.expanded;
		}

		return label;
	}

	/**
	 * Gets shape dimensions.
	 */
	getDimensions() {
		let dimensions = {
			x: parseInt(this.data.x, 10),
			y: parseInt(this.data.y, 10),
			width: parseInt(this.data.width, 10),
			height: parseInt(this.data.height, 10)
		};

		if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
			const width = parseInt(this.sysmap.data.width),
				height = parseInt(this.sysmap.data.height),
				x = Math.min(Math.max(0, Math.min(dimensions.x, dimensions.width)), width),
				y = Math.min(Math.max(0, Math.min(dimensions.y, dimensions.height)), height),
				dx = Math.max(dimensions.x, dimensions.width) - x,
				dy = Math.max(dimensions.y, dimensions.height) - y;

			dimensions = {
				x,
				y,
				width: Math.min(Math.max(0, dx), width - x),
				height: Math.min(Math.max(0, dy), height - y)
			};
		}

		return dimensions;
	}

	updateHandles() {
		if (this.handles === undefined) {
			this.handles = [
				$('<div>', {'class': 'ui-resize-dot cursor-move'}),
				$('<div>', {'class': 'ui-resize-dot cursor-move'})
			];

			this.domNode.parent().append(this.handles);

			for (let i = 0; i < 2; i++) {
				this.handles[i].data('id', i);
				this.handles[i].draggable({
					containment: 'parent',
					drag: (e, data) => {
						if (data.helper.data('id') === 0) {
							this.data.x = parseInt(data.position.left, 10) + 4;
							this.data.y = parseInt(data.position.top, 10) + 4;
						}
						else {
							this.data.width = parseInt(data.position.left, 10) + 4;
							this.data.height = parseInt(data.position.top, 10) + 4;
						}

						const dimensions = this.getDimensions();

						Object.assign(this.domNode[0].style, {
							top: `${dimensions.y}px`,
							left: `${dimensions.x}px`,
							width: `${dimensions.width}px`,
							height: `${dimensions.height}px`
						});

						this.sysmap.afterMove(this);
					}
				});
			}
		}

		this.handles[0].css({
			left: `${this.data.x - 3}px`,
			top: `${this.data.y - 3}px`
		});

		this.handles[1].css({
			left: `${this.data.width - 3}px`,
			top: `${this.data.height - 3}px`
		});
	}

	/**
	 * Allow dragging of shape.
	 */
	makeDraggable(enable) {
		const node = this.domNode,
			enabled = node[0].classList.contains('ui-draggable');

		if (enable) {
			if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
				this.updateHandles();
			}
			else {
				if (this.handles !== undefined) {
					this.handles.forEach((handle) => handle.remove());
					delete this.handles;
				}
			}

			if (!enabled) {
				node.draggable({
					containment: 'parent',
					helper: () => this.sysmap.dragGroupPlaceholder(node),
					start: () => {
						node[0].classList.add('cursor-dragging');
						node[0].classList.remove('cursor-pointer');
						this.sysmap.dragGroupInit(this);
					},
					drag: (e, data) => this.sysmap.dragGroupDrag(data, this),
					stop: () => {
						node[0].classList.add('cursor-pointer');
						node[0].classList.remove('cursor-dragging');
						this.sysmap.dragGroupStop(this);
					}
				});
			}
		}
		else {
			if (this.handles !== undefined) {
				this.handles.forEach((handle) => handle.remove());
				delete this.handles;
			}

			if (enabled) {
				node.draggable('destroy');
			}
		}
	}

	/**
	 * Allow resizing of shape.
	 */
	makeResizable(enable) {
		const node = this.domNode,
			enabled = node[0].classList.contains('ui-resizable');

		if (enable && !enabled) {
			const handles = {};

			['n', 'e', 's', 'w', 'ne', 'se', 'sw', 'nw'].forEach((key) => {
				const handle = document.createElement('div');

				handle.classList.add('ui-resizable-handle', `ui-resizable-${key}`);

				if (['n', 'e', 's', 'w'].indexOf(key) >= 0) {
					const dot = document.createElement('div'),
						border = document.createElement('div');

					dot.className = 'ui-resize-dot';
					border.className = `ui-resizable-border-${key}`;

					handle.appendChild(dot);
					handle.appendChild(border);
				}

				node[0].appendChild(handle);
				handles[key] = handle;
			});

			node[0].classList.add('ui-inner-handles');

			node.resizable({
				handles: handles,
				autoHide: true,
				stop: (e, data) => {
					this.updatePosition({
						x: parseInt(data.position.left, 10),
						y: parseInt(data.position.top, 10)
					});
				}
			});
		}
		else if (!enable && enabled) {
			node[0].classList.remove('ui-inner-handles', 'ui-resizable', 'ui-resizable-autohide');
			Object.values(this.domNode[0].childNodes).forEach((child) => child.remove());
			node.resizable('destroy');
		}
	}

	/**
	 * Toggle shape selection.
	 *
	 * @param {bool} state
	 */
	toggleSelect(state) {
		state = state || !this.selected;
		this.selected = state;

		if (this.selected) {
			this.domNode[0].classList.add('map-element-selected');
		}
		else {
			this.domNode[0].classList.remove('map-element-selected');
		}

		return this.selected;
	}

	/**
	 * Align shape to map or map grid.
	 *
	 * @param {bool} doAutoAlign if we should align element to grid
	 */
	align(doAutoAlign) {
		const dims = {
				height: this.domNode.height(),
				width: this.domNode.width()
			},
			dimensions = this.getDimensions(),
			x = dimensions.x,
			y = dimensions.y,
			shiftX = Math.round(dims.width / 2),
			shiftY = Math.round(dims.height / 2),
			gridSize = parseInt(this.sysmap.data.grid_size, 10);

			let newX = x,
				newY = y,
				newWidth = Math.round(dims.width),
				newHeight = Math.round(dims.height);

		// Lines should not be aligned.
		if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
			Object.assign(this.domNode[0].style, {
				top: `${dimensions.y}px`,
				left: `${dimensions.x}px`,
				width: `${dimensions.width}px`,
				height: `${dimensions.height}px`
			});

			return;
		}

		// If 'fit to map' area coords are 0 always.
		if (this.data.elementtype == SVGMapElement.TYPE_HOST_GROUP
				&& this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS
				&& this.data.areatype == SVGMapElement.AREA_TYPE_FIT) {
			newX = 0;
			newY = 0;
		}
		// If autoalign is off.
		else if (doAutoAlign === false
				|| (doAutoAlign === undefined && this.sysmap.data.grid_align == SYSMAP_GRID_ALIGN_OFF)) {
			if ((x + dims.width) > this.sysmap.data.width) {
				newX = this.sysmap.data.width - dims.width;
			}
			if ((y + dims.height) > this.sysmap.data.height) {
				newY = this.sysmap.data.height - dims.height;
			}
			if (newX < 0) {
				newX = 0;
				newWidth = this.sysmap.data.width;
			}
			if (newY < 0) {
				newY = 0;
				newHeight = this.sysmap.data.height;
			}
		}
		else {
			newX = x + shiftX;
			newY = y + shiftY;

			newX = Math.floor(newX / gridSize) * gridSize;
			newY = Math.floor(newY / gridSize) * gridSize;

			newX += Math.round(gridSize / 2) - shiftX;
			newY += Math.round(gridSize / 2) - shiftY;

			while ((newX + dims.width) > this.sysmap.data.width) {
				newX -= gridSize;
			}
			while ((newY + dims.height) > this.sysmap.data.height) {
				newY -= gridSize;
			}
			while (newX < 0) {
				newX += gridSize;
			}
			while (newY < 0) {
				newY += gridSize;
			}
		}

		this.data.y = newY;
		this.data.x = newX;

		if (this instanceof Shape || this.data.elementsubtype == SVGMapElement.SUBTYPE_HOST_GROUP_ELEMENTS) {
			this.data.width = newWidth;
			this.data.height = newHeight;
		}

		Object.assign(this.domNode[0].style, {
			top: `${this.data.y}px`,
			left: `${this.data.x}px`,
			width: newWidth,
			height: newHeight
		});
	}

	/**
	 * Updates element position.
	 *
	 * @param {object} coords
	 *
	 */
	updatePosition(coords, invalidate) {
		if (this instanceof Shape && this.data.type == SVGMapShape.TYPE_LINE) {
			const dx = coords.x - Math.min(parseInt(this.data.x, 10), parseInt(this.data.width, 10)),
				dy = coords.y - Math.min(parseInt(this.data.y, 10), parseInt(this.data.height, 10));

			this.data.x = parseInt(this.data.x, 10) + dx;
			this.data.y = parseInt(this.data.y, 10) + dy;
			this.data.width = parseInt(this.data.width, 10) + dx;
			this.data.height = parseInt(this.data.height, 10) + dy;

			this.updateHandles();
		}
		else {
			this.data.x = coords.x;
			this.data.y = coords.y;
		}

		if (invalidate !== false) {
			this.align();
			this.sysmap.afterMove(this);
		}
		else {
			const dimensions = this.getDimensions();

			Object.assign(this.domNode[0].style, {
				top: `${dimensions.y}px`,
				left: `${dimensions.x}px`
			});
		}
	}

	/**
	 * Removes Shape object, delete all reference to it.
	 */
	remove() {
		this.makeDraggable(false);
		this.domNode.remove();
		delete this.sysmap.data.shapes[this.id];
		delete this.sysmap.shapes[this.id];

		if (this.sysmap.selection.shapes[this.id] !== undefined) {
			this.sysmap.selection.count.shapes--;
		}

		delete this.sysmap.selection.shapes[this.id];
	}

	/**
	 * Gets Shape data.
	 *
	 * @return {object}
	 */
	getData() {
		return this.data;
	}
}
