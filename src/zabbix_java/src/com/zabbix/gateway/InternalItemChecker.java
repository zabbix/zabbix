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

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class InternalItemChecker extends ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(InternalItemChecker.class);

	InternalItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);
	}

	@Override
	protected String getStringValue(String key) throws Exception
	{
		ZabbixItem item = new ZabbixItem(key);

		if (item.getKeyId().equals("zabbix"))
		{
			if (3 != item.getArgumentCount() ||
					!item.getArgument(1).equals("java") ||
					!item.getArgument(2).equals(""))
				throw new ZabbixException("required key format: zabbix[java,,<parameter>]");

			String parameter = item.getArgument(3);

			if (parameter.equals("version"))
				return GeneralInformation.VERSION;
			else if (parameter.equals("ping"))
				return "1";
			else
				throw new ZabbixException("third parameter '%s' is not supported", parameter);
		}
		else
			throw new ZabbixException("key ID '%s' is not supported", item.getKeyId());
	}
}
