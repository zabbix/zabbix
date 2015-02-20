/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

package com.zabbix.gateway;

import java.util.Vector;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

abstract class ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(ItemChecker.class);

	public static final String JSON_TAG_CONN = "conn";
	public static final String JSON_TAG_DATA = "data";
	public static final String JSON_TAG_ERROR = "error";
	public static final String JSON_TAG_KEYS = "keys";
	public static final String JSON_TAG_PASSWORD = "password";
	public static final String JSON_TAG_PORT = "port";
	public static final String JSON_TAG_REQUEST = "request";
	public static final String JSON_TAG_RESPONSE = "response";
	public static final String JSON_TAG_USERNAME = "username";
	public static final String JSON_TAG_VALUE = "value";

	public static final String JSON_REQUEST_INTERNAL = "java gateway internal";
	public static final String JSON_REQUEST_JMX = "java gateway jmx";

	public static final String JSON_RESPONSE_FAILED = "failed";
	public static final String JSON_RESPONSE_SUCCESS = "success";

	protected JSONObject request;
	protected Vector<String> keys;

	protected ItemChecker(JSONObject request) throws ZabbixException
	{
		this.request = request;

		try
		{
			JSONArray jsonKeys = request.getJSONArray(JSON_TAG_KEYS);
			keys = new Vector<String>();

			for (int i = 0; i < jsonKeys.length(); i++)
				keys.add(jsonKeys.getString(i));
		}
		catch (Exception e)
		{
			throw new ZabbixException(e);
		}
	}

	public JSONArray getValues() throws ZabbixException
	{
		JSONArray values = new JSONArray();

		for (String key : keys)
			values.put(getJSONValue(key));

		return values;
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
				value.put(JSON_TAG_ERROR, e1.getMessage());
			}
			catch (JSONException e2)
			{
				Object[] logInfo = {JSON_TAG_ERROR, e1.getMessage(), e2};
				logger.warn("cannot add JSON attribute '{}' with message '{}'", logInfo);
			}
		}

		return value;
	}

	protected abstract String getStringValue(String key) throws Exception;
}
