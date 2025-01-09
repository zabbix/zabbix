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

import java.io.IOException;
import java.util.HashMap;
import java.util.Map;
import java.util.HashSet;
import javax.management.AttributeList;

import javax.management.InstanceNotFoundException;
import javax.management.AttributeNotFoundException;
import javax.management.MBeanAttributeInfo;
import javax.management.MBeanServerConnection;
import javax.management.ObjectName;
import javax.management.MalformedObjectNameException;
import javax.management.openmbean.CompositeData;
import javax.management.openmbean.TabularDataSupport;
import javax.management.openmbean.TabularData;
import javax.management.remote.JMXConnector;
import javax.management.remote.JMXServiceURL;
import javax.rmi.ssl.SslRMIClientSocketFactory;

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
	private String jmx_endpoint;

	private enum DiscoveryMode {
		ATTRIBUTES,
		BEANS
	}

	private static HashMap<String, Boolean> useRMISSLforURLHintCache = new HashMap<String, Boolean>();

	JMXItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);

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

			// We used to disallow other JNDI service providers than "rmi" here for security
			// reasonse but then we decided Zabbix shouldn't interfere this way and it's the
			// task of admin to ensure security when using different JNDI service providers.

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
			HashMap<String, Object> env = new HashMap<String, Object>();

			if (null != username && null != password)
			{
				env.put(JMXConnector.CREDENTIALS, new String[] {username, password});
			}

			if (!useRMISSLforURLHintCache.containsKey(url.getURLPath()) ||
					!useRMISSLforURLHintCache.get(url.getURLPath()))
			{
				try
				{
					jmxc = ZabbixJMXConnectorFactory.connect(url, env);
					useRMISSLforURLHintCache.put(url.getURLPath(), false);
				}
				catch (IOException e)
				{
					env.put("com.sun.jndi.rmi.factory.socket", new SslRMIClientSocketFactory());
					jmxc = ZabbixJMXConnectorFactory.connect(url, env);
					useRMISSLforURLHintCache.put(url.getURLPath(), true);
				}
			}
			else
			{
				try
				{
					env.put("com.sun.jndi.rmi.factory.socket", new SslRMIClientSocketFactory());
					jmxc = ZabbixJMXConnectorFactory.connect(url, env);
					useRMISSLforURLHintCache.put(url.getURLPath(), true);
				}
				catch (IOException e)
				{
					env.remove("com.sun.jndi.rmi.factory.socket");
					jmxc = ZabbixJMXConnectorFactory.connect(url, env);
					useRMISSLforURLHintCache.put(url.getURLPath(), false);
				}
			}

			mbsc = jmxc.getMBeanServerConnection();
			logger.debug("using RMI SSL for " + url.getURLPath() + ": " + useRMISSLforURLHintCache.get(url.getURLPath()));

			for (String key : keys)
				values.put(getJSONValue(key));
		}
		catch (SecurityException e1)
		{
			JSONObject value = new JSONObject();

			logger.warn("cannot process keys '{}': {}: {}", new Object[] {keys,
					ZabbixException.getRootCauseMessage(e1), url});
			logger.debug("error caused by", e1);

			try
			{
				value.put(JSON_TAG_ERROR, ZabbixException.getRootCauseMessage(e1));
			}
			catch (JSONException e2)
			{
				Object[] logInfo = {JSON_TAG_ERROR, e1.getMessage(),
						ZabbixException.getRootCauseMessage(e2)};
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
		int argumentCount = item.getArgumentCount();

		if (item.getKeyId().equals("jmx"))
		{
			if (2 != argumentCount && 3 != argumentCount)
				throw new ZabbixException("required key format: jmx[<mbean name>,<attribute name>,<unique short description>]");

			ObjectName mbeanName = new ObjectName(item.getArgument(1));
			String attributeName = item.getArgument(2);

			// Attribute name and composite data field names are separated by dots. On the other hand the
			// name may contain a dot too. In this case user needs to escape it with a backslash. Also the
			// backslash symbols in the name must be escaped. So a real separator is unescaped dot and
			// separatorIndex() is used to locate it.

			int separatorIndex = HelperFunctionChest.separatorIndex(attributeName);

			// MBean needs the first part of the attribute. The rest (if available) is so called metric.
			String mbeanAttribute;
			String metric;

			if (-1 != separatorIndex)
			{
				mbeanAttribute = attributeName.substring(0, separatorIndex);
				metric = attributeName.substring(separatorIndex + 1);
			}
			else
			{
				mbeanAttribute = attributeName;
				metric = "";
			}

			// unescape possible dots or backslashes that were escaped by user
			mbeanAttribute = HelperFunctionChest.unescapeUserInput(mbeanAttribute);

			logger.debug("obtaining [{}] [{}] [{}]", mbeanName, mbeanAttribute, metric);

			try
			{
				Object dataObject = mbsc.getAttribute(mbeanName, mbeanAttribute);

				return getValueByMetric(dataObject, metric);
			}
			catch (AttributeNotFoundException e)
			{
				throw new ZabbixException("Attribute not found: %s", ZabbixException.getRootCauseMessage(e));
			}
			catch (InstanceNotFoundException e)
			{
				throw new ZabbixException("Object or attribute not found: %s", ZabbixException.getRootCauseMessage(e));
			}
		}
		else if (item.getKeyId().equals("jmx.discovery") || item.getKeyId().equals("jmx.get"))
		{
			if (3 < argumentCount)
				throw new ZabbixException("required key format: " + item.getKeyId() +
						"[<discovery mode>,<object name>,<unique short description>]");

			ObjectName filter;

			try
			{
				filter = (2 <= argumentCount) ? new ObjectName(item.getArgument(2)) : null;
			}
			catch (MalformedObjectNameException e)
			{
				throw new ZabbixException("invalid object name format: " + item.getArgument(2));
			}

			boolean mapped = item.getKeyId().equals("jmx.discovery");
			JSONArray counters = new JSONArray();
			DiscoveryMode mode = DiscoveryMode.ATTRIBUTES;
			if (0 < argumentCount)
			{
				String modeName = item.getArgument(1);
				if (modeName.equals("beans"))
					mode = DiscoveryMode.BEANS;
				else if (!modeName.equals("attributes"))
					throw new ZabbixException("invalid discovery mode: " + modeName);
			}

			switch(mode)
			{
				case ATTRIBUTES:
					discoverAttributes(counters, filter, mapped);
					break;
				case BEANS:
					discoverBeans(counters, filter, mapped);
					break;
			}

			if (mapped)
			{
				JSONObject mapping = new JSONObject();
				mapping.put(ItemChecker.JSON_TAG_DATA, counters);
				return mapping.toString();
			}
			else
			{
				return counters.toString();
			}
		}
		else
			throw new ZabbixException("key ID '%s' is not supported", item.getKeyId());
	}

	private String getValueByMetric(Object dataObject, String metric) throws Exception
	{
		if (null == dataObject)
			throw new ZabbixException("data object is null");

		logger.trace("drilling down the {} to find metric '{}'", dataObject, metric);

		while (!metric.equals(""))
		{
			// get next objectName from the metric
			int separatorIndex = HelperFunctionChest.separatorIndex(metric);
			String objectName;

			if (-1 != separatorIndex)
			{
				objectName = metric.substring(0, separatorIndex);
				metric = metric.substring(separatorIndex + 1);
			}
			else
			{
				objectName = metric;
				metric = "";
			}

			// unescape possible dots or backslashes that were escaped by user
			objectName = HelperFunctionChest.unescapeUserInput(objectName);

			// get next object
			if (dataObject instanceof CompositeData)
			{
				logger.trace("[{}] contains composite data", metric);

				CompositeData obj = (CompositeData)dataObject;

				dataObject = obj.get(objectName);
			}
			else if (dataObject instanceof TabularData)
			{
				logger.trace("[{}] contains tabular data", metric);

				TabularData obj = (TabularData)dataObject;

				dataObject = obj.get(new String[]{objectName});
			}
			else
			{
				throw new ZabbixException("unsupported data object type along the path: %s", dataObject.getClass());
			}
		}

		try
		{
			if (isPrimitiveMetricType(dataObject))
			{
				logger.trace("found: {}", dataObject.toString());
				return dataObject.toString();
			}
			else
			{
				throw new NoSuchMethodException();
			}
		}
		catch (NoSuchMethodException e)
		{
			throw new ZabbixException("The value cannot be converted to string.");
		}
	}

	private JSONArray getTabularData(TabularData data) throws JSONException
	{
		JSONArray values = new JSONArray();

		for (Object value : data.values())
		{
			JSONObject tmp = getCompositeDataValues((CompositeData)value);

			if (tmp.length() > 0)
				values.put(tmp);
		}

		return values;
	}

	private JSONObject getCompositeDataValues(CompositeData compData) throws JSONException
	{
		JSONObject value = new JSONObject();

		for (String key : compData.getCompositeType().keySet())
		{
			Object data = compData.get(key);

			if (data == null)
			{
				value.put(key, JSONObject.NULL);
			}
			else if (data.getClass().isArray())
			{
				logger.trace("found attribute of a known, unsupported type: {}", data.getClass());
				continue;
			}
			else if (data instanceof TabularData)
			{
				value.put(key, getTabularData((TabularData)data));
			}
			else if (data instanceof CompositeData)
			{
				value.put(key, getCompositeDataValues((CompositeData)data));
			}
			else
				value.put(key, data);
		}

		return value;
	}

	private void discoverAttributes(JSONArray counters, ObjectName filter, boolean propertiesAsMacros) throws Exception
	{
		for (ObjectName name : mbsc.queryNames(filter, null))
		{
			Map<String, Object> values = new HashMap<String, Object>();
			MBeanAttributeInfo[] attributeArray = mbsc.getMBeanInfo(name).getAttributes();

			if (0 == attributeArray.length)
			{
				logger.trace("object has no attributes");
				return;
			}

			String[] attributeNames = getAttributeNames(attributeArray);
			AttributeList attributes;
			String discoveredObjKey = jmx_endpoint + "#" + name;
			Long expirationTime = JavaGateway.iterativeObjects.get(discoveredObjKey);
			long now = System.currentTimeMillis();

			if (null != expirationTime && now <= expirationTime)
			{
				attributes = getAttributesIterative(name, attributeNames);
			}
			else
			{
				try
				{
					attributes = getAttributesBulk(name, attributeNames);

					if (null != expirationTime)
						JavaGateway.iterativeObjects.remove(discoveredObjKey);
				}
				catch (Exception e)
				{
					attributes = getAttributesIterative(name, attributeNames);

					// This object's attributes will be collected iteratively for next 24h. After that it will
					// be checked if it is possible to successfully collect all attributes in bulk mode.
					JavaGateway.iterativeObjects.put(discoveredObjKey, now + SocketProcessor.MILLISECONDS_IN_HOUR * 24);
				}
			}

			if (attributes.isEmpty())
			{
				logger.warn("cannot process any attribute for object '{}'", name);
				return;
			}

			for (javax.management.Attribute attribute : attributes.asList())
				values.put(attribute.getName(), attribute.getValue());

			for (MBeanAttributeInfo attrInfo : attributeArray)
			{
				logger.trace("discovered attribute '{}'", attrInfo.getName());

				Object attribute;

				if (null == (attribute = values.get(attrInfo.getName())))
				{
					logger.trace("cannot retrieve attribute value, skipping");
					continue;
				}

				try
				{
					String descr = (attrInfo.getName().equals(attrInfo.getDescription()) ? null :
							attrInfo.getDescription());

					if (attribute instanceof TabularData)
					{
						logger.trace("looking for attributes of tabular types");

						formatPrimitiveTypeResult(counters, name, descr, attrInfo.getName(), attribute,
							propertiesAsMacros, getTabularData((TabularData)attribute));
					}
					else
					{
						logger.trace("looking for attributes of primitive types");
						getAttributeFields(counters, name, descr, attrInfo.getName(), attribute,
								propertiesAsMacros);
					}
				}
				catch (Exception e)
				{
					Object[] logInfo = {name, attrInfo.getName(), ZabbixException.getRootCauseMessage(e)};
					logger.warn("attribute processing '{},{}' failed: {}", logInfo);
					logger.debug("error caused by", e);
				}
			}
		}
	}

	private String[] getAttributeNames(MBeanAttributeInfo[] attributeArray)
	{
		int i = 0;
		String[] attributeNames = new String[attributeArray.length];

		for (MBeanAttributeInfo attrInfo : attributeArray)
		{
			if (!attrInfo.isReadable())
			{
				logger.trace("attribute '{}' not readable, skipping", attrInfo.getName());
				continue;
			}

			attributeNames[i++] = attrInfo.getName();
		}

		return attributeNames;
	}

	private AttributeList getAttributesBulk(ObjectName name, String[] attributeNames) throws Exception
	{
		return mbsc.getAttributes(name, attributeNames);
	}

	private AttributeList getAttributesIterative(ObjectName name, String[] attributeNames)
	{
		AttributeList attributes = new AttributeList();

		for (String attributeName: attributeNames)
		{
			try
			{
				Object attrValue = mbsc.getAttribute(name, attributeName);
				attributes.add(new javax.management.Attribute(attributeName, attrValue));
			}
			catch (Exception e)
			{
				Object[] logInfo = {name, attributeName, ZabbixException.getRootCauseMessage(e)};
				logger.warn("attribute processing '{},{}' failed: {}", logInfo);
				logger.debug("error caused by", e);
			}
		}

		return attributes;
	}

	private void discoverBeans(JSONArray counters, ObjectName filter, boolean propertiesAsMacros) throws Exception
	{
		for (ObjectName name : mbsc.queryNames(filter, null))
		{
			logger.trace("discovered bean '{}'", name);

			try
			{
				JSONObject counter = new JSONObject();

				if (propertiesAsMacros)
				{
					HashSet<String> properties = new HashSet<String>();

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
				}
				else
				{
					JSONObject properties = new JSONObject();
					counter.put("object", name);
					counter.put("domain", name.getDomain());

					for (Map.Entry<String, String> property : name.getKeyPropertyList().entrySet())
					{
						String key = property.getKey();
						properties.put(key, property.getValue());
					}
					counter.put("properties", properties);
				}

				counters.put(counter);
			}
			catch (Exception e)
			{
				logger.warn("bean processing '{}' failed: {}", name, ZabbixException.getRootCauseMessage(e));
				logger.debug("error caused by", e);
			}
		}
	}

	private void getAttributeFields(JSONArray counters, ObjectName name, String descr, String attrPath,
			Object attribute, boolean propertiesAsMacros) throws NoSuchMethodException, JSONException
	{
		if (null == attribute || isPrimitiveMetricType(attribute))
		{
			logger.trace("found attribute of a primitive type: {}", null == attribute ? "null" :
					attribute.getClass());
			formatPrimitiveTypeResult(counters, name, descr, attrPath, attribute, propertiesAsMacros, attribute);
		}
		else if (attribute instanceof CompositeData)
		{
			logger.trace("found attribute of a composite type: {}", attribute.getClass());

			CompositeData comp = (CompositeData)attribute;

			for (String key : comp.getCompositeType().keySet())
			{
				logger.trace("drilling down with attribute path '{}'", attrPath + "." + key);
				getAttributeFields(counters, name, comp.getCompositeType().getDescription(key),
						attrPath + "." + key, comp.get(key), propertiesAsMacros);
			}
		}
		else if (attribute.getClass().isArray())
		{
			logger.trace("found attribute of a known, unsupported type: {}", attribute.getClass());
		}
		else
			logger.trace("found attribute of an unknown, unsupported type: {}", attribute.getClass());
	}

	private void formatPrimitiveTypeResult(JSONArray counters, ObjectName name, String descr, String attrPath,
			Object attribute, boolean propertiesAsMacros, Object value) throws JSONException
	{
		JSONObject counter = new JSONObject();

		String checkedDescription = null == descr ? name + "," + attrPath : descr;
		Object checkedType = null == attribute ? JSONObject.NULL : attribute.getClass().getName();
		Object checkedValue = null == value ? JSONObject.NULL : value.toString();

		if (propertiesAsMacros)
		{
			counter.put("{#JMXDESC}", checkedDescription);
			counter.put("{#JMXOBJ}", name);
			counter.put("{#JMXATTR}", attrPath);
			counter.put("{#JMXTYPE}", checkedType);
			counter.put("{#JMXVALUE}", checkedValue);
		}
		else
		{
			counter.put("name", attrPath);
			counter.put("object", name);
			counter.put("description", checkedDescription);
			counter.put("type", checkedType);
			counter.put("value", checkedValue);
		}

		counters.put(counter);
	}

	private boolean isPrimitiveMetricType(Object obj) throws NoSuchMethodException
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

	public void cleanUseRMISSLforURLHintCache()
	{
		int s = useRMISSLforURLHintCache.size();
		useRMISSLforURLHintCache.clear();
		logger.debug("Finished cleanup of RMI SSL hint cache. " + s + " entries removed.");
	}
}
