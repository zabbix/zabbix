/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

class CSVGGauge {
	static SVGNS = 'http://www.w3.org/2000/svg';

	static FONT_SIZE_RATIO = 0.82;

	static DESC_V_POSITION_TOP = 0
	static DESC_V_POSITION_BOTTOM = 1;

	static UNITS_POSITION_BEFORE = 0;
	static UNITS_POSITION_ABOVE = 1;
	static UNITS_POSITION_AFTER = 2;
	static UNITS_POSITION_BELOW = 3;

	static MINMAX_DEFAULT_SIZE = 5;

	constructor(container, options) {
		this.options = options;

		this.svg = document.createElementNS(this.constructor.SVGNS, 'svg');
		this.svg.classList.add('svg-gauge');

		container.appendChild(this.svg);

		// Contains all the elements - description, value, min/max, thresholds ...
		this.elements = {};

		this.widgetContents = this.svg.closest('.dashboard-widget-gauge');

		// Set background color of widget
		this.widgetContents.style.backgroundColor = this.options.bg_color ? '#' + this.options.bg_color : '';

		this.isValueSet = false;

		this.angleStart = this.options.angle === 180 ? -90 : -135;
		this.angleEnd = this.options.angle === 180 ? 90 : 135;

		this.angleOld = this.angleStart;
		this.angleNew = this.angleStart;

		// Maximum space / radius for arcs - initial (when new svg created) and current
		this.maxSpaceInitial = 0;
		this.maxSpace = 0;

		// Radius of needle's round part at the bottom
		this.thicknessNeedle = 15;

		if (this.options.thresholds.data.length) {
			this.#prepareThresholdsArcParts();
		}
	}

	setSize({width, height}) {
		this.width = width;
		this.height = height;

		// Starting point - center coordinates of arc
		this.x = this.width / 2;
		this.y = this.height / 2;

		this.svg.setAttribute('width', this.width);
		this.svg.setAttribute('height', this.height);

		this.widgetContents.style.fontSize = this.height * this.constructor.FONT_SIZE_RATIO + 'px';

		this.minMaxSize = this.options.minmax.size || this.constructor.MINMAX_DEFAULT_SIZE;

		this.#drawDescription();
		this.#drawMinMax();
		this.#drawValue();
		this.#drawArc();
		this.#drawNeedle();

		if (this.options.thresholds.arc.show) {
			this.#calculateCoordinatesOfArcContainer(this.elements.threshold_arc_empty);
		}
		if (this.options.value.arc.show) {
			this.#calculateCoordinatesOfArcContainer(this.elements.value_arc_empty);
		}

		this.#positionMinMax();
		this.#positionValue();
	}

