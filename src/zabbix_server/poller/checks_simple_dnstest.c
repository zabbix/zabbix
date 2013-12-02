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

#include "sysinfo.h"
#include "checks_simple_dnstest.h"
#include "zbxserver.h"
#include "comms.h"
#include "log.h"	/* TODO: REMOVE ME */

#include <ldns/ldns.h>

#define ZBX_HOST_BUF_SIZE	128
#define ZBX_IP_BUF_SIZE		64
#define ZBX_ERR_BUF_SIZE	256
#define ZBX_LOGNAME_BUF_SIZE	128

extern const char	*CONFIG_LOG_FILE;

typedef struct
{
	char	*name;
	char	result;
	char	**ips;
	size_t	ips_num;
}
zbx_ns_t;

static int	zbx_set_resolver_ns(ldns_resolver *res, const char *name, const char *ip, char ipv4_enabled,
		char ipv6_enabled, char *err, size_t err_size)
{
	ldns_rdf	*ip_rdf = NULL;
	ldns_status	status;
	int		ret = FAIL;

	/* create a rdf from ip */
	if (0 == ipv4_enabled || NULL == (ip_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_A, ip)))
	{
		/* try IPv6 */
		if (0 != ipv6_enabled)
			ip_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_AAAA, ip);
	}

	if (NULL == ip_rdf)
	{
		zbx_snprintf(err, err_size, "invalid or unsupported %s IP \"%s\"", name, ip);
		goto out;
	}

	/* push nameserver to it */
	if (LDNS_STATUS_OK != (status = ldns_resolver_push_nameserver(res, ip_rdf)))
	{
		zbx_snprintf(err, err_size, "cannot set %s (%s) as resolver. %s.", name, ip,
				ldns_get_errorstr_by_id(status));
		goto out;
	}

	zabbix_log(LOG_LEVEL_WARNING, "successfully using %s (%s)", name, ip);

	ret = SUCCEED;
out:
	if (NULL != ip_rdf)
		ldns_rdf_deep_free(ip_rdf);

	return ret;
}

static int	zbx_create_resolver(ldns_resolver **res, const char *name, const char *ip, char proto,
		char ipv4_enabled, char ipv6_enabled, char *err, size_t err_size)
{
	struct timeval	tv;
	int		retries, ip_support, ret = FAIL;

	if (NULL != *res)
	{
		zbx_strlcpy(err, "unfreed memory detected", err_size);
		goto out;
	}

	/* create a new resolver */
	if (NULL == (*res = ldns_resolver_new()))
	{
		zbx_strlcpy(err, "out of memory", err_size);
		goto out;
	}

	/* push nameserver to it */
	if (SUCCEED != zbx_set_resolver_ns(*res, name, ip, ipv4_enabled, ipv6_enabled, err, err_size))
		goto out;

	if (ZBX_DNSTEST_UDP == proto)
	{
		tv.tv_sec = ZBX_DNSTEST_UDP_TIMEOUT;
		tv.tv_usec = 0;
		retries = ZBX_DNSTEST_UDP_RETRY;
	}
	else
	{
		tv.tv_sec = ZBX_DNSTEST_TCP_TIMEOUT;
		tv.tv_usec = 0;
		retries = ZBX_DNSTEST_TCP_RETRY;
	}

	/* set timeout of one try */
	ldns_resolver_set_timeout(*res, tv);

	/* set number of tries */
	ldns_resolver_set_retry(*res, retries);

	/* unset the CD flag */
	ldns_resolver_set_dnssec_cd(*res, false);

	/* use TCP or UDP */
	ldns_resolver_set_usevc(*res, ZBX_DNSTEST_UDP == proto ? false : true);

	/* set IP version support: 0: both, 1: IPv4 only, 2: IPv6 only */
	if (0 != ipv4_enabled && 0 != ipv6_enabled)
		ip_support = 0;
	else if (0 != ipv4_enabled && 0 == ipv6_enabled)
		ip_support = 1;
	else
		ip_support = 2;

	ldns_resolver_set_ip6(*res, ip_support);

	ret = SUCCEED;
out:
	return ret;
}

static int	zbx_change_resolver(ldns_resolver *res, const char *name, const char *ip, char ipv4_enabled,
		char ipv6_enabled, char *err, size_t err_size)
{
	ldns_rdf_deep_free(ldns_resolver_pop_nameserver(res));

	return zbx_set_resolver_ns(res, name, ip, ipv4_enabled, ipv6_enabled, err, err_size);
}

