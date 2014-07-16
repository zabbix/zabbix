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
 * Class that holds created and updated hosts and templates during the current import.
 */
class CImportedObjectContainer {
	/**
	 * @var array with created and updated hosts.
	 */
	protected $hosts = array();

	/**
	 * @var array with created and updated templates.
	 */
	protected $templates = array();

	/**
	 * @var CConfigurationImport.
	 */
	protected $configurationImport;

	/**
	 * @var CImportReferencer.
	 */
	protected $referencer;

	/**
	 * @var array importable object options "createMissing", "updateExisting", "deleteMissing".
	 */
	protected $options;

	public function __construct (CConfigurationImport $configurationImport, CImportReferencer $referencer,
			array $options = array()) {
		$this->configurationImport = $configurationImport;
		$this->referencer = $referencer;
		$this->options = $options;
	}

	/**
	 * Add host that have been created and updated.
	 *
	 * @param $host
	 */
	public function addHost($host) {
		$this->hosts[$host] = $host;
	}

	/**
	 * Add template that have been created and updated.
	 *
	 * @param $host
	 */
	public function addTemplate($template) {
		$this->templates[$template] = $template;
	}

	/**
	 * Checks if host has been created and updated during the current import.
	 *
	 * @param $host
	 *
	 * @return bool
	 */
	public function isHostProcessed($host) {
		return isset($this->hosts[$host]);
	}

	/**
	 * Checks if template has been created and updated during the current import.
	 *
	 * @param $template
	 *
	 * @return bool
	 */
	public function isTemplateProcessed($template) {
		return isset($this->templates[$template]);
	}

	/**
	 * Get array of created and updated hosts name and ID pairs.
	 *
	 * @return array
	 */
	public function getHostIds() {
		$hosts = array();

		if ($this->options['hosts']['updateExisting'] || $this->options['hosts']['createMissing']) {
			$hosts = $this->configurationImport->getFormattedHosts();
			if ($hosts) {
				$hosts = zbx_objectValues($hosts, 'host');
			}
		}

		$hostIdsXML = array();

		foreach ($hosts as $host) {
			if (!$this->isHostProcessed($host)) {
				continue;
			}

			$hostId = $this->referencer->resolveHostOrTemplate($host);
			$hostIdsXML[$host] = $hostId;
		}

		return $hostIdsXML;
	}

	/**
	 * Get array of created and updated template name and ID pairs.
	 *
	 * @return array
	 */
	public function getTemplateIds() {
		$templates = array();

		if ($this->options['templates']['updateExisting'] || $this->options['templates']['createMissing']) {
			$templates = $this->configurationImport->getFormattedTemplates();
			if ($templates) {
				$templates = zbx_objectValues($templates, 'host');
			}
		}

		$templateIdsXML = array();

		foreach ($templates as $template) {
			if (!$this->isTemplateProcessed($template)) {
				continue;
			}

			$templateId = $this->referencer->resolveHostOrTemplate($template);
			$templateIdsXML[$template] = $templateId;
		}

		return $templateIdsXML;
	}
}
