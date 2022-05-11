<?php
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
 * Class for inline script execution within a jQuery context.
 */
class CScriptTag extends CTag {

	/**
	 * Do run scripts as soon as the document is ready.
	 *
	 * @var boolean
	 */
	private $on_document_ready = false;

	/**
	 * Create a <script> tag.
	 *
	 * @param string $script  JavaScript code
	 */
	public function __construct($script = null) {
		parent::__construct('script', true, $script);
	}

	public function addItem($value) {
		if (is_array($value)) {
			foreach ($value as $item) {
				$this->addItem($item);
			}
		}
		else {
			parent::addItem(new CObject($value));
		}

		return $this;
	}

	protected function bodyToString() {
		$script = implode("\n", $this->items);

		if ($this->on_document_ready) {
			$script = self::wrapOnDocumentReady($script);
		}
		else {
			$script = self::wrapImmediate($script);
		}

		return $script;
	}

	/**
	 * Make scripts run as soon as the document is ready.
	 *
	 * @param bool $state
	 *
	 * @return CScriptTag
	 */
	public function setOnDocumentReady($state = true) {
		$this->on_document_ready = $state;

		return $this;
	}

	/**
	 * Ensure script execution in a jQuery context, immediately.
	 *
	 * @param string $script
	 *
	 * @return string
	 */
	private static function wrapImmediate($script) {
		return '(function($){'.$script.'})(jQuery);';
	}

	/**
	 * Ensure script execution in a jQuery context, as soon as the document is ready.
	 *
	 * @param string $script
	 *
	 * @return string
	 */
	private static function wrapOnDocumentReady($script) {
		return 'jQuery(function($){'.$script.'});';
	}
}
