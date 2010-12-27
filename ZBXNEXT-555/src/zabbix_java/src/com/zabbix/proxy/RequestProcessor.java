package com.zabbix.proxy;

import java.io.*;
import java.net.Socket;

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
