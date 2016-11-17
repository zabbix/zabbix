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

/* item values indicating an error code: NB! KEEP THIS IN SYNC WITH checks_simple_rsm.c and RSMSLV.pm */
/* -1   .. -199, (RDDS: -208, -250, -500)	internal monitoring software errors                   */
/* -200 .. -999					service errors (registry operator fault)              */

/* internal */
#define ZBX_EC_INTERNAL			-1	/* general internal error */
#define ZBX_EC_INTERNAL_IP_UNSUP	-2	/* IP version not supported by Probe */
/* auxiliary generic resolver error codes */
#define ZBX_EC_RES_NOREPLY		-3
#define ZBX_EC_RES_NOADBIT		-4
#define ZBX_EC_RES_SERVFAIL		-5
#define ZBX_EC_RES_NXDOMAIN		-6
#define ZBX_EC_RES_CATCHALL		-7
/* auxiliary generic HTTP error codes */
#define ZBX_EC_HTTP_ECON		-8
#define ZBX_EC_HTTP_EHTTP		-9
#define ZBX_EC_HTTP_EHTTPS		-10
#define ZBX_EC_HTTP_BASE		-11
/* DNS UDP specific */
#define ZBX_EC_DNS_UDP_NS_NOREPLY	-200	/* DNS UDP - No reply from Name Server */
/* The following set of DNS errors apply to both UDP and TCP, ZBX_EC_OFFSET_DNS_TCP is applied in case of TCP. */
#define ZBX_EC_DNS_IN_GOT_CHAOS		-207	/* DNS UDP/TCP - Expecting DNS class IN but got CHAOS */
#define ZBX_EC_DNS_IN_GOT_HESIOD	-208	/* DNS UDP/TCP - Expecting DNS class IN but got HESIOD */
#define ZBX_EC_DNS_IN_GOT_OTHER		-209	/* DNS UDP/TCP - Expecting DNS class IN but got something different than IN, CHAOS or HESIOD */
#define ZBX_EC_DNS_LDNS_BASE		-210	/* DNS UDP/TCP - LDNS errors: -210..-215 */
/* DNS UDP/TCP non-existent */
#define ZBX_EC_DNS_NXD_NO_AA		-250	/* DNS UDP/TCP - Querying for a non existent domain - AA flag not present in response */
#define ZBX_EC_DNS_NXD_NOT_IN_QUESTION	-251	/* DNS UDP/TCP - Querying for a non existent domain - Domain name being queried not present in question section */
#define ZBX_EC_DNS_NXD_RCODE_BASE	-252	/* DNS UDP/TCP - RCODE errors -252 .. -270 */
/* DNS UDP/TCP existent */
#define ZBX_EC_DNS_EXD_AA		-300	/* DNS UDP/TCP - Querying for an existent domain - AA flag present in response */
#define ZBX_EC_DNS_EXD_NOT_IN_QUESTION	-301	/* DNS UDP/TCP - Querying for an existent domain - Domain name being queried not present in question section */
#define ZBX_EC_DNS_EXD_ANSWER		-302	/* DNS UDP/TCP - Querying for an existent domain - Expecting referral but answer section is not empty */
#define ZBX_EC_DNS_EXD_NO_REFERRAL	-303	/* DNS UDP/TCP - Querying for an existent domain - Expecting referral but authority section does not contain a referral */
#define ZBX_EC_DNS_EXD_NO_UNIX_TS	-304	/* DNS UDP/TCP - Querying for an existent domain - No Unix timestamp */
#define ZBX_EC_DNS_EXD_INV_UNIX_TS	-305	/* DNS UDP/TCP - Querying for an existent domain - Invalid timestamp */
#define ZBX_EC_DNS_EXD_RCODE_BASE	-306	/* DNS UDP/TCP - RCODE errors -306 .. -324 */
/* DNS UDP/TCP DNAME */
#define ZBX_EC_DNS_DNAME_AA		-350	/* DNS UDP/TCP - Querying for a DNAME TLD - AA flag present in response */
#define ZBX_EC_DNS_DNAME_NO_RR		-351	/* DNS UDP/TCP - Querying for a DNAME TLD - DNAME RR not found or malformed in answer section */
#define ZBX_EC_DNS_DNAME_NO_CN_RR	-352	/* DNS UDP/TCP - Querying for a DNAME TLD - CNAME RR not found in answer section */
#define ZBX_EC_DNS_DNAME_RCODE_BASE	-353	/* DNS UDP/TCP - RCODE errors -353 .. -371 */
/* DNS UDP/TCP DNSSEC */
#define ZBX_EC_DNS_DNSSEC_NO_RESOLV	-400	/* DNS UDP/TCP - No reply from local resolver */
#define ZBX_EC_DNS_DNSSEC_NO_AD		-401	/* DNS UDP/TCP - No AD bit from local resolver */
#define ZBX_EC_DNS_DNSSEC_SERVFAIL	-402	/* DNS UDP/TCP - Expecting NOERROR RCODE but got SERVFAIL from local resolver  */
#define ZBX_EC_DNS_DNSSEC_NXDOMAIN	-403	/* DNS UDP/TCP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver */
#define ZBX_EC_DNS_DNSSEC_UNEXP		-404	/* DNS UDP/TCP - Expecting NOERROR RCODE but got unexpecting from local resolver */
#define ZBX_EC_DNS_DNSSEC_NO_RRSIGS	-407	/* DNS UDP/TCP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled */
#define ZBX_EC_DNS_DNSSEC_LDNS_BASE	-405	/* DNS UDP/TCP - LDNS errors: -405, -406, -408 .. -427 */
/* DNS TCP specific */
#define ZBX_EC_DNS_TCP_NS_TO		-600	/* DNS TCP - Timeout reply from name server */
#define ZBX_EC_DNS_TCP_NS_CONN		-601	/* DNS TCP - Error opening connection to name server */
/* generic DNS TCP error codes have the following offset */
#define ZBX_EC_OFFSET_DNS_TCP		-400
/* last DNS error code:			-827 (ZBX_EC_OFFSET_DNS_TCP + last of ZBX_EC_DNS_DNSSEC_LDNS_BASE */

