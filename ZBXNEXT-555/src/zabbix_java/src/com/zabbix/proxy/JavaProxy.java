package com.zabbix.proxy;

import java.net.ServerSocket;

public class JavaProxy
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
