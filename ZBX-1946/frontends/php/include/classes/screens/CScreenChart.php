<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CScreenChart extends CScreenBase {

	/**
	 * Graph id
	 *
	 * @var int
	 */
	public $graphid;

	/**
	 * Init screen data.
	 *
	 * @param array		$options
	 * @param int		$options['graphid']
	 */
	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->graphid = isset($options['graphid']) ? $options['graphid'] : null;
	}

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$this->dataId = 'graph_full';
		$containerId = 'graph_conteiner';

		// time control
		$graphDims = getGraphDims($this->graphid);
		if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
			$loadSBox = 0;
			$src = 'chart6.php';
		}
		else {
			$loadSBox = 1;
			$src = 'chart2.php';
		}
		$src .= '?graphid='.$this->graphid.'&period='.$this->timeline['period'].'&stime='.$this->timeline['stimeNow'].'&updateProfile='.(int) $this->updateProfile;

		$this->timeline['starttime'] = date('YmdHis', get_min_itemclock_by_graphid($this->graphid));

		$timeControlData = array(
			'id' => $this->getDataId(),
			'containerid' => $containerId,
			'src' => $src,
			'objDims' => $graphDims,
			'loadSBox' => $loadSBox,
			'loadImage' => 1,
			'dynamic' => 1,
			'periodFixed' => CProfile::get($this->profileIdx.'.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
		);

		// output
		if ($this->mode == SCREEN_MODE_JS) {
			$timeControlData['dynamic'] = 0;
			$timeControlData['loadSBox'] = 0;

			return 'timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).')';
		}
		else {
			if ($this->mode == SCREEN_MODE_SLIDESHOW) {
				insert_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');');
			}
			else {
				zbx_add_post_js('timeControl.addObject("'.$this->getDataId().'", '.zbx_jsvalue($this->timeline).', '.zbx_jsvalue($timeControlData).');');
			}

			// graph container
			$graphContainer = new CCol();
			$graphContainer->setAttribute('id', $containerId);

			$item = new CTableInfo(_('No graphs defined.'), 'chart');
			$item->addRow($graphContainer);

			return $this->getOutput($item, true, array('graphid' => $this->graphid));
		}
	}
}
