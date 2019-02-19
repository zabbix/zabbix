/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

import java.util.HashMap;
import java.util.Map;
import java.util.HashSet;

import javax.management.InstanceNotFoundException;
import javax.management.MBeanAttributeInfo;
import javax.management.MBeanServerConnection;
import javax.management.ObjectName;
import javax.management.openmbean.CompositeData;
import javax.management.openmbean.TabularDataSupport;
import javax.management.remote.JMXConnector;
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

	static final int DISCOVERY_MODE_ATTRIBUTES = 0;
	static final int DISCOVERY_MODE_BEANS = 1;

	JMXItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);

		String jmx_endpoint;

		try
		{
			jmx_endpoint = request.getString(JSON_TAG_JMX_ENDPOINT);
		}
		catch (Exception e)
		{
			throw new ZabbixException(e);
		}

		try
		{
			url = new JMXServiceURL(jmx_endpoint);
			jmxc = null;
			mbsc = null;

			username = request.optString(JSON_TAG_USERNAME, null);
			password = request.optString(JSON_TAG_PASSWORD, null);
		}
		catch (Exception e)
		{
			throw new ZabbixException("%s: %s", e, jmx_endpoint);
		}
	}

	@Override
	JSONArray getValues() throws ZabbixException
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

			jmxc = ZabbixJMXConnectorFactory.connect(url, env);
			mbsc = jmxc.getMBeanServerConnection();

			for (String key : keys)
				values.put(getJSONValue(key));
		}
		catch (SecurityException e1)
		{
			JSONObject value = new JSONObject();

			logger.warn("cannot process keys '{}': {}: {}", new Object[] {keys, ZabbixException.getRootCauseMessage(e1), url});
			logger.debug("error caused by", e1);

			try
			{
				value.put(JSON_TAG_ERROR, ZabbixException.getRootCauseMessage(e1));
			}
			catch (JSONException e2)
			{
				Object[] logInfo = {JSON_TAG_ERROR, e1.getMessage(), ZabbixException.getRootCauseMessage(e2)};
				logger.warn("cannot add JSON attribute '{}' with message '{}': {}", logInfo);
				logger.debug("error caused by", e2);
			}

			for (int i = 0; i < keys.size(); i++)
				values.put(value);
		}
		catch (Exception e)
		{
			throw new ZabbixException("%s: %s", ZabbixException.getRootCauseMessage(e), url);
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
			String realAttributeName;
			String fieldNames = "";

			// Attribute name and composite data field names are separated by dots. On the other hand the
			// name may contain a dot too. In this case user needs to escape it with a backslash. Also the
			// backslash symbols in the name must be escaped. So a real separator is unescaped dot and
			// separatorIndex() is used to locate it.

			int sep = HelperFunctionChest.separatorIndex(attributeName);

			if (-1 != sep)
			{
				logger.trace("'{}' contains composite data", attributeName);

				realAttributeName = attributeName.substring(0, sep);
				fieldNames = attributeName.substring(sep + 1);
			}
			else
				realAttributeName = attributeName;

			// unescape possible dots or backslashes that were escaped by user
			realAttributeName = HelperFunctionChest.unescapeUserInput(realAttributeName);

			logger.trace("attributeName:'{}'", realAttributeName);
			logger.trace("fieldNames:'{}'", fieldNames);

			try
			{
				return getPrimitiveAttributeValue(mbsc.getAttribute(objectName, realAttributeName), fieldNames);
			}
			catch (InstanceNotFoundException e)
			{
				throw new ZabbixException("Object or attribute not found.");
			}
		}
		else if (item.getKeyId().equals("jmx.discovery"))
		{
			int argumentCount = item.getArgumentCount();

			if (2 < argumentCount)
				throw new ZabbixException("required key format: jmx.discovery[<discovery mode>,<object name>]");

			JSONArray counters = new JSONArray();
			ObjectName filter = (2 == argumentCount) ? new ObjectName(item.getArgument(2)) : null;

			int mode = DISCOVERY_MODE_ATTRIBUTES;
			if (0 != argumentCount)
			{
				String modeName = item.getArgument(1);

				if (modeName.equals("beans"))
					mode = DISCOVERY_MODE_BEANS;
				else if (!modeName.equals("attributes"))
					throw new ZabbixException("invalid discovery mode: " + modeName);
			}

			for (ObjectName name : mbsc.queryNames(filter, null))
			{
				logger.trace("discovered object '{}'", name);

				if (DISCOVERY_MODE_ATTRIBUTES == mode)
					discoverAttributes(counters, name);
				else
					discoverBeans(counters, name);
			}

			JSONObject mapping = new JSONObject();
			mapping.put(ItemChecker.JSON_TAG_DATA, counters);
			return mapping.toString();
		}
		else
			throw new ZabbixException("key ID '%s' is not supported", item.getKeyId());
	}

	private String getPrimitiveAttributeValue(Object dataObject, String fieldNames) throws Exception
	{
		logger.trace("drilling down with data object '{}' and field names '{}'", dataObject, fieldNames);

		if (null == dataObject)
			throw new ZabbixException("data object is null");

		if (fieldNames.equals(""))
		{
			try
			{
				if (isPrimitiveAttributeType(dataObject))
					return dataObject.toString();
				else
					throw new NoSuchMethodException();
			}
			catch (NoSuchMethodException e)
			{
				throw new ZabbixException("Data object type cannot be converted to string.");
			}
		}

		if (dataObject instanceof CompositeData)
		{
			logger.trace("'{}' contains composite data", dataObject);

			CompositeData comp = (CompositeData)dataObject;

			String dataObjectName;
			String newFieldNames = "";

			int sep = HelperFunctionChest.separatorIndex(fieldNames);

			if (-1 != sep)
			{
				dataObjectName = fieldNames.substring(0, sep);
				newFieldNames = fieldNames.substring(sep + 1);
			}
			else
				dataObjectName = fieldNames;

			// unescape possible dots or backslashes that were escaped by user
			dataObjectName = HelperFunctionChest.unescapeUserInput(dataObjectName);

			return getPrimitiveAttributeValue(comp.get(dataObjectName), newFieldNames);
		}
		else
			throw new ZabbixException("unsupported data object type along the path: %s", dataObject.getClass());
	}

	private void discoverAttributes(JSONArray counters, ObjectName name) throws Exception
	{
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
				Object[] logInfo = {name, attrInfo.getName(), ZabbixException.getRootCauseMessage(e)};
				logger.warn("attribute processing '{},{}' failed: {}", logInfo);
				logger.debug("error caused by", e);
			}
		}
	}

	private void discoverBeans(JSONArray counters, ObjectName name)
	{
		try
		{
			HashSet<String> properties = new HashSet<String>();
			JSONObject counter = new JSONObject();

			// Default properties are added.
			counter.put("{#JMXOBJ}", name);
			counter.put("{#JMXDOMAIN}", name.getDomain());
			properties.add("OBJ");
			properties.add("DOMAIN");

			for (Map.Entry<String, String> property : name.getKeyPropertyList().entrySet())
			{
				String key = property.getKey().toUpperCase();

				// Property key should only contain valid characters and should not be already added to attribute list.
				if (key.matches("^[A-Z0-9_\\.]+$") && !properties.contains(key))
				{
					counter.put("{#JMX" + key + "}" , property.getValue());
					properties.add(key);
				}
				else
					logger.trace("bean '{}' property '{}' was ignored", name, property.getKey());
			}

			counters.put(counter);
		}
		catch (Exception e)
		{
			logger.warn("bean processing '{}' failed: {}", name, ZabbixException.getRootCauseMessage(e));
			logger.debug("error caused by", e);
		}
	}

	private void findPrimitiveAttributes(JSONArray counters, ObjectName name, String descr, String attrPath, Object attribute) throws NoSuchMethodException, JSONException
	{
		logger.trace("drilling down with attribute path '{}'", attrPath);

		if (isPrimitiveAttributeType(attribute))
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

	private boolean isPrimitiveAttributeType(Object obj) throws NoSuchMethodException
	{
		Class<?>[] clazzez = {Boolean.class, Character.class, Byte.class, Short.class, Integer.class, Long.class,
			Float.class, Double.class, String.class, java.math.BigDecimal.class, java.math.BigInteger.class,
			java.util.Date.class, javax.management.ObjectName.class, java.util.concurrent.atomic.AtomicBoolean.class,
			java.util.concurrent.atomic.AtomicInteger.class, java.util.concurrent.atomic.AtomicLong.class};

		// check if the type is either primitive or overrides toString()
		return HelperFunctionChest.arrayContains(clazzez, obj.getClass()) ||
				(!(obj instanceof CompositeData)) && (!(obj instanceof TabularDataSupport)) &&
				(obj.getClass().getMethod("toString").getDeclaringClass() != Object.class);
	}
}
