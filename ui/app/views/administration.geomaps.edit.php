<?php declare(strict_types = 0);
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


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.geomaps.edit.js.php');

$hintbox_tile_url = makeHelpIcon([
	_('The URL template is used to load and display the tile layer on geographical maps.'),
	BR(),
	BR(),
	_('Example'), ': ', (new CSpan('https://{s}.example.com/{z}/{x}/{y}{r}.png'))->addClass(ZBX_STYLE_MONOSPACE_FONT),
	BR(),
	BR(),
	_('The following placeholders are supported:'),
	(new CList([
		new CListItem([
			(new CSpan('{s}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents one of the available subdomains;')
		]),
		new CListItem([
			(new CSpan('{z}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('represents zoom level parameter in the URL;')
		]),
		new CListItem([
			(new CSpan('{x}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('and'), ' ',
			(new CSpan('{y}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ', _('represent tile coordinates;')
		]),
		new CListItem([
			(new CSpan('{r}'))->addClass(ZBX_STYLE_MONOSPACE_FONT), ' ',
			_('can be used to add "@2x" to the URL to load retina tiles.')
		])
	]))->addClass(ZBX_STYLE_LIST_DASHED)
]);

$warning_attribution = makeWarningIcon(_('Tile provider attribution data displayed in a small text box on the map.'));

$hintbox_max_zoom = makeHelpIcon(_('Maximum zoom level of the map.'));

$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Tile provider'), 'label-provider'))->setAsteriskMark(),
		new CFormField(
			(new CSelect('geomaps_tile_provider'))
				->setValue($data['geomaps_tile_provider'])
				->setFocusableElementId('label-provider')
				->addOptions(CSelect::createOptionsFromArray(
					array_combine(array_keys($data['tile_providers']), array_column($data['tile_providers'], 'name')) +
					['' => _('Other')]
				))
		)
	])
	->addItem([
		(new CLabel([_('Tile URL'), $hintbox_tile_url], 'geomaps_tile_url'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('geomaps_tile_url', $data['geomaps_tile_url'], false,
				DB::getFieldLength('config', 'geomaps_tile_url'))
			)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel([_('Attribution text'), $warning_attribution], 'geomaps_attribution'))
			->addClass($data['geomaps_tile_provider'] !== '' ? ZBX_STYLE_DISPLAY_NONE : null),
		(new CFormField(
			(new CTextArea('geomaps_attribution', $data['geomaps_attribution']))
				->addClass(ZBX_STYLE_MONOSPACE_FONT)
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setMaxLength(DB::getFieldLength('config', 'geomaps_attribution'))
		))->addClass($data['geomaps_tile_provider'] !== '' ? ZBX_STYLE_DISPLAY_NONE : null)
	])
	->addItem([
		(new CLabel([_('Max zoom level'), $hintbox_max_zoom], 'geomaps_max_zoom'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('geomaps_max_zoom', $data['geomaps_max_zoom'], 2, false, false, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setReadonly($data['geomaps_tile_provider'] !== '')
				->setAriaRequired()
		)
	]);

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('geomaps')))->removeId())
	->setId('geomaps-form')
	->setName('geomaps-form')
	->setAction(
		(new CUrl('zabbix.php'))
			->setArgument('action', 'geomaps.update')
			->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem(
		(new CTabView())
			->addTab('geomaps_tab', _('Geographical maps'), $form_grid)
			->setFooter(makeFormFooter(
				new CSubmit('update', _('Update'))
			))
	);

(new CHtmlPage())
	->setTitle(_('Geographical maps'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_GEOMAPS_EDIT))
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'tile_providers' => $data['tile_providers']
	]).');'
))
	->setOnDocumentReady()
	->show();