/* RDDS */
#define ZBX_EC_RDDS43_NONS		-201	/* RDDS43 - The output of the response does not match specification */
#define ZBX_EC_RDDS43_RES_NOREPLY	-208	/* RDDS43 - No reply from local resolver */
#define ZBX_EC_RDDS43_RES_NOADBIT	-209	/* RDDS43 - No AD bit from local resolver */
#define ZBX_EC_RDDS43_RES_SERVFAIL	-210	/* RDDS43 - Expecting NOERROR RCODE but got SERVFAIL when resolving server hostname */
#define ZBX_EC_RDDS43_RES_NXDOMAIN	-211	/* RDDS43 - Expecting NOERROR RCODE but got NXDOMAIN when resolving server hostname */
#define ZBX_EC_RDDS43_RES_CATCHALL	-212	/* RDDS43 - Expecting NOERROR RCODE but got unexpected when resolving server hostname */
#define ZBX_EC_RDDS43_TO		-213	/* RDDS43 - Timeout reply from server */
#define ZBX_EC_RDDS43_ECON		-214	/* RDDS43 - Error opening connection to server */
#define ZBX_EC_RDDS43_NOTS		-215	/* RDDS43 - No Unix timestamp in response */
#define ZBX_EC_RDDS43_ETS		-216	/* RDDS43 - Invalid Unix timestamp in response */
#define ZBX_EC_RDDS43_EMPTY		-217	/* RDDS43 - Empty response */
#define ZBX_EC_RDDS80_RES_NOREPLY	-250	/* RDDS80 - No reply from local resolver */
#define ZBX_EC_RDDS80_RES_NOADBIT	-251	/* RDDS80 - No AD bit from local resolver */
#define ZBX_EC_RDDS80_RES_SERVFAIL	-252	/* RDDS80 - Expecting NOERROR RCODE but got SERVFAIL when resolving server hostname */
#define ZBX_EC_RDDS80_RES_NXDOMAIN	-253	/* RDDS80 - Expecting NOERROR RCODE but got NXDOMAIN when resolving server hostname */
#define ZBX_EC_RDDS80_RES_CATCHALL	-254	/* RDDS80 - Expecting NOERROR RCODE but got unexpected when resolving server hostname */
#define ZBX_EC_RDDS80_TO		-255	/* RDDS80 - Timeout reply from server */
#define ZBX_EC_RDDS80_ECON		-256	/* RDDS80 - Error opening connection to server */
#define ZBX_EC_RDDS80_EHTTP		-257	/* RDDS80 - Error in HTTP protocol */
#define ZBX_EC_RDDS80_EHTTPS		-258	/* RDDS80 - Error in HTTPS protocol */
#define ZBX_EC_RDDS80_HTTP_BASE		-300
/* Code ZBX_EC_RDDS80_HTTP_BASE - zbx_map_http_code(xxx) means */
						/* RDDS80 - Expecting HTTP status code 200 but got xxx */
