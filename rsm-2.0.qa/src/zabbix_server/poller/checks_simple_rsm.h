/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_CHECKS_SIMPLE_RSM_H
#define ZABBIX_CHECKS_SIMPLE_RSM_H

#include "dbcache.h"

#define ZBX_EC_NOERROR	0

/* item values indicating an error code: */
/* -1   .. -199    internal monitoring software errors */
/* -200 .. -999    service errors (registry operator fault) */

/* internal */
#define ZBX_EC_INTERNAL			-1	/* general internal error */
#define ZBX_EC_INTERNAL_IP_UNSUP	-2	/* IP version not supported by Probe */
/* DNS */
#define ZBX_EC_DNS_NS_NOREPLY		-200	/* no reply from Name Server */
#define ZBX_EC_DNS_NS_EREPLY		-201	/* invalid reply from Name Server */
#define ZBX_EC_DNS_NS_NOTS		-202	/* no UNIX timestamp */
#define ZBX_EC_DNS_NS_ETS		-203	/* invalid UNIX timestamp */
#define ZBX_EC_DNS_NS_EDNSSEC		-204	/* DNSSEC error */
#define ZBX_EC_DNS_RES_NOREPLY		-205	/* no reply from resolver */
#define ZBX_EC_DNS_RES_NOADBIT		-206	/* no AD bit in the answer from resolver */
/* RDDS */
#define ZBX_EC_RDDS43_NOREPLY		-200	/* no reply from RDDS43 server */
#define ZBX_EC_RDDS43_NONS		-201	/* Whois server returned no NS */
#define ZBX_EC_RDDS43_NOTS		-202	/* no Unix timestamp */
#define ZBX_EC_RDDS43_ETS		-203	/* invalid Unix timestamp */
#define ZBX_EC_RDDS80_NOREPLY		-204	/* no reply from RDDS80 server */
#define ZBX_EC_RDDS_ERES		-205	/* cannot resolve a Whois server name */
#define ZBX_EC_RDDS80_NOHTTPCODE	-206	/* no HTTP response code in response from RDDS80 server */
#define ZBX_EC_RDDS80_EHTTPCODE		-207	/* invalid HTTP response code in response from RDDS80 server */
/* EPP */
#define ZBX_EC_EPP_NO_IP		-200	/* IP is missing for EPP server */
#define ZBX_EC_EPP_CONNECT		-201	/* cannot connect to EPP server */
#define ZBX_EC_EPP_CRYPT		-202	/* invalid certificate or private key */
#define ZBX_EC_EPP_FIRSTTO		-203	/* first message timeout */
#define ZBX_EC_EPP_FIRSTINVAL		-204	/* first message is invalid */
#define ZBX_EC_EPP_LOGINTO		-205	/* LOGIN command timeout */
#define ZBX_EC_EPP_LOGININVAL		-206	/* invalid reply to LOGIN command */
#define ZBX_EC_EPP_UPDATETO		-207	/* UPDATE command timeout */
#define ZBX_EC_EPP_UPDATEINVAL		-208	/* invalid reply to UPDATE command */
#define ZBX_EC_EPP_INFOTO		-209	/* INFO command timeout */
#define ZBX_EC_EPP_INFOINVAL		-210	/* invalid reply to INFO command */
#define ZBX_EC_EPP_SERVERCERT		-211	/* Server certificate validation failed */

#define ZBX_EC_PROBE_OFFLINE		0	/* probe in automatic offline mode */
#define ZBX_EC_PROBE_ONLINE		1	/* probe in automatic online mode */
#define ZBX_EC_PROBE_UNSUPPORTED	2	/* internal use only */

#define ZBX_NO_VALUE			-1000	/* no item value should be set */

#define ZBX_RSM_UDP	0
#define ZBX_RSM_TCP	1

