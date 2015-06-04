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


/**
 * A class for rendering HTML trees.
 */
class CTree {

	public $tree;
	public $fields;
	public $treename;
	private $size;
	private $maxlevel;

	public function __construct($treename, $value = [], $fields = []) {
		$this->maxlevel = 0;
		$this->tree = $value;
		$this->fields = $fields;
		$this->treename = $treename;
		$this->size = count($value);
		unset($value, $fields);

		if (!$this->checkTree()) {
			$this->destroy();
			return false;
		}
		else {
			$this->countDepth();
		}
	}

	public function getTree() {
		return $this->tree;
	}

	public function getHTML() {
		$html[] = $this->createJS();
		$html[] = $this->simpleHTML();
		return $html;
	}

	private function makeHeaders() {
		$headers = array_values($this->fields);

		$keys = array_keys($this->fields);
		array_shift($keys);
		$this->fields = $keys;

		return $headers;
	}

	private function simpleHTML() {
		$table = new CTableInfo();
		$table->setHeader($this->makeHeaders());

		foreach ($this->tree as $id => $rows) {
			$table->addRow($this->makeRow($id));
		}
		return $table;
	}

	private function makeRow($id) {
		$td = $this->makeCell($id);

		$tr = new CRow();
		$tr->addItem($td);
		$tr->setAttribute('id', 'id_'.$id);
		$tr->setAttribute('style', $this->tree[$id]['parentid'] != '0' ? 'display: none;' : '');

		foreach ($this->fields as $value) {
			$tr->addItem($this->makeCol($id, $value));
		}
		return $tr;
	}

	/**
	 * Returns a column object for the given row and field.
	 *
	 * @param $rowId
	 * @param $colName
	 *
	 * @return CCol
	 */
	protected function makeCol($rowId, $colName) {
		return new CCol($this->tree[$rowId][$colName]);
	}

	private function makeCell($id) {
		$td = new CCol();
		$level = max(1, $this->tree[$id]['Level']);
		$td->setAttribute('style', 'padding-left:'. (2 * $level) .'em;');

		if ($id != 0 && array_key_exists('childnodes', $this->tree[$id])) {
			$div = new CDiv(new CSpan(null, 'arrow-right'), 'treeview');
			$div->setAttribute('onclick', $this->treename.'.closeSNodeX("'.$id.'", this.getElementsByTagName(\'span\')[0]);');
			$div->setAttribute('id', 'idi_'.$id);
			$td->addItem($div);
		}

		$td->addItem($this->tree[$id]['caption']);

		return $td;
	}

	private function countDepth() {
		foreach ($this->tree as $id => $rows) {
			if ($rows['id'] == 0) {
				$this->tree[$id]['Level'] = 0;
				continue;
			}
			$parentid = $this->tree[$id]['parentid'];

			$this->tree[$parentid]['nodetype'] = 2;
			$this->tree[$id]['Level'] = isset($this->tree[$parentid]['Level']) ? $this->tree[$parentid]['Level'] + 1 : 1;

			$this->maxlevel>$this->tree[$id]['Level'] ? '' : $this->maxlevel = $this->tree[$id]['Level'];
		}
	}

	public function createJS() {
		$js = '<script src="js/class.ctree.js" type="text/javascript"></script>'."\n".
				'<script type="text/javascript"> var '.$this->treename.'_tree = {};';

		foreach ($this->tree as $id => $rows) {
			$parentid = $rows['parentid'];
			$this->tree[$parentid]['nodelist'] .= $id.',';
		}

		foreach ($this->tree as $id => $rows) {
			if ($rows['nodetype'] == '2') {
				$rows['nodelist'] = rtrim($rows['nodelist'], ',');
				$js .= $this->treename.'_tree[\''.$id.'\'] = { status: \'close\', nodelist : \''.$rows['nodelist'].'\', parentid : \''.$rows['parentid'].'\'};';
				$js .= "\n";
			}
		}

		$js.= 'var '.$this->treename.' = null';
		$js.= '</script>'."\n";

		zbx_add_post_js($this->treename.' = new CTree("tree_'.CWebUser::$data['alias'].'_'.$this->treename.'", '.$this->treename.'_tree);');

		return new CJsScript($js);
	}

	private function checkTree() {
		if (!is_array($this->tree)) {
			return false;
		}
		foreach ($this->tree as $id => $cell) {
			$this->tree[$id]['nodetype'] = 0;

			$parentid = $cell['parentid'];
			$this->tree[$parentid]['childnodes'][] = $id;
			$this->tree[$id]['nodelist'] = '';
		}
		return true;
	}

	private function destroy() {
		unset($this->tree);
	}
}