	setValue({value, value_text, units_text}) {
		if (this.options.units.show) {
			this.elements.value.node.textContent = value_text;
			this.elements.units.node.textContent = units_text;
		}
		else {
			this.elements.value_container.node.textContent = value_text;
		}

		if (!this.isValueSet) {
			this.#positionValue();
			this.isValueSet = true;
		}

		this.angleOld = this.angleNew;
		this.angleNew = this.#getAngle(value, this.options.min, this.options.max);

		if (this.options.value.arc.show) {
			window.requestAnimationFrame(() => {
				this.#animate(this.angleOld, (currentAngle) => {
					const pathDefinition = this.#defineArc(this.angleStart, currentAngle, this.radiusValueArc, this.thicknessValueArc);
					this.elements.value_arc.node.setAttribute('d', pathDefinition);
					this.elements.value_arc.node.style.fill = '#' + this.#getCurrentThresholdColor(currentAngle);
				});
			});
		}

		if (this.options.needle.show) {
			window.requestAnimationFrame(() => {
				this.#animate(this.angleOld, (currentAngle) => {
					this.elements.needle.node.setAttribute('transform', `translate(${this.x} ${this.y}) rotate(${currentAngle})`);
					this.#setNeedleColor(currentAngle);
				});
			});
		}
	}

	destroy() {
		this.svg.remove();
	}

	#drawDescription() {
		let container = this.svg.querySelector('.svg-gauge-description');
		const linesTexts = this.options.description.text.split('\r\n');

		if (!container) {
			container = document.createElementNS(this.constructor.SVGNS, 'text');
			container.classList.add('svg-gauge-description');
			container.style.fontSize = this.options.description.size / linesTexts.length + '%';
			container.style.fontWeight = this.options.description.is_bold ? 'bold' : 'normal';
			container.style.fill = this.options.description.color ? '#' + this.options.description.color : '';
			this.svg.appendChild(container);

			for (let i = 0; i < linesTexts.length; i++) {
				let line = document.createElementNS(this.constructor.SVGNS, 'tspan');
				line.setAttribute('x', '50%');
				container.appendChild(line);

				// Simulate new line
				if (i > 0) {
					line.setAttribute('dy', this.options.description.size / linesTexts.length + '%');
				}
			}

			const title = document.createElementNS(this.constructor.SVGNS, 'title');
			title.textContent = this.options.description.text;
			container.appendChild(title);
		}

		const linesNodes = this.svg.querySelectorAll('.svg-gauge-description tspan');

		for (let i = 0; i < linesNodes.length; i++) {
			linesNodes[i].textContent = linesTexts[i];

			// Add ellipsis for each line if text is too long
			while (linesNodes[i].getComputedTextLength() > this.width && linesNodes[i].textContent.length >= 4) {
				linesNodes[i].textContent = linesNodes[i].textContent.slice(0, -4) + '...';
			}
		}

		const bbox = container.getBBox();

		this.elements.description = {
			node: container,
			width: bbox.width,
			height: bbox.height
		};

		if (this.options.description.position === this.constructor.DESC_V_POSITION_TOP) {
			container.setAttribute('y', '0px');
		}
		else if (this.options.description.position === this.constructor.DESC_V_POSITION_BOTTOM) {
			container.setAttribute('y', this.height - bbox.height + 'px');
		}
	}

	#drawArc() {
		if (this.options.description.position === this.constructor.DESC_V_POSITION_TOP) {
			this.y = this.height;
		}
		else if (this.options.description.position === this.constructor.DESC_V_POSITION_BOTTOM) {
			this.y = this.height - this.elements.description.height;
		}

		// Maximum available space for arc depending on widget size
		this.maxSpace = this.height - this.elements.description.height;

		if (this.width <= this.height) {
			this.maxSpace = this.width - this.elements.description.height - (this.elements.min?.width || 0) * 2;
		}

		if (this.options.needle.show && this.options.angle === 180) {
			const valueSize = this.options.value.size || 0;
			const unitsSize = this.options.units.size || 0;

			let valueHeightPercents = valueSize;

			if (this.options.units.show) {
				if (this.options.units.position === this.constructor.UNITS_POSITION_BEFORE
					|| this.options.units.position === this.constructor.UNITS_POSITION_AFTER) {
					valueHeightPercents = valueSize >= unitsSize ? valueSize : unitsSize;
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_ABOVE
					|| this.options.units.position === this.constructor.UNITS_POSITION_BELOW) {
					valueHeightPercents = valueSize + unitsSize;
				}
			}

			const valueHeightPixels = valueHeightPercents * this.height / 100;
			this.maxSpace -= this.thicknessNeedle + valueHeightPixels;
			this.y -= this.thicknessNeedle + valueHeightPixels;
		}
		else if (this.options.angle === 270) {
			// TODO: calculate more precise
			this.y -= this.maxSpace * 0.43;
			this.maxSpace *= 0.57;
		}

		if (this.width <= this.maxSpace * 2 + (this.elements.min?.width || 0) * 2) {
			// Widget's width is too small - need to shrink elements
			const offset = this.maxSpace - this.width / 2 + (this.elements.min?.width || 0);
			this.maxSpace -= offset;
		}

		// Reserve space for threshold labels if they should be displayed
		if (this.options.thresholds.arc.show && this.options.thresholds.show_labels) {
			const labelHeight = (this.minMaxSize * this.height) / 100;
			this.maxSpace -= labelHeight;
		}

		if (this.maxSpace <= 0) {
			this.maxSpace = 0;
		}
		else if (this.maxSpaceInitial === 0) {
			this.maxSpaceInitial = this.maxSpace;
		}

		// Show both arcs
		if (this.options.value.arc.show && this.options.thresholds.arc.show && this.options.thresholds.data.length) {
			// Don't allow to exceed 100% for both arcs in total. If so - shrink thresholds arc
			if (this.options.value.arc.size + this.options.thresholds.arc.size > 100) {
				this.options.thresholds.arc.size = 100 - this.options.value.arc.size;
			}

			// Gap between arcs
			const gap = 5;

			this.radiusThresholdArc = this.maxSpace;
			this.thicknessThresholdArc = (this.options.thresholds.arc.size * (this.maxSpace - gap)) / 100;
			this.radiusValueArc = this.maxSpace - this.thicknessThresholdArc - gap;
			this.thicknessValueArc = (this.options.value.arc.size * (this.maxSpace - gap)) / 100;

			this.#addThresholdArc();
			this.#addValueArc();
		}
		// Show only value arc
		else if (this.options.value.arc.show && !this.options.thresholds.arc.show) {
			this.radiusValueArc = this.maxSpace;
			this.thicknessValueArc = (this.options.value.arc.size * this.radiusValueArc) / 100;

			this.#addValueArc();
		}
		// Show only threshold arc
		else if (!this.options.value.arc.show && this.options.thresholds.arc.show && this.options.thresholds.data.length) {
			this.radiusThresholdArc = this.maxSpace;
			this.thicknessThresholdArc = (this.options.thresholds.arc.size * this.radiusThresholdArc) / 100;

			this.#addThresholdArc();
		}
	}

	#addThresholdArc() {
		let container = this.svg.querySelector('.svg-gauge-threshold-arc-container');

		if (!container) {
			container = document.createElementNS(this.constructor.SVGNS, 'g');
			container.classList.add('svg-gauge-threshold-arc-container');
			this.svg.appendChild(container);

			this.elements.threshold_arc_container = {
				node: container
			};

			this.#addArcEmpty(container);

			this.#drawThresholdsArcParts();
		}

		if (this.options.thresholds.show_labels) {
			this.#addThresholdsLabels();
		}

		container.setAttribute('transform', `translate(${this.x} ${this.y}) scale(${this.maxSpace / this.maxSpaceInitial})`);
	}

	#drawThresholdsArcParts() {
		for (let i = 0; i < this.thresholdsArcParts.length; i++) {
			const path = document.createElementNS(this.constructor.SVGNS, 'path');
			path.classList.add('svg-gauge-threshold-arc-part');
			path.style.fill = '#' + this.thresholdsArcParts[i].color;
			this.elements.threshold_arc_container.node.appendChild(path);

			const pathDefinition = this.#defineArc(this.thresholdsArcParts[i].angleStart, this.thresholdsArcParts[i].angleEnd, this.radiusThresholdArc, this.thicknessThresholdArc);

			path.setAttribute('d', pathDefinition);
		}
	}

	#prepareThresholdsArcParts() {
		this.thresholdsArcParts = [];

		for (let i = 0; i < this.options.thresholds.data.length; i++) {
			let valueEnd = 0;

			if (i < this.options.thresholds.data.length - 1) {
				valueEnd = this.options.thresholds.data[i + 1].value;
			}
			else {
				valueEnd = this.options.max;
			}

			this.thresholdsArcParts[i] = {
				angleStart: this.#getAngle(this.options.thresholds.data[i].value, this.options.min, this.options.max),
				angleEnd: this.#getAngle(valueEnd, this.options.min, this.options.max),
				label: this.options.thresholds.data[i].text,
				color: this.options.thresholds.data[i].color
			};
		}
	}

	#addThresholdsLabels() {
		let container = this.svg.querySelector('.svg-gauge-thresholds-labels');

		if (!container) {
			container = document.createElementNS(this.constructor.SVGNS, 'g');
			container.classList.add('svg-gauge-thresholds-labels');
			this.svg.appendChild(container);

			this.elements.thresholds_labels = {
				node: container,
				children: []
			};

			this.#drawThresholdsLabels();
		}

		this.#positionThresholdLabels();
	}

	#drawThresholdsLabels() {
		for (let i = 0; i < this.thresholdsArcParts.length; i++) {
			// Don't draw label if it is the same as min or max
			if (this.thresholdsArcParts[i].angleStart === this.angleStart || this.thresholdsArcParts[i].angleStart === this.angleEnd) {
				continue;
			}

			const container = document.createElementNS(this.constructor.SVGNS, 'text');
			container.textContent = this.thresholdsArcParts[i].label;
			container.style.fontSize = this.minMaxSize + '%';
			this.elements.thresholds_labels.node.appendChild(container);

			this.elements.thresholds_labels.children[i] = {
				node: container
			}
		}
	}

	#positionThresholdLabels() {
		for (let i = 0; i < this.elements.thresholds_labels.children.length; i++) {
			const child = this.elements.thresholds_labels.children[i];

			if (child) {
				const bbox = child.node.getBBox();

				child.width = bbox.width;
				child.height = bbox.height;

				const angle = this.thresholdsArcParts[i].angleStart;

				const radians = this.#degreesToRadians(angle);

				// Coordinates on arc outer side
				const tempX = this.x + (Math.cos(radians) * this.radiusThresholdArc);
				const tempY = this.y + (Math.sin(radians) * this.radiusThresholdArc);

				let x = 0;
				let y = 0;

				// Coordinates regarding arc quadrants and sizes of labels
				if (angle < -90) {
					x = tempX - child.width;
					y = tempY;
				}
				else if (angle >= -90 && angle < 0) {
					x = tempX - child.width;
					y = tempY - child.height;
				}
				else if (angle >= 0 && angle < 90) {
					x = tempX;
					y = tempY - child.height;
				}
				else if (angle >= 90) {
					x = tempX;
					y = tempY;
				}

				child.node.setAttribute('x', x + 'px');
				child.node.setAttribute('y', y + 'px');
			}
		}
	}

	#addValueArc() {
		let container = this.svg.querySelector('.svg-gauge-value-arc-container');

		if (!container) {
			container = document.createElementNS(this.constructor.SVGNS, 'g');
			container.classList.add('svg-gauge-value-arc-container');
			this.svg.appendChild(container);

			this.elements.value_arc_container = {
				node: container
			};

			this.#addArcEmpty(container);

			const path = document.createElementNS(this.constructor.SVGNS, 'path');
			path.classList.add('svg-gauge-arc-value');
			container.appendChild(path);

			this.elements.value_arc = {
				node: path
			};

			const pathDefinition = this.#defineArc(this.angleStart, this.angleStart, this.radiusValueArc, this.thicknessValueArc);

			path.setAttribute('d', pathDefinition);
		}

		container.setAttribute('transform', `translate(${this.x} ${this.y}) scale(${this.maxSpace / this.maxSpaceInitial})`);
	}

	#addArcEmpty(container) {
		const path = document.createElementNS(this.constructor.SVGNS, 'path');
		path.classList.add('svg-gauge-arc-empty');
		path.style.fill = this.options.empty_color ? '#' + this.options.empty_color : '';
		container.appendChild(path);

		let arc = 'value_arc';
		let radius = 0;
		let thickness = 0;

		if (container.classList.contains('svg-gauge-value-arc-container')) {
			arc = 'value_arc_empty';
			radius = this.radiusValueArc;
			thickness = this.thicknessValueArc;
		}
		else if (container.classList.contains('svg-gauge-threshold-arc-container')) {
			arc = 'threshold_arc_empty';
			radius = this.radiusThresholdArc;
			thickness = this.thicknessThresholdArc;
		}

		this.elements[arc] = {
			node: path
		};

		const pathDefinition = this.#defineArc(this.angleStart, this.angleEnd, radius, thickness);

		path.setAttribute('d', pathDefinition);
	}

	#drawValue() {
		if (this.options.units.show) {
			let container = this.svg.querySelector('.svg-gauge-value-container');

			if (!container) {
				container = document.createElementNS(this.constructor.SVGNS, 'text');
				container.classList.add('svg-gauge-value-container');
				container.setAttribute('x', '50%');
				this.svg.appendChild(container);

				const value = document.createElementNS(this.constructor.SVGNS, 'tspan');
				value.classList.add('svg-gauge-value');
				value.style.fontSize = this.options.value.size + '%';
				value.style.fontWeight = this.options.value.is_bold ? 'bold' : 'normal';
				value.style.fill = this.options.value.color ? '#' + this.options.value.color : '';

				const units = document.createElementNS(this.constructor.SVGNS, 'tspan');
				units.classList.add('svg-gauge-units');
				units.style.fontSize = this.options.units.size + '%';
				units.style.fontWeight = this.options.units.is_bold ? 'bold' : 'normal';
				units.style.fill = this.options.units.color ? '#' + this.options.units.color : '';

				if (this.options.units.position === this.constructor.UNITS_POSITION_BEFORE) {
					container.appendChild(units);
					container.appendChild(value);
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_AFTER) {
					container.appendChild(value);
					container.appendChild(units);
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_ABOVE) {
					value.setAttribute('x', '50%');
					units.setAttribute('x', '50%');

					container.appendChild(units);
					container.appendChild(value);
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_BELOW) {
					value.setAttribute('x', '50%');
					units.setAttribute('x', '50%');

					container.appendChild(value);
					container.appendChild(units);
				}

				this.elements.value_container = {
					node: container
				};

				this.elements.value = {
					node: value
				};

				this.elements.units = {
					node: units
				};
			}
		}
		else {
			let container = this.svg.querySelector('.svg-gauge-value-container');

			if (!container) {
				container = document.createElementNS(this.constructor.SVGNS, 'text');
				container.classList.add('svg-gauge-value-container');
				container.style.fontSize = this.options.value.size + '%';
				container.style.fontWeight = this.options.value.is_bold ? 'bold' : 'normal';
				container.style.color = this.options.value.color ? '#' + this.options.value.color : '';
				container.setAttribute('x', '50%');
				this.svg.appendChild(container);

				this.elements.value_container = {
					node: container
				};
			}
		}
	}

	#drawMinMax() {
		if (this.options.minmax.show) {
			const minmax = {min: this.options.minmax.min_text, max: this.options.minmax.max_text};

			for (const [key, value] of Object.entries(minmax)) {
				let container = this.svg.querySelector(`.svg-gauge-${key}`);

				if (!container) {
					container = document.createElementNS(this.constructor.SVGNS, 'text');
					container.textContent = value;
					container.classList.add(`svg-gauge-${key}`);
					container.style.fontSize = this.minMaxSize + '%';
					container.style.textAlign = key === 'min' ? 'right' : 'left';
					this.svg.appendChild(container);
				}

				const bbox = container.getBBox();

				this.elements[key] = {
					node: container,
					width: bbox.width,
					height: bbox.height
				};
			}

			// Equalize widths - take the widest
			if (this.elements.min.width > this.elements.max.width) {
				this.elements.max.width = this.elements.min.width;
			}
			else {
				this.elements.min.width = this.elements.max.width;
			}
		}
	}

	#drawNeedle() {
		if (this.options.needle.show) {
			let path = this.svg.querySelector('.svg-gauge-needle');

			if (path) {
				path.setAttribute('transform', `translate(${this.x} ${this.y}) rotate(${this.angleNew}) scale(${this.maxSpace / this.maxSpaceInitial})`);
			}
			else {
				path = document.createElementNS(this.constructor.SVGNS, 'path');
				path.classList.add('svg-gauge-needle');
				this.svg.appendChild(path);

				const pathDefinition = this.#defineNeedle();
				path.setAttribute('d', pathDefinition);

				path.setAttribute('transform', `translate(${this.x} ${this.y})`);

				this.elements.needle = {
					node: path
				};
			}
		}
	}

	#setNeedleColor(angle) {
		if (this.options.needle.color) {
			// Use user chosen color
			this.elements.needle.node.style.fill = '#' + this.options.needle.color;
		}
		else if (this.options.thresholds.data.length) {
			// Use thresholds colors
			const color = this.#getCurrentThresholdColor(angle);
			this.elements.needle.node.style.fill = '#' + color;
		}
	}

	#getCurrentThresholdColor(angle) {
		for (let i = 0; i < this.thresholdsArcParts?.length; i++) {
			if (this.thresholdsArcParts[i].angleStart <= angle && angle <= this.thresholdsArcParts[i].angleEnd) {
				return this.thresholdsArcParts[i].color;
			}
		}

		return '';
	}

	#defineNeedle() {
		// Length of needle
		let length = 0;

		if (this.options.thresholds.arc.show) {
			length = this.radiusThresholdArc - this.thicknessThresholdArc / 2;
		}
		else if (this.options.value.arc.show) {
			length = this.radiusValueArc - this.thicknessValueArc / 2;
		}

		// 0 degrees (needle points to the top) because here is defined only needle's path
		// Needle will be rotated later to the necessary angle
		const angleInRadians = this.#degreesToRadians(0);

		// Coordinates of needle's tip
		const tipX = length * Math.cos(angleInRadians);
		const tipY = length * Math.sin(angleInRadians);

		return [
			'M', this.thicknessNeedle, 0,
			'A', this.thicknessNeedle, this.thicknessNeedle, 0, 0, 1, -this.thicknessNeedle, 0,
			'L', tipX, tipY,
			'Z'
		].join(' ');
	}

	#defineArc(startAngle, endAngle, radius, thickness) {
		const x = 0;
		const y = 0;

		const innerStart = this.#polarToCartesian(x, y, radius - thickness, endAngle);
		const innerEnd = this.#polarToCartesian(x, y, radius - thickness, startAngle);
		const outerStart = this.#polarToCartesian(x, y, radius, endAngle);
		const outerEnd = this.#polarToCartesian(x, y, radius, startAngle);

		const largeArcFlag = endAngle - startAngle <= 180 ? '0' : '1';

		return [
			'M', outerStart.x, outerStart.y,
			'A', radius, radius, 0, largeArcFlag, 0, outerEnd.x, outerEnd.y,
			'L', innerEnd.x, innerEnd.y,
			'A', radius - thickness, radius - thickness, 0, largeArcFlag, 1, innerStart.x, innerStart.y,
			'L', outerStart.x, outerStart.y,
			'Z'
		].join(' ');
	}

	#polarToCartesian(centerX, centerY, radius, angleInDegrees) {
		const angleInRadians = this.#degreesToRadians(angleInDegrees);

		return {
			x: centerX + (radius * Math.cos(angleInRadians)),
			y: centerY + (radius * Math.sin(angleInRadians))
		};
	}

	#degreesToRadians(degrees) {
		return (degrees - 90) * Math.PI / 180;
	}

	#animate(angle, callback) {
		let currentAngle = angle;

		// A step to move in arc (in degrees)
		const step = 1;

		if (this.angleOld < this.angleNew) {
			const angleNext = currentAngle + step;
			if (angleNext <= this.angleNew) {
				currentAngle = angleNext;
			}
			else {
				currentAngle += this.angleNew - currentAngle;
			}
		}
		else {
			const angleNext = currentAngle - step;
			if (angleNext >= this.angleNew) {
				currentAngle = angleNext;
			}
			else {
				currentAngle -= currentAngle - this.angleNew;
			}
		}

		if (callback && typeof callback === 'function') {
			callback(currentAngle);

			if (currentAngle !== this.angleNew) {
				window.requestAnimationFrame(() => {
					this.#animate(currentAngle, callback);
				});
			}
		}
	}

	#getAngle(value, min, max) {
		let angle = this.angleStart;

		if (value) {
			const tempMin = 0;
			const tempMax = max - min;
			const tempValue = value - min;

			if (value < min) {
				angle = this.angleStart;
			}
			else if (value > max) {
				angle = this.angleEnd;
			}
			else {
				if (this.options.angle === 180) {
					angle = ((tempValue * 180) / (tempMax + tempMin)) - 90;
				}
				else {
					angle = ((tempValue * 270) / (tempMax + tempMin)) - 135;
				}
			}
		}

		return angle;
	}

	#positionMinMax() {
		if (this.options.minmax.show) {
			let minX = 0;
			let minY = 0;
			let maxX = 0;
			let maxY = 0;
			let radius = 0;

			if (this.options.thresholds.arc.show) {
				minX = this.elements.threshold_arc_empty.coordinates.x1 - this.elements.min.width;
				minY = this.elements.threshold_arc_empty.coordinates.y4 - this.elements.min.height;
				maxX = this.elements.threshold_arc_empty.coordinates.x2;
				maxY = this.elements.threshold_arc_empty.coordinates.y4 - this.elements.max.height;
				radius = this.radiusThresholdArc;
			}
			else {
				minX = this.elements.value_arc_empty.coordinates.x1 - this.elements.min.width;
				minY = this.elements.value_arc_empty.coordinates.y4 - this.elements.min.height;
				maxX = this.elements.value_arc_empty.coordinates.x2;
				maxY = this.elements.value_arc_empty.coordinates.y4 - this.elements.max.height;
				radius = this.radiusValueArc;
			}

			if (this.options.angle === 270) {
				const angleMin = -135;
				const angleMax = 135;

				const radiansMin = this.#degreesToRadians(angleMin);
				const radiansMax = this.#degreesToRadians(angleMax);

				minX = this.x + (Math.cos(radiansMin) * radius) - this.elements.min.width - this.elements.min.height;
				minY = this.y + (Math.sin(radiansMin) * radius) - this.elements.min.height;
				maxX = this.x + (Math.cos(radiansMax) * radius) + this.elements.max.height;
				maxY = this.y + (Math.sin(radiansMax) * radius) - this.elements.max.height;
			}

			const minXOffset = this.elements.min.width - this.elements.min.node.getComputedTextLength();

			this.elements.min.node.setAttribute('x', minX + minXOffset + 'px');
			this.elements.min.node.setAttribute('y', minY + 'px');
			this.elements.max.node.setAttribute('x', maxX + 'px');
			this.elements.max.node.setAttribute('y', maxY + 'px');
		}
	}

	#positionValue() {
		const container = this.elements.value_container.node;
		const bboxContainer = container.getBBox();

		if (bboxContainer.height) {
			let y = 0;

			if (this.options.thresholds.arc.show) {
				y = this.elements.threshold_arc_empty.coordinates.y3;
			}
			else if (this.options.value.arc.show) {
				y = this.elements.value_arc_empty.coordinates.y3;
			}
			else {
				if (this.options.description.position === this.constructor.DESC_V_POSITION_TOP) {
					y = this.height / 2 + this.elements.description.height / 2;
				}
				else if (this.options.description.position === this.constructor.DESC_V_POSITION_BOTTOM) {
					y = this.height / 2 - this.elements.description.height / 2;
				}
			}

			if (this.options.units.show) {
				const bboxValue = this.elements.value.node.getBBox();
				const bboxUnits = this.elements.units.node.getBBox();

				if (this.options.units.position === this.constructor.UNITS_POSITION_BEFORE
					|| this.options.units.position === this.constructor.UNITS_POSITION_AFTER) {
					if (!this.options.value.arc.show && !this.options.thresholds.arc.show) {
						container.setAttribute('y', y + bboxContainer.height / 2 + 'px');
					}
					else {
						if (this.options.needle.show && this.options.angle === 180) {
							const height = bboxValue.height >= bboxUnits.height ? bboxValue.height : bboxUnits.height;
							y += height + this.thicknessNeedle;
						}

						const offset = 12 * bboxContainer.height / 100;

						container.setAttribute('y', y - offset + 'px');
					}
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_ABOVE) {
					if (!this.options.value.arc.show && !this.options.thresholds.arc.show) {
						container.style.dominantBaseline = 'central';

						this.elements.value.node.setAttribute('y', y + bboxUnits.height / 2 + 'px');
						this.elements.units.node.setAttribute('y', y - bboxValue.height / 2 + 'px');
					}
					else {
						container.style.dominantBaseline = 'text-after-edge';

						if (this.options.needle.show && this.options.angle === 180) {
							y += bboxValue.height + bboxUnits.height + this.thicknessNeedle;
						}

						this.elements.value.node.setAttribute('y', y + 'px');
						this.elements.units.node.setAttribute('y', y - bboxValue.height + 'px');
					}
				}
				else if (this.options.units.position === this.constructor.UNITS_POSITION_BELOW) {
					if (!this.options.value.arc.show && !this.options.thresholds.arc.show) {
						container.style.dominantBaseline = 'central';

						this.elements.value.node.setAttribute('y', y - bboxUnits.height / 2 + 'px');
						this.elements.units.node.setAttribute('y', y + bboxValue.height / 2 + 'px');
					}
					else {
						container.style.dominantBaseline = 'text-after-edge';

						if (this.options.needle.show && this.options.angle === 180) {
							y += bboxValue.height + bboxUnits.height + this.thicknessNeedle;
						}

						this.elements.value.node.setAttribute('y', y - bboxUnits.height + 'px');
						this.elements.units.node.setAttribute('y', y + 'px');
					}
				}
			}
			else {
				if (!this.options.value.arc.show && !this.options.thresholds.arc.show) {
					container.style.dominantBaseline = 'central';
				}
				else {
					container.style.dominantBaseline = 'text-after-edge';

					if (this.options.needle.show && this.options.angle === 180) {
						y += this.thicknessNeedle + bboxContainer.height;
					}
				}

				container.setAttribute('y', y + 'px');
			}

			// Move value element to the bottom of svg tree, so it can be shown on top of arc
			const clone = container;
			container.remove();
			this.svg.appendChild(clone);
		}
	}

	#calculateCoordinatesOfArcContainer(container) {
		const rect = container.node.getBoundingClientRect();

		container.width = rect.width;
		container.height = rect.height;

		let radius = 0;

		if (this.options.thresholds.arc.show) {
			radius = this.radiusThresholdArc;
		}
		else {
			radius = this.radiusValueArc;
		}

		const x = this.x - radius;
		const y = this.y - radius;

		container.coordinates = this.#calcCoordinates(x, y, rect.width, rect.height);
	}

	#calcCoordinates(x, y, width, height) {
		let coordinates = {};

		coordinates.y1 = y;
		coordinates.x1 = x;

		coordinates.x2 = coordinates.x1 + width;
		coordinates.y2 = coordinates.y1;

		coordinates.x3 = coordinates.x1;
		coordinates.y3 = coordinates.y1 + height;

		coordinates.x4 = coordinates.x3 + width;
		coordinates.y4 = coordinates.y3;

		return coordinates;
	}
}
