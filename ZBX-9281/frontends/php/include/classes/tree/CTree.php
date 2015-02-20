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
?>
<?php

/**
 * A class for rendering HTML trees.
 */
class CTree {

	public $tree;
	public $fields;
	public $treename;
	private $size;
	private $maxlevel;

	public function __construct($treename, $value = array(), $fields = array()) {
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
		$c = 0;
		$tr = new CRow($this->fields['caption'], 'header');
		unset($this->fields['caption']);

		foreach ($this->fields as $id => $caption) {
			$tr->addItem($caption);
			$fields[$c] = $id;
			$c++;
		}
		$this->fields = $fields;
		return $tr;
	}

	private function simpleHTML() {
		$table = new CTableInfo();
		$table->addRow($this->makeHeaders());

		foreach ($this->tree as $id => $rows) {
			$table->addRow($this->makeRow($id));
		}
		return $table;
	}

	private function makeRow($id) {
		$table = new CTable();
		$tr = $this->makeSImgStr($id);
		$tr->addItem($this->tree[$id]['caption']);

		$table->addRow($tr);

		$tr = new CRow();
		$tr->addItem($table);
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

	private function makeSImgStr($id) {
		$tr = new CRow();

		$count = isset($this->tree[$id]['nodeimg']) ? zbx_strlen($this->tree[$id]['nodeimg']) : 0;
		for ($i = 0; $i < $count; $i++) {
			$td = new CCol();
			switch ($this->tree[$id]['nodeimg'][$i]) {
				case 'O':
					$td->setAttribute('style', 'width: 22px');
					$img = new CImg('images/general/tree/zero.gif', 'o', '22', '14');
					break;
				case 'I':
					$td->setAttribute('style', 'width:22px; background-image:url(images/general/tree/pointc.gif);');
					$img = new CImg('images/general/tree/zero.gif', 'i', '22', '14');
					break;
				case 'L':
					$td->setAttribute('valign', 'top');
					$div = new CTag('div', 'yes');
					$div->setAttribute('style', 'height: 10px; width:22px; margin-left: -3px; background-image:url(images/general/tree/pointc.gif);');

					if ($this->tree[$id]['nodetype'] == 2) {
						$img = new CImg('images/general/tree/plus.gif', 'y', '22', '14');
						$img->setAttribute('onclick', $this->treename.'.closeSNodeX("'.$id.'",this);');
						$img->setAttribute('id', 'idi_'.$id);
						$img->setAttribute('class', 'pointer');
					}
					else {
						$img = new CImg('images/general/tree/pointl.gif', 'y', '22', '14');
					}
					$div->addItem($img);
					$img = $div;
					break;
				case 'T':
					$td->setAttribute('valign', 'top');
					if ($this->tree[$id]['nodetype'] == 2) {
						$td->setAttribute('style', 'width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img = new CImg('images/general/tree/plus.gif', 't', '22', '14');
						$img->setAttribute('onclick', $this->treename.'.closeSNodeX("'.$id.'",this);');
						$img->setAttribute('id', 'idi_'.$id);
						$img->setAttribute('class', 'pointer');
						$img->setAttribute('style', 'top:2px;left:-3px;position:relative;');
					}
					else {
						$td->setAttribute('style', 'width:22px; background-image:url(images/general/tree/pointc.gif);');
						$img = new CImg('images/general/tree/pointl.gif', 't', '22', '14');
					}
					break;
			}
			$td->addItem($img);
			$tr->addItem($td);
		}
		return $tr;
	}

	private function countDepth() {
		foreach ($this->tree as $id => $rows) {
			if ($rows['id'] == '0') {
				continue;
			}
			$parentid = $this->tree[$id]['parentid'];

			$this->tree[$id]['nodeimg'] = $this->getImg($id, isset($this->tree[$parentid]['nodeimg']) ? $this->tree[$parentid]['nodeimg'] : '');
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

		return new CJSscript($js);
	}

	private function getImg($id, $img) {
		$img = str_replace('T', 'I', $img);
		$img = str_replace('L', 'O', $img);
		$ch = 'L';

		$childs = $this->tree[$this->tree[$id]['parentid']]['childnodes'];
		$childs_last = count($this->tree[$this->tree[$id]['parentid']]['childnodes']) - 1;

		if (isset($childs[$childs_last]) && $childs[$childs_last] != $id) {
			$ch = 'T';
		}
		$img .= $ch;
		return $img;
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
?>