static int	zbx_remove_unmatched_dnskeys(ldns_rr_list **keys, const ldns_rr *rrsig, int *rtt,
		char *err, size_t err_size)
{
	size_t		i, keys_count;
	uint16_t	rrsig_keytag;
	ldns_rr_list	*matched_keys = NULL;
	int		ret = FAIL;

	rrsig_keytag = ldns_rdf2native_int16(ldns_rr_rrsig_keytag(rrsig));

	keys_count = ldns_rr_list_rr_count(*keys);
	for (i = 0; i < keys_count; i++)
	{
		uint16_t	dnskey_keytag;
		ldns_rr		*rr;

		rr = ldns_rr_list_rr(*keys, i);
		dnskey_keytag = ldns_calc_keytag(rr);

		if (dnskey_keytag == rrsig_keytag)
		{
			if (NULL == matched_keys)
				matched_keys = ldns_rr_list_new();

			if (false == ldns_rr_list_push_rr(matched_keys, ldns_rr_clone(rr)))
			{
				zbx_strlcpy(err, "internal error: cannot add rr to rr_list", err_size);
				*rtt = ZBX_EC_INTERNAL;
				ldns_rr_list_deep_free(matched_keys);
				goto out;
			}
		}
	}

	if (NULL == matched_keys)
	{
		zbx_strlcpy(err, "DNSKEY matching RRSIG not found", err_size);
		*rtt = ZBX_EC_DNS_NS_ERRSIG;
		goto out;
	}

	ret = SUCCEED;
	ldns_rr_list_deep_free(*keys);
	*keys = matched_keys;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_ts_from_host                                             *
 *                                                                            *
 * Purpose: Extract the Unix timestamp from the host name. Expected format of *
 *          the host: ns<optional digits><DOT or DASH><Unix timestamp>.       *
 *          Examples: ns2-1376488934.icann.org.                               *
 *                    ns1.1376488934.icann.org.                               *
 * Return value: SUCCEED - if host name correctly formatted and timestamp     *
 *               extracted, FAIL - otherwise                                  *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static int      zbx_get_ts_from_host(char *host, time_t *ts)
{
	char	*p, *p2;

	p = host;

	if (0 != strncmp("ns", p, 2))
		return FAIL;

	p += 2;

	while (0 != isdigit(*p))
		p++;

	if ('.' != *p && '-' != *p)
		return FAIL;

	p++;
	p2 = p;

	while (0 != isdigit(*p2))
		p2++;

	if ('.' != *p2)
		return FAIL;

	if (p2 == p || '0' == *p)
		return FAIL;

	*p2 = '\0';
	*ts = atoi(p);
	*p2 = '.';

	return SUCCEED;
}

static int	zbx_random(int max)
{
	zbx_timespec_t	timespec;

	zbx_timespec(&timespec);

	srand(timespec.sec + timespec.ns);

	return rand() % max;
}

static int	zbx_get_ns_values(ldns_resolver *res, const char *ns, const char *ip, const ldns_rr_list *keys,
		const char *testprefix, const char *domain, FILE *dtlog, int *rtt, int *upd, char ipv4_enabled,
		char ipv6_enabled, char epp_enabled, char *err, size_t err_size)
{
	char		testname[ZBX_HOST_BUF_SIZE], *host;
	ldns_rdf	*testname_rdf = NULL;
	ldns_pkt	*pkt = NULL;
	ldns_rr_list	*nsset = NULL, *dsset = NULL, *rrsig = NULL, *matched_keys = NULL;
	ldns_status	status;
	ldns_rr		*rr = NULL;
	time_t		now, ts;
	ldns_pkt_rcode	rcode;
	int		ret = FAIL;

	if (NULL != keys)
		matched_keys = ldns_rr_list_clone(keys);

	/* change the resolver */
	if (SUCCEED != zbx_change_resolver(res, ns, ip, ipv4_enabled, ipv6_enabled, err, err_size))
	{
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	/* prepare test name */
	if (0 != strcmp(".", domain))
		zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, domain);
	else
		zbx_strlcpy(testname, testprefix, sizeof(testname));

	if (NULL == (testname_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, testname)))
	{
		zbx_snprintf(err, err_size, "invalid test name generated \"%s\"", testname);
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	if (NULL != keys)
	{
		/* set edns DO flag */
		ldns_resolver_set_dnssec(res, true);
	}

	/* IN A query */
	if (NULL == (pkt = ldns_resolver_query(res, testname_rdf, LDNS_RR_TYPE_A, LDNS_RR_CLASS_IN, 0)))
	{
		zbx_snprintf(err, err_size, "cannot connect to nameserver \"%s\" (%s)", ns, ip);
		*rtt = ZBX_EC_DNS_NS_NOREPLY;
		goto out;
	}

	/* verify RCODE */
	if (LDNS_RCODE_NOERROR != (rcode = ldns_pkt_get_rcode(pkt)) && LDNS_RCODE_NXDOMAIN != rcode)
	{
		char	*rcode_str;

		rcode_str = ldns_pkt_rcode2str(rcode);
		zbx_snprintf(err, err_size, "IN A query of %s from nameserver \"%s\" (%s) failed (rcode:%s)",
				testname, ns, ip, rcode_str);
		zbx_free(rcode_str);
		*rtt = ZBX_EC_DNS_NS_ERRREPLY;
		goto out;
	}

	ldns_pkt_print(dtlog, pkt);

	nsset = ldns_pkt_rr_list_by_name_and_type(pkt, testname_rdf, LDNS_RR_TYPE_NS, LDNS_SECTION_AUTHORITY);
	rrsig = ldns_pkt_rr_list_by_name_and_type(pkt, testname_rdf, LDNS_RR_TYPE_RRSIG, LDNS_SECTION_AUTHORITY);

	if (0 != epp_enabled)
	{
		ldns_rr_list	*rrlist;

		/* start referral validation */

		/* no AA flag */
		if (0 != ldns_pkt_aa(pkt))
		{
			zbx_snprintf(err, err_size, "AA flag is set in the answer of \"%s\" from nameserver \"%s\" (%s)",
					testname, ns, ip);
			*rtt = ZBX_EC_DNS_NS_ERRREPLY;
			goto out;
		}

		/* no RRs in the ANSWER section */
		if (NULL != (rrlist = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_ANY, LDNS_SECTION_ANSWER)))
		{
			ldns_rr_list_deep_free(rrlist);
			zbx_snprintf(err, err_size, "ANSWER section of \"%s\" contains RRs from nameserver \"%s\" (%s)",
					testname, ns, ip);
			*rtt = ZBX_EC_DNS_NS_ERRREPLY;
			goto out;
		}

		/* ownername in the AUTHORITY section must contain NS RRs */
		if (NULL == nsset)
		{
			zbx_snprintf(err, err_size, "no NS records of \"%s\" at nameserver \"%s\" (%s)", testname,
					ns, ip);
			*rtt = ZBX_EC_DNS_NS_ERRREPLY;
			goto out;
		}

		/* ownername in the AUTHORITY section must contain DS RRs */
		if (NULL == (dsset = ldns_pkt_rr_list_by_name_and_type(pkt, testname_rdf, LDNS_RR_TYPE_DS,
				LDNS_SECTION_AUTHORITY)))
		{
			zbx_snprintf(err, err_size, "no DS records of \"%s\" at nameserver \"%s\" (%s)", testname, ns, ip);
			*rtt = ZBX_EC_DNS_NS_ERRREPLY;
			goto out;
		}

		/* end referral validation */

		if (NULL != upd)
		{
			/* extract UNIX timestamp of random NS record */
			rr = ldns_rr_list_rr(nsset, zbx_random(ldns_rr_list_rr_count(nsset)));
			host = ldns_rdf2str(ldns_rr_rdf(rr, 0));

			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS randomly chose ns %s", host);
			if (SUCCEED != zbx_get_ts_from_host(host, &ts))
			{
				zbx_snprintf(err, err_size, "cannot extract Unix timestamp from %s", host);
				zbx_free(host);
				*upd = ZBX_EC_DNS_NS_NOTS;
				goto out;
			}

			now = time(NULL);

			if (0 > now - ts)
			{
				zbx_snprintf(err, err_size, "Unix timestamp of %s is in the future (current: %lu)",
						host, now);
				zbx_free(host);
				*upd = ZBX_EC_DNS_NS_ERRTS;
				goto out;
			}

			zbx_free(host);

			/* successful update time */
			*upd = now - ts;
		}

		if (NULL != keys)
		{
			if (NULL == rrsig)
			{
				zbx_snprintf(err, err_size, "no RRSIG records of \"%s\" at nameserver \"%s\" (%s)",
						testname, ns, ip);
				*rtt = ZBX_EC_DNS_NS_ERRSIG;
				goto out;
			}

			if (1 != ldns_rr_list_rr_count(rrsig))
			{
				zbx_snprintf(err, err_size, "more than one RRSIG record of \"%s\""
						" at nameserver \"%s\" (%s)", testname, ns, ip);
				*rtt = ZBX_EC_DNS_NS_ERRSIG;
				goto out;
			}

			if (SUCCEED != zbx_remove_unmatched_dnskeys(&matched_keys, ldns_rr_list_rr(rrsig, 0), rtt,
					err, err_size))
			{
				goto out;
			}

			if (LDNS_STATUS_OK != (status = ldns_verify(dsset, rrsig, matched_keys, NULL)))
			{
				zbx_snprintf(err, err_size, "cannot verify DS keys of \"%s\" at nameserver \"%s\" (%s). %s",
						testname, ns, ip, ldns_get_errorstr_by_id(status));
				*rtt = ZBX_EC_DNS_NS_ERRSIG;
				goto out;
			}
		}
	}
	else if (NULL != keys)	/* EPP disabled, DNSSEC enabled */
	{
		if (SUCCEED != zbx_remove_unmatched_dnskeys(&matched_keys, ldns_rr_list_rr(rrsig, 0), rtt,
				err, err_size))
		{
			goto out;
		}
	}

	/* successful rtt */
	*rtt = ldns_pkt_querytime(pkt);


	/* no errors */
	ret = SUCCEED;
out:
	if (NULL != upd)
		zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS \"%s\" (%s) RTT:%d UPD:%d", ns, ip, *rtt, *upd);
	else
		zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS \"%s\" (%s) RTT:%d", ns, ip, *rtt);

	if (NULL != rrsig)
		ldns_rr_list_deep_free(rrsig);

	if (NULL != dsset)
		ldns_rr_list_deep_free(dsset);

	if (NULL != nsset)
		ldns_rr_list_deep_free(nsset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	if (NULL != testname_rdf)
		ldns_rdf_deep_free(testname_rdf);

	if (NULL != matched_keys)
		ldns_rr_list_deep_free(matched_keys);

	return ret;
}

static void	zbx_set_value_ts(zbx_timespec_t *ts, int sec)
{
	ts->sec = sec;
	ts->ns = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_add_value                                                    *
 *                                                                            *
 * Purpose: Inject result directly into the cache because we want to specify  *
 *          the value timestamp (beginning of the test).                      *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_add_value(const DC_ITEM *item, AGENT_RESULT *result, int ts)
{
	const char	*__function_name = "zbx_add_value";
	zbx_timespec_t	timespec;

	zabbix_log(LOG_LEVEL_WARNING, "In %s() itemid:" ZBX_FS_UI64 " ts:%d", __function_name, item->itemid, ts);

	zbx_set_value_ts(&timespec, ts);

	dc_add_history(item->itemid, item->value_type, item->flags, result, &timespec, ITEM_STATUS_ACTIVE,
			NULL, 0, NULL, 0, 0, 0, 0);

	zabbix_log(LOG_LEVEL_WARNING, "End of %s()", __function_name);
}

static void	zbx_add_value_uint(const DC_ITEM *item, int ts, int value)
{
	AGENT_RESULT	result;

	result.type = 0;

	SET_UI64_RESULT(&result, value);
	zbx_add_value(item, &result, ts);
}

static void	zbx_add_value_dbl(const DC_ITEM *item, int ts, int value)
{
	AGENT_RESULT	result;

	result.type = 0;

	SET_DBL_RESULT(&result, value);
	zbx_add_value(item, &result, ts);
}

static void	zbx_add_value_str(const DC_ITEM *item, int ts, const char *value)
{
	AGENT_RESULT	result;

	result.type = 0;

	SET_STR_RESULT(&result, value);
	zbx_add_value(item, &result, ts);
}

static void	zbx_set_dnstest_values(const char *item_ns, const char *item_ip, int rtt, int upd, int value_ts,
		size_t keypart_size, const DC_ITEM *items, size_t items_num)
{
	const char	*__function_name = "zbx_set_dnstest_values";

	size_t		i;
	const DC_ITEM	*item;
	const char	*p;
	char		rtt_set = 0, upd_set = 0, ns[ZBX_HOST_BUF_SIZE], ip[ZBX_IP_BUF_SIZE];

	zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS In %s()", __function_name);

	if (ZBX_NO_VALUE == upd)
		upd_set = 1;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		p = item->key + keypart_size;	/* skip "dnstest.dns.<tcp|udp>." part */

		if (0 == rtt_set && 0 == strncmp(p, "rtt[", 4))
		{
			get_param(item->params, 2, ns, sizeof(ns));
			get_param(item->params, 3, ip, sizeof(ip));

			if (0 == strcmp(ns, item_ns) && 0 == strcmp(ip, item_ip))
			{
				zbx_add_value_dbl(item, value_ts, rtt);

				zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS set item %s value %d", item->key, rtt);

				rtt_set = 1;
			}
		}
		else if (0 == upd_set && 0 == strncmp(p, "upd[", 4))
		{
			get_param(item->params, 2, ns, sizeof(ns));
			get_param(item->params, 3, ip, sizeof(ip));

			if (0 == strcmp(ns, item_ns) && 0 == strcmp(ip, item_ip))
			{
				zbx_add_value_dbl(item, value_ts, upd);

				zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS set item %s value %d", item->key, upd);

				upd_set = 1;
			}
		}

		if (0 != rtt_set && 0 != upd_set)
			return;
	}

	zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS End of %s() rtt_set:%d", __function_name, rtt_set);
}

