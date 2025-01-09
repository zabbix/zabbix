<?php
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


class CTag extends CObject {

	/**
	 * The list of tag attributes.
	 *
	 * @var array
	 */
	protected $attributes = [];

	/**
	 * The name of the tag.
	 *
	 * @var string
	 */
	protected $tagname = '';

	/**
	 * The type of tag.
	 *
	 * @var bool
	 */
	private $paired = false;

	public function __construct(string $tagname, bool $paired = false, $body = null) {
		parent::__construct();

		$this->tagname = $tagname;
		$this->paired = $paired;

		if (!is_null($body)) {
			$this->addItem($body);
		}
	}

	protected function startToString() {
		$attributes = '';

		foreach ($this->attributes as $key => $value) {
			if ($value !== null) {
				$attributes .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
			}
		}

		return '<'.$this->tagname.$attributes.'>';
	}

	protected function bodyToString() {
		return parent::toString(false);
	}

	protected function endToString() {
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
		if (is_string($value)) {
			$value = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
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
		if ($class !== null) {
			if (!array_key_exists('class', $this->attributes) || $this->attributes['class'] === '') {
				$this->attributes['class'] = $class;
			}
			else {
				$this->attributes['class'] .= ' '.$class;
			}
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
			$value = json_encode($value);
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
		return $this;
	}

	/**
	 * Adds a hint box to the element.
	 *
	 * @param string|array|CTag		$text				Hint content.
	 * @param string				$span_class			Wrap the content in a span element and assign this class
	 *													to the span.
	 * @param bool					$freeze_on_click	If set to true, it will be possible to "freeze" the hint box
	 *													via a mouse click.
	 * @param string				$styles				Custom css styles.
	 *													Syntax:
	 *														property1: value1; property2: value2; property(n): value(n)
	 * @param int					$delay				Delay in milliseconds before showing hintbox.
	 * @return CTag
	 */
	public function setHint($text, $span_class = '', $freeze_on_click = true, $styles = '', $delay = null) {
		$this->setAttribute('data-hintbox-contents', (new CTag('', false, $text))->bodyToString());

		$this->setAttribute('data-hintbox', '1');

		if ($span_class !== '') {
			$this->setAttribute('data-hintbox-class', $span_class);
		}

		if ($styles !== '') {
			$this->setAttribute('data-hintbox-style', $styles);
		}

		if ($freeze_on_click) {
			$this->setAttribute('data-hintbox-static', '1');
		}

		if ($delay !== null) {
			$this->setAttribute('data-hintbox-delay', $delay);
		}

		return $this;
	}

	/**
	 * Add a hint box with preloader to the element.
	 *
	 * @param array  $data
	 * @param string $span_class
	 * @param bool   $freeze_on_click
	 * @param string $styles
	 *
	 * @return $this
	 */
	public function setAjaxHint(array $data, string $span_class = '', bool $freeze_on_click = true,
			string $styles = ''): CTag {
		$this
			->setAttribute('data-hintbox-preload', $data)
			->setHint('', $span_class, $freeze_on_click, $styles);

		return $this;
	}

	/**
	 * Set data for menu popup.
	 *
	 * @param array $data
	 */
	public function setMenuPopup(array $data) {
		$this->setAttribute('data-menu-popup', $data);
		$this->setAttribute('aria-expanded', 'false');

		if (!$this->getAttribute('disabled')) {
			$this->setAttribute('aria-haspopup', 'true');
		}

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

	public function onKeyup($script) {
		$this->addAction('onkeyup', $script);
		return $this;
	}

	public function addStyle($value) {
		if (!isset($this->attributes['style'])) {
			$this->attributes['style'] = '';
		}
		if (isset($value)) {
			$this->attributes['style'] .= $value;
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
	 * Remove ID attribute from tag.
	 *
	 * @return CTag
	 */
	public function removeId() {
		$this->removeAttribute('id');

		return $this;
	}

	/**
	 * Set or reset element 'aria-required' attribute.
	 *
	 * @param bool $is_required  Define aria-required attribute for element.
	 *
	 * @return $this
	 */
	public function setAriaRequired($is_required = true) {
		if ($is_required) {
			$this->setAttribute('aria-required', 'true');
		}
		else {
			$this->removeAttribute('aria-required');
		}

		return $this;
	}
}
