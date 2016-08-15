<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CScreenBuilder {

	/**
	 * Switch on/off flicker-free screens auto refresh.
	 *
	 * @var boolean
	 */
	public $isFlickerfree;

	/**
	 * Page file.
	 *
	 * @var string
	 */
	public $pageFile;

	/**
	 * Screen data
	 *
	 * @var array
	 */
	public $screen;

	/**
	 * Display mode
	 *
	 * @var int
	 */
	public $mode;

	/**
	 * @see Request timestamp
	 */
	public $timestamp;

	/**
	 * Host id
	 *
	 * @var string
	 */
	public $hostid;

	/**
	 * Profile table entity name #1
	 *
	 * @var string
	 */
	public $profileIdx;

	/**
	 * Profile table record id belongs to #1
	 *
	 * @var int
	 */
	public $profileIdx2;

	/**
	 * Is profile will be updated
	 *
	 * @var boolean
	 */
	public $updateProfile;

	/**
	 * Time control timeline
	 *
	 * @var array
	 */
	public $timeline;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param boolean	$options['isFlickerfree']
	 * @param string	$options['pageFile']
	 * @param int		$options['mode']
	 * @param int		$options['timestamp']
	 * @param int		$options['hostid']
	 * @param int		$options['period']
	 * @param int		$options['stime']
	 * @param string	$options['profileIdx']
	 * @param int		$options['profileIdx2']
	 * @param boolean	$options['updateProfile']
	 * @param array		$options['screen']
	 */
	public function __construct(array $options = []) {
		$this->isFlickerfree = isset($options['isFlickerfree']) ? $options['isFlickerfree'] : true;
		$this->mode = isset($options['mode']) ? $options['mode'] : SCREEN_MODE_SLIDESHOW;
		$this->timestamp = !empty($options['timestamp']) ? $options['timestamp'] : time();
		$this->hostid = !empty($options['hostid']) ? $options['hostid'] : null;

		// get page file
		if (!empty($options['pageFile'])) {
			$this->pageFile = $options['pageFile'];
		}
		else {
			global $page;
			$this->pageFile = $page['file'];
		}

		// get screen
		if (!empty($options['screen'])) {
			$this->screen = $options['screen'];
		}
		elseif (array_key_exists('screenid', $options) && $options['screenid'] > 0) {
			$this->screen = API::Screen()->get([
				'screenids' => $options['screenid'],
				'output' => API_OUTPUT_EXTEND,
				'selectScreenItems' => API_OUTPUT_EXTEND,
				'editable' => ($this->mode == SCREEN_MODE_EDIT)
			]);

			if (!empty($this->screen)) {
				$this->screen = reset($this->screen);
			}
			else {
				access_deny();
			}
		}

		// calculate time
		$this->profileIdx = !empty($options['profileIdx']) ? $options['profileIdx'] : '';
		$this->profileIdx2 = !empty($options['profileIdx2']) ? $options['profileIdx2'] : null;
		$this->updateProfile = isset($options['updateProfile']) ? $options['updateProfile'] : true;

		$this->timeline = CScreenBase::calculateTime([
			'profileIdx' => $this->profileIdx,
			'profileIdx2' => $this->profileIdx2,
			'updateProfile' => $this->updateProfile,
			'period' => !empty($options['period']) ? $options['period'] : null,
			'stime' => !empty($options['stime']) ? $options['stime'] : null
		]);
	}

	/**
	 * Get particular screen object.
	 *
	 * @static
	 *
	 * @param array		$options
	 * @param int		$options['resourcetype']
	 * @param int		$options['screenitemid']
	 * @param int		$options['hostid']
	 * @param array		$options['screen']
	 * @param int		$options['screenid']
	 *
	 * @return CScreenBase
	 */
	public static function getScreen(array $options = []) {
		if (!array_key_exists('resourcetype', $options)) {
			$options['resourcetype'] = null;

			// get resourcetype from screenitem
			if (!array_key_exists('screenitem', $options) && array_key_exists('screenitemid', $options)) {
				if (array_key_exists('hostid', $options) && $options['hostid'] > 0) {
					$options['screenitem'] = API::TemplateScreenItem()->get([
						'screenitemids' => $options['screenitemid'],
						'hostids' => $options['hostid'],
						'output' => API_OUTPUT_EXTEND
					]);
				}
				else {
					$options['screenitem'] = API::ScreenItem()->get([
						'screenitemids' => $options['screenitemid'],
						'output' => API_OUTPUT_EXTEND
					]);
				}
				$options['screenitem'] = reset($options['screenitem']);
			}

			if (array_key_exists('screenitem', $options) && array_key_exists('resourcetype', $options['screenitem'])) {
				$options['resourcetype'] = $options['screenitem']['resourcetype'];
			}
		}

		if ($options['resourcetype'] === null) {
			return null;
		}

		// get screen
		switch ($options['resourcetype']) {
			case SCREEN_RESOURCE_GRAPH:
				return new CScreenGraph($options);

			case SCREEN_RESOURCE_SIMPLE_GRAPH:
				return new CScreenSimpleGraph($options);

			case SCREEN_RESOURCE_MAP:
				return new CScreenMap($options);

			case SCREEN_RESOURCE_PLAIN_TEXT:
				return new CScreenPlainText($options);

			case SCREEN_RESOURCE_HOSTS_INFO:
				return new CScreenHostsInfo($options);

			case SCREEN_RESOURCE_TRIGGERS_INFO:
				return new CScreenTriggersInfo($options);

			case SCREEN_RESOURCE_SERVER_INFO:
				return new CScreenServerInfo($options);

			case SCREEN_RESOURCE_CLOCK:
				return new CScreenClock($options);

			case SCREEN_RESOURCE_SCREEN:
				return new CScreenScreen($options);

			case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				return new CScreenTriggersOverview($options);

			case SCREEN_RESOURCE_DATA_OVERVIEW:
				return new CScreenDataOverview($options);

			case SCREEN_RESOURCE_URL:
				$options = self::appendTemplatedScreenOption($options);
				return new CScreenUrl($options);

			case SCREEN_RESOURCE_ACTIONS:
				return new CScreenActions($options);

			case SCREEN_RESOURCE_EVENTS:
				return new CScreenEvents($options);

			case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
				return new CScreenHostgroupTriggers($options);

			case SCREEN_RESOURCE_SYSTEM_STATUS:
				return new CScreenSystemStatus($options);

			case SCREEN_RESOURCE_HOST_TRIGGERS:
				return new CScreenHostTriggers($options);

			case SCREEN_RESOURCE_HISTORY:
				return new CScreenHistory($options);

			case SCREEN_RESOURCE_CHART:
				return new CScreenChart($options);

			case SCREEN_RESOURCE_LLD_GRAPH:
				$options = self::appendTemplatedScreenOption($options);
				return new CScreenLldGraph($options);

			case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
				$options = self::appendTemplatedScreenOption($options);
				return new CScreenLldSimpleGraph($options);

			case SCREEN_RESOURCE_HTTPTEST_DETAILS:
				return new CScreenHttpTestDetails($options);

			case SCREEN_RESOURCE_DISCOVERY:
				return new CScreenDiscovery($options);

			case SCREEN_RESOURCE_HTTPTEST:
				return new CScreenHttpTest($options);

			default:
				return null;
		}
	}

	/**
	 * Appends boolean option 'isTemplatedScreen' to ouput options.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected static function appendTemplatedScreenOption(array $options) {
		if (array_key_exists('screen', $options)) {
			$options['isTemplatedScreen'] = (bool) $options['screen']['templateid'];
		}
		elseif (array_key_exists('screenid', $options) && $options['screenid'] > 0) {
			$options['isTemplatedScreen'] = (bool) API::TemplateScreen()->get([
				'screenids' => [$options['screenid']],
				'output' => []
			]);
		}

		return $options;
	}

	/**
	 * Process screen with particular screen objects.
	 *
	 * @return CTable
	 */
	public function show() {
		if (empty($this->screen)) {
			return new CTableInfo();
		}

		$skipedFields = [];
		$screenitems = [];
		$emptyScreenColumns = [];

		// calculate table columns and rows
		foreach ($this->screen['screenitems'] as $screenitem) {
			$screenitems[] = $screenitem;

			for ($i = 0; $i < $screenitem['rowspan'] || $i == 0; $i++) {
				for ($j = 0; $j < $screenitem['colspan'] || $j == 0; $j++) {
					if ($i != 0 || $j != 0) {
						if (!isset($skipedFields[$screenitem['y'] + $i])) {
							$skipedFields[$screenitem['y'] + $i] = [];
						}
						$skipedFields[$screenitem['y'] + $i][$screenitem['x'] + $j] = 1;
					}
				}
			}
		}

		// create screen table
		$screenTable = (new CTable())
			->setId(self::makeScreenTableId($this->screen['screenid']))
			->addClass(ZBX_STYLE_SCREEN_TABLE);

		if ($this->mode == SCREEN_MODE_EDIT) {
			$screenTable->addClass(ZBX_STYLE_DASHED_BORDER);
		}

		// action top row
		if ($this->mode == SCREEN_MODE_EDIT) {
			$newColumns = [(new CCol())->addClass(ZBX_STYLE_CELL_WIDTH)];

			for ($i = 0, $size = $this->screen['hsize']; $i < $size; $i++) {
				if ($this->screen['hsize'] >= SCREEN_MAX_SIZE) {
					$link = (new CDiv('+'))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addClass(ZBX_STYLE_DISABLED);
				}
				else {
					$link = (new CLink('+', 'screenedit.php?screenid='.$this->screen['screenid'].
						url_param('templateid').'&add_col='.$i
					))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addSID();
				}

				$newColumns[] = (new CCol($link))
					->addClass(ZBX_STYLE_CENTER)
					->addClass(ZBX_STYLE_MIDDLE);
			}

			if ($this->screen['hsize'] >= SCREEN_MAX_SIZE) {
				$link = (new CDiv('+'))
					->addClass(ZBX_STYLE_TREEVIEW_PLUS)
					->addClass(ZBX_STYLE_DISABLED);
			}
			else {
				$link = (new CLink('+', 'screenedit.php?screenid='.$this->screen['screenid'].url_param('templateid').
					'&add_col='.$this->screen['hsize']
				))
					->addClass(ZBX_STYLE_TREEVIEW_PLUS)
					->addSID();
			}

			$newColumns[] = (new CCol($link))
				->addClass(ZBX_STYLE_CENTER)
				->addClass(ZBX_STYLE_MIDDLE)
				->addClass(ZBX_STYLE_CELL_WIDTH);

			$screenTable->addRow($newColumns);
		}

		for ($r = 0; $r < $this->screen['vsize']; $r++) {
			$newColumns = [];
			$emptyScreenRow = true;

			// action left cell
			if ($this->mode == SCREEN_MODE_EDIT) {
				if ($this->screen['vsize'] >= SCREEN_MAX_SIZE) {
					$link = (new CDiv('+'))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addClass(ZBX_STYLE_DISABLED);
				}
				else {
					$link = (new CLink('+', 'screenedit.php?screenid='.$this->screen['screenid'].
						url_param('templateid').'&add_row='.$r
					))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addSID();
				}

				$newColumns[] = (new CCol($link))
					->addClass(ZBX_STYLE_CENTER)
					->addClass(ZBX_STYLE_MIDDLE);
			}

			for ($c = 0; $c < $this->screen['hsize']; $c++) {
				if (isset($skipedFields[$r][$c])) {
					continue;
				}

				// screen item
				$isEditForm = false;
				$screenitem = [];

				foreach ($screenitems as $tmprow) {
					if ($tmprow['x'] == $c && $tmprow['y'] == $r) {
						$screenitem = $tmprow;
						break;
					}
				}

				if (empty($screenitem)) {
					$screenitem = [
						'screenitemid' => 0,
						'resourcetype' => 0,
						'resourceid' => 0,
						'width' => 0,
						'height' => 0,
						'colspan' => 1,
						'rowspan' => 1,
						'elements' => 0,
						'valign' => VALIGN_DEFAULT,
						'halign' => HALIGN_DEFAULT,
						'style' => 0,
						'url' => '',
						'dynamic' => 0,
						'sort_triggers' => SCREEN_SORT_TRIGGERS_DATE_DESC
					];
				}

				if (!empty($screenitem['screenitemid'])) {
					$emptyScreenRow = false;
					$emptyScreenColumns[$c] = true;
				}

				// action
				if ($this->mode == SCREEN_MODE_EDIT) {
					if ($screenitem['screenitemid'] != 0) {
						$action = 'screenedit.php?form=update'.url_params(['screenid', 'templateid']).
							'&screenitemid='.$screenitem['screenitemid'];
					}
					else {
						$action = 'screenedit.php?form=update'.url_params(['screenid', 'templateid']).'&x='.$c.'&y='.$r;
					}
				}
				else {
					$action = null;
				}

				// edit form cell
				if ($this->mode == SCREEN_MODE_EDIT
						&& (isset($_REQUEST['form']) && $_REQUEST['form'] == 'update')
						&& ((isset($_REQUEST['x']) && $_REQUEST['x'] == $c && isset($_REQUEST['y']) && $_REQUEST['y'] == $r)
								|| (isset($_REQUEST['screenitemid']) && bccomp($_REQUEST['screenitemid'], $screenitem['screenitemid']) == 0))) {
					$screenView = new CView('monitoring.screen.constructor.edit', ['screen' => $this->screen]);
					$item = $screenView->render();
					$isEditForm = true;
				}
				// screen cell
				elseif (!empty($screenitem['screenitemid']) && isset($screenitem['resourcetype'])) {
					$screenBase = CScreenBuilder::getScreen([
						'screen' => $this->screen,
						'screenid' => $this->screen['screenid'],
						'isFlickerfree' => $this->isFlickerfree,
						'pageFile' => $this->pageFile,
						'mode' => $this->mode,
						'timestamp' => $this->timestamp,
						'hostid' => $this->hostid,
						'profileIdx' => $this->profileIdx,
						'profileIdx2' => $this->profileIdx2,
						'updateProfile' => $this->updateProfile,
						'timeline' => $this->timeline,
						'resourcetype' => $screenitem['resourcetype'],
						'screenitem' => $screenitem
					]);

					if (!empty($screenBase)) {
						$screenBase->action = $action;

						$item = $screenBase->get();
					}
					else {
						$item = null;
					}
				}
				// change/empty cell
				elseif ($this->mode == SCREEN_MODE_EDIT) {
					$item =[
						(new CDiv(
							(new CLink(_('Change'), $action))->addClass('empty_change_link')
						))->addClass(ZBX_STYLE_CENTER)
					];
				}
				else {
					$item = null;
				}

				if ($this->mode == SCREEN_MODE_EDIT && !$isEditForm) {
					$item = (new CDiv($item))
						->addClass('draggable')
						->setId('position_'.$r.'_'.$c)
						->setAttribute('data-xcoord', $c)
						->setAttribute('data-ycoord', $r);
				}

				// colspan/rowspan
				$newColumn = (new CCol($item))->addClass('screenitem');

				if ($screenitem['halign'] == HALIGN_CENTER || $isEditForm) {
					$newColumn->addClass(ZBX_STYLE_CENTER);
				}
				elseif ($screenitem['halign'] == HALIGN_LEFT) {
					$newColumn->addClass(ZBX_STYLE_LEFT);
				}
				elseif ($screenitem['halign'] == HALIGN_RIGHT) {
					$newColumn->addClass(ZBX_STYLE_RIGHT);
				}

				if ($screenitem['valign'] == VALIGN_MIDDLE || $isEditForm) {
					$newColumn->addClass(ZBX_STYLE_MIDDLE);
				}
				elseif ($screenitem['valign'] == VALIGN_TOP) {
					$newColumn->addClass(ZBX_STYLE_TOP);
				}
				elseif ($screenitem['valign'] == VALIGN_BOTTOM) {
					$newColumn->addClass(ZBX_STYLE_BOTTOM);
				}

				if ($screenitem['colspan'] > 1) {
					$newColumn->setColSpan($screenitem['colspan']);
				}
				if ($screenitem['rowspan'] > 1) {
					$newColumn->setRowSpan($screenitem['rowspan']);
				}
				$newColumns[] = $newColumn;
			}

			// action right cell
			if ($this->mode == SCREEN_MODE_EDIT) {
				if ($this->screen['vsize'] == SCREEN_MIN_SIZE) {
					$link = (new CDiv('−'))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addClass(ZBX_STYLE_DISABLED);
				}
				else {
					$link = (new CLink('−', 'screenedit.php?screenid='.$this->screen['screenid'].
						url_param('templateid').'&rmv_row='.$r
					))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addSID();
					if (!$emptyScreenRow) {
						$link->addConfirmation(_('This screen row is not empty. Delete it?'));
					}
				}

				$newColumns[] = (new CCol($link))
					->addClass(ZBX_STYLE_CENTER)
					->addClass(ZBX_STYLE_MIDDLE);
			}
			$screenTable->addRow(new CRow($newColumns));
		}

		// action bottom row
		if ($this->mode == SCREEN_MODE_EDIT) {
			if ($this->screen['vsize'] >= SCREEN_MAX_SIZE) {
				$link = (new CDiv('+'))
					->addClass(ZBX_STYLE_TREEVIEW_PLUS)
					->addClass(ZBX_STYLE_DISABLED);
			}
			else {
				$link = (new CLink('+', 'screenedit.php?screenid='.$this->screen['screenid'].url_param('templateid').
					'&add_row='.$this->screen['vsize']
				))
					->addClass(ZBX_STYLE_TREEVIEW_PLUS)
					->addSID();
			}

			$newColumns = [
				(new CCol($link))
					->addClass(ZBX_STYLE_CENTER)
					->addClass(ZBX_STYLE_MIDDLE)
			];

			for ($i = 0; $i < $this->screen['hsize']; $i++) {
				if ($this->screen['hsize'] == SCREEN_MIN_SIZE) {
					$link = (new CDiv('−'))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addClass(ZBX_STYLE_DISABLED);
				}
				else {
					$link = (new CLink('−', 'screenedit.php?screenid='.$this->screen['screenid'].
						url_param('templateid').'&rmv_col='.$i
					))
						->addClass(ZBX_STYLE_TREEVIEW_PLUS)
						->addSID();

					if (array_key_exists($i, $emptyScreenColumns)) {
						$link->addConfirmation(_('This screen column is not empty. Delete it?'));
					}
				}

				$newColumns[] = (new CCol($link))
					->addClass(ZBX_STYLE_CENTER)
					->addClass(ZBX_STYLE_MIDDLE);
			}

			$newColumns[] = '';
			$screenTable->addRow($newColumns);
		}

		return $screenTable;
	}

	/**
	 * Insert javascript to create scroll in time control.
	 *
	 * @static
	 *
	 * @param array $options
	 * @param array $options['timeline']
	 * @param string $options['profileIdx']
	 */
	public static function insertScreenScrollJs(array $options = []) {
		$options['timeline'] = empty($options['timeline']) ? '' : $options['timeline'];
		$options['profileIdx'] = empty($options['profileIdx']) ? '' : $options['profileIdx'];

		$timeControlData = [
			'id' => 'scrollbar',
			'loadScroll' => 1,
			'mainObject' => 1,
			'periodFixed' => CProfile::get($options['profileIdx'].'.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		];

		zbx_add_post_js('timeControl.addObject("scrollbar", '.zbx_jsvalue($options['timeline']).', '.zbx_jsvalue($timeControlData).');');
	}

	/**
	 * Insert javascript to make time control synchronizes with NOW!
	 *
	 * @static
	 */
	public static function insertScreenRefreshTimeJs() {
		zbx_add_post_js('timeControl.useTimeRefresh('.CWebUser::$data['refresh'].');');
	}

	/**
	 * Insert javascript to init screens.
	 *
	 * @static
	 *
	 * @param string $screenid
	 */
	public static function insertInitScreenJs($screenid) {
		zbx_add_post_js('init_screen("'.$screenid.'", "'.self::makeScreenTableId($screenid).'", "'.$screenid.'");');
	}

	/**
	 * Insert javascript to start time control rendering.
	 *
	 * @static
	 */
	public static function insertProcessObjectsJs() {
		zbx_add_post_js('timeControl.processObjects();');
	}

	/**
	 * Insert javascript to clean all screen items.
	 *
	 * @static
	 */
	public static function insertScreenCleanJs() {
		zbx_add_post_js('window.flickerfreeScreen.cleanAll();');
	}

	/**
	 * Insert javascript for standard screens.
	 *
	 * @param array $options
	 * @param array $options['timeline']
	 * @param string $options['profileIdx']
	 *
	 * @static
	 */
	public static function insertScreenStandardJs(array $options = []) {
		CScreenBuilder::insertScreenScrollJs($options);
		CScreenBuilder::insertScreenRefreshTimeJs();
		CScreenBuilder::insertProcessObjectsJs();
	}

	/**
	 * Creates a string for screen table ID attribute.
	 *
	 * @param string $screenId
	 *
	 * @return string
	 */
	protected static function makeScreenTableId($screenId) {
		return 'screentable_'.$screenId;
	}
}