#define ZBX_EC_RDAP_RES_NOREPLY		-500	/* RDAP - No reply from local resolver */
#define ZBX_EC_RDAP_RES_NOADBIT		-501	/* RDAP - No AD bit from local resolver */
#define ZBX_EC_RDAP_RES_SERVFAIL	-502	/* RDAP - Expecting NOERROR RCODE but got SERVFAIL when resolving server hostname */
#define ZBX_EC_RDAP_RES_NXDOMAIN	-503	/* RDAP - Expecting NOERROR RCODE but got NXDOMAIN when resolving server hostname */
#define ZBX_EC_RDAP_RES_CATCHALL	-504	/* RDAP - Expecting NOERROR RCODE but got unexpected when resolving server hostname */
#define ZBX_EC_RDAP_TO			-505	/* RDAP - Timeout reply from server */
#define ZBX_EC_RDAP_ECON		-506	/* RDAP - Error opening connection to server */
#define ZBX_EC_RDAP_EJSON		-507	/* RDAP - Invalid JSON format in response */
#define ZBX_EC_RDAP_NONAME		-508	/* RDAP - ldhName member not found in response */
#define ZBX_EC_RDAP_ENAME		-509	/* RDAP - ldhName member doesn't match query in response */
#define ZBX_EC_RDAP_NONS		-510	/* RDAP - Syntax valid Name server member not found in response */
#define ZBX_EC_RDAP_NOTS		-511	/* RDAP - No Unix timestamp in response */
#define ZBX_EC_RDAP_ETS			-512	/* RDAP - Invalid Unix timestamp in response */
#define ZBX_EC_RDAP_EHTTP		-513	/* RDAP - Error in HTTP protocol */
#define ZBX_EC_RDAP_EHTTPS		-514	/* RDAP - Error in HTTPS protocol */
#define ZBX_EC_RDAP_HTTP_BASE		-550
/* Code ZBX_EC_RDAP_HTTP_BASE - zbx_map_http_code(xxx) means */
						/* RDAP - Expecting HTTP status code 200 but got xxx */
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

#define ZBX_NOT_PERFORMED		-100000	/* NB! Must not conflict with any of the error codes above! */

/* RCODES that are missing in LDNS library */
#define ZBX_LDNS_BADVERS_OR_BADSIG	16
#define ZBX_LDNS_BADKEY			17
#define ZBX_LDNS_BADTIME		18
#define ZBX_LDNS_BADMODE		19
#define ZBX_LDNS_BADNAME		20
#define ZBX_LDNS_BADALG			21
#define ZBX_LDNS_BADTRUNC		22
#define ZBX_LDNS_BADCOOKIE		23

#define ZBX_RSM_UDP	0
#define ZBX_RSM_TCP	1