static int	zbx_get_dnskeys(const ldns_resolver *res, const char *domain, const char *resolver,
		ldns_rr_list **keys, FILE *pkt_file, int *ec, char *err, size_t err_size)
{
	ldns_pkt	*pkt = NULL;
	ldns_rdf	*domain_rdf = NULL;
	ldns_rr_list	*rrset = NULL;
	int		i, ret = FAIL;

	if (NULL == (domain_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, domain)))
	{
		zbx_snprintf(err, err_size, "invalid domain name \"%s\"", domain);
		*ec = ZBX_EC_INTERNAL;
		goto out;
	}

	/* query domain records */
	if (NULL == (pkt = ldns_resolver_query(res, domain_rdf, LDNS_RR_TYPE_DNSKEY, LDNS_RR_CLASS_IN, LDNS_RD)))
	{
		zbx_snprintf(err, err_size, "cannot connect to resolver \"%s\"", resolver);
		*ec = ZBX_EC_DNS_RES_NOREPLY;
		goto out;
	}

	/* log the packet */
	ldns_pkt_print(pkt_file, pkt);

	/* check the ad flag */
	if (0 == ldns_pkt_ad(pkt))
	{
		zbx_snprintf(err, err_size, "AD flag not set in the answer of \"%s\" from resolver \"%s\"",
				domain, resolver);
		*ec = ZBX_EC_DNS_RES_NOADBIT;
		goto out;
	}

	/* get the DNSKEY records */
	if (NULL == (rrset = ldns_pkt_rr_list_by_name_and_type(pkt, domain_rdf, LDNS_RR_TYPE_DNSKEY,
			LDNS_SECTION_ANSWER)))
	{
		zbx_snprintf(err, err_size, "no DNSKEY records of domain \"%s\" from resolver \"%s\"", domain,
				resolver);
		*ec = ZBX_EC_DNS_NS_ERRSIG;
		goto out;
	}

	/* ignore non-ZSK DNSKEYs */
	for (i = 0; i < ldns_rr_list_rr_count(rrset); i++)
	{
		const ldns_rr	*rr;

		rr = ldns_rr_list_rr(rrset, i);

		if (256 == ldns_rdf2native_int16(ldns_rr_dnskey_flags(rr)))
		{
			if (NULL == *keys)
				*keys = ldns_rr_list_new();

			if (false == ldns_rr_list_push_rr(*keys, ldns_rr_clone(rr)))
			{
				zbx_strlcpy(err, "internal error: cannot add rr to rr_list", err_size);
				*ec = ZBX_EC_INTERNAL;
				goto out;
			}
		}
	}

	if (NULL == *keys)
	{
		zbx_snprintf(err, err_size, "no ZSK DNSKEY records of domain \"%s\" from resolver \"%s\"", domain,
				resolver);
		*ec = ZBX_EC_DNS_NS_ERRSIG;
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != domain_rdf)
		ldns_rdf_deep_free(domain_rdf);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	return ret;
}

#define zbx_dns_err(f, text)	zbx_dns_log(f, "Error", text)
static void	zbx_dns_log(FILE *f, const char *prefix, const char *text)
{
	struct timeval	current_time;
	struct tm	*tm;
	long		ms;

	gettimeofday(&current_time, NULL);
	tm = localtime(&current_time.tv_sec);
	ms = current_time.tv_usec / 1000;

	fprintf(f, "[%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld] %s: %s\n",
			tm->tm_year + 1900,
			tm->tm_mon + 1,
			tm->tm_mday,
			tm->tm_hour,
			tm->tm_min,
			tm->tm_sec,
			ms,
			prefix,
			text);
}

#define zbx_dns_errf(f, fmt, ...)	zbx_dns_logf(f, "Error", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#define zbx_dns_warnf(f, fmt, ...)	zbx_dns_logf(f, "Warning", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#define zbx_dns_infof(f, fmt, ...)	zbx_dns_logf(f, "Info", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
static void	zbx_dns_logf(FILE *f, const char *prefix, const char *fmt, ...)
{
	va_list		args;
	char		fmt_buf[ZBX_ERR_BUF_SIZE];
	struct timeval	current_time;
	struct tm	*tm;
	long		ms;

	gettimeofday(&current_time, NULL);
	tm = localtime(&current_time.tv_sec);
	ms = current_time.tv_usec / 1000;

	zbx_snprintf(fmt_buf, sizeof(fmt_buf), "[%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld] %s: %s\n",
			tm->tm_year + 1900,
			tm->tm_mon + 1,
			tm->tm_mday,
			tm->tm_hour,
			tm->tm_min,
			tm->tm_sec,
			ms,
			prefix,
			fmt);
	fmt = fmt_buf;

	va_start(args, fmt);
	vfprintf(f, fmt, args);
	va_end(args);
}

static int	zbx_parse_dns_item(DC_ITEM *item, char *host, size_t host_size)
{
	char	keyname[32], params[MAX_STRING_LEN];
	char	ns[ZBX_HOST_BUF_SIZE], ip[ZBX_IP_BUF_SIZE];

	if (0 == parse_command(item->key, keyname, sizeof(keyname), params, sizeof(params)))
	{
		/* unexpected key syntax */
		return FAIL;
	}

	if (0 != get_param(params, 1, host, host_size) || '\0' == *host)
	{
		/* first parameter missing */
		return FAIL;
	}

	if (0 != get_param(params, 2, ns, sizeof(ns)) || '\0' == *ns)
	{
		/* second parameter missing */
		return FAIL;
	}

	if (0 != get_param(params, 3, ip, sizeof(ip)) || '\0' == *ip)
	{
		/* third parameter missing */
		return FAIL;
	}

	ZBX_STRDUP(item->params, params);

	return SUCCEED;
}

static int	zbx_parse_rdds_item(DC_ITEM *item, char *host, size_t host_size)
{
	char	keyname[32], params[MAX_STRING_LEN];

	if (0 == parse_command(item->key, keyname, sizeof(keyname), params, sizeof(params)))
	{
		/* unexpected key syntax */
		return FAIL;
	}

	if (0 != get_param(params, 1, host, host_size) || '\0' == *host)
	{
		/* first parameter missing */
		return FAIL;
	}

	return SUCCEED;
}

