/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#ifndef ZABBIX_VMWARE_CONSTANTS_H
#define ZABBIX_VMWARE_CONSTANTS_H

#define ZBX_POST_VSPHERE_HEADER									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"					\
		"<SOAP-ENV:Envelope"								\
			" xmlns:ns0=\"urn:vim25\""						\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""		\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""		\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"	\
			"<SOAP-ENV:Header/>"							\
			"<ns1:Body>"
#define ZBX_POST_VSPHERE_FOOTER									\
			"</ns1:Body>"								\
		"</SOAP-ENV:Envelope>"

#endif	/* ZABBIX_VMWARE_CONSTANTS_H */
