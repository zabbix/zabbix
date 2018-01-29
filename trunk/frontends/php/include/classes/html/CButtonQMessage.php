<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CButtonQMessage extends CSubmit {

	public $vars;
	public $msg;
	public $name;

	public function __construct($name, $caption, $msg = null, $vars = null) {
		$this->vars = null;
		$this->msg = null;
		$this->name = $name;
		parent::__construct($name, $caption);
		$this->setMessage($msg);
		$this->setVars($vars);
		$this->setAction(null);
	}

	public function setVars($value = null) {
		$this->vars = $value;
		$this->setAction(null);
		return $this;
	}

	public function setMessage($value = null) {
		if (is_null($value)) {
			$value = _('Are you sure you want perform this action?');
		}
		// if message will contain single quotes, it will break everything, so it must be escaped
		$this->msg = zbx_jsvalue(
			$value,
			false, // not as object
			false // do not add quotes to the string
		);
		$this->setAction(null);
		return $this;
	}

	public function setAction($value = null) {
		if (!is_null($value)) {
			parent::onClick($value);
			return $this;
		}

		global $page;
		$confirmation = "Confirm('".$this->msg."')";

		if (isset($this->vars)) {
			$link = $page['file'].'?'.$this->name.'=1'.$this->vars;
			$action = "redirect('".(new CUrl($link))->getUrl()."')";
		}
		else {
			$action = 'true';
		}
		parent::onClick('if ('.$confirmation.') { return '.$action.'; } else { return false; }');
		return $this;
	}
}
