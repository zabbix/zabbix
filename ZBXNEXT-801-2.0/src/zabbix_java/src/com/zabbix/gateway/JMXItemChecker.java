/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

package com.zabbix.gateway;

import java.util.HashMap;
import java.util.Vector;

import javax.management.MBeanAttributeInfo;
import javax.management.MBeanInfo;
import javax.management.MBeanServerConnection;
import javax.management.ObjectName;
import javax.management.openmbean.CompositeData;
import javax.management.openmbean.TabularDataSupport;
import javax.management.remote.JMXConnector;
import javax.management.remote.JMXConnectorFactory;
import javax.management.remote.JMXServiceURL;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class JMXItemChecker extends ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(JMXItemChecker.class);

	private JMXServiceURL url;
	private JMXConnector jmxc;
	private MBeanServerConnection mbsc;

	private String username;
	private String password;

	public JMXItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);

		try
		{
			String conn = request.getString(JSON_TAG_CONN);
			int port = request.getInt(JSON_TAG_PORT);

			url = new JMXServiceURL("service:jmx:rmi:///jndi/rmi://" + conn + ":" + port + "/jmxrmi");
			jmxc = null;
			mbsc = null;

			username = request.optString(JSON_TAG_USERNAME, null);
			password = request.optString(JSON_TAG_PASSWORD, null);

			if (null != username && null == password || null == username && null != password)
				throw new IllegalArgumentException("invalid username and password nullness combination");
		}
		catch (Exception e)
		{
			throw new ZabbixException(e);
		}
	}

	@Override
	public JSONArray getValues() throws ZabbixException
	{
		JSONArray values = new JSONArray();

		try
		{
			HashMap<String, String[]> env = null;

			if (null != username && null != password)
			{
				env = new HashMap<String, String[]>();
				env.put(JMXConnector.CREDENTIALS, new String[] {username, password});
			}

			logger.debug("connecting to JMX agent at {}", url);
			jmxc = JMXConnectorFactory.connect(url, env);
			mbsc = jmxc.getMBeanServerConnection();

			for (String key : keys)
				values.put(getJSONValue(key));
		}
		catch (Exception e)
		{
			throw new ZabbixException(e);
		}
		finally
		{
			try { if (null != jmxc) jmxc.close(); } catch (java.io.IOException exception) { }

			jmxc = null;
			mbsc = null;
		}

		return values;
	}

	@Override
	protected String getStringValue(String key) throws Exception
	{
		ZabbixItem item = new ZabbixItem(key);

		if (item.getKeyId().equals("jmx"))
		{
			if (2 != item.getArgumentCount())
				throw new ZabbixException("required key format: jmx[<object name>,<attribute name>]");

			ObjectName objectName = new ObjectName(item.getArgument(1));
			String attributeName = item.getArgument(2);

			logger.trace("looking for value of primitive type for '{},{}'", objectName, attributeName);

			String subAttributeNames = "";
			int dot = attributeName.indexOf('.');

			if (-1 != dot)
			{
				subAttributeNames = attributeName.substring(dot + 1);
				attributeName = attributeName.substring(0, dot);
			}

			return getPrimitiveAttributeValue(mbsc.getAttribute(objectName, attributeName), subAttributeNames);
		}
		else if (item.getKeyId().equals("jmx.discovery"))
		{
			if (0 != item.getArgumentCount())
				throw new ZabbixException("required key format: jmx.discovery");

			JSONArray counters = new JSONArray();

			for (ObjectName name : mbsc.queryNames(null, null))
			{
				logger.trace("discovered object '{}'", name);

				for (MBeanAttributeInfo attrInfo : mbsc.getMBeanInfo(name).getAttributes())
				{
					logger.trace("discovered attribute '{}'", attrInfo.getName());

					if (!attrInfo.isReadable())
					{
						logger.trace("attribute not readable, skipping");
						continue;
					}

					try
					{
						logger.trace("looking for attributes of primitive types");
						String descr = (attrInfo.getName().equals(attrInfo.getDescription()) ? null : attrInfo.getDescription());
						findPrimitiveAttributes(counters, name, descr, attrInfo.getName(), mbsc.getAttribute(name, attrInfo.getName()));
					}
					catch (Exception e)
					{
						Object[] logInfo = {name, attrInfo.getName(), e};
						logger.trace("processing '{},{}' failed", logInfo);
					}
				}
			}

			JSONObject mapping = new JSONObject();
			mapping.put(ItemChecker.JSON_TAG_DATA, counters);
			return mapping.toString(2);
		}
		else
			throw new ZabbixException("key ID '%s' is not supported", item.getKeyId());
	}

	private String getPrimitiveAttributeValue(Object attribute, String subAttributeNames) throws ZabbixException
	{
		logger.trace("drilling down with attribute '{}' and subattributes '{}'", attribute, subAttributeNames);

		if (null == attribute)
			throw new ZabbixException("attribute is null");

		if (subAttributeNames.equals(""))
		{
			if (isPrimitiveAttributeType(attribute.getClass()))
				return attribute.toString();
			else
				throw new ZabbixException("attribute type is not primitive: %s" + attribute.getClass());
		}
		else if (attribute instanceof CompositeData)
		{
			CompositeData comp = (CompositeData)attribute;

			int dot = subAttributeNames.indexOf('.');

			if (-1 == dot)
				return getPrimitiveAttributeValue(comp.get(subAttributeNames), "");
			else
				return getPrimitiveAttributeValue(comp.get(subAttributeNames.substring(0, dot)), subAttributeNames.substring(dot + 1));
		}
		else
			throw new ZabbixException("unsupported attribute type along the path: %s", attribute.getClass());
	}

	private void findPrimitiveAttributes(JSONArray counters, ObjectName name, String descr, String attrPath, Object attribute) throws JSONException
	{
		logger.trace("drilling down with attribute path '{}'", attrPath);

		if (isPrimitiveAttributeType(attribute.getClass()))
		{
			logger.trace("found attribute of a primitive type: {}", attribute.getClass());

			JSONObject counter = new JSONObject();

			counter.put("{#JMXDESC}", null == descr ? name + "," + attrPath : descr);
			counter.put("{#JMXOBJ}", name);
			counter.put("{#JMXATTR}", attrPath);
			counter.put("{#JMXTYPE}", attribute.getClass().getName());
			counter.put("{#JMXVALUE}", attribute.toString());

			counters.put(counter);
		}
		else if (attribute instanceof CompositeData)
		{
			logger.trace("found attribute of a composite type: {}", attribute.getClass());

			CompositeData comp = (CompositeData)attribute;

			for (String key : comp.getCompositeType().keySet())
				findPrimitiveAttributes(counters, name, descr, attrPath + "." + key, comp.get(key));
		}
		else if (attribute instanceof TabularDataSupport || attribute.getClass().isArray())
		{
			logger.trace("found attribute of a known, unsupported type: {}", attribute.getClass());
		}
		else
			logger.trace("found attribute of an unknown, unsupported type: {}", attribute.getClass());
	}

	private boolean isPrimitiveAttributeType(Class<?> clazz)
	{
		Class<?>[] clazzez = {Boolean.class, Byte.class, Short.class, Integer.class, Long.class, Float.class, Double.class, String.class};

		return HelperFunctionChest.arrayContains(clazzez, clazz);
	}
}
