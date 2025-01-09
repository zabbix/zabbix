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

import java.util.ArrayList;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

abstract class ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(ItemChecker.class);

	static final String JSON_TAG_DATA = "data";
	static final String JSON_TAG_ERROR = "error";
	static final String JSON_TAG_KEYS = "keys";
	static final String JSON_TAG_PASSWORD = "password";
	static final String JSON_TAG_REQUEST = "request";
	static final String JSON_TAG_RESPONSE = "response";
	static final String JSON_TAG_USERNAME = "username";
	static final String JSON_TAG_VALUE = "value";
	static final String JSON_TAG_JMX_ENDPOINT = "jmx_endpoint";

	static final String JSON_REQUEST_INTERNAL = "java gateway internal";
	static final String JSON_REQUEST_JMX = "java gateway jmx";

	static final String JSON_RESPONSE_FAILED = "failed";
	static final String JSON_RESPONSE_SUCCESS = "success";

	protected JSONObject request;
	protected ArrayList<String> keys;

	protected ItemChecker(JSONObject request) throws ZabbixException
	{
		this.request = request;

		try
		{
			JSONArray jsonKeys = request.getJSONArray(JSON_TAG_KEYS);
			keys = new ArrayList<String>();

			for (int i = 0; i < jsonKeys.length(); i++)
				keys.add(jsonKeys.getString(i));
		}
		catch (Exception e)
		{
			throw new ZabbixException(e);
		}
	}

	@SuppressWarnings("removal")
	protected final void finalize() throws Throwable
	{
	}

	JSONArray getValues() throws ZabbixException
	{
		JSONArray values = new JSONArray();

		for (String key : keys)
			values.put(getJSONValue(key));

		return values;
	}

	String getFirstKey()
	{
		return 0 == keys.size() ? null : keys.get(0);
	}

	protected final JSONObject getJSONValue(String key)
	{
		JSONObject value = new JSONObject();

		try
		{
			logger.debug("getting value for item '{}'", key);
			String text = getStringValue(key);
			logger.debug("received value '{}' for item '{}'", text, key);
			value.put(JSON_TAG_VALUE, text);
		}
		catch (Exception e1)
		{
			try
			{
				logger.debug("caught exception for item '{}'", key, e1);
				value.put(JSON_TAG_ERROR, ZabbixException.getRootCauseMessage(e1));
			}
			catch (JSONException e2)
			{
				Object[] logInfo = {JSON_TAG_ERROR, e1.getMessage(), ZabbixException.getRootCauseMessage(e2)};
				logger.warn("cannot add JSON attribute '{}' with message '{}': {}", logInfo);
				logger.debug("error caused by", e2);
			}
		}

		return value;
	}

	protected abstract String getStringValue(String key) throws Exception;
}
