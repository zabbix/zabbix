/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

import java.io.*;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.charset.Charset;
import java.util.Arrays;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class BinaryProtocolSpeaker
{
	private static final Logger logger = LoggerFactory.getLogger(BinaryProtocolSpeaker.class);

	private static final byte[] PROTOCOL_HEADER = {'Z', 'B', 'X', 'D', '\1'};
	private static final Charset UTF8_CHARSET = Charset.forName("UTF-8");

	private Socket socket;
	private DataInputStream dis = null;
	private BufferedOutputStream bos = null;

	public BinaryProtocolSpeaker(Socket socket)
	{
		this.socket = socket;
	}

	public String getRequest() throws IOException, ZabbixException
	{
		dis = new DataInputStream(socket.getInputStream());

		byte[] data;

		logger.debug("reading Zabbix protocol header");
		data = new byte[5];
		dis.readFully(data);

		if (!Arrays.equals(data, PROTOCOL_HEADER))
			throw new ZabbixException("bad protocol header: %02X %02X %02X %02X %02X", data[0], data[1], data[2], data[3], data[4]);

		logger.debug("reading 8 bytes of data length");
		data = new byte[8];
		dis.readFully(data);

		ByteBuffer buffer = ByteBuffer.wrap(data);
		buffer.order(ByteOrder.LITTLE_ENDIAN);
		long length = buffer.getLong();

		if (!(0 <= length && length <= Integer.MAX_VALUE))
			throw new ZabbixException("bad data length: %d", length);

		logger.debug("reading {} bytes of request data", length);
		data = new byte[(int)length];
		dis.readFully(data);

		String request = new String(data, UTF8_CHARSET);
		logger.debug("received the following data in request: {}", request);
		return request;
	}

	public void sendResponse(String response) throws IOException, ZabbixException
	{
		bos = new BufferedOutputStream(socket.getOutputStream());

		logger.debug("sending the following data in response: {}", response);

		byte[] data, responseBytes;

		data = PROTOCOL_HEADER;
		bos.write(data, 0, data.length);

		responseBytes = response.getBytes(UTF8_CHARSET);
		ByteBuffer buffer = ByteBuffer.allocate(8);
		buffer.order(ByteOrder.LITTLE_ENDIAN);
		buffer.putLong(responseBytes.length);
		data = buffer.array();
		bos.write(data, 0, data.length);

		data = responseBytes;
		bos.write(data, 0, data.length);

		bos.flush();
	}

	public void close()
	{
		try { if (null != dis) dis.close(); } catch (Exception e) { }
		try { if (null != bos) bos.close(); } catch (Exception e) { }
		try { if (null != socket) socket.close(); } catch (Exception e) { }
	}
}
