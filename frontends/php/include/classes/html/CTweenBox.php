<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class CTweenBox
 *
 * Renders two list boxes (multiselects) with buttons to move items between them. Move happens on client side.
 * Item values of left side list box gets sent to server on form submit. All items are instances of CComboItem.
 * Items in both list boxes are sorted  by captions, alphabetically.
 */
class CTweenBox {

	/**
	 * Stores form in which tween box is used.
	 *
	 * @var CForm
	 */
	protected $form;

	/**
	 * Name of tween box element.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Name of element used for storing selected values.
	 *
	 * @var string
	 */
	protected $variableName;

	/**
	 * Holds currently selected (i.e. in left list box) item values.
	 *
	 * @var array
	 */
	protected $selectedValues = array();

	/**
	 * Holds all items used in this tween box.  Indexed by item value.
	 *
	 * @var CComboItem[]
	 */
	protected $items = array();

	/**
	 * Height of list boxes.
	 *
	 * @var int
	 */
	protected $size;

	/**
	 * Constructs new instance of CTweenBox.
	 *
	 * @param CForm  $form form in which this tween combo box is in
	 * @param string $name name of tween combo box
	 * @param int    $size height of list boxes
	 */
	public function __construct(CForm $form, $name, $size = 10) {
		zbx_add_post_js('if (IE7) $$("select option[disabled]").each(function(e) { e.setStyle({color: "gray"}); });');

		$this->form = $form;
		$this->name = $name . '_tweenbox';
		$this->variableName = $name;

		$this->size = $size;
	}

	/**
	 * Constructs new item and adds it to the possible items list. Uses CTweenBox::addItem() to add.
	 *
	 * @param int|string $value   value of item
	 * @param string     $caption caption of item
	 * @param bool       $enabled specifies whether it will be possible to move item between lists
	 */
	public function addItem($value, $caption, $enabled = true) {
		$this->items[$value] = new CComboItem($value, $caption, null, $enabled);
	}

	/**
	 * Sets $values to be selected. Items with these values will appear in the left list box.
	 *
	 * @param array $values
	 */
	public function setSelectedValues(array $values) {
		$this->selectedValues = zbx_toHash($values);
	}

	/**
	 * Marks $value to be selected. Item with this value will appear in the left list box.
	 *
	 * @param int|string $value
	 */
	public function addSelectedValue($value) {
		$this->selectedValues[$value] = $value;
	}

	/**
	 * Generates and returns layout element for tween box based on current items and selected values.
	 * It is possible to set custom captions for the list boxes with anything that layout engine supports -
	 * a string, subclass of CTag, or even array of previous.
	 *
	 * @param mixed|null $leftCaption  custom caption for left list box caption
	 * @param mixed|null $rightCaption custom caption for right list box caption
	 *
	 * @return CTable
	 */
	public function get($leftCaption = null, $rightCaption = null) {
		if ($leftCaption === null) {
			$leftCaption = _('In');
		}
		if ($rightCaption === null) {
			$rightCaption = _('Other');
		}

		$tweenBoxTable = new CTable(null, 'tweenBoxTable');
		$tweenBoxTable->attr('name', $this->name);
		$tweenBoxTable->attr('id', zbx_formatDomId($this->name));
		$tweenBoxTable->setCellSpacing(0);
		$tweenBoxTable->setCellPadding(0);

		if ($leftCaption || $rightCaption) {
			$tweenBoxTable->addRow(array($leftCaption, '', $rightCaption));
		}

		$listBoxAttributes = array('name' => null, 'style' => 'width: 280px;');

		$leftListBox = new CListBox($this->variableName . '_left', null, $this->size);
		$leftListBox->setAttributes($listBoxAttributes);

		$rightListBox = new CListBox($this->variableName . '_right', null, $this->size);
		$rightListBox->setAttributes($listBoxAttributes);

		// Items for left and right list boxes are sorted by checking if value of item is in $selectedValues.
		$leftItems = array();
		$rightItems = array();
		foreach ($this->items as $value => $item) {
			if (isset($this->selectedValues[$value])) {
				$leftItems[] = $item;
			}
			else {
				$rightItems[] = $item;
			}
		}

		// Left and right list box items are sorted by caption to behave same way as client side.
		foreach ($this->sortItemsByCaptions($leftItems) as $item) {
			$leftListBox->addItem($item);
		}

		foreach ($this->sortItemsByCaptions($rightItems) as $item) {
			$rightListBox->addItem($item);
		}

		$formName = $this->form->getName();
		$leftListBoxId = $leftListBox->getAttribute('id');
		$rightListBoxId = $rightListBox->getAttribute('id');

		$moveToLeftListButton = new CButton('add', '  &laquo;  ', null, 'formlist');
		$moveToLeftListButton->setAttribute(
			'onclick',
			'moveListBoxSelectedItem("'.$formName.'", "'.$this->variableName.'", "'.$rightListBoxId.'", "'.
			$leftListBoxId.'", "add");'
		);

		$moveToRightListButton = new CButton('remove', '  &raquo;  ', null, 'formlist');
		$moveToRightListButton->setAttribute(
			'onclick',
			'moveListBoxSelectedItem("'.$formName.'", "'.$this->variableName.'", "'.$leftListBoxId.'", "'.
			$rightListBoxId.'", "rmv");'
		);

		$tweenBoxTable->addRow(array(
			$leftListBox,
			new CCol(array($moveToLeftListButton, BR(), $moveToRightListButton)),
			$rightListBox
		));

		$tweenBoxTable->addItem(new CVar($this->variableName, CJs::encodeJson(array_values($this->selectedValues))));

		return $tweenBoxTable;
	}

	/**
	 * Sorts array of CComboItem items by their captions.
	 *
	 * @param CComboItem[] $items
	 *
	 * @return CComboItem[]
	 */
	protected function sortItemsByCaptions(array $items) {
		$itemCaptions = array();

		foreach ($items as $key => $item) {
			$itemCaptions[$key] = $item->items[0];
		}
		order_result($itemCaptions);

		$resultItems = array();
		foreach (array_keys($itemCaptions) as $key) {
			$resultItems[$key] = $items[$key];
		}

		return $resultItems;
	}

	/**
	 * Returns generated layout element as string.
	 *
	 * @return string
	 */
	public function toString() {
		return $this->get()->toString();
	}
}
