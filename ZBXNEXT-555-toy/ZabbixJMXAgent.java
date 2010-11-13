import javax.management.remote.JMXConnector;
import javax.management.remote.JMXConnectorFactory;
import javax.management.remote.JMXServiceURL;
import javax.management.ObjectName;
import javax.management.JMX;
import javax.management.MBeanServerConnection;
import javax.management.MBeanInfo;
import javax.management.MBeanAttributeInfo;
import javax.management.openmbean.CompositeData;
import javax.management.openmbean.TabularDataSupport;

import java.util.Arrays;
import java.util.Set;
import java.util.TreeSet;
import java.util.Vector;

import java.io.*;
import java.net.*;

class ZabbixItem
{
	private String keyId = null;
	private Vector<String> args = null;

	public ZabbixItem(String key)
	{
		int bracket = key.indexOf('[');

		if (-1 != bracket)
		{
			if (key.charAt(key.length() - 1) != ']')
				throw new IllegalArgumentException("malformed item key: " + key);

			keyId = key.substring(0, bracket);
			args = parseArguments(key.substring(bracket + 1, key.length() - 1));
		}
		else
			keyId = key;
	}

	public String getKeyId()
	{
		return keyId;
	}
	
	public String getArgument(int n)
	{
		return args.elementAt(n - 1);
	}

	public int getArgumentCount()
	{
		return null == args ? 0 : args.size();
	}

	private Vector<String> parseArguments(String keyArgs)
	{
		Vector<String> args = new Vector<String>();

		while (!keyArgs.equals(""))
		{
			if ('"' == keyArgs.charAt(0))
			{
				int index = 1;

				while (index < keyArgs.length())
				{
					if ('"' == keyArgs.charAt(index) && '\\' != keyArgs.charAt(index - 1))
						break;
					else
						index++;
				}

				if (index == keyArgs.length())
					throw new IllegalArgumentException("malformed quoted arguments: " + keyArgs);

				args.add(keyArgs.substring(1, index).replaceAll("\\\"", "\""));

				if (index + 1 < keyArgs.length())
					if (',' != keyArgs.charAt(index + 1))
						throw new IllegalArgumentException("badly terminated quoted argument: " + keyArgs);
					else
						index += 2;
				else
					index++;
				
				keyArgs = keyArgs.substring(index);
			}
			else
			{
				int index = 0;

				while (index < keyArgs.length() && ',' != keyArgs.charAt(index))
					index++;

				args.add(keyArgs.substring(0, index));

				if (index < keyArgs.length())
					index++;

				keyArgs = keyArgs.substring(index);
			}
		}

		return args;
	}
}

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

class RequestProcessor implements Runnable
{
	private Socket socket;

	public RequestProcessor(Socket socket)
	{
		this.socket = socket;
	}

	public void run()
	{
		PrintWriter out = null;
		BufferedReader in = null;

		try
		{
			out = new PrintWriter(socket.getOutputStream(), true);
			in = new BufferedReader(new InputStreamReader(socket.getInputStream()));

			char[] chars = new char[100];
			in.read(chars, 0, 100);

			if ('Z' == chars[0] && 'B' == chars[1] && 'X' == chars[2] && 'D' == chars[3])
			{
				int length = chars[5] + 256 * (int)chars[6];
				String key = new String(chars, 13, length - 1);

				System.out.println("Received key: '" + key + "'");

				out.println(JMXProcessor.getValue(key));
			}
			else
				out.println("bad zabbix_get request");
		}
		catch (Exception e)
		{
			out.println(e.getMessage());
			e.printStackTrace();
		}
		finally
		{
			try { if (null != socket) socket.close(); } catch (Exception ex) { }
			try { if (null != out) out.close(); } catch (Exception ex) { }
			try { if (null != in) in.close(); } catch (Exception ex) { }
		}
	}
}

public class ZabbixJMXAgent
{
	public static void main(String[] args)
	{
		try
		{
			ServerSocket socket = new ServerSocket(10052);

			while (true)
				new Thread(new RequestProcessor(socket.accept())).start();
		}
		catch (Exception e)
		{
			e.printStackTrace();
		}
	}
}