static size_t	zbx_get_dns_items(const char *keyname, DC_ITEM *item, const char *domain, DC_ITEM **out_items)
{
	char		*keypart, host[ZBX_HOST_BUF_SIZE];
	const char	*p;
	DC_ITEM		*in_items = NULL;
	size_t		i, in_items_num, out_items_num = 0, out_items_alloc = 8, keypart_size;

	/* get items from config cache */
	keypart = zbx_dsprintf(NULL, "%s.", keyname);
	keypart_size = strlen(keypart);
	in_items_num = DCconfig_get_host_items_by_keypart(&in_items, item->host.hostid, ITEM_TYPE_TRAPPER, keypart,
			keypart_size);
	zbx_free(keypart);

	/* filter out invalid items */
	for (i = 0; i < in_items_num; i++)
	{
		ZBX_STRDUP(in_items[i].key, in_items[i].key_orig);
		if (SUCCEED != substitute_key_macros(&in_items[i].key, NULL, item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0))
		{
			/* unexpected item key syntax, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS %s: cannot substitute key macros", in_items[i].key_orig);
			continue;
		}

		in_items[i].params = NULL;
		if (SUCCEED != zbx_parse_dns_item(&in_items[i], host, sizeof(host)))
		{
			/* unexpected item key syntax, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS %s: unexpected key syntax", in_items[i].key);
			continue;
		}

		if (0 != strcmp(host, domain))
		{
			/* first parameter does not match expected domain name, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS %s: first parameter does not match host %s", in_items[i].key,
					domain);
			continue;
		}

		p = in_items[i].key + keypart_size;
		if (0 != strncmp(p, "rtt[", 4) && 0 != strncmp(p, "upd[", 4))
		{
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS %s: not our item", in_items[i].key);
			continue;
		}

		if (0 == out_items_num)
		{
			*out_items = zbx_malloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}
		else if (out_items_num == out_items_alloc)
		{
			out_items_alloc += 8;
			*out_items = zbx_realloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}

		memcpy(&(*out_items)[out_items_num], &in_items[i], sizeof(DC_ITEM));
		(*out_items)[out_items_num].key = in_items[i].key;
		(*out_items)[out_items_num].params = in_items[i].params;
		in_items[i].key = NULL;
		in_items[i].params = NULL;

		out_items_num++;
	}

	if (0 != in_items_num)
		zbx_free(in_items);

	return out_items_num;
}

static size_t	zbx_get_nameservers(const DC_ITEM *items, size_t items_num, zbx_ns_t **nss)
{
	char		ns[ZBX_HOST_BUF_SIZE], ip[ZBX_IP_BUF_SIZE], ns_found, ip_found;
	size_t		i, j, j2, nss_num = 0, nss_alloc = 8;
	zbx_ns_t	*ns_entry;
	const DC_ITEM	*item;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		ns_found = ip_found = 0;

		get_param(item->params, 2, ns, sizeof(ns));
		get_param(item->params, 3, ip, sizeof(ip));

		if (0 == nss_num)
		{
			*nss = zbx_malloc(*nss, nss_alloc * sizeof(zbx_ns_t));
		}
		else
		{
			/* check if need to add entry */
			for (j = 0; j < nss_num; j++)
			{
				ns_entry = &(*nss)[j];

				if (0 != strcmp(ns_entry->name, ns))
					continue;

				ns_found = 1;

				for (j2 = 0; j2 < ns_entry->ips_num; j2++)
				{
					if (0 == strcmp(ns_entry->ips[j2], ip))
					{
						ip_found = 1;
						break;
					}
				}

				break;
			}

			if (0 != ip_found)
				continue;
		}

		if (nss_num == nss_alloc)
		{
			nss_alloc += 8;
			*nss = zbx_realloc(*nss, nss_alloc * sizeof(zbx_ns_t));
		}

		/* add entry here */
		if (0 == ns_found)
		{
			ns_entry = &(*nss)[nss_num];

			ns_entry->name = zbx_strdup(NULL, ns);
			ns_entry->result = SUCCEED;
			ns_entry->ips_num = 1;
			ns_entry->ips = zbx_malloc(NULL, sizeof(char *));
			ns_entry->ips[0] = zbx_strdup(NULL, ip);

			nss_num++;
		}
		else
		{
			ns_entry = &(*nss)[j];

			ns_entry->ips_num++;
			ns_entry->ips = zbx_realloc(ns_entry->ips, ns_entry->ips_num * sizeof(char *));
			ns_entry->ips[ns_entry->ips_num - 1] = zbx_strdup(NULL, ip);
		}
	}

	return nss_num;
}

static void	zbx_clean_nss(zbx_ns_t *nss, size_t nss_num)
{
	size_t	i, j;

	for (i = 0; i < nss_num; i++)
	{
		for (j = 0; j < nss[i].ips_num; j++)
			zbx_free(nss[i].ips[j]);

		zbx_free(nss[i].ips);
		zbx_free(nss[i].name);
	}
}

static int	zbx_check_rtt_limit(int rtt, int rtt_limit)
{
	if (rtt <= rtt_limit * 5)
		return SUCCEED;

	return FAIL;
}

static int	zbx_is_service_error(int ec)
{
	if (-200 >= ec && ec >= -999)
		return SUCCEED;

	return FAIL;
}

