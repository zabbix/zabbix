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

#ifndef ZABBIX_CHECKS_SIMPLE_DNSTEST_H
#define ZABBIX_CHECKS_SIMPLE_DNSTEST_H

#include "dbcache.h"

#define ZBX_EC_NOERROR	0

/* item values indicating an error code: */
/* -1   .. -199    internal monitoring software errors */
/* -200 .. -999    service errors (registry operator fault) */

#define ZBX_EC_INTERNAL			-1	/* Zabbix or ldns error */
#define ZBX_EC_DNS_NS_NOREPLY		-200	/* no reply from Name Server */
#define ZBX_EC_DNS_NS_ERRREPLY		-201	/* invalid reply from Name Server */
#define ZBX_EC_DNS_NS_NOTS		-202	/* no UNIX timestamp */
#define ZBX_EC_DNS_NS_ERRTS		-203	/* invalid UNIX timestamp */
#define ZBX_EC_DNS_NS_ERRSIG		-204	/* DNSSEC error */
#define ZBX_EC_DNS_RES_NOREPLY		-205	/* no reply from resolver */
#define ZBX_EC_DNS_RES_NOADBIT		-206	/* no AD bit in the answer from resolver */
#define ZBX_EC_RDDS43_NOREPLY		-200	/* no reply from RDDS43 server */
#define ZBX_EC_RDDS43_NONS		-201	/* Whois server returned no NS */
#define ZBX_EC_RDDS43_NOTS		-202	/* no Unix timestamp */
#define ZBX_EC_RDDS43_ERRTS		-203	/* invalid Unix timestamp */
#define ZBX_EC_RDDS80_NOREPLY		-204	/* no reply from RDDS80 server */
#define ZBX_EC_RDDS_ERRRES		-205	/* cannot resolve a Whois server name */
#define ZBX_EC_PROBE_ONLINE		1	/* probe in automatic online mode */
#define ZBX_EC_PROBE_OFFLINE		0	/* probe in automatic offline mode */
#define ZBX_EC_PROBE_UNSUPPORTED	2	/* internal use only */

#define ZBX_NO_VALUE		-1000	/* no item value should be set */

#define ZBX_DNSTEST_UDP	0
#define ZBX_DNSTEST_TCP	1

#define ZBX_MACRO_DNS_RESOLVER		"{$DNSTEST.RESOLVER}"
#define ZBX_MACRO_DNS_TESTPREFIX	"{$DNSTEST.DNS.TESTPREFIX}"
#define ZBX_MACRO_DNS_UDP_RTT		"{$DNSTEST.DNS.UDP.RTT}"
#define ZBX_MACRO_DNS_TCP_RTT		"{$DNSTEST.DNS.TCP.RTT}"
#define ZBX_MACRO_RDDS_RTT		"{$DNSTEST.RDDS.RTT}"
#define ZBX_MACRO_IP4_ENABLED		"{$DNSTEST.IP4.ENABLED}"
#define ZBX_MACRO_IP6_ENABLED		"{$DNSTEST.IP6.ENABLED}"
#define ZBX_MACRO_IP4_MIN_SERVERS	"{$DNSTEST.IP4.MIN.SERVERS}"
#define ZBX_MACRO_IP6_MIN_SERVERS	"{$DNSTEST.IP6.MIN.SERVERS}"
#define ZBX_MACRO_IP4_REPLY_MS		"{$DNSTEST.IP4.REPLY.MS}"
#define ZBX_MACRO_IP6_REPLY_MS		"{$DNSTEST.IP6.REPLY.MS}"
#define ZBX_MACRO_RDDS_NS_STRING	"{$DNSTEST.RDDS.NS.STRING}"
#define ZBX_MACRO_PROBE_ONLINE_DELAY	"{$DNSTEST.PROBE.ONLINE.DELAY}"
#define ZBX_MACRO_RDDS_ENABLED		"{$DNSTEST.RDDS.ENABLED}"

#define ZBX_MACRO_TLD_DNSSEC_ENABLED	"{$DNSTEST.TLD.DNSSEC.ENABLED}"
#define ZBX_MACRO_TLD_RDDS_ENABLED	"{$DNSTEST.TLD.RDDS.ENABLED}"
#define ZBX_MACRO_TLD_EPP_ENABLED	"{$DNSTEST.TLD.EPP.ENABLED}"

#define ZBX_DNSTEST_UDP_TIMEOUT	5	/* seconds */
#define ZBX_DNSTEST_UDP_RETRY	3
#define ZBX_DNSTEST_TCP_TIMEOUT	20	/* seconds */
#define ZBX_DNSTEST_TCP_RETRY	1

#define ZBX_DNSTEST_DEFAULT_LOGDIR	"/var/log"	/* if Zabbix log dir is undefined */
#define ZBX_DNS_LOG_PREFIX		"dns"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_DNS_LOG_PREFIX.log */
#define ZBX_RDDS_LOG_PREFIX		"rdds"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_RDDS_LOG_PREFIX.log */
#define ZBX_PROBESTATUS_LOG_PREFIX	"probestatus"	/* file will be <LOGDIR>/probestatus.log */

int	check_dnstest_dns(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result, char proto);
int	check_dnstest_rdds(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_dnstest_probe_status(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);

#endif
