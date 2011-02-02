package com.zabbix.proxy;

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
				throw new IllegalArgumentException("invalid 'username' and 'password' null-ness combination");
		}
		catch (Exception exception)
		{
			throw new ZabbixException(exception);
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

			jmxc = JMXConnectorFactory.connect(url, env);
			mbsc = jmxc.getMBeanServerConnection();

			for (ZabbixItem item : items)
				values.put(getJSONValue(item));
		}
		catch (Exception exception)
		{
			throw new ZabbixException(exception);
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
	protected String getStringValue(ZabbixItem item) throws Exception
	{
		if (item.getKeyId().equals("jmx"))
		{
			if (2 != item.getArgumentCount())
				throw new ZabbixException("required format: jmx[object_name,attribute_name]");

			ObjectName objectName = new ObjectName(item.getArgument(1));
			String attributeName = item.getArgument(2);

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
				throw new ZabbixException("required format: jmx.discovery");

			JSONArray counters = new JSONArray();

			for (ObjectName name : mbsc.queryNames(null, null))
			{
				for (MBeanAttributeInfo attrInfo : mbsc.getMBeanInfo(name).getAttributes())
				{
					if (!attrInfo.isReadable())
					{
						System.out.printf("attribute '%s,%s' is not readable\n", name, attrInfo.getName());
						continue;
					}

					try
					{
						String descr = (attrInfo.getName().equals(attrInfo.getDescription()) ? null : attrInfo.getDescription());
						findPrimitiveAttributes(counters, name, descr, attrInfo.getName(), mbsc.getAttribute(name, attrInfo.getName()));
					}
					catch (Exception exception)
					{
						System.out.printf("processing '%s,%s' failed with:\n",  name, attrInfo.getName());
						System.out.println(exception.getClass().getName() + " " + exception.getMessage());
					}
				}
			}

			JSONObject mapping = new JSONObject();
			mapping.put(item.getKey(), counters);
			return mapping.toString(2);
		}
		else
			throw new ZabbixException("Key ID '%s' is not supported", item.getKeyId());
	}

	private String getPrimitiveAttributeValue(Object attribute, String subAttributeNames) throws ZabbixException
	{
		if (null == attribute)
			throw new ZabbixException("Attribute is NULL");

		if (subAttributeNames.equals(""))
		{
			if (isPrimitiveAttributeType(attribute.getClass()))
				return attribute.toString();
			else
				throw new ZabbixException("Attribute type is not primitive");
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
			throw new ZabbixException("Unsupported attribute type along the path");
	}

	private void findPrimitiveAttributes(JSONArray counters, ObjectName name, String descr, String attrPath, Object attribute) throws JSONException
	{
		if (isPrimitiveAttributeType(attribute.getClass()))
		{
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
			CompositeData comp = (CompositeData)attribute;

			for (String key : comp.getCompositeType().keySet())
				findPrimitiveAttributes(counters, name, descr, attrPath + "." + key, comp.get(key));
		}
		else if (attribute instanceof TabularDataSupport || attribute.getClass().isArray())
		{
			// not supported
		}
		else
			System.out.println("unknown type: " + attribute.getClass().getName());
	}

	private boolean isPrimitiveAttributeType(Class<?> clazz)
	{
		Class<?>[] clazzez = { Boolean.class, Byte.class, Short.class, Integer.class, Long.class, Float.class, Double.class, String.class };

		return HelperFunctionChest.arrayContains(clazzez, clazz);
	}
}
