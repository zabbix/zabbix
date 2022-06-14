/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * Timeout controlled player.
 *
 * It plays, meanwhile decrementing timeout. Pausing and playing is done by control of 'volume' and 'muted' properties.
 * It holds infinite loop, so it allows us easily adjust timeout during playback.
 */
function ZBX_NotificationsAudio() {
	try {
		this.audio = new Audio();

		this.audio.volume = 0;
		this.audio.muted = true;
		this.audio.autoplay = true;
		this.audio.loop = true;

		this.audio.onloadeddata = this.handleOnloadeddata.bind(this);

		this.audio.load();
	}
	catch(e) {
		console.warn('Cannot support notification audio for this device.');
	}

	this.wave = '';
	this.ms_timeout = 0;
	this.is_playing = false;
	this.message_timeout = 0;
	this.callback = null;

	this.resetPromise();
	this.listen();
}

/**
 * Starts main loop.
 *
 * @return int  Interval ID.
 */
ZBX_NotificationsAudio.prototype.listen = function() {
	var ms_step = 10;

	if (!this.audio) {
		return;
	}

	function resolveAudioState() {
		if (this.play_once_on_ready) {
			return this.once();
		}

		this.ms_timeout -= ms_step;
		this.is_playing = (this.ms_timeout > 0.0001);
		this.audio.volume = this.is_playing ? 1 : 0;

		if (this.ms_timeout < 0.0001) {
			this._resolve_timeout(this);
			this.ms_timeout = 0;
			this.seek(0);

			if (this.callback !== null) {
				this.callback();
				this.callback = null;
			}
		}
	}

	resolveAudioState.call(this);

	return setInterval(resolveAudioState.bind(this), ms_step);
};

/**
 * File is applied only if it is different than on instate, so this method may be called repeatedly, and will not
 * interrupt playback.
 *
 * @param {string} file  Audio file path relative to DOCUMENT_ROOT/audio/ directory.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.file = function(file) {
	if (!this.audio) {
		return this;
	}

	if (this.wave == file) {
		return this;
	}

	this.wave = file;
	this.seek(0);

	if (!this.wave) {
		this.audio.removeAttribute('src');
	}
	else {
		this.audio.src = 'audio/' + this.wave;
	}

	return this;
};

/**
 * Sets player seek position. There are no safety checks, if one decides to seek out of audio file bounds - no audio.
 *
 * @param {number} seconds
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.seek = function(seconds) {
	if (!this.audio) {
		return this;
	}

	if (this.audio.readyState > 0) {
		this.audio.currentTime = seconds;
	}

	return this;
};

/**
 * Once file duration is known, this method seeks player to the beginning and sets timeout equal to file duration.
 *
 * @return {Promise}
 */
ZBX_NotificationsAudio.prototype.once = function() {
	if (!this.audio) {
		return this.resetPromise();
	}

	if (this.play_once_on_ready && this.audio.readyState >= 3) {
		this.play_once_on_ready = false;

		var timeout = (this.message_timeout == 0)
			? this.audio.duration
			: Math.min(this.message_timeout, this.audio.duration);

		return this.timeout(timeout);
	}

	this.play_once_on_ready = true;

	return this.resetPromise();
};

/**
 * An alias method. Player is stopped by exhausting timeout.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.stop = function() {
	this.ms_timeout = 0;
	this.is_playing = false;

	return this;
};

/**
 * Mute player.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.mute = function() {
	if (!this.audio) {
		return this;
	}

	this.audio.muted = true;

	return this;
};

/**
 * Unmute player.
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.unmute = function() {
	if (!this.audio) {
		return this;
	}

	this.audio.muted = false;

	return this;
};

/**
 * Tune player.
 *
 * @argument {object} options
 * @argument {bool}   options[playOnce]        Player will not play in the loop if set to true.
 * @argument {number} options[messageTimeout]  Message display timeout. Used to avoid playing when message box is gone.
 * @argument {mixed}  options[callback]
 *
 * @return {ZBX_NotificationsAudio}
 */
ZBX_NotificationsAudio.prototype.tune = function(options) {
	if (!this.audio) {
		return this;
	}

	if (typeof options.playOnce === 'boolean') {
		this.audio.loop = !options.playOnce;
	}

	if (typeof options.messageTimeout === 'number') {
		this.message_timeout = options.messageTimeout;
	}

	if (typeof options.callback !== 'undefined') {
		this.callback = options.callback;
	}

	return this;
};

/**
 * Assigns new promise property in place, any pending promise will not be resolved.
 *
 * @return {Promise}
 */
ZBX_NotificationsAudio.prototype.resetPromise = function() {
	this.timeout_promise = new Promise(function(resolve, reject) {
		this._resolve_timeout = resolve;
	}.bind(this));

	return this.timeout_promise;
};

/**
 * Will play in loop for seconds given, since this call. If "0" given - will just not play. If "-1" is given - file will
 * be played once.
 *
 * @param {number} seconds
 *
 * @return {Promise}
 */
ZBX_NotificationsAudio.prototype.timeout = function(seconds) {
	if (!this.audio) {
		return this.resetPromise();
	}

	if (this.message_timeout == 0) {
		this.stop();
		return this.resetPromise();
	}

	if (!this.audio.loop) {
		if (seconds == ZBX_Notifications.ALARM_ONCE_PLAYER) {
			return this.once();
		}
		else if (this.is_playing) {
			return this.timeout_promise;
		}
		else {
			this.audio.load();
		}
	}

	this.ms_timeout = seconds * 1000;

	return this.resetPromise();
};

/**
 * Get current player seek position.
 *
 * @return {float}  Amount of seconds.
 */
ZBX_NotificationsAudio.prototype.getSeek = function() {
	if (!this.audio) {
		return 0;
	}

	return this.audio.currentTime;
};

/**
 * Get the time player will play for.
 *
 * @return {float}  Amount of seconds.
 */
ZBX_NotificationsAudio.prototype.getTimeout = function() {
	return this.ms_timeout / 1000;
};

/**
 * This handler will be invoked once audio file has successfully pre-loaded. Attempt to auto play and see, if auto play
 * policy error occurs.
 */
ZBX_NotificationsAudio.prototype.handleOnloadeddata = function() {
	if (!this.audio) {
		return;
	}

	var promise = this.audio.play();

	// Internet explorer does not return promise.
	if (typeof promise === 'undefined') {
		return;
	}

	promise.catch(function(error) {
		if (error.name === 'NotAllowedError' && this.audio.paused) {
			console.warn(error.message);
			console.warn(
				'Zabbix was not able to play audio due to "Autoplay policy". Please see manual for more information.'
			);
		}
	}.bind(this));
};
