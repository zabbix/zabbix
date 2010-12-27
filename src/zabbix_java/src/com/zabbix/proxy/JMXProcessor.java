package com.zabbix.proxy;

import java.util.TreeSet;

import javax.management.MBeanAttributeInfo;
import javax.management.MBeanInfo;
import javax.management.MBeanServerConnection;
import javax.management.ObjectName;
import javax.management.openmbean.CompositeData;
import javax.management.remote.JMXConnector;
import javax.management.remote.JMXConnectorFactory;
import javax.management.remote.JMXServiceURL;

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

			value.append("Domains:\n");

			for (String domain: mbsc.getDomains())
			{
				value.append("\tDomain = " + domain + "\n");
			}

			value.append("\nMBeanServer default domain = " + mbsc.getDefaultDomain() + "\n");

			value.append("\nMBean count = " + mbsc.getMBeanCount() + "\n");

			value.append("\nQuery MBeanServer MBeans:\n\n");

			for (ObjectName name: new TreeSet<ObjectName>(mbsc.queryNames(null, null)))
			{
				value.append("  ***  ObjectName = " + name + "\n\n");

				MBeanInfo info = mbsc.getMBeanInfo(name);
				MBeanAttributeInfo[] attrInfo = info.getAttributes();

				for (int i = 0; i < attrInfo.length; i++)
				{
					value.append("\tNAME: " + attrInfo[i].getName() + "\n");
					value.append("\tDESC: " + attrInfo[i].getDescription() + "\n");
					value.append("\tTYPE: " + attrInfo[i].getType().toString() + "\n");
					value.append("\tREAD: " + attrInfo[i].isReadable() + "\n");
					value.append("\tWRITE: " + attrInfo[i].isWritable() + "\n");

					try
					{
						if (attrInfo[i].getType().equals("javax.management.openmbean.CompositeData"))
						{
							appendFields(value, "\tVALUE: ", (CompositeData)mbsc.getAttribute(name, attrInfo[i].getName()));
						}
						else
							value.append("\tVALUE: " + mbsc.getAttribute(name, attrInfo[i].getName()) + "\n");
					}
					catch (Exception e)
					{
						value.append("\tVALUE: caught exception: " + e + "\n");
					}

					value.append("\n");
				}
			}

			jmxc.close();

			return value.toString();
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

	private static void appendFields(StringBuilder value, String prefix, CompositeData attribute)
	{
		for (String key: attribute.getCompositeType().keySet())
		{
			Object object = attribute.get(key);

			if (object instanceof CompositeData)
			{
				appendFields(value, prefix + "." + key, (CompositeData)object);
			}
			else
				value.append(prefix + "." + key + " = " + attribute.get(key) + "\n");
		}
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
}
