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


class CButtonQMessage extends CSubmit {

	public $vars;
	public $msg;
	public $name;

	/**
	 * @var string  An URL parameter to be left in URL after redirect.
	 */
	private $url_param_exclude;

	public function __construct($name, $caption, $msg = null, $vars = null, $url_param_exclude = '') {
		$this->vars = null;
		$this->msg = null;
		$this->name = $name;
		$this->url_param_exclude = $url_param_exclude;
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
			$value = _('Are you sure you want to perform this action?');
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
			$action = "redirect('".(new CUrl($link))->getUrl()."', 'post'".
				(($this->url_param_exclude !== '') ? ", '".$this->url_param_exclude."'" : "").
			")";
		}
		else {
			$action = 'true';
		}

		parent::onClick('if ('.$confirmation.') { return '.$action.'; } else { return false; }');

		return $this;
	}
}