static int	zbx_conf_str(zbx_uint64_t *hostid, const char *macro, char **value, char *err, size_t err_size)
{
	int	ret = FAIL;

	if (NULL != *value)
	{
		zbx_snprintf(err, err_size, "internal error getting macro %s, unfreed memory", macro);
		goto out;
	}

	DCget_user_macro(hostid, 1, macro, value);
	if (NULL == *value || '\0' == **value)
	{
		zbx_snprintf(err, err_size, "macro %s is not set", macro);
		zbx_free(*value);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	zbx_conf_int(zbx_uint64_t *hostid, const char *macro, int *value, char min, char *err, size_t err_size)
{
	char	*value_str = NULL;
	int	ret = FAIL;

	DCget_user_macro(hostid, 1, macro, &value_str);
	if (NULL == value_str || '\0' == *value_str)
	{
		zbx_snprintf(err, err_size, "macro %s is not set", macro);
		goto out;
	}

	*value = atoi(value_str);

	if (min > *value)
	{
		zbx_snprintf(err, err_size, "the value of macro %s cannot be less than %d", macro, min);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(value_str);

	return ret;
}

static int	zbx_conf_ip_support(zbx_uint64_t *hostid, int *ipv4_enabled, int *ipv6_enabled,
		char *err, size_t err_size)
{
	int	ret = FAIL;

	if (SUCCEED != zbx_conf_int(hostid, ZBX_MACRO_IP4_ENABLED, ipv4_enabled, 0, err, err_size))
		goto out;

	if (SUCCEED != zbx_conf_int(hostid, ZBX_MACRO_IP6_ENABLED, ipv6_enabled, 0, err, err_size))
		goto out;

	if (0 == *ipv4_enabled && 0 == *ipv6_enabled)
	{
		zbx_strlcpy(err, "both IPv4 and IPv6 disabled", err_size);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

FILE	*open_item_log(const char *domain, const char *prefix, char *err, size_t err_size)
{
	FILE		*fd;
	char		*file_name;
	const char	*p = NULL;

	if (NULL == CONFIG_LOG_FILE)
	{
		zbx_strlcpy(err, "zabbix log file (LogFile) is not set", err_size);
		return NULL;
	}

	p = CONFIG_LOG_FILE + strlen(CONFIG_LOG_FILE) - 1;

	while (CONFIG_LOG_FILE != p && '/' != *p)
		p--;

	if (CONFIG_LOG_FILE == p)
		file_name = zbx_strdup(NULL, ZBX_DNSTEST_DEFAULT_LOGDIR);
	else
		file_name = zbx_dsprintf(NULL, "%.*s", p - CONFIG_LOG_FILE, CONFIG_LOG_FILE);

	if (NULL == domain)
		file_name = zbx_strdcatf(file_name, "/%s.log", prefix);
	else
		file_name = zbx_strdcatf(file_name, "/%s-%s.log", domain, prefix);

	if (NULL == (fd = fopen(file_name, "a")))
		zbx_snprintf(err, err_size, "cannot open log file \"%s\". %s.", file_name, strerror(errno));

	zbx_free(file_name);

	return fd;
}

/* rr - round robin */
static char	*zbx_get_rr_tld(const char *self, char *err, size_t err_size)
{
	static int		index = 0;

	zbx_vector_uint64_t	hostids;
	char			*tld = NULL, *p;
	DC_HOST			host;

	zbx_vector_uint64_create(&hostids);

	DBget_hostids_by_item(&hostids, "dnstest.dns.udp[{$DNSTEST.TLD}]");	/* every monitored host has this item */

	if (2 > hostids.values_num)	/* skip self */
	{
		zbx_strlcpy(err, "cannot get random TLD: no hosts found", err_size);
		goto out;
	}

	do
	{
		if (index >= hostids.values_num)
			index = 0;

		if (1 < hostids.values_num)
			zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (SUCCEED != DCget_host_by_hostid(&host, hostids.values[index]))
		{
			zbx_strlcpy(err, "cannot get random TLD: configuration cache error", err_size);
			goto out;
		}

		tld = zbx_strdup(tld, host.host);

		p = tld;
		while ('\0' != *p && 0 == isspace(*p))
			p++;

		if (0 != isspace(*p))
			*p = '\0';

		index++;

		if (0 == strcmp(self, tld))
			zbx_free(tld);
		else
			break;
	}
	while (1);
out:
	zbx_vector_uint64_destroy(&hostids);

	return tld;
}

int	check_dnstest_dns(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result, char proto)
{
	const char	*__function_name = "check_dnstest_dns";
	char		err[ZBX_ERR_BUF_SIZE], domain[ZBX_HOST_BUF_SIZE], ok_nss_num = 0, *res_ip = NULL,
			*testprefix = NULL;
	ldns_resolver	*res = NULL;
	ldns_rr_list	*keys = NULL;
	FILE		*log_fd;
	DC_ITEM		*items = NULL;
	zbx_ns_t	*nss = NULL;
	size_t		i, j, items_num = 0, nss_num;
	int		ipv4_enabled, ipv6_enabled, dnssec_enabled, epp_enabled, rdds_enabled, res_ec = ZBX_EC_NOERROR,
			rtt, upd = ZBX_NO_VALUE, rtt_limit, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_WARNING, "In %s() keyname:'%s' params:'%s'", __function_name, keyname, params);

	if (0 != get_param(params, 1, domain, sizeof(domain)) || '\0' == *domain)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "first key parameter missing"));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_DNSSEC_ENABLED, &dnssec_enabled, 0,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_RDDS_ENABLED, &rdds_enabled, 0,
					err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_EPP_ENABLED, &epp_enabled, 0,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_RESOLVER, &res_ip, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_TESTPREFIX, &testprefix, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (0 == strcmp(testprefix, "*RANDOMTLD*"))
	{
		zbx_free(testprefix);

		if (NULL == (testprefix = zbx_get_rr_tld(domain, err, sizeof(err))))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		zabbix_log(LOG_LEVEL_WARNING, "DNSTEST DNS testprefix: \"%s\"", testprefix);
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_DNSTEST_UDP == proto ? ZBX_MACRO_DNS_UDP_RTT :
			ZBX_MACRO_DNS_TCP_RTT, &rtt_limit, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_ip_support(&item->host.hostid, &ipv4_enabled, &ipv6_enabled, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, proto, ipv4_enabled, ipv6_enabled,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot create resolver: %s", err));
		goto out;
	}

	/* open dns log file */
	if (NULL == (log_fd = open_item_log(domain, ZBX_DNS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* get dnstest items */
	if (0 == (items_num = zbx_get_dns_items(keyname, item, domain, &items)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "no trapper %s.* items found", keyname));
		goto out;
	}

	ret = SYSINFO_RET_OK;

	/* get DNSKEY records */
	if (0 != dnssec_enabled && SUCCEED != zbx_get_dnskeys(res, domain, res_ip, &keys, log_fd, &res_ec,
			err, sizeof(err)))
	{
		zbx_dns_err(log_fd, err);
	}

	nss_num = zbx_get_nameservers(items, items_num, &nss);

	for (i = 0; i < nss_num; i++)
	{
		for (j = 0; j < nss[i].ips_num; j++)
		{
			if (ZBX_EC_NOERROR == res_ec)
			{
				if (SUCCEED != zbx_get_ns_values(res, nss[i].name, nss[i].ips[j], keys, testprefix,
						domain, log_fd, &rtt,
						(ZBX_DNSTEST_UDP == proto && 0 != rdds_enabled) ? &upd : NULL,
						ipv4_enabled, ipv6_enabled, epp_enabled, err, sizeof(err)))
				{
					zbx_dns_err(log_fd, err);
				}
			}
			else
				rtt = res_ec;

			zbx_set_dnstest_values(nss[i].name, nss[i].ips[j], rtt, upd, item->nextcheck,
					strlen(keyname) + 1, items, items_num);

			/* The ns is considered non-working only in case it was the one to blame. Resolver */
			/* and internal errors do not count. Another case of ns fail is slow response.     */
			if ((0 > rtt && SUCCEED == zbx_is_service_error(rtt)) || SUCCEED != zbx_check_rtt_limit(rtt, rtt_limit))
				nss[i].result = FAIL;
		}
	}

	if (0 != items_num)
	{
		for (i = 0; i < items_num; i++)
		{
			zbx_free(items[i].key);
			zbx_free(items[i].params);
		}

		zbx_free(items);
	}

	for (i = 0; i < nss_num; i++)
	{
		if (SUCCEED == nss[i].result)
			ok_nss_num++;
	}

	zbx_add_value_uint(item, item->nextcheck, ok_nss_num);

	if (0 != nss_num)
	{
		zbx_clean_nss(nss, nss_num);
		zbx_free(nss);
	}

	if (NULL != keys)
		ldns_rr_list_deep_free(keys);

	if (NULL != log_fd)
		fclose(log_fd);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}
out:
	zbx_free(testprefix);
	zbx_free(res_ip);

	zabbix_log(LOG_LEVEL_WARNING, "End of %s():%s", __function_name, SYSINFO_RET_OK == ret ? "SUCCEED" : "FAIL");

	return ret;
}

static void	zbx_get_rdds43_nss(zbx_vector_str_t *nss, const char *recv_buf, const char *rdds_ns_string, FILE *f)
{
	const char	*p;
	char		ns_buf[ZBX_HOST_BUF_SIZE];
	size_t		rdds_ns_string_size, ns_buf_len;

	p = recv_buf;
	rdds_ns_string_size = strlen(rdds_ns_string);

	do
	{
		while ('\0' != *p && 0 != isspace(*p))
			p++;

		if (0 == strncmp(p, rdds_ns_string, rdds_ns_string_size))
		{
			p += rdds_ns_string_size;

			while (0 != isblank(*p))
				p++;

			if (0 == isalnum(*p))
				continue;

			ns_buf_len = 0;
			while ('\0' != *p && 0 == isspace(*p) && ns_buf_len < sizeof(ns_buf))
			{
				ns_buf[ns_buf_len++] = *p++;
			}

			if (sizeof(ns_buf) == ns_buf_len)
			{
				/* internal error, ns buffer not enough */
				zbx_dns_errf(f, "DNSTEST RDDS internal error, ns buffer too small (%u) for host in \"%s\"",
						sizeof(ns_buf), p);
				continue;
			}

			ns_buf[ns_buf_len] = '\0';
			zbx_vector_str_append(nss, zbx_strdup(NULL, ns_buf));
		}
	}
	while (NULL != (p = strstr(p, "\n")) && '\0' != *++p);

	if (0 != nss->values_num)
	{
		zbx_vector_str_sort(nss, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(nss, ZBX_DEFAULT_STR_COMPARE_FUNC);
	}
}

static size_t	zbx_get_rdds_items(const char *keyname, DC_ITEM *item, const char *domain, DC_ITEM **out_items)
{
	char		*keypart, host[ZBX_HOST_BUF_SIZE];
	const char	*p;
	DC_ITEM		*in_items = NULL;
	size_t		i, in_items_num, out_items_num = 0, out_items_alloc = 8, keypart_size;

	/* get items from config cache */
	keypart = zbx_dsprintf(NULL, "%s.", keyname);
	keypart_size = strlen(keypart);
	in_items_num = DCconfig_get_host_items_by_keypart(&in_items, item->host.hostid, ITEM_TYPE_TRAPPER, keypart,
			keypart_size);
	zbx_free(keypart);

	/* filter out invalid items */
	for (i = 0; i < in_items_num; i++)
	{
		ZBX_STRDUP(in_items[i].key, in_items[i].key_orig);
		if (SUCCEED != substitute_key_macros(&in_items[i].key, NULL, item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0))
		{
			/* unexpected item key syntax, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS %s: cannot substitute key macros", in_items[i].key_orig);
			continue;
		}

		if (SUCCEED != zbx_parse_rdds_item(&in_items[i], host, sizeof(host)))
		{
			/* unexpected item key syntax, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS %s: unexpected key syntax", in_items[i].key);
			continue;
		}

		if (0 != strcmp(host, domain))
		{
			/* first parameter does not match expected domain name, skip it */
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS %s: first parameter does not match host %s", in_items[i].key,
					domain);
			continue;
		}

		p = in_items[i].key + keypart_size;
		if (0 != strncmp(p, "43.ip[", 6) && 0 != strncmp(p, "43.rtt[", 7) && 0 != strncmp(p, "43.upd[", 7) &&
				0 != strncmp(p, "80.ip[", 6) && 0 != strncmp(p, "80.rtt[", 7))
		{
			zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS %s: not our item", in_items[i].key);
			continue;
		}

		if (0 == out_items_num)
		{
			*out_items = zbx_malloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}
		else if (out_items_num == out_items_alloc)
		{
			out_items_alloc += 8;
			*out_items = zbx_realloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}

		memcpy(&(*out_items)[out_items_num], &in_items[i], sizeof(DC_ITEM));
		(*out_items)[out_items_num].key = in_items[i].key;
		(*out_items)[out_items_num].params = in_items[i].params;
		in_items[i].key = NULL;
		in_items[i].params = NULL;

		out_items_num++;
	}

	if (0 != in_items_num)
		zbx_free(in_items);

	return out_items_num;
}

static int	zbx_tcp_exchange(const char *request, const char *host, short port, int timeout, char **answer,
		int *rtt, FILE *f, char *err, size_t err_size)
{
	zbx_sock_t	s;
	char		*recv_buf, *send_buf = NULL;
	zbx_timespec_t	start, now;
	int		ret = FAIL;

	memset(&s, 0, sizeof(s));
	zbx_timespec(&start);

	zbx_dns_infof(f, "start RDDS%hd test", port);

	if (SUCCEED != zbx_tcp_connect(&s, NULL, host, port, timeout))
	{
		zbx_strlcpy(err, "cannot connect to host", err_size);
		goto out;
	}

	send_buf = zbx_dsprintf(send_buf, "%s\r\n", request);
	timeout -= time(NULL) - start.sec;

	if (SUCCEED != zbx_tcp_send_ext(&s, send_buf, 0, timeout))
	{
		zbx_strlcpy(err, "error sending data", err_size);
		goto out;
	}

	timeout -= time(NULL) - start.sec;

	if (SUCCEED != SUCCEED_OR_FAIL(zbx_tcp_recv_ext(&s, &recv_buf, ZBX_TCP_READ_UNTIL_CLOSE, timeout)))
	{
		zbx_strlcpy(err, "error receiving data", err_size);
		goto out;
	}

	ret = SUCCEED;
	zbx_timespec(&now);
	*rtt = (now.sec - start.sec) * 1000 + (now.ns - start.ns) / 1000000;

	zbx_dns_infof(f, "===>\n%s\n<=== end RDDS%hd test", recv_buf, port);

	if (NULL != answer)
		*answer = zbx_strdup(*answer, recv_buf);
out:
	zbx_tcp_close(&s);
	zbx_free(send_buf);

	return ret;
}

static int	zbx_resolve_hosts(const ldns_resolver *res, const zbx_vector_str_t *hosts, zbx_vector_str_t *ips,
		int ipv4_enabled, int ipv6_enabled, FILE *f, char *err, size_t err_size)
{
	ldns_pkt	*pkt = NULL;
	ldns_rdf	*host_rdf = NULL;
	ldns_rr_list	*rrset = NULL;
	size_t		i, j;
	const char	*host;
	char		*ip;
	int		ret = FAIL, rr_count, ip_count;

	for (i = 0; i < hosts->values_num; i++)
	{
		ip_count = 0;
		host = hosts->values[i];

		if (NULL != pkt)
			ldns_pkt_free(pkt);

		if (0 != ipv4_enabled)
		{
			if (NULL != host_rdf)
				ldns_rdf_deep_free(host_rdf);

			if (NULL == (host_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, host)))
			{
				zbx_snprintf(err, err_size, "invalid host name \"%s\"", host);
				goto out;
			}

			if (NULL == (pkt = ldns_resolver_query(res, host_rdf, LDNS_RR_TYPE_A, LDNS_RR_CLASS_IN,
					LDNS_RD)))
			{
				zbx_snprintf(err, err_size, "cannot resolve host \"%s\" to IPv4 address", host);
				goto out;
			}

			ldns_pkt_print(f, pkt);

			if (NULL != rrset)
				ldns_rr_list_deep_free(rrset);

			if (NULL != (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_A, LDNS_SECTION_ANSWER)))
			{
				rr_count = ldns_rr_list_rr_count(rrset);
				for (j = 0; j < rr_count; j++)
				{
					ip = ldns_rdf2str(ldns_rr_a_address(ldns_rr_list_rr(rrset, j)));
					zbx_vector_str_append(ips, ip);

					ip_count++;
				}
			}
		}

		if (0 != ipv6_enabled)
		{
			ldns_pkt_free(pkt);

			if (NULL == host_rdf && NULL == (host_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, host)))
			{
				zbx_snprintf(err, err_size, "invalid host name \"%s\"", host);
				goto out;
			}

			if (NULL == (pkt = ldns_resolver_query(res, host_rdf, LDNS_RR_TYPE_AAAA, LDNS_RR_CLASS_IN,
					LDNS_RD)))
			{
				zbx_snprintf(err, err_size, "cannot resolve host \"%s\" to IPv6 address", host);
				goto out;
			}

			ldns_pkt_print(f, pkt);

			if (NULL != rrset)
				ldns_rr_list_deep_free(rrset);

			if (NULL != (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_AAAA, LDNS_SECTION_ANSWER)))
			{
				rr_count = ldns_rr_list_rr_count(rrset);
				for (j = 0; j < rr_count; j++)
				{
					ip = ldns_rdf2str(ldns_rr_a_address(ldns_rr_list_rr(rrset, j)));
					zbx_vector_str_append(ips, ip);

					ip_count++;
				}
			}
		}

		if (0 == ip_count)
		{
			zbx_snprintf(err, err_size, "no IPv4 or IPv6 addresses of \"%s\" returned from resolver", host);
			goto out;
		}
	}

	if (0 != ips->values_num)
	{
		zbx_vector_str_sort(ips, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(ips, ZBX_DEFAULT_STR_COMPARE_FUNC);

		ret = SUCCEED;
	}
	else
		zbx_strlcpy(err, "no hosts to resolve", err_size);
out:
	if (NULL != host_rdf)
		ldns_rdf_deep_free(host_rdf);

	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	return ret;
}

static int	zbx_validate_host_list(const char *list, char delim)
{
	const char	*p;

	p = list;

	while ('\0' != *p && (0 != isalnum(*p) || '.' == *p || '-' == *p || '_' == *p || ':' == *p || delim == *p))
		p++;

	if ('\0' == *p)
		return SUCCEED;

	return FAIL;
}

static void	zbx_get_strings_from_list(zbx_vector_str_t *strings, char *list, char delim, char *err, size_t err_size)
{
	char	*p, *p_end;

	if (NULL == list || '\0' == *list)
		return;

	p = list;
	while ('\0' != *p && delim == *p)
		p++;

	if ('\0' == *p)
		return;

	do
	{
		p_end = strchr(p, delim);
		if (NULL != p_end)
			*p_end = '\0';

		zbx_vector_str_append(strings, zbx_strdup(NULL, p));

		if (NULL != p_end)
		{
			*p_end = delim;

			while ('\0' != *p_end && delim == *p_end)
				p_end++;

			if ('\0' == *p_end)
				p_end = NULL;
			else
				p = p_end;
		}
	}
	while (NULL != p_end);
}

static void	zbx_set_rddstest_values(const char *ip43, int rtt43, int upd43, const char *ip80, int rtt80,
		int value_ts, size_t keypart_size, const DC_ITEM *items, size_t items_num)
{
	size_t		i;
	const DC_ITEM	*item;
	const char	*p;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		p = item->key + keypart_size + 1;	/* skip "rddstest." part */

		if (NULL != ip43 && 0 == strncmp(p, "43.ip[", 6))
			zbx_add_value_str(item, value_ts, ip43);
		else if (0 == strncmp(p, "43.rtt[", 7))
			zbx_add_value_dbl(item, value_ts, rtt43);
		else if (ZBX_NO_VALUE != upd43 && 0 == strncmp(p, "43.upd[", 7))
			zbx_add_value_dbl(item, value_ts, upd43);
		else if (NULL != ip80 && 0 == strncmp(p, "80.ip[", 6))
			zbx_add_value_str(item, value_ts, ip80);
		else if (ZBX_NO_VALUE != rtt80 && 0 == strncmp(p, "80.rtt[", 7))
			zbx_add_value_dbl(item, value_ts, rtt80);
	}
}

