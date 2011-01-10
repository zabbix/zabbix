package com.zabbix.proxy;

import java.util.TreeSet;

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

class JMXProcessor
{
	public static String getValue(String key) throws Exception
	{
		ZabbixItem item = new ZabbixItem(key);

		if (item.getKeyId().equals("jmx.discovery"))
		{
			StringBuilder value = new StringBuilder();

			if (2 != item.getArgumentCount())
				throw new IllegalArgumentException("required format: jmx.discovery[host,port]");

			String host = item.getArgument(1);
			String port = item.getArgument(2);

			JMXServiceURL url = new JMXServiceURL("service:jmx:rmi:///jndi/rmi://" + host + ":" + port + "/jmxrmi");
			JMXConnector jmxc = JMXConnectorFactory.connect(url, null);
			MBeanServerConnection mbsc = jmxc.getMBeanServerConnection();

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

			return counters.toString(2);
		}
		else if (item.getKeyId().equals("jmx"))
		{
			if (4 != item.getArgumentCount())
				throw new IllegalArgumentException("required format: jmx[host,port,object_name,property_name]");

			String host = item.getArgument(1);
			String port = item.getArgument(2);
			ObjectName objectName = new ObjectName(item.getArgument(3));
			String propertyName = item.getArgument(4);
			String subproperties = "";
			int dot = propertyName.indexOf('.');

			if (-1 != dot)
			{
				subproperties = propertyName.substring(dot + 1);
				propertyName = propertyName.substring(0, dot);
			}

			JMXServiceURL url = new JMXServiceURL("service:jmx:rmi:///jndi/rmi://" + host + ":" + port + "/jmxrmi");
			JMXConnector jmxc = JMXConnectorFactory.connect(url, null);
			MBeanServerConnection mbsc = jmxc.getMBeanServerConnection();

			String value = getPropertyValue(mbsc.getAttribute(objectName, propertyName), subproperties);

			jmxc.close();

			return value;
		}
		else
			return "ZBX_NOTSUPPORTED";
	}

	private static void findPrimitiveAttributes(JSONArray counters, ObjectName name, String descr, String attrPath, Object attribute) throws Exception
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

	private static String getPropertyValue(Object attribute, String subproperties)
	{
		if (null == attribute)
			return "null attribute";

		int dot;

		if (subproperties.equals(""))
			dot = -1;
		else if (-1 != subproperties.indexOf('.'))
			dot = subproperties.indexOf('.');
		else
			dot = subproperties.length();

		if (-1 != dot)
			return getPropertyValue(((CompositeData)attribute).get(subproperties.substring(0, dot)),
					subproperties.substring(dot == subproperties.length() ? dot : dot + 1));
		else
			return attribute.toString();
	}

	public static boolean isPrimitiveAttributeType(Class<?> clazz)
	{
		Class<?>[] clazzez = { Boolean.class, Byte.class, Short.class, Integer.class, Long.class, Float.class, Double.class, String.class };

		return HelperFunctionChest.arrayContains(clazzez, clazz);
	}
}
