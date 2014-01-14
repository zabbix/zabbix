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


$slideWidget = new CWidget('hat_slides');

// create header form
$slideHeaderForm = new CForm('get');
$slideHeaderForm->setName('slideHeaderForm');

$configComboBox = new CComboBox('config', 'slides.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configComboBox->addItem('screens.php', _('Screens'));
$configComboBox->addItem('slides.php', _('Slide shows'));
$slideHeaderForm->addItem($configComboBox);

if (empty($this->data['slideshows'])) {
	$slideWidget->addPageHeader(
		_('SLIDE SHOWS'),
		array(
			$slideHeaderForm,
			SPACE,
			get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
		)
	);
	$slideWidget->addItem(BR());
	$slideWidget->addItem(new CTableInfo(_('No slide shows found.')));
}
else {
	$favouriteIcon = $this->data['screen']
		? get_icon('favourite', array('fav' => 'web.favorite.screenids', 'elname' => 'slideshowid', 'elid' => $this->data['elementid']))
		: new CIcon(_('Favourites'), 'iconplus');

	$refreshIcon = new CIcon(_('Menu'), 'iconmenu');
	if (!empty($this->data['screen'])) {
		$refreshIcon->addAction('onclick', 'javascript: create_page_menu(event, "hat_slides");');
	}

	$slideWidget->addPageHeader(
		_('SLIDE SHOWS'),
		array(
			$slideHeaderForm,
			SPACE,
			$favouriteIcon,
			SPACE,
			$refreshIcon,
			SPACE,
			get_icon('fullscreen', array('fullscreen' => $this->data['fullscreen']))
		)
	);

	$slideForm = new CForm('get');
	$slideForm->setName('slideForm');
	$slideForm->addVar('fullscreen', $this->data['fullscreen']);

	$elementsComboBox = new CComboBox('elementid', $this->data['elementid'], 'submit()');
	foreach ($this->data['slideshows'] as $slideshow) {
		$elementsComboBox->addItem($slideshow['slideshowid'], get_node_name_by_elid($slideshow['slideshowid'], null, NAME_DELIMITER).$slideshow['name']);
	}
	$slideForm->addItem(array(_('Slide show').SPACE, $elementsComboBox));

	$slideWidget->addHeader($this->data['slideshows'][$this->data['elementid']]['name'], $slideForm);

	if (!empty($this->data['screen'])) {
		if (isset($this->data['isDynamicItems'])) {
			$slideForm->addItem(array(SPACE, _('Group'), SPACE, $this->data['pageFilter']->getGroupsCB()));
			$slideForm->addItem(array(SPACE, _('Host'), SPACE, $this->data['pageFilter']->getHostsCB()));
		}

		$scrollDiv = new CDiv();
		$scrollDiv->setAttribute('id', 'scrollbar_cntr');
		$slideWidget->addFlicker($scrollDiv, CProfile::get('web.slides.filter.state', 1));
		$slideWidget->addFlicker(BR(), CProfile::get('web.slides.filter.state', 1));

		// js menu
		insert_js('var page_menu='.zbx_jsvalue($this->data['menu']).";\n".'var page_submenu='.zbx_jsvalue($this->data['submenu']).";\n");

		add_doll_objects(array(array(
			'id' => 'hat_slides',
			'frequency' => $this->data['element']['delay'] * $this->data['refresh_multiplier'],
			'url' => 'slides.php?elementid='.$this->data['elementid'].url_param('groupid').url_param('hostid'),
			'params'=> array('lastupdate' => time())
		)));

		$slideWidget->addItem(new CSpan(_('Loading...'), 'textcolorstyles'));
	}
	else {
		$slideWidget->addItem(new CTableInfo(_('No slides found.')));
	}
}

return $slideWidget;