int	check_dnstest_rdds(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result)
{
	const char		*__function_name = "check_dnstest_rdds";

	char			domain[ZBX_HOST_BUF_SIZE], *value_str = NULL, *res_ip = NULL, *testprefix = NULL,
				*rdds_ns_string = NULL, *answer = NULL, testname[ZBX_HOST_BUF_SIZE],
				err[ZBX_ERR_BUF_SIZE], *random_ns = NULL;
	const char		*ip43 = NULL, *ip80 = NULL;
	zbx_vector_str_t	hosts43, hosts80, ips43, ips80, nss;
	FILE			*log_fd = NULL;
	ldns_resolver		*res = NULL;
	DC_ITEM			*items = NULL;
	size_t			i, items_num = 0;
	time_t			ts, now;
	int			rtt43 = ZBX_NO_VALUE, upd43 = ZBX_NO_VALUE, rtt80 = ZBX_NO_VALUE, rtt_limit,
				ipv4_enabled, ipv6_enabled, rdds_enabled, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_WARNING, "In %s() keyname:'%s' params:'%s'", __function_name, keyname, params);

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_RDDS_ENABLED, &rdds_enabled, 0, err, sizeof(err)) ||
			0 == rdds_enabled)
	{
		zabbix_log(LOG_LEVEL_WARNING, "End of %s(): disabled on this probe", __function_name);
		return SYSINFO_RET_OK;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_RDDS_ENABLED, &rdds_enabled, 0,
			err, sizeof(err)) || 0 == rdds_enabled)
	{
		zabbix_log(LOG_LEVEL_WARNING, "End of %s(): disabled on this TLD", __function_name);
		return SYSINFO_RET_OK;
	}

	zbx_vector_str_create(&hosts43);
	zbx_vector_str_create(&hosts80);
	zbx_vector_str_create(&ips43);
	zbx_vector_str_create(&ips80);
	zbx_vector_str_create(&nss);

	if (0 != get_param(params, 1, domain, sizeof(domain)) || '\0' == *domain)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "first key parameter missing"));
		goto out;
	}

	if (NULL == (value_str = get_param_dyn(params, 2)) || '\0' == *value_str)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "second key parameter missing"));
		goto out;
	}

	if (SUCCEED != zbx_validate_host_list(value_str, ','))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "invalid character in RDDS43 host list"));
		goto out;
	}

	zbx_get_strings_from_list(&hosts43, value_str, ',', err, sizeof(err));

	if (0 == hosts43.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "no RDDS43 hosts"));
		goto out;
	}

	zbx_free(value_str);

	if (NULL == (value_str = get_param_dyn(params, 3)) || '\0' == *value_str)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "third key parameter missing"));
		goto out;
	}

	if (SUCCEED != zbx_validate_host_list(value_str, ','))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "invalid character in RDDS80 host list"));
		goto out;
	}

	zbx_get_strings_from_list(&hosts80, value_str, ',', err, sizeof(err));

	if (0 == hosts80.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "no RDDS80 hosts"));
		goto out;
	}

	/* get configuration */
	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_RESOLVER, &res_ip, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_TESTPREFIX, &testprefix, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (0 == strcmp(testprefix, "*RANDOMTLD*"))
	{
		zbx_free(testprefix);

		if (NULL == (testprefix = zbx_get_rr_tld(domain, err, sizeof(err))))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS testprefix: \"%s\"", testprefix);
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_RDDS_NS_STRING, &rdds_ns_string, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_RDDS_RTT, &rtt_limit, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_ip_support(&item->host.hostid, &ipv4_enabled, &ipv6_enabled, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, ZBX_DNSTEST_TCP, ipv4_enabled, ipv6_enabled,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot create resolver: %s", err));
		goto out;
	}

	for (i = 0; i < items_num; i++)
		zabbix_log(LOG_LEVEL_WARNING, "DNSTEST RDDS got item %s", items[i].key);

	/* open rdds log file */
	if (NULL == (log_fd = open_item_log(domain, ZBX_RDDS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* get rddstest items */
	if (0 == (items_num = zbx_get_rdds_items(keyname, item, domain, &items)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "no rddstest items found"));
		goto out;
	}

	/* from this point something will be saved as rddstest item values, either error code or real result */
	ret = SYSINFO_RET_OK;

	/* start RDDS43 test, resolve hosts to ips */
	if (SUCCEED != zbx_resolve_hosts(res, &hosts43, &ips43, ipv4_enabled, ipv6_enabled, log_fd, err, sizeof(err)))
	{
		rtt43 = ZBX_EC_RDDS_ERRRES;
		zbx_dns_errf(log_fd, "RDDS43: %s", err);
		goto out;
	}

	/* choose random IP */
	i = zbx_random(ips43.values_num);
	ip43 = ips43.values[i];

	if (0 != strcmp(".", domain))
		zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, domain);
	else
		zbx_strlcpy(testname, testprefix, sizeof(testname));

	if (SUCCEED != zbx_tcp_exchange(testname, ip43, 43, ZBX_DNSTEST_TCP_TIMEOUT, &answer, &rtt43, log_fd, err,
			sizeof(err)))
	{
		rtt43 = ZBX_EC_RDDS43_NOREPLY;
		zbx_dns_errf(log_fd, "no answer from RDDS43 server %s", ip43, err);
		goto out;
	}

	zabbix_log(LOG_LEVEL_WARNING, "RECEIVED ==>%s<==", answer ? answer : "NULL");

	zbx_get_rdds43_nss(&nss, answer, rdds_ns_string, log_fd);

	if (0 == nss.values_num)
	{
		rtt43 = ZBX_EC_RDDS43_NONS;
		zbx_dns_errf(log_fd, "no Name Servers found in the output of RDDS43 server %s for query \"%s\""
				" (using prefix \"%s\")", ip43, testname, rdds_ns_string);
		goto out;
	}

	/* choose random host */
	i = zbx_random(nss.values_num);
	random_ns = nss.values[i];

	zbx_dns_infof(log_fd, "selected Name Server \"%s\" from the output", random_ns);

	/* If we are here it means RDDS43 was successful. Now we will perform the UPD */
	/* test but its errors will not affect the fact that we will run RDDS80 test. */

	/* start RDDS UPD test, get timestamp from the host name */
	if (SUCCEED != zbx_get_ts_from_host(random_ns, &ts))
	{
		upd43 = ZBX_EC_RDDS43_NOTS;
		zbx_dns_errf(log_fd, "cannot extract Unix timestamp from Name Server \"%s\"", random_ns);
	}

	if (upd43 == ZBX_NO_VALUE)
	{
		now = time(NULL);

		if (0 > now - ts)
		{
			zbx_dns_errf(log_fd, "Unix timestamp of Name Server \"%s\" is in the future (current: %lu)",
					random_ns, now);
			upd43 = ZBX_EC_RDDS43_ERRTS;
		}
	}

	if (upd43 == ZBX_NO_VALUE)
	{
		/* successful UPD */
		upd43 = now - ts;
	}

	/* start RDDS80 test, resolve hosts to ips */
	if (SUCCEED != zbx_resolve_hosts(res, &hosts80, &ips80, ipv4_enabled, ipv6_enabled, log_fd, err, sizeof(err)))
	{
		rtt80 = ZBX_EC_RDDS_ERRRES;
		zbx_dns_errf(log_fd, "RDDS80: %s", err);
		goto out;
	}

	/* choose random IP */
	i = zbx_random(ips80.values_num);
	ip80 = ips80.values[i];

	if (SUCCEED != zbx_tcp_exchange("GET / HTTP/1.1", ip80, 80, ZBX_DNSTEST_TCP_TIMEOUT, NULL, &rtt80, log_fd, err,
			sizeof(err)))
	{
		rtt80 = ZBX_EC_RDDS80_NOREPLY;
		zbx_dns_errf(log_fd, "no answer from RDDS80 server %s: %s", ip80, err);
		goto out;
	}
out:
	if (SYSINFO_RET_OK == ret)
	{
		int	ok_tests = 2;	/* RDDS43 and RDDS80 */

		zbx_set_rddstest_values(ip43, rtt43, upd43, ip80, rtt80, item->nextcheck, strlen(keyname), items,
				items_num);

		if ((0 > rtt43 && SUCCEED == zbx_is_service_error(rtt43)) || SUCCEED != zbx_check_rtt_limit(rtt43,
				rtt_limit))
		{
			ok_tests -= 2;
		}
		else if ((0 > rtt80 && SUCCEED == zbx_is_service_error(rtt80)) || SUCCEED != zbx_check_rtt_limit(rtt80,
				rtt_limit))
		{
			ok_tests -= 1;
		}

		zbx_add_value_uint(item, item->nextcheck, ok_tests);
	}

	zbx_free(answer);

	if (0 != items_num)
	{
		for (i = 0; i < items_num; i++)
			zbx_free(items[i].key);

		zbx_free(items);
	}

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	if (NULL != log_fd)
		fclose(log_fd);

	zbx_free(testprefix);
	zbx_free(res_ip);
	zbx_free(value_str);

	zbx_vector_str_destroy(&nss);
	zbx_vector_str_destroy(&ips80);
	zbx_vector_str_destroy(&ips43);
	zbx_vector_str_destroy(&hosts80);
	zbx_vector_str_destroy(&hosts43);

	zabbix_log(LOG_LEVEL_WARNING, "End of %s():%s", __function_name, SYSINFO_RET_OK == ret ? "SUCCEED" : "FAIL");

	return ret;
}