#define ZBX_MACRO_DNS_RESOLVER		"{$RSM.RESOLVER}"
#define ZBX_MACRO_DNS_TESTPREFIX	"{$RSM.DNS.TESTPREFIX}"
#define ZBX_MACRO_DNS_UDP_RTT		"{$RSM.DNS.UDP.RTT.HIGH}"
#define ZBX_MACRO_DNS_TCP_RTT		"{$RSM.DNS.TCP.RTT.HIGH}"
#define ZBX_MACRO_RDDS_TESTPREFIX	"{$RSM.RDDS.TESTPREFIX}"
#define ZBX_MACRO_RDDS_RTT		"{$RSM.RDDS.RTT.HIGH}"
#define ZBX_MACRO_RDDS_NS_STRING	"{$RSM.RDDS.NS.STRING}"
#define ZBX_MACRO_RDDS_MAXREDIRS	"{$RSM.RDDS.MAXREDIRS}"
#define ZBX_MACRO_RDDS_ENABLED		"{$RSM.RDDS.ENABLED}"
#define ZBX_MACRO_EPP_LOGIN_RTT		"{$RSM.EPP.LOGIN.RTT.HIGH}"
#define ZBX_MACRO_EPP_UPDATE_RTT	"{$RSM.EPP.UPDATE.RTT.HIGH}"
#define ZBX_MACRO_EPP_INFO_RTT		"{$RSM.EPP.INFO.RTT.HIGH}"
#define ZBX_MACRO_IP4_ENABLED		"{$RSM.IP4.ENABLED}"
#define ZBX_MACRO_IP6_ENABLED		"{$RSM.IP6.ENABLED}"
#define ZBX_MACRO_IP4_MIN_SERVERS	"{$RSM.IP4.MIN.SERVERS}"
#define ZBX_MACRO_IP6_MIN_SERVERS	"{$RSM.IP6.MIN.SERVERS}"
#define ZBX_MACRO_IP4_REPLY_MS		"{$RSM.IP4.REPLY.MS}"
#define ZBX_MACRO_IP6_REPLY_MS		"{$RSM.IP6.REPLY.MS}"
#define ZBX_MACRO_PROBE_ONLINE_DELAY	"{$RSM.PROBE.ONLINE.DELAY}"
#define ZBX_MACRO_EPP_ENABLED		"{$RSM.EPP.ENABLED}"
#define ZBX_MACRO_EPP_USER		"{$RSM.EPP.USER}"
#define ZBX_MACRO_EPP_PASSWD		"{$RSM.EPP.PASSWD}"
#define ZBX_MACRO_EPP_CERT		"{$RSM.EPP.CERT}"
#define ZBX_MACRO_EPP_PRIVKEY		"{$RSM.EPP.PRIVKEY}"
#define ZBX_MACRO_EPP_KEYSALT		"{$RSM.EPP.KEYSALT}"
#define ZBX_MACRO_EPP_COMMANDS		"{$RSM.EPP.COMMANDS}"
#define ZBX_MACRO_EPP_SERVERID		"{$RSM.EPP.SERVERID}"
#define ZBX_MACRO_EPP_TESTPREFIX	"{$RSM.EPP.TESTPREFIX}"
#define ZBX_MACRO_EPP_SERVERCERTMD5	"{$RSM.EPP.SERVERCERTMD5}"
#define ZBX_MACRO_TLD_DNSSEC_ENABLED	"{$RSM.TLD.DNSSEC.ENABLED}"
#define ZBX_MACRO_TLD_RDDS_ENABLED	"{$RSM.TLD.RDDS.ENABLED}"
#define ZBX_MACRO_TLD_EPP_ENABLED	"{$RSM.TLD.EPP.ENABLED}"

#define ZBX_RSM_UDP_TIMEOUT	3	/* seconds */
#define ZBX_RSM_UDP_RETRY	1
#define ZBX_RSM_TCP_TIMEOUT	20	/* seconds */
#define ZBX_RSM_TCP_RETRY	1

#define ZBX_RSM_DEFAULT_LOGDIR		"/var/log"	/* if Zabbix log dir is undefined */
#define ZBX_DNS_LOG_PREFIX		"dns"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_DNS_LOG_PREFIX.log */
#define ZBX_RDDS_LOG_PREFIX		"rdds"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_RDDS_LOG_PREFIX.log */
#define ZBX_EPP_LOG_PREFIX		"epp"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_EPP_LOG_PREFIX.log */
#define ZBX_PROBESTATUS_LOG_PREFIX	"probestatus"	/* file will be <LOGDIR>/probestatus.log */

int	check_rsm_dns(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result, char proto);
int	check_rsm_rdds(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_rsm_epp(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_rsm_probe_status(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);

#endif