#define ZBX_MACRO_DNS_MAX_TEST_STEP	"{$RSM.DNS.TEST.PROTO.RATIO}"
#define ZBX_MACRO_DNS_MAX_UPD_STEP	"{$RSM.DNS.TEST.UPD.RATIO}"
#define ZBX_MACRO_DNS_MAX_REC_STEP	"{$RSM.DNS.TEST.CRIT.RECOVER}"
#define ZBX_MACRO_DNS_MINNS		"{$RSM.DNS.AVAIL.MINNS}"
#define ZBX_MACRO_DNS_RESOLVER		"{$RSM.RESOLVER}"
#define ZBX_MACRO_DNS_TESTPREFIX	"{$RSM.DNS.TESTPREFIX}"
#define ZBX_MACRO_DNS_TESTPREFIX_EPP	"{$RSM.DNS.TESTPREFIX.EPP}"
#define ZBX_MACRO_DNS_UDP_RTT		"{$RSM.DNS.UDP.RTT.HIGH}"
#define ZBX_MACRO_DNS_TCP_RTT		"{$RSM.DNS.TCP.RTT.HIGH}"
#define ZBX_MACRO_RDDS_TESTPREFIX	"{$RSM.RDDS.TESTPREFIX}"
#define ZBX_MACRO_RDDS43_RTT		"{$RSM.RDDS43.RTT.HIGH}"
#define ZBX_MACRO_RDDS80_RTT		"{$RSM.RDDS80.RTT.HIGH}"
#define ZBX_MACRO_RDAP_RTT		"{$RSM.RDAP.RTT.HIGH}"
#define ZBX_MACRO_RDDS_NS_STRING	"{$RSM.RDDS.NS.STRING}"
#define ZBX_MACRO_RDDS80_MAXREDIRS	"{$RSM.RDDS80.MAXREDIRS}"
#define ZBX_MACRO_RDAP_MAXREDIRS	"{$RSM.RDAP.MAXREDIRS}"
#define ZBX_MACRO_RDDS43_ENABLED	"{$RSM.RDDS43.ENABLED}"
#define ZBX_MACRO_RDDS80_ENABLED	"{$RSM.RDDS80.ENABLED}"
#define ZBX_MACRO_RDAP_ENABLED		"{$RSM.RDAP.ENABLED}"
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
#define ZBX_MACRO_TLD_RDDS43_ENABLED	"{$RSM.TLD.RDDS43.ENABLED}"
#define ZBX_MACRO_TLD_RDDS80_ENABLED	"{$RSM.TLD.RDDS80.ENABLED}"
#define ZBX_MACRO_TLD_RDAP_ENABLED	"{$RSM.TLD.RDAP.ENABLED}"
#define ZBX_MACRO_TLD_EPP_ENABLED	"{$RSM.TLD.EPP.ENABLED}"

#define ZBX_RSM_UDP_TIMEOUT	3	/* seconds */
#define ZBX_RSM_UDP_RETRY	2
#define ZBX_RSM_TCP_TIMEOUT	20	/* seconds */
#define ZBX_RSM_TCP_RETRY	1

#define ZBX_RSM_DEFAULT_LOGDIR		"/var/log"	/* if Zabbix log dir is undefined */
#define ZBX_DNS_LOG_PREFIX		"dns"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_DNS_LOG_PREFIX.log */
#define ZBX_RDDS_LOG_PREFIX		"rdds"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_RDDS_LOG_PREFIX.log */
#define ZBX_EPP_LOG_PREFIX		"epp"		/* file will be <LOGDIR>/<DOMAIN>-ZBX_EPP_LOG_PREFIX.log */
#define ZBX_PROBESTATUS_LOG_PREFIX	"probestatus"	/* file will be <LOGDIR>/probestatus.log */

int	check_rsm_dns(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_rsm_rdds(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_rsm_epp(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);
int	check_rsm_probe_status(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result);

#endif
