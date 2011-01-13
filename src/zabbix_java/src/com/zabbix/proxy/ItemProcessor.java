package com.zabbix.proxy;

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

class ItemProcessor
{
	public static final String JSON_TAG_ERROR_MSG = "error msg";
	public static final String JSON_TAG_VALUE = "value";

	private JMXServiceURL url;
	private JMXConnector jmxc;
	private MBeanServerConnection mbsc;

	public ItemProcessor(String conn, int port) throws java.net.MalformedURLException
	{
		url = new JMXServiceURL("service:jmx:rmi:///jndi/rmi://" + conn + ":" + port + "/jmxrmi");
		jmxc = null;
		mbsc = null;
	}

	public JSONArray processAll(Vector<ZabbixItem> items) throws java.io.IOException
	{
		JSONArray values = new JSONArray();

		jmxc = JMXConnectorFactory.connect(url, null);
		mbsc = jmxc.getMBeanServerConnection();

		for (ZabbixItem item : items)
			values.put(process(item));

		try { if (null != jmxc) jmxc.close(); } catch (java.io.IOException exception) { }

		jmxc = null;
		mbsc = null;

		return values;
	}

	private JSONObject process(ZabbixItem item)
	{
		JSONObject value = new JSONObject();

		try
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

				value.put(JSON_TAG_VALUE, getPrimitiveAttributeValue(mbsc.getAttribute(objectName, attributeName), subAttributeNames));
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

				jmxc.close();

				value.put(JSON_TAG_VALUE, counters.toString(2));
			}
			else
				throw new ZabbixException("Key ID '%s' is not supported", item.getKeyId());
		}
		catch (Exception exception)
		{
			try
			{
				value.put(JSON_TAG_ERROR_MSG, exception.getMessage());
			}
			catch (JSONException jsonException)
			{
				throw new RuntimeException(jsonException);
			}
		}

		return value;
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