static int	zbx_check_dns_connection(ldns_resolver **res, const char *ip, ldns_rdf *query_rdf, int reply_ms,
		int *dns_res, FILE *f, int ipv4_enabled, int ipv6_enabled, char *err, size_t err_size)
{
	const char	*__function_name = "zbx_check_dns_connection";
	ldns_pkt	*pkt = NULL;
	ldns_rr_list	*rrset = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_WARNING, "In %s() ip:'%s'", __function_name, ip);

	if (NULL == *res)
	{
		if (SUCCEED != zbx_create_resolver(res, "root server", ip, ZBX_DNSTEST_UDP, ipv4_enabled, ipv6_enabled,
				err, err_size))
		{
			goto out;
		}
	}
	else if (SUCCEED != zbx_change_resolver(*res, "root server", ip, ipv4_enabled, ipv6_enabled, err, sizeof(err)))
		goto out;

	/* not internal error */
	ret = SUCCEED;
	*dns_res = FAIL;

	/* set edns DO flag */
	ldns_resolver_set_dnssec(*res, true);

	if (NULL == (pkt = ldns_resolver_query(*res, query_rdf, LDNS_RR_TYPE_SOA, LDNS_RR_CLASS_IN, 0)))
	{
		zbx_dns_errf(f, "cannot connect to root server %s", ip);
		goto out;
	}

	ldns_pkt_print(f, pkt);

	if (NULL == (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_SOA, LDNS_SECTION_ANSWER)))
	{
		zbx_dns_warnf(f, "no SOA records from %s", ip);
		goto out;
	}

	ldns_rr_list_deep_free(rrset);

	if (NULL == (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_RRSIG, LDNS_SECTION_ANSWER)))
	{
		zbx_dns_warnf(f, "no RRSIG records from %s", ip);
		goto out;
	}

	if (ldns_pkt_querytime(pkt) > reply_ms)
	{
		zbx_dns_warnf(f, "%s query RTT %d over limit (%d)", ip, ldns_pkt_querytime(pkt), reply_ms);
		goto out;
	}

	/* target succeeded */
	*dns_res = SUCCEED;
