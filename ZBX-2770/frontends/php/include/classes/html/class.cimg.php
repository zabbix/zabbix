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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CImg extends CTag {

	public $preloader;

	public function __construct($src, $name = null, $width = null, $height = null, $class = null) {
		if (is_null($name)) {
			$name = 'image';
		}

		parent::__construct('img', 'no');
		$this->tag_start = '';
		$this->tag_end = '';
		$this->tag_body_start = '';
		$this->tag_body_end = '';
		$this->attr('border', 0);
		$this->setName($name);
		$this->setAltText($name);
		$this->setSrc($src);
		$this->setWidth($width);
		$this->setHeight($height);
		$this->attr('class', $class);
	}

	public function setSrc($value) {
		if (!is_string($value)) {
			return $this->error('Incorrect value for SetSrc "'.$value.'".');
		}
		return $this->attr('src', $value);
	}

	public function setAltText($value = null) {
		if (!is_string($value)) {
			return $this->error('Incorrect value for SetText "'.$value.'".');
		}
		return $this->attr('alt', $value);
	}

	public function setMap($value = null) {
		if (is_null($value)) {
			$this->deleteOption('usemap');
		}
		if (!is_string($value)) {
			return $this->error('Incorrect value for SetMap "'.$value.'".');
		}
		$value = '#'.ltrim($value, '#');
		return $this->attr('usemap', $value);
	}

	public function setWidth($value = null) {
		if (is_null($value)) {
			return $this->removeAttr('width');
		}
		elseif (is_numeric($value) || is_int($value)) {
			return $this->attr('width', $value);
		}
		else {
			return $this->error('Incorrect value for SetWidth "'.$value.'".');
		}
	}

	public function setHeight($value = null) {
		if (is_null($value)) {
			return $this->removeAttr('height');
		}
		elseif (is_numeric($value) || is_int($value)) {
			return $this->attr('height', $value);
		}
		else {
			return $this->error('Incorrect value for SetHeight "'.$value.'".');
		}
	}

	public function preload() {
		$id = $this->getAttr('id');
		if (empty($id)) {
			$id = 'img'.uniqid();
			$this->attr('id', $id);
		}

		insert_js(
			'jQuery("'.$this->toString().'").load(function() {
				var parent = jQuery("#'.$id.'preloader").parent();
				jQuery("#'.$id.'preloader").remove();
				jQuery(parent).append(jQuery(this));
			});',
			true
		);

		$this->addClass('preloader');
		$this->attr('id', $id.'preloader');
		$this->attr('src', 'styles/themes/'.getUserTheme(CWebUser::$data).'/images/preloader.gif');
	}
}
