<?php

class CConfigurationExportBuilder {

	const EXPORT_VERSION = '2.0';

	/**
	 * @var CExportElement
	 */
	private $rootElement;

	/**
	 * @return mixed
	 */
	public function getExport() {
		return $this->rootElement;
	}

	public function buildRoot() {
		$zabbixExportData = array(
			'version' => self::EXPORT_VERSION,
			'date' => date('Y-m-d\TH:i:s\Z', time() - date('Z')),
		);
		$this->rootElement = new CExportElement('zabbix_export', $zabbixExportData);
	}

	public function buildGroups($groups) {
		order_result($groups, 'name');
		$groupsElement = new CExportElement('groups');
		foreach ($groups as $group) {
			$groupsElement->addElement(new CGroupExportElement($group));
		}
		$this->rootElement->addElement($groupsElement);
	}

	public function buildHosts($hosts) {
		$hostsElement = new CExportElement('hosts');
		order_result($hosts, 'host');
		foreach ($hosts as $host) {
			$hostsElement->addElement(new CHostExportElement($host));
		}
		$this->rootElement->addElement($hostsElement);
	}

	public function buildTemplates($templates) {
		$templatesElement = new CExportElement('templates');
		order_result($templates, 'host');
		foreach ($templates as $template) {
			$templatesElement->addElement(new CTemplateExportElement($template));
		}
		$this->rootElement->addElement($templatesElement);
	}

	public function buildGraphs(array $graphs) {
		order_result($graphs, 'name');
		$graphsElement = new CExportElement('graphs');
		foreach ($graphs as $graph) {
			$graphsElement->addElement(new CGraphExportElement($graph));
		}
		$this->rootElement->addElement($graphsElement);
	}

	public function buildTriggers(array $triggers) {
		order_result($triggers, 'description');
		$triggersElement = new CExportElement('triggers');
		foreach ($triggers as $trigger) {
			$triggersElement->addElement(new CTriggerExportElement($trigger));
		}
		$this->rootElement->addElement($triggersElement);
	}

	public function buildScreens(array $screens) {
		order_result($screens, 'name');
		$screensElement = new CExportElement('screens');
		foreach ($screens as $screen) {
			$screensElement->addElement(new CScreenExportElement($screen));
		}
		$this->rootElement->addElement($screensElement);
	}

	public function buildImages(array $images) {
		$imagesElement = new CExportElement('images');

		foreach ($images as $image) {
			$imagesElement->addElement(new CImageExportElement($image));
		}

		$this->rootElement->addElement($imagesElement);
	}

	public function buildMaps(array $maps) {
		$mapsElement = new CExportElement('maps');
		foreach ($maps as $map) {
			$mapsElement->addElement(new CMapExportElement($map));
		}
		$this->rootElement->addElement($mapsElement);
	}

}