out:
	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	zabbix_log(LOG_LEVEL_WARNING, "End of %s():%s dns_res:%d", __function_name, zbx_result_string(ret), *dns_res);

	return ret;
}

int	check_dnstest_probe_status(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result)
{
	char			*value_str = NULL, err[ZBX_ERR_BUF_SIZE], ips4_init = 0, ips6_init = 0;
	const char		*ip;
	zbx_vector_str_t	ips4, ips6;
	ldns_resolver		*res = NULL;
	ldns_rdf		*query_rdf = NULL;
	size_t			i;
	FILE			*log_fd = NULL;
	int			ipv4_enabled = 0, ipv6_enabled = 0, min_servers, reply_ms, online_delay, dns_res,
				ok_servers, ret, status = ZBX_EC_PROBE_UNSUPPORTED;

	if (SUCCEED != zbx_conf_ip_support(&item->host.hostid, &ipv4_enabled, &ipv6_enabled, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* request to root servers to check the connection */
	if (NULL == (query_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, ".")))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cannot create request to root servers"));
		goto out;
	}

	/* open probestatus log file */
	if (NULL == (log_fd = open_item_log(NULL, ZBX_PROBESTATUS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_PROBE_ONLINE_DELAY, &online_delay, 60,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	zabbix_log(LOG_LEVEL_WARNING, "PROBESTATUS IPv4:%s IPv6:%s", 0 == ipv4_enabled ? "DISABLED" : "ENABLED",
			0 == ipv6_enabled ? "DISABLED" : "ENABLED");

	if (0 != ipv4_enabled)
	{
		zbx_vector_str_create(&ips4);
		ips4_init = 1;

		if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_IP4_MIN_SERVERS, &min_servers, 1,
				err, sizeof(err)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_IP4_REPLY_MS, &reply_ms, 1, err, sizeof(err)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		if (NULL == (value_str = get_param_dyn(params, 2)) || '\0' == *value_str)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "second key parameter missing"));
			goto out;
		}

		if (SUCCEED != zbx_validate_host_list(value_str, ','))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "invalid character in IPv4 host list"));
			goto out;
		}

		zbx_get_strings_from_list(&ips4, value_str, ',', err, sizeof(err));

		ok_servers = 0;

		for (i = 0; i < ips4.values_num; i++)
		{
			ip = ips4.values[i];

			if (SUCCEED != zbx_check_dns_connection(&res, ip, query_rdf, reply_ms, &dns_res, log_fd,
					ipv4_enabled, ipv6_enabled, err, sizeof(err)))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, err));
				goto out;
			}

			if (SUCCEED == dns_res)
				ok_servers++;

			if (ok_servers == min_servers)
			{
				zbx_dns_infof(log_fd, "%d successful results, IPv4 considered working", ok_servers);
				break;
			}
		}

		if (ok_servers != min_servers)
		{
			/* IP protocol check failed */
			zbx_dns_warnf(log_fd, "status OFFLINE. IPv4 protocol check failed, %d out of %d root servers"
					" replied successfully, minimum required %d",
					ok_servers, ips4.values_num, min_servers);
			status = ZBX_EC_PROBE_OFFLINE;
			goto out;
		}
	}

	if (0 != ipv6_enabled)
	{
		zbx_vector_str_create(&ips6);
		ips6_init = 1;

		if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_IP6_MIN_SERVERS, &min_servers, 1,
				err, sizeof(err)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_IP6_REPLY_MS, &reply_ms, 1, err, sizeof(err)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}

		zbx_free(value_str);

		if (NULL == (value_str = get_param_dyn(params, 3)) || '\0' == *value_str)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "third key parameter missing"));
			goto out;
		}

		if (SUCCEED != zbx_validate_host_list(value_str, ','))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "invalid character in IPv6 host list"));
			goto out;
		}

		zbx_get_strings_from_list(&ips6, value_str, ',', err, sizeof(err));

		ok_servers = 0;

		for (i = 0; i < ips6.values_num; i++)
		{
			ip = ips6.values[i];

			if (SUCCEED != zbx_check_dns_connection(&res, ip, query_rdf, reply_ms, &dns_res, log_fd,
					ipv4_enabled, ipv6_enabled, err, sizeof(err)))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, err));
				goto out;
			}

			if (SUCCEED == dns_res)
				ok_servers++;

			if (ok_servers == min_servers)
			{
				zbx_dns_infof(log_fd, "%d successful results, IPv6 considered working", ok_servers);
				break;
			}
		}

		if (ok_servers != min_servers)
		{
			/* IP protocol check failed */
			zbx_dns_warnf(log_fd, "status OFFLINE. IPv6 protocol check failed, %d out of %d root servers"
					" replied successfully, minimum required %d",
					ok_servers, ips6.values_num, min_servers);
			status = ZBX_EC_PROBE_OFFLINE;
			goto out;
		}
	}

	status = ZBX_EC_PROBE_ONLINE;
out:
	/* If tests are successful and we are ONLINE currently we continue being ONLINE. If     */
	/* tests are successful and we are OFFLINE we can change to ONLINE only if successful   */
	/* test results were received for PROBE_ONLINE_DELAY seconds. Otherwise we are OFFLINE. */
	if (ZBX_EC_PROBE_UNSUPPORTED != status)
	{
		ret = SYSINFO_RET_OK;

		if (ZBX_EC_PROBE_OFFLINE == status)
		{
			DCset_probe_online_since(0);
		}
		else if (ZBX_EC_PROBE_ONLINE == status && ZBX_EC_PROBE_OFFLINE == DCget_probe_last_status())
		{
			time_t	probe_online_since, now;

			probe_online_since = DCget_probe_online_since();
			now = time(NULL);

			if (0 == DCget_probe_online_since())
			{
				DCset_probe_online_since(now);
			}
			else
			{
				if (now - probe_online_since < online_delay)
				{
					zbx_dns_warnf(log_fd, "probe status successful for % seconds, still OFFLINE",
							now - probe_online_since);
					status = ZBX_EC_PROBE_OFFLINE;
				}
				else
				{
					zbx_dns_warnf(log_fd, "probe status successful for % seconds, changing to ONLINE",
							now - probe_online_since);
				}
			}
		}

		zbx_add_value_uint(item, item->nextcheck, status);
	}
	else
	{
		ret = SYSINFO_RET_FAIL;
		DCset_probe_online_since(0);
	}

	DCset_probe_last_status(status);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	if (NULL != log_fd)
		fclose(log_fd);

	zbx_free(value_str);

	if (0 != ips6_init)
		zbx_vector_str_destroy(&ips6);

	if (0 != ips4_init)
		zbx_vector_str_destroy(&ips4);

	if (NULL != query_rdf)
		ldns_rdf_deep_free(query_rdf);

	return ret;
}
