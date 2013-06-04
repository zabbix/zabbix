<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CFormList extends CDiv {

	protected $formList = null;
	protected $editable = true;
	protected $formInputs = array('ctextbox', 'cnumericbox', 'ctextarea', 'ccombobox', 'ccheckbox', 'cpassbox', 'cipbox');

	public function __construct($id, $class = null, $editable = true) {
		$this->editable = $editable;
		$this->formList = new CList(null, 'formlist');

		parent::__construct();

		$this->attr('id', zbx_formatDomId($id));
		$this->attr('class', $class);
	}

	public function addRow($term, $description = null, $hidden = false, $id = null, $class = null) {
		$label = (is_object($description) && in_array(zbx_strtolower(get_class($description)), $this->formInputs))
			? new CLabel($term, $description->getAttribute('id'))
			: $term;

		$defaultClass = $hidden ? 'formrow hidden' : 'formrow';

		if ($class === null) {
			$class = $defaultClass;
		}
		else {
			$class .= ' '.$defaultClass;
		}

		if ($description === null) {
			$this->formList->addItem(array(new CDiv(SPACE, 'dt floatleft right'), new CDiv($label, 'dd')), $class, $id);
		}
		else {
			$this->formList->addItem(array(new CDiv($label, 'dt floatleft right'), new CDiv($description, 'dd')), $class, $id);
		}
	}

	public function addInfo($text, $label = null) {
		$this->formList->addItem(
			array(
				new CDiv($label ? $label : _('Info'), 'dt right listInfoLabel'),
				new CDiv($text, 'objectgroup inlineblock border_dotted ui-corner-all listInfoText')
			),
			'formrow listInfo'
		);
	}

	public function toString($destroy = true) {
		$this->addItem($this->formList);

		insert_js('
			jQuery(window).resize(function() {
				var parent = jQuery("#'.$this->getAttribute('id').'").parent();

				if (!parent.is(":visible")) {
					return;
				}

				function getLeftColumnMaxHeight() {
					var maxHeight = 0;

					jQuery("#'.$this->getAttribute('id').' .formrow .dt", parent).find("*").each(function() {
						var height = jQuery(this).height();

						if (height > maxHeight) {
							maxHeight = height;
						}
					});

					return maxHeight;
				}

				function getLeftColumnMaxWidth() {
					var maxWidth = 0;

					jQuery("#'.$this->getAttribute('id').' .formrow .dt", parent).find("*").each(function() {
						var width = jQuery(this).width();

						if (width > maxWidth) {
							maxWidth = width;
						}
					});

					return maxWidth;
				}

				function getRightColumnMaxWidth() {
					var maxWidth = 0;

					jQuery("#'.$this->getAttribute('id').' .formrow .dd", parent).find("*").each(function() {
						var width = jQuery(this).width();

						if (width > maxWidth) {
							maxWidth = width;
						}
					});

					return maxWidth;
				}

				function getLeftColumnWidth() {
					var leftColumn = jQuery(jQuery(".formrow .dt", parent)[0]);

					return Math.round(100 * leftColumn.width() / leftColumn.offsetParent().width());
				}

				function resizeFormList(newWidth) {
					if (typeof(newWidth) == "undefined") {
						newWidth = getLeftColumnWidth() + 5;
					}

					jQuery(".formrow .dt", parent).css({width: newWidth + "%"});
					jQuery(".formrow .dd", parent).css({width: 100 - newWidth + "%", "margin-left": newWidth + 1 + "%"});
					jQuery("#'.$this->getAttribute('id').'").data("resize", jQuery(window).width());

					if (newWidth > 0 && getLeftColumnMaxHeight() > 20) {
						resizeFormList();
					}
				}

				var pageWidth = jQuery(window).width(),
					leftColumnMaxWidth = getLeftColumnMaxWidth(),
					rightColumnMaxWidth = getRightColumnMaxWidth();

				// increase left column
				if (getLeftColumnMaxHeight() > 20) {
					resizeFormList();
				}

				// increase right column if left column is empty
				else if (leftColumnMaxWidth == 0 && rightColumnMaxWidth + 200 > pageWidth) {
					resizeFormList(0);
				}

				// increase right column if it"s too big but left column too small
				else if (leftColumnMaxWidth < 100 && rightColumnMaxWidth + 200 > pageWidth) {
					resizeFormList(10);
				}

				// return initial left/right column proportion
				else {
					var resize = jQuery("#'.$this->getAttribute('id').'").data("resize");

					if (!empty(resize)) {
						if (pageWidth > resize) {
							var newWidth = getLeftColumnWidth() - Math.round((pageWidth - resize) * 100 / pageWidth);

							if (newWidth < 20) {
								newWidth = 20;
							}

							resizeFormList(newWidth);
						}
					}
				}
			});

			jQuery(window).trigger("resize");'
		, true);

		return parent::toString($destroy);
	}

	public function addVar($name, $value, $id = null) {
		if ($value !== null) {
			return $this->addItem(new CVar($name, $value, $id));
		}
	}
}
