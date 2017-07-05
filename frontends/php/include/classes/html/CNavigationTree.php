<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CNavigationTree extends CDiv {
		private $error;
		private $script_file;
		private $script_run;
		private $data;

		public function __construct(array $data = []) {
			parent::__construct();

			$this->data = $data;
			$this->error = null;
			$this->script_file = 'js/class.cnavtree.js';
			$this->script_run = '';

			$this->setId(uniqid());
			$this->addClass(ZBX_STYLE_NAVIGATIONTREE);
		}

		public function setError($value) {
			$this->error = $value;
			return $this;
		}

		public function getScriptFile() {
			return $this->script_file;
		}

		public function getScriptRun() {
			if ($this->error === null) {
				$this->script_run .= ''.
					'jQuery("#'.$this->getId().'").zbx_navtree({'.
						'problems: '.json_encode($this->data['problems']).','.
						'severity_levels: '.json_encode($this->data['severity_config']).','.
						'navtree_items_opened: "'.implode(',', $this->data['navtree_items_opened']).'",'.
						'navtree_item_selected: '.intval($this->data['navtree_item_selected']).','.
						'initial_load: '.$this->data['initial_load'].','.
						'uniqueid: "'.$this->data['uniqueid'].'",'.
						'max_depth: '.WIDGET_NAVIGATION_TREE_MAX_DEPTH.
					'});';
			}

			return $this->script_run;
		}

		private function build() {
			if ($this->error !== null) {
				$span->addClass(ZBX_STYLE_DISABLED);
			}

			$this->addItem((new CDiv())->addClass('tree'));
		}

		public function toString($destroy = true) {
			$this->build();

			return parent::toString($destroy);
		}
}
