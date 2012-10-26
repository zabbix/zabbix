<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class CUploadFile {

	protected $name;
	protected $type;
	protected $tmp_name;
	protected $error;
	protected $size;

	/**
	 * Get upload_max_filesize value in bytes defined in php.ini.
	 * If value is not defined in php.ini, returns default 2M.
	 *
	 * @static
	 *
	 * @return int
	 */
	static public function getMaxUploadSize() {
		$maxSize = trim(ini_get('upload_max_filesize'));

		if ($maxSize === '') {
			$maxSize = '2m'; // PHP default value
		}

		return str2mem($maxSize);
	}

	/**
	 * Accepts file array from $_FILES global variable.
	 *
	 * @throws Exception if file_uploads are disabled
	 *
	 * @param $file
	 */
	public function __construct($file) {
		if (!ini_get('file_uploads')) {
			throw new Exception(_('Unable to uploade file because "file_uploads" is disabled.'));
		}

		$this->name = $file['name'];
		$this->type = $file['type'];
		$this->tmp_name = $file['tmp_name'];
		$this->error = $file['error'];
		$this->size = $file['size'];
	}

	/**
	 * Get content of uploaded file.
	 *
	 * @throws Exception
	 * @return string
	 */
	public function getContent() {
		if (!$this->isValid()) {
			throw new Exception($this->getErrorMessage());
		}

		return file_get_contents($this->tmp_name);
	}

	/**
	 * Get extension of uploaded file.
	 *
	 * @throws Exception
	 * @return string
	 */
	public function getExtension() {
		if (!$this->isValid()) {
			throw new Exception($this->getErrorMessage());
		}

		return pathinfo($this->name, PATHINFO_EXTENSION);
	}

	/**
	 * Get size in bytes of uploaded file.
	 *
	 * @return int
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * Get error.
	 *
	 * @return int
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Returns true if file was uploaded without errors.
	 *
	 * @return bool
	 */
	public function isValid() {
		return ($this->error == UPLOAD_ERR_OK);
	}

	/**
	 * Validate image size.
	 *
	 * @throws Exception if image size is greater than 1MB.
	 */
	public function validateImageSize() {
		if ($this->size > ZBX_MAX_IMAGE_SIZE) {
			throw new Exception(_('Image size must be less than 1MB.'));
		}
	}

	/**
	 * Return true if file wos uploaded, maybe with errors.
	 *
	 * @return bool
	 */
	public function wasUploaded() {
		return ($this->error != UPLOAD_ERR_NO_FILE);
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	protected function getErrorMessage() {
		switch ($this->error) {
			case UPLOAD_ERR_OK:
				return '';

			case UPLOAD_ERR_INI_SIZE:
				return _s('File is too big, max upload size is %1$s bytes.', self::getMaxUploadSize());

			case UPLOAD_ERR_NO_FILE:
				return _('No file was uploaded.');

			default:
				return _('Incorrect file upload.');
		}
	}
}
