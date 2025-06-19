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
 * ImageCache class.
 *
 * Implements basic functionality needed to preload images, get image attributes and avoid flickering.
 */
class ImageCache {
	constructor() {
		this.lock = 0;
		this.images = {};
		this.context = null;
		this.callback = null;
		this.queue = [];
	}

	/**
	 * Invoke callback (if any), update image preload task queue.
	 */
	#invokeCallback() {
		if (typeof this.callback === 'function') {
			this.callback.call(this.context);
		}

		// Preloads next image list if any.
		const task = this.queue.pop();

		if (task !== undefined) {
			this.preload(task.urls, task.callback, task.context);
		}
	}

	/**
	 * Handle image processing event (loaded or error).
	 */
	#handleCallback() {
		this.lock--;

		// If all images are loaded (error is treated as "loaded"), invoke callback.
		if (this.lock == 0) {
			this.#invokeCallback();
		}
	}

	/**
	 * Callback for successful image load.
	 *
	 * @param {string} id     Image ID.
	 * @param {object} image  Loaded image.
	 */
	#onImageLoaded(id, image) {
		this.images[id] = image;
		this.#handleCallback();
	}

	/**
	 * Callback for image loading errors.
	 *
	 * @param {string} id  Image ID.
	 */
	#onImageError(id) {
		this.images[id] = null;
		this.#handleCallback();
	}

	/**
	 * Preload images.
	 *
	 * @param {object}   urls      Urls of images to be preloaded (urls are provided in key=>value format).
	 * @param {function} callback  Callback to be called when loading is finished. Can be null if no callback is needed.
	 * @param {object}   context   Context of a callback. (@see first argument of Function.prototype.apply)
	 *
	 * @return {boolean}           True if preloader started loading images and false if preloader is busy.
	 */
	preload(urls, callback, context) {
		// If preloader is busy, new preloading task is pushed to queue.
		if (this.lock != 0) {
			this.queue.push({urls, callback, context});

			return false;
		}

		this.context = context;
		this.callback = callback;

		let images = 0;

		Object.keys(urls).forEach((key) => {
			const url = urls[key];

			if (typeof url !== 'string') {
				this.#onImageError.call(this, key);

				return;
			}

			if (this.images[key] !== undefined) {
				// Image is pre-loaded already.
				return true;
			}

			const image = new Image();

			image.onload = () => this.#onImageLoaded.call(this, key, image);
			image.onerror = () => this.#onImageError.call(this, key);
			image.src = url;

			this.lock++;
			images++;
		});

		if (images == 0) {
			this.#invokeCallback();
		}

		return true;
	}
}
