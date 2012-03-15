<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
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


	public function __construct($tagname = null, $paired = 'no', $body = null, $class = null) {
		parent::__construct();

		$this->attributes = array();

		if (!is_string($tagname)) {
			return $this->error('Incorrect tagname for CTag ['.$tagname.']');
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

		$this->setClass($class);
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

	// Do not put new line symbol (\n) before or after html tags,
	// it adds spaces in unwanted places
	public function startToString() {
		$res = $this->tag_start.'<'.$this->tagname;

		foreach ($this->attributes as $key => $value) {
			// a special encoding strategy should be used for the "value" attribute
			$value = $this->encode($value, ($key == 'value') ? $this->valueEncStrategy : self::ENC_NOAMP);
			$res .= ' '.$key.'="'.$value.'"';
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
		if (is_null($value)) return $value;

		if (!is_string($value)) {
			return $this->error("Incorrect value for SetName [$value]");
		}
		return $this->setAttribute("name", $value);
	}

	public function getName() {
		return (isset($this->attributes['name'])) ? $this->attributes['name'] : null;
	}

	public function setClass($value) {
		if (isset($value)) {
			$this->attributes['class'] = $value;
		}
		else {
			unset($this->attributes['class']);
		}

		return $value;
	}

	public function getAttribute($name) {
		$ret = null;
		if (isset($this->attributes[$name])) {
			$ret = $this->attributes[$name];
		}

		return $ret;
	}

	public function setAttribute($name, $value) {
		if (is_object($value)) {
			$this->attributes[$name] = unpack_object($value);
		}
		else if (isset($value)) {
			$this->attributes[$name] = $value;
		}
		else {
			unset($this->attributes[$name]);
		}
	}

	public function removeAttribute($name){
		unset($this->attributes[$name]);
	}

	public function addAction($name, $value) {
		// strip new lines from scripts
		$value = str_replace(array("\n", "\r"), '', $value);

		$this->setAttribute($name, $value);
	}

	public function setHint($text, $width = '', $class = '', $byclick = true) {
		if (empty($text)) {
			return false;
		}

		$text = unpack_object($text);

		$this->addAction('onmouseover', "javascript: hintBox.showOver(event,this,".zbx_jsvalue($text).",'".$width."','".$class."');");
		$this->addAction('onmouseout', "javascript: hintBox.hideOut(event,this);");
		if ($byclick) {
			$this->addAction('onclick', "javascript: hintBox.onClick(event,this,".zbx_jsvalue($text).",'".$width."','".$class."');");
		}
	}

	public function onClick($handle_code) {
		$this->addAction('onclick', $handle_code);
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
	}

	public function setEnabled($value = 'yes') {
		if ((is_string($value) && ($value == 'yes' || $value == 'enabled' || $value == 'on') || $value == '1') || (is_int($value) && $value != 0)) {
			unset($this->attributes['disabled']);
		}
		else if ((is_string($value) && ($value == 'no' || $value == 'disabled' || $value == 'off') || $value == '0') || (is_int($value) && $value == 0)) {
			$this->attributes['disabled'] = 'disabled';
		}
	}

	public function error($value) {
		error('class('.get_class($this).') - '.$value);
		return 1;
	}

	/**
	 * Sanitizes a string according to the given strategy before outputting it to the browser.
	 *
	 * @param string $value
	 * @param int $strategy
	 *
	 * @return string
	 */
	protected function encode($value, $strategy = self::ENC_NOAMP) {
		return ($strategy == self::ENC_NOAMP) ? zbx_htmlstr($value) : htmlspecialchars($value);
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
?>
