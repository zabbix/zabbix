<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
	 * The HTML encoding strategy for the "value" attribute.
	 *
	 * @var int
	 */
	protected $valueEncStrategy = self::ENC_ALL;


	public function __construct($tagname, $paired = 'no', $body = null, $class = null) {
		parent::__construct();
		$this->attributes = array();

		if (!is_string($tagname)) {
			return $this->error('Incorrect tagname for CTag "'.$tagname.'".');
		}

		$this->tagname = $tagname;
		$this->paired = $paired;
		$this->tag_start = $this->tag_end = $this->tag_body_start = $this->tag_body_end = '';

		if (is_null($body)) {
			$this->tag_end = $this->tag_body_start = '';
		}
		else {
			$this->addItem($body);
		}
		$this->addClass($class);
	}

	public function showStart() {
		echo $this->startToString();
	}

	public function showBody() {
		echo $this->bodyToString();
	}

	public function showEnd() {
		echo $this->endToString();
	}

	// do not put new line symbol (\n) before or after html tags, it adds spaces in unwanted places
	public function startToString() {
		$res = $this->tag_start.'<'.$this->tagname;

		foreach ($this->attributes as $key => $value) {
			// if value is null render boolean attribute without value
			if ($value === null) {
				$res .= ' '.$key;
			}
			else {
				// a special encoding strategy should be used for the "value" attribute
				$value = $this->encode($value, ($key == 'value') ? $this->valueEncStrategy : self::ENC_NOAMP);
				$res .= ' '.$key.'="'.$value.'"';
			}
		}

		$res .= ($this->paired === 'yes') ? '>' : ' />';

		return $res;
	}

	public function bodyToString() {
		return $this->tag_body_start.parent::toString(false);
	}

	public function endToString() {
		$res = ($this->paired === 'yes') ? $this->tag_body_end.'</'.$this->tagname.'>' : '';
		$res .= $this->tag_end;

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
	}

	public function setName($value) {
		if (is_null($value)) {
			return $value;
		}
		if (!is_string($value)) {
			return $this->error('Incorrect value for SetName "'.$value.'".');
		}

		return $this->attr('name', $value);
	}

	public function getName() {
		if (isset($this->attributes['name'])) {
			return $this->attributes['name'];
		}

		return null;
	}

	public function addClass($cssClass) {
		if (!isset($this->attributes['class']) || zbx_empty($this->attributes['class'])) {
			$this->attributes['class'] = $cssClass;
		}
		else {
			$this->attributes['class'] .= ' '.$cssClass;
		}

		return $this->attributes['class'];
	}

	public function getAttr($name) {
		return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
	}

	/**
	 * Set HTML attribut.
	 * If attribute is boolean attribute value is ignored.
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function attr($name, $value = null) {
		if ($this->isBooleanAttribute($name)) {
			$this->attributes[$name] = null;
		}
		elseif (is_null($value)) {
			$this->removeAttr($name);
		}
		else {
			$this->attributes[$name] = $value;
		}
	}

	/**
	 * Sets multiple HTML attributes.
	 *
	 * @param array $attributes defined as array(attributeName1 => value1, attributeName2 => value2, ...)
	 */
	public function attrs(array $attributes) {
		foreach ($attributes as $name => $value) {
			$this->attr($name, $value);
		}
	}

	/**
	 * Set data attribute.
	 * attribute name is prefixed with 'data-' and value is serialized if it contains array.
	 *
	 * @param string $name
	 * @param string|array $value
	 */
	public function dataAttr($name, $value) {
		if (is_array($value)) {
			$value = CHtml::serialize($value);
		}
		$this->attr('data-'.$name, $value);
	}

	/**
	 * Set array as data attributes.
	 *
	 * @param array $data
	 */
	public function dataAttrs(array $data) {
		foreach ($data as $key => $value) {
			$this->dataAttr($key, $value);
		}
	}

	/**
	 * Remove HTML attribute.
	 *
	 * @param $name
	 */
	public function removeAttr($name) {
		unset($this->attributes[$name]);
	}

	public function onClick($handle_code) {
		$this->attributes['onclick'] = $handle_code;
	}

	public function onMouseOver($handle_code) {
		$this->attributes['onmouseover'] = $handle_code;
	}

	public function onMouseOut($handle_code) {
		$this->attributes['onmouseout'] = $handle_code;
	}

	public function onChange($handle_code) {
		$this->attributes['onchange'] = $handle_code;
	}

	public function onFocus($handle_code) {
		$this->attributes['onfocus'] = $handle_code;
	}

	public function addStyle($value) {
		if (!empty($value)) {
			if (!isset($this->attributes['style'])) {
				$this->attributes['style'] = '';
			}
			$this->attributes['style'] .= htmlspecialchars(strval($value));
		}
	}

	public function setEnabled($value = true) {
		if ($value) {
			$this->removeAttr('disabled');
		}
		else {
			$this->attr('disabled', true);
		}
	}

	public function error($value) {
		error('class('.get_class($this).') - '.$value);
		return 1;
	}

	public function setTitle($value = 'title') {
		$this->attr('title', $value);
	}

	public function setHint($text, $width = '', $class = '', $byClick = true) {
		if (empty($text)) {
			return false;
		}

		encodeValues($text);
		$text = unpack_object($text);

		$this->onMouseOver('hintBox.HintWraper(event, this, '.zbx_jsvalue($text).', "'.$width.'", "'.$class.'");');
		if ($byClick) {
			$this->onClick('hintBox.showStaticHint(event, this, '.zbx_jsvalue($text).', "'.$width.'", "'.$class.'");');
		}

		return true;
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
			$value = str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $value);
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

	protected function isBooleanAttribute($attr) {
		$booleanAttrs = array(
			'checked' => 1,
			'disabled' => 1,
			'autoplay' => 1,
			'async' => 1,
			'autofocus' => 1,
			'controls' => 1,
			'default' => 1,
			'defer' => 1,
			'formnovalidate' => 1,
			'hidden' => 1,
			'ismap' => 1,
			'itemscope' => 1,
			'loop' => 1,
			'multiple' => 1,
			'novalidate' => 1,
			'open' => 1,
			'pubdate' => 1,
			'readonly' => 1,
			'required' => 1,
			'reversed' => 1,
			'scoped' => 1,
			'seamless' => 1,
			'selected' => 1
		);

		return isset($booleanAttrs[$attr]);
	}
}
