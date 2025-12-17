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

package com.zabbix.gateway;

class GeneralInformation
{
	static final String APPLICATION_NAME = "Zabbix Java Gateway";
	static final String REVISION_DATE = "29 October 2025";
	static final String REVISION = "{ZABBIX_REVISION}";
	static final String VERSION = "8.0.0alpha2";

	static void printVersion()
	{
		System.out.println(String.format("%s v%s (revision %s) (%s)", APPLICATION_NAME, VERSION, REVISION, REVISION_DATE));
	}
}
