<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CTag extends CObject {

	/**
	 * Encodes the '<', '>', '"' and '&' symbols.
	 */
	const ENC_ALL = 1;

	/**
	 * Encodes all symbols in ENC_ALL except for '&'.
	 */
	const ENC_NOAMP = 2;

	/**
	 * The HTML encoding strategy to use for the contents of the tag.
	 *
	 * @var int
	 */
	protected $encStrategy = self::ENC_NOAMP;

	/**
	 * The HTML encoding strategy for the "value", "name" and "id" attributes.
	 *
	 * @var int
	 */
	protected $attrEncStrategy = self::ENC_ALL;

	public function __construct($tagname, $paired = false, $body = null) {
		parent::__construct();

		$this->attributes = [];
		$this->tagname = $tagname;
		$this->paired = $paired;

		if (!is_null($body)) {
			$this->addItem($body);
		}
	}

	// do not put new line symbol (\n) before or after html tags, it adds spaces in unwanted places
	public function startToString() {
		$res = '<'.$this->tagname;
		foreach ($this->attributes as $key => $value) {
			if ($value === null) {
				continue;
			}

			// a special encoding strategy should be used for the "value", "name" and "id" attributes
			$strategy = in_array($key, ['value', 'name', 'id'], true) ? $this->attrEncStrategy : $this->encStrategy;
			$value = $this->encode($value, $strategy);
			$res .= ' '.$key.'="'.$value.'"';
		}
		$res .= ($this->paired) ? '>' : ' />';

		return $res;
	}

	public function bodyToString() {
		return parent::toString(false);
	}

	public function endToString() {
		$res = ($this->paired) ? '</'.$this->tagname.'>' : '';

		return $res;
	}

	public function toString($destroy = true) {
		$res = $this->startToString();
		$res .= $this->bodyToString();
		$res .= $this->endToString();
		if ($destroy) {
			$this->destroy();
		}

		return $res;
	}

	public function addItem($value) {
		// the string contents of an HTML tag should be properly encoded
		if (is_string($value)) {
			$value = $this->encode($value, $this->getEncStrategy());
		}

		parent::addItem($value);

		return $this;
	}

	public function setName($value) {
		$this->setAttribute('name', $value);

		return $this;
	}

	public function getName() {
		return $this->getAttribute('name');
	}

	public function addClass($class) {
		if (!isset($this->attributes['class']) || zbx_empty($this->attributes['class'])) {
			$this->attributes['class'] = $class;
		}
		else {
			$this->attributes['class'] .= ' '.$class;
		}

		return $this;
	}

	public function getAttribute($name) {
		return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
	}

	public function setAttribute($name, $value) {
		if (is_object($value)) {
			$value = unpack_object($value);
		}
		elseif (is_array($value)) {
			$value = CHtml::serialize($value);
		}
		$this->attributes[$name] = $value;

		return $this;
	}

	public function removeAttribute($name) {
		unset($this->attributes[$name]);

		return $this;
	}

	private function addAction($name, $value) {
		$this->attributes[$name] = $value;
	}

	/**
	 * Adds a hint box to the elemt.
	 *
	 * @param string|array|CTag     $text          hint content
	 * @param string                $spanClass     wrap the content in a span element and assign a this class to the span
	 * @param bool                  $freezeOnCLick if set to true, it will be possible to "freeze" the hint box via a mouse
	 *                                          click
	 *
	 * @return bool
	 */
	public function setHint($text, $spanClass = '', $freezeOnClick = true) {
		if (empty($text)) {
			return;
		}

		encodeValues($text);
		$text = unpack_object($text);

		$this->onMouseover('hintBox.HintWraper(event, this, '.zbx_jsvalue($text).', "'.$spanClass.'");');
		if ($freezeOnClick) {
			$this->onClick('hintBox.showStaticHint(event, this, '.zbx_jsvalue($text).', "'.$spanClass.'");');
		}

		return $this;
	}

	/**
	 * Set data for menu popup.
	 *
	 * @param array $data
	 */
	public function setMenuPopup(array $data) {
		$this->setAttribute('data-menu-popup', $data);

		return $this;
	}

	public function onChange($script) {
		$this->addAction('onchange', $script);

		return $this;
	}

	public function onClick($script) {
		$this->addAction('onclick', $script);

		return $this;
	}

	public function onMouseover($script) {
		$this->addAction('onmouseover', $script);

		return $this;
	}

	public function onMouseout($script) {
		$this->addAction('onmouseout', $script);

		return $this;
	}

	public function addStyle($value) {
		if (!isset($this->attributes['style'])) {
			$this->attributes['style'] = '';
		}
		if (isset($value)) {
			$this->attributes['style'] .= htmlspecialchars(strval($value));
		}
		else {
			unset($this->attributes['style']);
		}

		return $this;
	}

	public function error($value) {
		error('class('.get_class($this).') - '.$value);
		return 1;
	}

	public function getForm($method = 'post', $action = null, $enctype = null) {
		$form = new CForm($method, $action, $enctype);
		$form->addItem($this);

		return $form;
	}

	public function setTitle($value) {
		$this->setAttribute('title', $value);

		return $this;
	}

	public function setId($id) {
		$this->setAttribute('id', $id);

		return $this;
	}

	public function getId() {
		return $this->getAttribute('id');
	}

	/**
	 * Sanitizes a string according to the given strategy before outputting it to the browser.
	 *
	 * @param string	$value
	 * @param int		$strategy
	 *
	 * @return string
	 */
	protected function encode($value, $strategy = self::ENC_NOAMP) {
		if ($strategy == self::ENC_NOAMP) {
			$value = str_replace(['<', '>', '"'], ['&lt;', '&gt;', '&quot;'], $value);
		}
		else {
			$value = CHtml::encode($value);
		}

		return $value;
	}

	/**
	 * @param int $encStrategy
	 */
	public function setEncStrategy($encStrategy) {
		$this->encStrategy = $encStrategy;
	}

	/**
	 * @return int
	 */
	public function getEncStrategy() {
		return $this->encStrategy;
	}
}
