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

#include <ldns/ldns.h>

#include "sysinfo.h"
#include "checks_simple_rsm.h"
#include "zbxserver.h"
#include "comms.h"
#include "base64.h"
#include "md5.h"
#include "rsm.h"

#define ZBX_HOST_BUF_SIZE	128
#define ZBX_IP_BUF_SIZE		64
#define ZBX_ERR_BUF_SIZE	8192
#define ZBX_LOGNAME_BUF_SIZE	128
#define ZBX_SEND_BUF_SIZE	128
#define ZBX_RDDS_PREVIEW_SIZE	100

#define ZBX_HTTP_RESPONSE_OK	200

#define XML_PATH_SERVER_ID	0
#define XML_PATH_RESULT_CODE	1

#define COMMAND_BUF_SIZE	1024
#define XML_VALUE_BUF_SIZE	512

#define EPP_SUCCESS_CODE_GENERAL	"1000"
#define EPP_SUCCESS_CODE_LOGOUT		"1500"

#define COMMAND_LOGIN	"login"
#define COMMAND_INFO	"info"
#define COMMAND_UPDATE	"update"
#define COMMAND_LOGOUT	"logout"

extern const char	*CONFIG_LOG_FILE;
extern const char	epp_passphrase[128];

typedef struct
{
	char	*name;
	char	result;
	char	**ips;
	size_t	ips_num;
}
zbx_ns_t;

#define zbx_rsm_errf(log_fd, fmt, ...)	zbx_rsm_logf(log_fd, "Error", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#define zbx_rsm_warnf(log_fd, fmt, ...)	zbx_rsm_logf(log_fd, "Warning", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#define zbx_rsm_infof(log_fd, fmt, ...)	zbx_rsm_logf(log_fd, "Info", ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
static void	zbx_rsm_logf(FILE *log_fd, const char *prefix, const char *fmt, ...)
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
	vfprintf(log_fd, fmt, args);
	va_end(args);
}

#define zbx_rsm_err(log_fd, text)	zbx_rsm_log(log_fd, "Error", text)
#define zbx_rsm_info(log_fd, text)	zbx_rsm_log(log_fd, "Info", text)
static void	zbx_rsm_log(FILE *log_fd, const char *prefix, const char *text)
{
	struct timeval	current_time;
	struct tm	*tm;
	long		ms;

	gettimeofday(&current_time, NULL);
	tm = localtime(&current_time.tv_sec);
	ms = current_time.tv_usec / 1000;

	fprintf(log_fd, "[%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld] %s: %s\n",
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

static int	zbx_validate_ip(const char *ip, char ipv4_enabled, char ipv6_enabled, ldns_rdf **ip_rdf_out,
		char *is_ipv4)
{
	ldns_rdf	*ip_rdf = NULL;
	int		ret = FAIL;

	/* try IPv4 */
	if (0 == ipv4_enabled || NULL == (ip_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_A, ip)))
	{
		/* try IPv6 */
		if (0 != ipv6_enabled && NULL != (ip_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_AAAA, ip)))
		{
			if (NULL != is_ipv4)
				*is_ipv4 = 0;
		}
	}
	else
	{
		if (NULL != is_ipv4)
			*is_ipv4 = 1;
	}

	if (NULL == ip_rdf)
		goto out;

	if (NULL != ip_rdf_out)
		*ip_rdf_out = ldns_rdf_clone(ip_rdf);

	ret = SUCCEED;
out:
	if (NULL != ip_rdf)
		ldns_rdf_deep_free(ip_rdf);

	return ret;
}

static int	zbx_set_resolver_ns(ldns_resolver *res, const char *name, const char *ip, char ipv4_enabled,
		char ipv6_enabled, FILE *log_fd, char *err, size_t err_size)
{
	ldns_rdf	*ip_rdf = NULL;
	ldns_status	status;
	int		ret = FAIL;

	if (SUCCEED != zbx_validate_ip(ip, ipv4_enabled, ipv6_enabled, &ip_rdf, NULL))
	{
		zbx_snprintf(err, err_size, "invalid or unsupported IP of \"%s\": \"%s\"", name, ip);
		goto out;
	}

	/* push nameserver to it */
	if (LDNS_STATUS_OK != (status = ldns_resolver_push_nameserver(res, ip_rdf)))
	{
		zbx_snprintf(err, err_size, "cannot set %s (%s) as resolver. %s.", name, ip,
				ldns_get_errorstr_by_id(status));
		goto out;
	}

	zbx_rsm_infof(log_fd, "successfully using %s (%s)", name, ip);

	ret = SUCCEED;
out:
	if (NULL != ip_rdf)
		ldns_rdf_deep_free(ip_rdf);

	return ret;
}

static int	zbx_create_resolver(ldns_resolver **res, const char *name, const char *ip, char proto,
		char ipv4_enabled, char ipv6_enabled, FILE *log_fd, char *err, size_t err_size)
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
	if (SUCCEED != zbx_set_resolver_ns(*res, name, ip, ipv4_enabled, ipv6_enabled, log_fd, err, err_size))
		goto out;

	if (ZBX_RSM_UDP == proto)
	{
		tv.tv_sec = ZBX_RSM_UDP_TIMEOUT;
		tv.tv_usec = 0;
		retries = ZBX_RSM_UDP_RETRY;
	}
	else
	{
		tv.tv_sec = ZBX_RSM_TCP_TIMEOUT;
		tv.tv_usec = 0;
		retries = ZBX_RSM_TCP_RETRY;
	}

	/* set timeout of one try */
	ldns_resolver_set_timeout(*res, tv);

	/* set number of tries */
	ldns_resolver_set_retry(*res, retries);

	/* set DNSSEC */
	ldns_resolver_set_dnssec(*res, true);

	/* unset the CD flag */
	ldns_resolver_set_dnssec_cd(*res, false);

	/* use TCP or UDP */
	ldns_resolver_set_usevc(*res, ZBX_RSM_UDP == proto ? false : true);

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
		char ipv6_enabled, FILE *log_fd, char *err, size_t err_size)
{
	ldns_rdf	*pop;

	/* remove current list of nameservers from resolver */
	while (NULL != (pop = ldns_resolver_pop_nameserver(res)))
		ldns_rdf_deep_free(pop);

	return zbx_set_resolver_ns(res, name, ip, ipv4_enabled, ipv6_enabled, log_fd, err, err_size);
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

static int	zbx_get_last_label(const char *name, char **last_label, char *err, size_t err_size)
{
	const char	*last_label_start;

	if (NULL == name || '\0' == *name)
	{
		zbx_strlcpy(err, "the test name (PREFIX.TLD) is empty", err_size);
		return FAIL;
	}

	last_label_start = name + strlen(name) - 1;

	while (name != last_label_start && '.' != *last_label_start)
		last_label_start--;

	if (name == last_label_start)
	{
		zbx_snprintf(err, err_size, "cannot get last label from \"%s\"", name);
		return FAIL;
	}

	/* skip the dot */
	last_label_start--;

	if (name == last_label_start)
	{
		zbx_snprintf(err, err_size, "cannot get last label from \"%s\"", name);
		return FAIL;
	}

	while (name != last_label_start && '.' != *last_label_start)
		last_label_start--;

	if (name != last_label_start)
		last_label_start++;

	*last_label = zbx_strdup(*last_label, last_label_start);

	return SUCCEED;
}

#define ZBX_COVERED_TYPE_NSEC	0
#define ZBX_COVERED_TYPE_DS	1
static int	zbx_get_rrset_to_verify(const ldns_pkt *pkt, const ldns_rdf *owner, ldns_pkt_section section,
		int covered, ldns_rr_list **result)
{
	ldns_rr_list	*rrset = NULL, *rrset2 = NULL;
	int		ret = FAIL;

	switch (covered)
	{
		case ZBX_COVERED_TYPE_NSEC:
			rrset = ldns_pkt_rr_list_by_name_and_type(pkt, owner, LDNS_RR_TYPE_NSEC, section);
			rrset2 = ldns_pkt_rr_list_by_name_and_type(pkt, owner, LDNS_RR_TYPE_NSEC3, section);

			if (NULL == (*result = ldns_rr_list_new()))
				goto out;

			if (NULL != rrset && 0 != ldns_rr_list_rr_count(rrset))
			{
				int		rv;
				ldns_rr_list	*cloned_rrset;

				if (NULL == (cloned_rrset = ldns_rr_list_clone(rrset)))
					goto out;

				rv = ldns_rr_list_push_rr_list(*result, cloned_rrset);
				ldns_rr_list_free(cloned_rrset);

				if (false == rv)
					goto out;
			}

			if (NULL != rrset2 && 0 != ldns_rr_list_rr_count(rrset2))
			{
				int		rv;
				ldns_rr_list	*cloned_rrset;

				if (NULL == (cloned_rrset = ldns_rr_list_clone(rrset2)))
					goto out;

				rv = ldns_rr_list_push_rr_list(*result, cloned_rrset);
				ldns_rr_list_free(cloned_rrset);

				if (false == rv)
					goto out;
			}

			break;
		case ZBX_COVERED_TYPE_DS:
			*result = ldns_pkt_rr_list_by_name_and_type(pkt, owner, LDNS_RR_TYPE_DS, section);
			break;
		default:
			goto out;
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret || 0 == ldns_rr_list_rr_count(*result))
	{
		ldns_rr_list_deep_free(*result);
		*result = NULL;
	}

	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != rrset2)
		ldns_rr_list_deep_free(rrset2);

	return ret;
}

static int	zbx_get_covered_rrsigs(const ldns_pkt *pkt, const ldns_rdf *owner, ldns_pkt_section s, int covered, ldns_rr_list **result)
{
	ldns_rr_list	*rrsigs;
	ldns_rr		*rr;
	size_t		i, count;
	int		ret = FAIL;

	if (NULL != owner)
		rrsigs = ldns_pkt_rr_list_by_name_and_type(pkt, owner, LDNS_RR_TYPE_RRSIG, s);
	else
		rrsigs = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_RRSIG, s);

	*result = ldns_rr_list_new();

	if (NULL != rrsigs)
	{
		ldns_rdf	*covered_rdf;
		ldns_rr_type	covered_type;

		count = ldns_rr_list_rr_count(rrsigs);
		for (i = 0; i < count; i++)
		{
			if (NULL == (rr = ldns_rr_list_rr(rrsigs, i)))
				goto out;

			if (NULL == (covered_rdf = ldns_rr_rrsig_typecovered(rr)))
				goto out;

			covered_type = ldns_rdf2rr_type(covered_rdf);

			switch (covered)
			{
				case ZBX_COVERED_TYPE_NSEC:
					if (LDNS_RR_TYPE_NSEC == covered_type || LDNS_RR_TYPE_NSEC3 == covered_type)
					{
						if (0 == ldns_rr_list_push_rr(*result, ldns_rr_clone(rr)))
							goto out;
					}
					break;
				case ZBX_COVERED_TYPE_DS:
					if (LDNS_RR_TYPE_DS == covered_type)
					{
						if (0 == ldns_rr_list_push_rr(*result, ldns_rr_clone(rr)))
							goto out;
					}
					break;
				default:
					goto out;
			}
		}
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret || 0 == ldns_rr_list_rr_count(*result))
	{
		ldns_rr_list_deep_free(*result);
		*result = NULL;
	}

	if (NULL != rrsigs)
		ldns_rr_list_deep_free(rrsigs);

	return ret;
}

static int	zbx_ldns_rdf_compare(const void *d1, const void *d2)
{
	return ldns_rdf_compare(*(const ldns_rdf **)d1, *(const ldns_rdf **)d2);
}

static void	zbx_get_owners(const ldns_rr_list *rr_list, zbx_vector_ptr_t *owners)
{
	size_t		i, count;
	ldns_rdf	*owner;

	count = ldns_rr_list_rr_count(rr_list);
	for (i = 0; i < count; i++)
	{
		owner = ldns_rr_owner(ldns_rr_list_rr(rr_list, i));

		zbx_vector_ptr_append(owners, ldns_rdf_clone(owner));
	}

	zbx_vector_ptr_sort(owners, zbx_ldns_rdf_compare);
	zbx_vector_ptr_uniq(owners, zbx_ldns_rdf_compare);
}

static void	zbx_destroy_owners(zbx_vector_ptr_t *owners)
{
	size_t	i;

	for (i = 0; i < owners->values_num; i++)
		ldns_rdf_deep_free((ldns_rdf *)owners->values[i]);

	zbx_vector_ptr_destroy(owners);
}

static const char	*zbx_covered_to_str(int covered)
{
	switch (covered)
	{
		case ZBX_COVERED_TYPE_DS:
			return "DS";
		case ZBX_COVERED_TYPE_NSEC:
			return "NSEC*";
	}

	return "*UNKNOWN*";
}

static int	zbx_verify_rrsigs(const ldns_pkt *pkt, ldns_pkt_section section, int covered, const ldns_rr_list *keys,
		const char *ns, const char *ip, int *rtt, char *err, size_t err_size)
{
	zbx_vector_ptr_t	owners;
	ldns_rr_list		*rrset = NULL, *rrsigs = NULL;
	ldns_rdf		*owner_rdf;
	size_t			i;
	char			*owner_str, owner_buf[256];
	int			ret = FAIL;

	zbx_vector_ptr_create(&owners);

	if (SUCCEED != zbx_get_covered_rrsigs(pkt, NULL, section, covered, &rrsigs))
	{
		zbx_snprintf(err, err_size, "internal error: cannot generate RR list");
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	if (NULL == rrsigs)
	{
		zbx_snprintf(err, err_size, "no %s RRSIG records found at nameserver \"%s\" (%s)",
				zbx_covered_to_str(covered), ns, ip);
		*rtt = ZBX_EC_DNS_NS_EDNSSEC;
		goto out;
	}

	zbx_get_owners(rrsigs, &owners);

	for (i = 0; i < owners.values_num; i++)
	{
		owner_rdf = (ldns_rdf *)owners.values[i];

		if (NULL == (owner_str = ldns_rdf2str(owner_rdf)))
		{
			*rtt = ZBX_EC_INTERNAL;
			zbx_strlcpy(err, "internal error: cannot convert owner name to a string", err_size);
			goto out;
		}

		zbx_strlcpy(owner_buf, owner_str, sizeof(owner_buf));
		zbx_free(owner_str);

		if (NULL != rrset)
		{
			ldns_rr_list_deep_free(rrset);
			rrset = NULL;
		}

		/* collect RRs to verify */
		if (SUCCEED != zbx_get_rrset_to_verify(pkt, owner_rdf, section, covered, &rrset))
		{
			zbx_snprintf(err, err_size, "internal error: cannot generate RR list");
			*rtt = ZBX_EC_INTERNAL;
			goto out;
		}

		if (NULL == rrset)
		{
			zbx_snprintf(err, err_size, "no %s records of \"%s\" found at nameserver \"%s\" (%s)",
					zbx_covered_to_str(covered), owner_buf, ns, ip);
			*rtt = ZBX_EC_DNS_NS_EDNSSEC;
			goto out;
		}

		if (NULL != rrsigs)
		{
			ldns_rr_list_deep_free(rrsigs);
			rrsigs = NULL;
		}

		/* now get RRSIGs of that owner, we know at least one exists */
		if (SUCCEED != zbx_get_covered_rrsigs(pkt, owner_rdf, section, covered, &rrsigs))
		{
			zbx_strlcpy(err, "internal error: cannot generate RR list", err_size);
			*rtt = ZBX_EC_INTERNAL;
			goto out;
		}

		/* verify RRSIGs */
		if (LDNS_STATUS_OK != ldns_verify(rrset, rrsigs, keys, NULL))
		{
			zbx_snprintf(err, err_size, "DNSKEY that verifies %s RRSIGs of \"%s\" not found"
					" (used %u %s, %u RRSIG and %u DNSKEY RRs)",
					zbx_covered_to_str(covered),
					owner_buf,
					ldns_rr_list_rr_count(rrset),
					zbx_covered_to_str(covered),
					ldns_rr_list_rr_count(rrsigs),
					ldns_rr_list_rr_count(keys));
			*rtt = ZBX_EC_DNS_NS_EDNSSEC;
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_destroy_owners(&owners);

	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != rrsigs)
		ldns_rr_list_deep_free(rrsigs);

	return ret;
}

static int	zbx_get_ns_ip_values(ldns_resolver *res, const char *ns, const char *ip, const ldns_rr_list *keys,
		const char *testprefix, const char *domain, FILE *log_fd, int *rtt, int *upd, char ipv4_enabled,
		char ipv6_enabled, char epp_enabled, char *err, size_t err_size)
{
	char		testname[ZBX_HOST_BUF_SIZE], *host, *last_label = NULL;
	ldns_rdf	*testname_rdf = NULL, *last_label_rdf = NULL;
	ldns_pkt	*pkt = NULL;
	ldns_rr_list	*nsset = NULL;
	ldns_rr		*rr = NULL;
	time_t		now, ts;
	ldns_pkt_rcode	rcode;
	int		ret = FAIL;

	/* change the resolver */
	if (SUCCEED != zbx_change_resolver(res, ns, ip, ipv4_enabled, ipv6_enabled, log_fd, err, err_size))
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
		*rtt = ZBX_EC_DNS_NS_EREPLY;
		goto out;
	}

	ldns_pkt_print(log_fd, pkt);

	if (0 != epp_enabled)
	{
		/* start referral validation */

		/* no AA flag */
		if (0 != ldns_pkt_aa(pkt))
		{
			zbx_snprintf(err, err_size, "AA flag is set in the answer of \"%s\" from nameserver \"%s\" (%s)",
					testname, ns, ip);
			*rtt = ZBX_EC_DNS_NS_EREPLY;
			goto out;
		}

		/* the AUTHORITY section should contain at least one NS RR for the last label in  */
		/* PREFIX, e.g. "icann-test" when querying for "blahblah.icann-test.example." */
		if (SUCCEED != zbx_get_last_label(testname, &last_label, err, err_size))
		{
			*rtt = ZBX_EC_DNS_NS_EREPLY;
			goto out;
		}

		if (NULL == (last_label_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, last_label)))
		{
			zbx_snprintf(err, err_size, "invalid last label \"%s\" generated from testname \"%s\"",
					last_label, testname);
			*rtt = ZBX_EC_DNS_NS_EREPLY;
			goto out;
		}

		if (NULL == (nsset = ldns_pkt_rr_list_by_name_and_type(pkt, last_label_rdf, LDNS_RR_TYPE_NS,
				LDNS_SECTION_AUTHORITY)))
		{
			zbx_snprintf(err, err_size, "no NS records of \"%s\" at nameserver \"%s\" (%s)", last_label,
					ns, ip);
			*rtt = ZBX_EC_DNS_NS_EREPLY;
			goto out;
		}

		/* end referral validation */

		if (NULL != upd)
		{
			/* extract UNIX timestamp of random NS record */

			rr = ldns_rr_list_rr(nsset, zbx_random(ldns_rr_list_rr_count(nsset)));
			host = ldns_rdf2str(ldns_rr_rdf(rr, 0));

			zbx_rsm_infof(log_fd, "randomly chose ns %s", host);
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
				*upd = ZBX_EC_DNS_NS_ETS;
				goto out;
			}

			zbx_free(host);

			/* successful update time */
			*upd = now - ts;
		}

		if (NULL != keys)	/* DNSSEC enabled */
		{
			if (SUCCEED != zbx_verify_rrsigs(pkt, LDNS_SECTION_AUTHORITY, ZBX_COVERED_TYPE_DS, keys,
					ns, ip, rtt, err, err_size))
			{
				goto out;
			}
		}
	}
	else if (NULL != keys)	/* EPP disabled, DNSSEC enabled */
	{
		if (SUCCEED != zbx_verify_rrsigs(pkt, LDNS_SECTION_AUTHORITY, ZBX_COVERED_TYPE_NSEC, keys, ns, ip, rtt,
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
		zbx_rsm_infof(log_fd, "RSM DNS \"%s\" (%s) RTT:%d UPD:%d", ns, ip, *rtt, *upd);
	else
		zbx_rsm_infof(log_fd, "RSM DNS \"%s\" (%s) RTT:%d", ns, ip, *rtt);

	if (NULL != nsset)
		ldns_rr_list_deep_free(nsset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	if (NULL != testname_rdf)
		ldns_rdf_deep_free(testname_rdf);

	if (NULL != last_label_rdf)
		ldns_rdf_deep_free(last_label_rdf);

	if (NULL != last_label)
		zbx_free(last_label);

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
	zbx_timespec_t	timespec;

	zbx_set_value_ts(&timespec, ts);

	dc_add_history(item->itemid, item->value_type, item->flags, result, &timespec, ITEM_STATUS_ACTIVE,
			NULL, 0, NULL, 0, 0, 0, 0);
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

static void	zbx_set_dns_values(const char *item_ns, const char *item_ip, int rtt, int upd, int value_ts,
		size_t keypart_size, const DC_ITEM *items, size_t items_num)
{
	size_t		i;
	const char	*p;
	const DC_ITEM	*item;
	char		rtt_set = 0, upd_set = 0, ns[ZBX_HOST_BUF_SIZE], ip[ZBX_IP_BUF_SIZE];

	if (ZBX_NO_VALUE == upd)
		upd_set = 1;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		p = item->key + keypart_size;	/* skip "rsm.dns.<tcp|udp>." part */

		if (0 == rtt_set && 0 == strncmp(p, "rtt[", 4))
		{
			get_param(item->params, 2, ns, sizeof(ns));
			get_param(item->params, 3, ip, sizeof(ip));

			if (0 == strcmp(ns, item_ns) && 0 == strcmp(ip, item_ip))
			{
				zbx_add_value_dbl(item, value_ts, rtt);

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

				upd_set = 1;
			}
		}

		if (0 != rtt_set && 0 != upd_set)
			return;
	}
}

static int	zbx_get_dnskeys(const ldns_resolver *res, const char *domain, const char *resolver,
		ldns_rr_list **keys, FILE *pkt_file, int *ec, char *err, size_t err_size)
{
	ldns_pkt	*pkt = NULL;
	ldns_rdf	*domain_rdf = NULL;
	int		ret = FAIL;

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
	if (NULL == (*keys = ldns_pkt_rr_list_by_name_and_type(pkt, domain_rdf, LDNS_RR_TYPE_DNSKEY,
			LDNS_SECTION_ANSWER)))
	{
		zbx_snprintf(err, err_size, "no DNSKEY records of domain \"%s\" from resolver \"%s\"", domain,
				resolver);
		*ec = ZBX_EC_DNS_NS_EDNSSEC;
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != domain_rdf)
		ldns_rdf_deep_free(domain_rdf);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	return ret;
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

static void	free_items(DC_ITEM *items, size_t items_num)
{
	if (0 != items_num)
	{
		DC_ITEM	*item;
		size_t	i;

		for (i = 0; i < items_num; i++)
		{
			item = &items[i];

			zbx_free(item->key);
			zbx_free(item->params);
		}

		zbx_free(items);
	}
}

static size_t	zbx_get_dns_items(const char *keyname, DC_ITEM *item, const char *domain, DC_ITEM **out_items,
		FILE *log_fd)
{
	char		*keypart, host[ZBX_HOST_BUF_SIZE];
	const char	*p;
	DC_ITEM		*in_items = NULL, *in_item;
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
		in_item = &in_items[i];

		ZBX_STRDUP(in_item->key, in_item->key_orig);
		in_item->params = NULL;

		if (SUCCEED != substitute_key_macros(&in_item->key, NULL, item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0))
		{
			/* problem with key macros, skip it */
			zbx_rsm_warnf(log_fd, "%s: cannot substitute key macros", in_item->key_orig);
			continue;
		}

		if (SUCCEED != zbx_parse_dns_item(in_item, host, sizeof(host)))
		{
			/* unexpected item key syntax, skip it */
			zbx_rsm_warnf(log_fd, "%s: unexpected key syntax", in_item->key);
			continue;
		}

		if (0 != strcmp(host, domain))
		{
			/* first parameter does not match expected domain name, skip it */
			zbx_rsm_warnf(log_fd, "%s: first parameter does not match host %s", in_item->key, domain);
			continue;
		}

		p = in_item->key + keypart_size;
		if (0 != strncmp(p, "rtt[", 4) && 0 != strncmp(p, "upd[", 4))
			continue;

		if (0 == out_items_num)
		{
			*out_items = zbx_malloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}
		else if (out_items_num == out_items_alloc)
		{
			out_items_alloc += 8;
			*out_items = zbx_realloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}

		memcpy(&(*out_items)[out_items_num], in_item, sizeof(DC_ITEM));
		(*out_items)[out_items_num].key = in_item->key;
		(*out_items)[out_items_num].params = in_item->params;
		in_item->key = NULL;
		in_item->params = NULL;

		out_items_num++;
	}

	free_items(in_items, in_items_num);

	return out_items_num;
}

static size_t	zbx_get_nameservers(const DC_ITEM *items, size_t items_num, zbx_ns_t **nss, char ipv4_enabled,
		char ipv6_enabled, FILE *log_fd)
{
	char		ns[ZBX_HOST_BUF_SIZE], ip[ZBX_IP_BUF_SIZE], ns_found, ip_found;
	size_t		i, j, j2, nss_num = 0, nss_alloc = 8;
	zbx_ns_t	*ns_entry;
	const DC_ITEM	*item;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		ns_found = ip_found = 0;
		*ns = *ip = '\0';

		if (SUCCEED != get_param(item->params, 2, ns, sizeof(ns)))
		{
			zbx_rsm_errf(log_fd, "%s: cannot get Name Server from item %s (itemid:" ZBX_FS_UI64 ")",
					item->host.host, item->key_orig, item->itemid);
			continue;
		}

		if (SUCCEED != get_param(item->params, 3, ip, sizeof(ip)))
		{
			zbx_rsm_errf(log_fd, "%s: cannot get IP address from item %s (itemid:" ZBX_FS_UI64 ")",
					item->host.host, item->key_orig, item->itemid);
			continue;
		}

		if (0 == nss_num)
		{
			*nss = zbx_malloc(*nss, nss_alloc * sizeof(zbx_ns_t));
		}
		else
		{
			/* check if need to add NS */
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

		/* add NS here */
		if (0 == ns_found)
		{
			ns_entry = &(*nss)[nss_num];

			ns_entry->name = zbx_strdup(NULL, ns);
			ns_entry->result = SUCCEED;	/* by default Name Server is considered working */
			ns_entry->ips_num = 0;

			nss_num++;
		}
		else
			ns_entry = &(*nss)[j];

		if (SUCCEED != zbx_validate_ip(ip, ipv4_enabled, ipv6_enabled, NULL, NULL))
			continue;

		/* add IP here */
		if (0 == ns_entry->ips_num)
			ns_entry->ips = zbx_malloc(NULL, sizeof(char *));
		else
			ns_entry->ips = zbx_realloc(ns_entry->ips, (ns_entry->ips_num + 1) * sizeof(char *));

		ns_entry->ips[ns_entry->ips_num] = zbx_strdup(NULL, ip);

		ns_entry->ips_num++;
	}

	return nss_num;
}

static void	zbx_clean_nss(zbx_ns_t *nss, size_t nss_num)
{
	size_t	i, j;

	for (i = 0; i < nss_num; i++)
	{
		if (0 != nss[i].ips_num)
		{
			for (j = 0; j < nss[i].ips_num; j++)
				zbx_free(nss[i].ips[j]);

			zbx_free(nss[i].ips);
		}

		zbx_free(nss[i].name);
	}
}

static int	is_service_err(int ec)
{
	if (0 > ec && -200 >= ec && ec > ZBX_NO_VALUE)
		return SUCCEED;

	/* not a service error */
	return FAIL;
}

/* The ns is considered non-working only in case it was the one to blame. Resolver */
/* and internal errors do not count. Another case of ns fail is slow response.     */
static int	rtt_result(int rtt, int rtt_limit)
{
	if (SUCCEED == is_service_err(rtt) || rtt > rtt_limit)
		return FAIL;

	return SUCCEED;
}

static int	zbx_conf_str(zbx_uint64_t *hostid, const char *macro, char **value, char *err, size_t err_size)
{
	int	ret = FAIL;

	if (NULL != *value)
	{
		zbx_strlcpy(err, "unfreed memory detected", err_size);
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
		zbx_strlcpy(err, "zabbix log file configuration parameter (LogFile) is not set", err_size);
		return NULL;
	}

	p = CONFIG_LOG_FILE + strlen(CONFIG_LOG_FILE) - 1;

	while (CONFIG_LOG_FILE != p && '/' != *p)
		p--;

	if (CONFIG_LOG_FILE == p)
		file_name = zbx_strdup(NULL, ZBX_RSM_DEFAULT_LOGDIR);
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

	DBget_hostids_by_item(&hostids, "rsm.dns.udp[{$RSM.TLD}]");	/* every monitored host has this item */

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

int	check_rsm_dns(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result, char proto)
{
	char		err[ZBX_ERR_BUF_SIZE], domain[ZBX_HOST_BUF_SIZE], ok_nss_num = 0, *res_ip = NULL,
			*testprefix = NULL;
	ldns_resolver	*res = NULL;
	ldns_rr_list	*keys = NULL;
	FILE		*log_fd;
	DC_ITEM		*items = NULL;
	zbx_ns_t	*nss = NULL;
	size_t		i, j, items_num = 0, nss_num = 0;
	int		ipv4_enabled, ipv6_enabled, dnssec_enabled, epp_enabled, rdds_enabled, res_ec = ZBX_EC_NOERROR,
			rtt, upd = ZBX_NO_VALUE, rtt_limit, ret = SYSINFO_RET_FAIL;

	if (0 != get_param(params, 1, domain, sizeof(domain)) || '\0' == *domain)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "first key parameter missing"));
		return SYSINFO_RET_FAIL;
	}

	/* open log file */
	if (NULL == (log_fd = open_item_log(domain, ZBX_DNS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		return SYSINFO_RET_FAIL;
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

	if (0 == strcmp(testprefix, "*randomtld*"))
	{
		zbx_free(testprefix);

		if (NULL == (testprefix = zbx_get_rr_tld(domain, err, sizeof(err))))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, err));
			goto out;
		}
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_RSM_UDP == proto ? ZBX_MACRO_DNS_UDP_RTT :
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
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, proto, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot create resolver: %s", err));
		goto out;
	}

	/* get rsm items */
	if (0 == (items_num = zbx_get_dns_items(keyname, item, domain, &items, log_fd)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "no trapper %s.* items found", keyname));
		goto out;
	}

	/* from this point item will not become NOTSUPPORTED */
	ret = SYSINFO_RET_OK;

	/* get DNSKEY records */
	if (0 != dnssec_enabled && SUCCEED != zbx_get_dnskeys(res, domain, res_ip, &keys, log_fd, &res_ec,
			err, sizeof(err)))
	{
		zbx_rsm_err(log_fd, err);
	}

	/* get list of Name Servers and IPs, by default it will set every Name Server */
	/* as working so if we have no IPs the result of Name Server will be SUCCEED  */
	nss_num = zbx_get_nameservers(items, items_num, &nss, ipv4_enabled, ipv6_enabled, log_fd);

	for (i = 0; i < nss_num; i++)
	{
		for (j = 0; j < nss[i].ips_num; j++)
		{
			if (ZBX_EC_NOERROR == res_ec)
			{
				if (SUCCEED != zbx_get_ns_ip_values(res, nss[i].name, nss[i].ips[j], keys, testprefix,
						domain, log_fd, &rtt,
						(ZBX_RSM_UDP == proto && 0 != rdds_enabled) ? &upd : NULL,
						ipv4_enabled, ipv6_enabled, epp_enabled, err, sizeof(err)))
				{
					zbx_rsm_err(log_fd, err);
				}
			}
			else
				rtt = res_ec;

			zbx_set_dns_values(nss[i].name, nss[i].ips[j], rtt, upd, item->nextcheck, strlen(keyname) + 1,
					items, items_num);

			/* if a single IP of the Name Server fails, consider the whole Name Server down */
			if (SUCCEED != rtt_result(rtt, rtt_limit))
				nss[i].result = FAIL;
		}
	}

	free_items(items, items_num);

	for (i = 0; i < nss_num; i++)
	{
		if (SUCCEED == nss[i].result)
			ok_nss_num++;
	}

	/* set the value of our simple check item itself */
	zbx_add_value_uint(item, item->nextcheck, ok_nss_num);
out:
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

	if (0 != ISSET_MSG(result))
		zbx_rsm_err(log_fd, result->msg);

	zbx_free(testprefix);
	zbx_free(res_ip);

	return ret;
}

static void	zbx_get_rdds43_nss(zbx_vector_str_t *nss, const char *recv_buf, const char *rdds_ns_string, FILE *log_fd)
{
	const char	*p;
	char		ns_buf[ZBX_HOST_BUF_SIZE];
	size_t		rdds_ns_string_size, ns_buf_len;

	p = recv_buf;
	rdds_ns_string_size = strlen(rdds_ns_string);

	while (NULL != (p = zbx_strcasestr(p, rdds_ns_string)))
	{
		p += rdds_ns_string_size;

		while (0 != isblank(*p))
			p++;

		if (0 == isalnum(*p))
			continue;

		ns_buf_len = 0;
		while ('\0' != *p && 0 == isspace(*p) && ns_buf_len < sizeof(ns_buf))
			ns_buf[ns_buf_len++] = *p++;

		if (sizeof(ns_buf) == ns_buf_len)
		{
			/* internal error, ns buffer not enough */
			zbx_rsm_errf(log_fd, "RSM RDDS internal error, NS buffer too small (%u bytes)"
					" for host in \"%.*s...\"", sizeof(ns_buf), sizeof(ns_buf), p);
			continue;
		}

		ns_buf[ns_buf_len] = '\0';
		zbx_vector_str_append(nss, zbx_strdup(NULL, ns_buf));
	}

	if (0 != nss->values_num)
	{
		zbx_vector_str_sort(nss, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(nss, ZBX_DEFAULT_STR_COMPARE_FUNC);
	}
}

static size_t	zbx_get_rdds_items(const char *keyname, DC_ITEM *item, const char *domain, DC_ITEM **out_items,
		FILE *log_fd)
{
	char		*keypart, host[ZBX_HOST_BUF_SIZE];
	const char	*p;
	DC_ITEM		*in_items = NULL, *in_item;
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
		in_item = &in_items[i];

		ZBX_STRDUP(in_item->key, in_item->key_orig);
		in_item->params = NULL;

		if (SUCCEED != substitute_key_macros(&in_item->key, NULL, item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0))
		{
			/* problem with key macros, skip it */
			zbx_rsm_warnf(log_fd, "%s: cannot substitute key macros", in_item->key_orig);
			continue;
		}

		if (SUCCEED != zbx_parse_rdds_item(in_item, host, sizeof(host)))
		{
			/* unexpected item key syntax, skip it */
			zbx_rsm_warnf(log_fd, "%s: unexpected key syntax", in_item->key);
			continue;
		}

		if (0 != strcmp(host, domain))
		{
			/* first parameter does not match expected domain name, skip it */
			zbx_rsm_warnf(log_fd, "%s: first parameter does not match host %s", in_item->key, domain);
			continue;
		}

		p = in_item->key + keypart_size;
		if (0 != strncmp(p, "43.ip[", 6) && 0 != strncmp(p, "43.rtt[", 7) && 0 != strncmp(p, "43.upd[", 7) &&
				0 != strncmp(p, "80.ip[", 6) && 0 != strncmp(p, "80.rtt[", 7))
		{
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

		memcpy(&(*out_items)[out_items_num], in_item, sizeof(DC_ITEM));
		(*out_items)[out_items_num].key = in_item->key;
		(*out_items)[out_items_num].params = in_item->params;
		in_item->key = NULL;
		in_item->params = NULL;

		out_items_num++;
	}

	free_items(in_items, in_items_num);

	return out_items_num;
}

static int	zbx_rdds43_test(const char *request, const char *ip, short port, int timeout, char **answer,
		int *rtt, char *err, size_t err_size)
{
	zbx_sock_t	s;
	char		*recv_buf, send_buf[ZBX_SEND_BUF_SIZE];
	zbx_timespec_t	start, now;
	int		ret = FAIL;

	memset(&s, 0, sizeof(s));
	zbx_timespec(&start);

	if (SUCCEED != zbx_tcp_connect(&s, NULL, ip, port, timeout))
	{
		zbx_strlcpy(err, "cannot connect to host", err_size);
		goto out;
	}

	zbx_snprintf(send_buf, sizeof(send_buf), "%s\r\n", request);

	if (SUCCEED != zbx_tcp_send_raw(&s, send_buf))
	{
		zbx_snprintf(err, err_size, "cannot send data: %s", zbx_tcp_strerror());
		goto out;
	}

	timeout -= time(NULL) - start.sec;

	if (SUCCEED != SUCCEED_OR_FAIL(zbx_tcp_recv_ext(&s, &recv_buf, ZBX_TCP_READ_UNTIL_CLOSE | ZBX_TCP_EXTERNAL,
			timeout)))
	{
		if (EINTR == errno)
			zbx_strlcpy(err, "timeout occured", err_size);
		else
			zbx_snprintf(err, err_size, "cannot receive data: %s", zbx_tcp_strerror());
		goto out;
	}

	ret = SUCCEED;
	zbx_timespec(&now);
	*rtt = (now.sec - start.sec) * 1000 + (now.ns - start.ns) / 1000000;

	if (NULL != answer)
		*answer = zbx_strdup(*answer, recv_buf);
out:
	zbx_tcp_close(&s);	/* takes care of freeing received buffer */

	return ret;
}

static int	zbx_resolve_host(const ldns_resolver *res, const char *host, zbx_vector_str_t *ips,
		int ipv4_enabled, int ipv6_enabled, FILE *log_fd, char *err, size_t err_size)
{
	ldns_pkt	*pkt = NULL;
	ldns_rdf	*host_rdf = NULL;
	ldns_rr_list	*rrset = NULL;
	size_t		i;
	char		*ip;
	int		ret = FAIL, rr_count;

	if (0 != ipv4_enabled)
	{
		if (NULL == (host_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, host)))
		{
			zbx_snprintf(err, err_size, "invalid host name \"%s\"", host);
			goto out;
		}

		if (NULL == (pkt = ldns_resolver_query(res, host_rdf, LDNS_RR_TYPE_A, LDNS_RR_CLASS_IN, LDNS_RD)))
		{
			zbx_snprintf(err, err_size, "cannot resolve host \"%s\" to IPv4 address", host);
			goto out;
		}

		ldns_pkt_print(log_fd, pkt);

		if (NULL != (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_A, LDNS_SECTION_ANSWER)))
		{
			rr_count = ldns_rr_list_rr_count(rrset);
			for (i = 0; i < rr_count; i++)
			{
				ip = ldns_rdf2str(ldns_rr_a_address(ldns_rr_list_rr(rrset, i)));
				zbx_vector_str_append(ips, ip);
			}
		}
	}

	if (0 != ipv6_enabled)
	{
		if (NULL == host_rdf && NULL == (host_rdf = ldns_rdf_new_frm_str(LDNS_RDF_TYPE_DNAME, host)))
		{
			zbx_snprintf(err, err_size, "invalid host name \"%s\"", host);
			goto out;
		}

		if (NULL != pkt)
			ldns_pkt_free(pkt);

		if (NULL == (pkt = ldns_resolver_query(res, host_rdf, LDNS_RR_TYPE_AAAA, LDNS_RR_CLASS_IN, LDNS_RD)))
		{
			zbx_snprintf(err, err_size, "cannot resolve host \"%s\" to IPv6 address", host);
			goto out;
		}

		ldns_pkt_print(log_fd, pkt);

		if (NULL != rrset)
			ldns_rr_list_deep_free(rrset);

		if (NULL != (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_AAAA, LDNS_SECTION_ANSWER)))
		{
			rr_count = ldns_rr_list_rr_count(rrset);
			for (i = 0; i < rr_count; i++)
			{
				ip = ldns_rdf2str(ldns_rr_a_address(ldns_rr_list_rr(rrset, i)));
				zbx_vector_str_append(ips, ip);
			}
		}
	}

	if (0 == ips->values_num)
	{
		zbx_snprintf(err, err_size, "no IPs of host \"%s\" returned from resolver", host);
		goto out;
	}

	zbx_vector_str_sort(ips, ZBX_DEFAULT_STR_COMPARE_FUNC);
	zbx_vector_str_uniq(ips, ZBX_DEFAULT_STR_COMPARE_FUNC);

	ret = SUCCEED;
out:
	if (NULL != host_rdf)
		ldns_rdf_deep_free(host_rdf);

	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	return ret;
}

static void	zbx_delete_unsupported_ips(zbx_vector_str_t *ips, char ipv4_enabled, char ipv6_enabled)
{
	size_t	i;
	char	is_ipv4;

	for (i = 0; i < ips->values_num; i++)
	{
		if (SUCCEED != zbx_validate_ip(ips->values[i], ipv4_enabled, ipv6_enabled, NULL, &is_ipv4))
		{
			zbx_free(ips->values[i]);
			zbx_vector_str_remove(ips, i--);

			continue;
		}

		if ((0 != is_ipv4 && 0 == ipv4_enabled) || (0 == is_ipv4 && 0 == ipv6_enabled))
		{
			zbx_free(ips->values[i]);
			zbx_vector_str_remove(ips, i--);
		}
	}
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

static void	zbx_get_strings_from_list(zbx_vector_str_t *strings, char *list, char delim)
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

static void	zbx_set_rdds_values(const char *ip43, int rtt43, int upd43, const char *ip80, int rtt80,
		int value_ts, size_t keypart_size, const DC_ITEM *items, size_t items_num)
{
	size_t		i;
	const DC_ITEM	*item;
	const char	*p;

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		p = item->key + keypart_size + 1;	/* skip "rsm.rdds." part */

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

/* discard the curl output (using inline to hide "unused" compiler warning when -Wunused) */
static inline size_t	curl_devnull(char *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

static int	zbx_rdds80_test(const char *host, const char *url, short port, int timeout, int maxredirs, int *rtt80,
		char *err, size_t err_size)
{
#ifdef HAVE_LIBCURL
	int			curl_err, opt;
	CURL			*easyhandle = NULL;
	char			host_buf[ZBX_HOST_BUF_SIZE];
	double			total_time;
	long			response_code;
	struct curl_slist	*slist = NULL;
#endif
	int	ret = FAIL;

#ifdef HAVE_LIBCURL
	if (NULL == (easyhandle = curl_easy_init()))
	{
		*rtt80 = ZBX_EC_INTERNAL;
		zbx_strlcpy(err, "cannot init cURL library", err_size);
		goto out;
	}

	zbx_snprintf(host_buf, sizeof(host_buf), "Host: %s", host);
	if (NULL == (slist = curl_slist_append(slist, host_buf)))
	{
		*rtt80 = ZBX_EC_INTERNAL;
		zbx_strlcpy(err, "cannot generate cURL list of HTTP headers", err_size);
		goto out;
	}

	if (CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_MAXREDIRS, (long)maxredirs)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, slist)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)) ||
			CURLE_OK != (curl_err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, curl_devnull)))
	{
		*rtt80 = ZBX_EC_INTERNAL;
		zbx_snprintf(err, err_size, "cannot set cURL option [%d] (%s)", opt, curl_easy_strerror(curl_err));
		goto out;
	}

	if (CURLE_OK != (curl_err = curl_easy_perform(easyhandle)))
	{
		*rtt80 = ZBX_EC_RDDS80_NOREPLY;
		zbx_strlcpy(err, curl_easy_strerror(curl_err), err_size);
		goto out;
	}

	if (CURLE_OK != (curl_err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &response_code)))
	{
		*rtt80 = ZBX_EC_RDDS80_NOHTTPCODE;
		zbx_snprintf(err, err_size, "cannot get HTTP response code (%s)", curl_easy_strerror(curl_err));
		goto out;
	}

	if (ZBX_HTTP_RESPONSE_OK != (int)response_code)
	{
		*rtt80 = ZBX_EC_RDDS80_EHTTPCODE;
		zbx_snprintf(err, err_size, "invalid HTTP response code (%d), expected %d", (int)response_code,
				ZBX_HTTP_RESPONSE_OK);
		goto out;
	}

	if (CURLE_OK != (curl_err = curl_easy_getinfo(easyhandle, CURLINFO_TOTAL_TIME, &total_time)))
	{
		*rtt80 = ZBX_EC_INTERNAL;
		zbx_snprintf(err, err_size, "cannot get HTTP request time (%s)", curl_easy_strerror(curl_err));
		goto out;
	}

	*rtt80 = total_time * 1000;	/* expected in ms */

	ret = SUCCEED;
out:
	if (NULL != slist)
		curl_slist_free_all(slist);

	if (NULL != easyhandle)
		curl_easy_cleanup(easyhandle);
#else
	*rtt80 = ZBX_EC_INTERNAL;
	zbx_strlcpy(err, "zabbix is not compiled with libcurl support (--with-libcurl)", err_size);
#endif
	return ret;
}

#define RDDS_DOWN	0
#define RDDS_UP		1
#define RDDS_ONLY43	2
#define RDDS_ONLY80	3

static int	zbx_ec_noerror(int ec)
{
	if (0 < ec || ZBX_NO_VALUE == ec)
		return SUCCEED;

	return FAIL;
}

static void	zbx_vector_str_clean_and_destroy(zbx_vector_str_t *v)
{
	size_t	i;

	for (i = 0; i < v->values_num; i++)
		zbx_free(v->values[i]);

	zbx_vector_str_destroy(v);
}

int	check_rsm_rdds(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result)
{
	char			domain[ZBX_HOST_BUF_SIZE], *value_str = NULL, *res_ip = NULL, *testprefix = NULL,
				*rdds_ns_string = NULL, *answer = NULL, testname[ZBX_HOST_BUF_SIZE], is_ipv4, *random_ns,
				err[ZBX_ERR_BUF_SIZE];
	const char		*random_host, *ip43 = NULL, *ip80 = NULL;
	zbx_vector_str_t	hosts43, hosts80, ips43, ips80, nss;
	FILE			*log_fd = NULL;
	ldns_resolver		*res = NULL;
	DC_ITEM			*items = NULL;
	size_t			i, items_num = 0;
	time_t			ts, now;
	int			rtt43 = ZBX_NO_VALUE, upd43 = ZBX_NO_VALUE, rtt80 = ZBX_NO_VALUE, rtt_limit,
				ipv4_enabled, ipv6_enabled, rdds_enabled, epp_enabled, ret = SYSINFO_RET_FAIL, maxredirs;

	/* first read the TLD */
	if (SUCCEED != get_param(params, 1, domain, sizeof(domain)) || '\0' == *domain)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "first key parameter missing"));
		return SYSINFO_RET_FAIL;
	}

	/* open log file */
	if (NULL == (log_fd = open_item_log(domain, ZBX_RDDS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_str_create(&hosts43);
	zbx_vector_str_create(&hosts80);
	zbx_vector_str_create(&ips43);
	zbx_vector_str_create(&ips80);
	zbx_vector_str_create(&nss);

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_RDDS_ENABLED, &rdds_enabled, 0, err, sizeof(err)) ||
			0 == rdds_enabled)
	{
		zbx_rsm_info(log_fd, "RDDS disabled on this probe");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_RDDS_ENABLED, &rdds_enabled, 0,
			err, sizeof(err)) || 0 == rdds_enabled)
	{
		zbx_rsm_info(log_fd, "RDDS disabled on this TLD");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	/* read rest of key parameters */
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

	zbx_get_strings_from_list(&hosts43, value_str, ',');

	if (0 == hosts43.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cannot get RDDS43 hosts from key parameter"));
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

	zbx_get_strings_from_list(&hosts80, value_str, ',');

	if (0 == hosts80.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cannot get RDDS80 hosts from key parameter"));
		goto out;
	}

	/* get rest of configuration */
	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_RESOLVER, &res_ip, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_RDDS_TESTPREFIX, &testprefix, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_EPP_ENABLED, &epp_enabled, 0, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (0 != epp_enabled && SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_EPP_ENABLED, &epp_enabled, 0,
			err, sizeof(err)))
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

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_RDDS_MAXREDIRS, &maxredirs, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, ZBX_RSM_TCP, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot create resolver: %s", err));
		goto out;
	}

	/* get rddstest items */
	if (0 == (items_num = zbx_get_rdds_items(keyname, item, domain, &items, log_fd)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "no RDDS items found"));
		goto out;
	}

	/* from this point item will not become NOTSUPPORTED */
	ret = SYSINFO_RET_OK;

	/* choose random host */
	i = zbx_random(hosts43.values_num);
	random_host = hosts43.values[i];

	/* start RDDS43 test, resolve host to ips */
	if (SUCCEED != zbx_resolve_host(res, random_host, &ips43, 1, 1, log_fd, err, sizeof(err)))
	{
		rtt43 = ZBX_EC_RDDS_ERES;
		zbx_rsm_errf(log_fd, "RDDS43 \"%s\": %s", random_host, err);
	}

	/* if RDDS43 fails we should still process RDDS80 */

	if (SUCCEED == zbx_ec_noerror(rtt43))
	{
		zbx_delete_unsupported_ips(&ips43, ipv4_enabled, ipv6_enabled);

		if (0 == ips43.values_num)
		{
			rtt43 = ZBX_EC_INTERNAL_IP_UNSUP;
			zbx_rsm_errf(log_fd, "RDDS43 \"%s\": IP address(es) of host not supported by this probe",
					random_host);
		}
	}

	if (SUCCEED == zbx_ec_noerror(rtt43))
	{
		/* choose random IP */
		i = zbx_random(ips43.values_num);
		ip43 = ips43.values[i];

		if (0 != strcmp(".", domain))
			zbx_snprintf(testname, sizeof(testname), "%s.%s", testprefix, domain);
		else
			zbx_strlcpy(testname, testprefix, sizeof(testname));

		zbx_rsm_infof(log_fd, "start RDDS43 test (ip %s, request \"%s\", expected prefix \"%s\")",
				ip43, testname, rdds_ns_string);

		if (SUCCEED != zbx_rdds43_test(testname, ip43, 43, ZBX_RSM_TCP_TIMEOUT, &answer, &rtt43,
				err, sizeof(err)))
		{
			rtt43 = ZBX_EC_RDDS43_NOREPLY;
			zbx_rsm_errf(log_fd, "RDDS43 of \"%s\" (%s) failed: %s", random_host, ip43, err);
		}
	}

	if (SUCCEED == zbx_ec_noerror(rtt43))
	{
		zbx_get_rdds43_nss(&nss, answer, rdds_ns_string, log_fd);

		if (0 == nss.values_num)
		{
			rtt43 = ZBX_EC_RDDS43_NONS;
			zbx_rsm_errf(log_fd, "no Name Servers found in the output of RDDS43 server \"%s\""
					" (%s) for query \"%s\" (expecting prefix \"%s\")",
					random_host, ip43, testname, rdds_ns_string);
		}
	}

	if (SUCCEED == zbx_ec_noerror(rtt43))
	{
		/* choose random NS from the output */
		i = zbx_random(nss.values_num);
		random_ns = nss.values[i];

		zbx_rsm_infof(log_fd, "randomly selected Name Server server \"%s\"", random_ns);

		if (0 != epp_enabled)
		{
			/* start RDDS UPD test, get timestamp from the host name */
			if (SUCCEED != zbx_get_ts_from_host(random_ns, &ts))
			{
				upd43 = ZBX_EC_RDDS43_NOTS;
				zbx_rsm_errf(log_fd, "cannot extract Unix timestamp from Name Server \"%s\"", random_ns);
			}

			if (upd43 == ZBX_NO_VALUE)
			{
				now = time(NULL);

				if (0 > now - ts)
				{
					zbx_rsm_errf(log_fd, "Unix timestamp of Name Server \"%s\" is in the future"
							" (current: %lu)", random_ns, now);
					upd43 = ZBX_EC_RDDS43_ETS;
				}
			}

			if (upd43 == ZBX_NO_VALUE)
			{
				/* successful UPD */
				upd43 = now - ts;
			}

			zbx_rsm_infof(log_fd, "===>\n%.*s\n<=== end RDDS43 test (rtt:%d upd43:%d)",
					ZBX_RDDS_PREVIEW_SIZE, answer, rtt43, upd43);
		}
		else
		{
			zbx_rsm_infof(log_fd, "===>\n%.*s\n<=== end RDDS43 test (rtt:%d)",
					ZBX_RDDS_PREVIEW_SIZE, answer, rtt43);
		}
	}

	zbx_rsm_infof(log_fd, "start RDDS80 test (url %s, host %s)", testname, random_host);

	/* choose random host */
	i = zbx_random(hosts80.values_num);
	random_host = hosts80.values[i];

	/* start RDDS80 test, resolve host to ips */
	if (SUCCEED != zbx_resolve_host(res, random_host, &ips80, ipv4_enabled, ipv6_enabled, log_fd, err, sizeof(err)))
	{
		rtt80 = ZBX_EC_RDDS_ERES;
		zbx_rsm_errf(log_fd, "RDDS80 \"%s\": %s", random_host, err);
		goto out;
	}

	zbx_delete_unsupported_ips(&ips80, ipv4_enabled, ipv6_enabled);

	if (0 == ips80.values_num)
	{
		rtt80 = ZBX_EC_INTERNAL_IP_UNSUP;
		zbx_rsm_errf(log_fd, "RDDS80 \"%s\": IP address(es) of host not supported by this probe", random_host);
		goto out;
	}

	/* choose random IP */
	i = zbx_random(ips80.values_num);
	ip80 = ips80.values[i];

	if (SUCCEED != zbx_validate_ip(ip80, ipv4_enabled, ipv6_enabled, NULL, &is_ipv4))
	{
		rtt80 = ZBX_EC_INTERNAL;
		zbx_rsm_errf(log_fd, "internal error, selected unsupported IP of \"%s\": \"%s\"", random_host, ip80);
		goto out;
	}

	if (0 != is_ipv4)
		zbx_snprintf(testname, sizeof(testname), "http://%s", ip80);
	else
		zbx_snprintf(testname, sizeof(testname), "http://[%s]", ip80);

	if (SUCCEED != zbx_rdds80_test(random_host, testname, 80, ZBX_RSM_TCP_TIMEOUT, maxredirs, &rtt80,
			err, sizeof(err)))
	{
		zbx_rsm_errf(log_fd, "RDDS80 of \"%s\" (%s) failed: %s", random_host, ip80, err);
		goto out;
	}

	zbx_rsm_infof(log_fd, "end RDDS80 test (rtt:%d)", rtt80);
out:
	if (0 != ISSET_MSG(result))
		zbx_rsm_err(log_fd, result->msg);

	if (SYSINFO_RET_OK == ret)
	{
		int	rdds_result, rdds43, rdds80;

		zbx_set_rdds_values(ip43, rtt43, upd43, ip80, rtt80, item->nextcheck, strlen(keyname), items,
				items_num);

		rdds43 = rtt_result(rtt43, rtt_limit);
		rdds80 = rtt_result(rtt80, rtt_limit);

		if (SUCCEED == rdds43)
		{
			if (SUCCEED == rdds80)
				rdds_result = RDDS_UP;
			else
				rdds_result = RDDS_ONLY43;
		}
		else
		{
			if (SUCCEED == rdds80)
				rdds_result = RDDS_ONLY80;
			else
				rdds_result = RDDS_DOWN;
		}

		/* set the value of our item itself */
		zbx_add_value_uint(item, item->nextcheck, rdds_result);
	}

	free_items(items, items_num);

	if (NULL != res)
	{
		if (0 != ldns_resolver_nameserver_count(res))
			ldns_resolver_deep_free(res);
		else
			ldns_resolver_free(res);
	}

	if (NULL != log_fd)
		fclose(log_fd);

	zbx_free(answer);
	zbx_free(rdds_ns_string);
	zbx_free(testprefix);
	zbx_free(res_ip);
	zbx_free(value_str);

	zbx_vector_str_clean_and_destroy(&nss);
	zbx_vector_str_clean_and_destroy(&ips80);
	zbx_vector_str_clean_and_destroy(&ips43);
	zbx_vector_str_clean_and_destroy(&hosts80);
	zbx_vector_str_clean_and_destroy(&hosts43);

	return ret;
}

static int	epp_recv_buf(SSL *ssl, void *buf, int num)
{
	void	*p;
	int	read, ret = FAIL;

	if (1 > num)
		goto out;

	p = buf;

	while (0 < num)
	{
		if (0 >= (read = SSL_read(ssl, p, num)))
			goto out;

		p += read;
		num -= read;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	epp_recv_message(SSL *ssl, char **data, size_t *data_len, FILE *log_fd)
{
	int	message_size, ret = FAIL;

	if (NULL == data || NULL != *data)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	/* receive header */
	if (SUCCEED != epp_recv_buf(ssl, &message_size, sizeof(message_size)))
		goto out;

	*data_len = ntohl(message_size) - sizeof(message_size);
	*data = malloc(*data_len);

	/* receive body */
	if (SUCCEED != epp_recv_buf(ssl, *data, *data_len - 1))
		goto out;

	(*data)[*data_len - 1] = '\0';

	zbx_rsm_infof(log_fd, "received message ===>\n%s\n<===", *data);

	ret = SUCCEED;
out:
	if (SUCCEED != ret && NULL != *data)
	{
		free(*data);
		*data = NULL;
	}

	return ret;
}

static int	epp_send_buf(SSL *ssl, const void *buf, int num)
{
	const void	*p;
	int		written, ret = FAIL;

	if (1 > num)
		goto out;

	p = buf;

	while (0 < num)
	{
		if (0 >= (written = SSL_write(ssl, p, num)))
			goto out;

		p += written;
		num -= written;
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	epp_send_message(SSL *ssl, const char *data, int data_size, FILE *log_fd)
{
	int	message_size, ret = FAIL;

	message_size = htonl(data_size + sizeof(message_size));

	/* send header */
	if (SUCCEED != epp_send_buf(ssl, &message_size, sizeof(message_size)))
		goto out;

	/* send body */
	if (SUCCEED != epp_send_buf(ssl, data, data_size))
		goto out;

	zbx_rsm_infof(log_fd, "sent message ===>\n%s\n<===", data);

	ret = SUCCEED;
out:
	return ret;
}

static int	get_xml_value(const char *data, int xml_path, char *xml_value, size_t xml_value_size, FILE *log_fd)
{
	const char	*p_start, *p_end, *start_tag, *end_tag;
	int		ret = FAIL;

	switch (xml_path)
	{
		case XML_PATH_SERVER_ID:
			start_tag = "<svID>";
			end_tag = "</svID>";
			break;
		case XML_PATH_RESULT_CODE:
			start_tag = "<result code=\"";
			end_tag = "\">";
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	if (NULL == (p_start = zbx_strcasestr(data, start_tag)))
		goto out;

	p_start += strlen(start_tag);

	if (NULL == (p_end = zbx_strcasestr(p_start, end_tag)))
		goto out;

	zbx_strlcpy(xml_value, p_start, MIN(p_end - p_start + 1, xml_value_size));

	ret = SUCCEED;
out:
	return ret;
}

static int	get_tmpl(const char *epp_commands, const char *command, char **tmpl)
{
	char	buf[256];
	size_t	tmpl_alloc = 512, tmpl_offset = 0;
	int	f, nbytes, ret = FAIL;

	zbx_snprintf(buf, sizeof(buf), "%s/%s.tmpl", epp_commands, command);

	if (-1 == (f = zbx_open(buf, O_RDONLY)))
		goto out;

	*tmpl = zbx_malloc(*tmpl, tmpl_alloc);

	while (0 < (nbytes = zbx_read(f, buf, sizeof(buf), "")))
		zbx_strncpy_alloc(tmpl, &tmpl_alloc, &tmpl_offset, buf, nbytes);

	if (-1 == nbytes)
	{
		zbx_free(*tmpl);
		goto out;
	}

	ret = SUCCEED;
out:
	if (-1 != f)
		close(f);

	return ret;
}

static int	get_first_message(SSL *ssl, int *res, FILE *log_fd, const char *epp_serverid, char *err, size_t err_size)
{
	char	xml_value[XML_VALUE_BUF_SIZE], *data = NULL;
	size_t	data_len;
	int	ret = FAIL;

	if (SUCCEED != epp_recv_message(ssl, &data, &data_len, log_fd))
	{
		zbx_strlcpy(err, "cannot receive first message from server", err_size);
		*res = ZBX_EC_EPP_FIRSTTO;
		goto out;
	}

	if (SUCCEED != get_xml_value(data, XML_PATH_SERVER_ID, xml_value, sizeof(xml_value), log_fd))
	{
		zbx_snprintf(err, err_size, "no Server ID in first message from server");
		*res = ZBX_EC_EPP_FIRSTINVAL;
		goto out;
	}

	if (0 != strcmp(epp_serverid, xml_value))
	{
		zbx_snprintf(err, err_size, "invalid Server ID in the first message from server: \"%s\""
				" (expected \"%s\")", xml_value, epp_serverid);
		*res = ZBX_EC_EPP_FIRSTINVAL;
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != data)
		free(data);

	return ret;
}

static void	zbx_tmpl_replace(char **tmpl, const char *variable, const char *value)
{
	const char	*p;
	size_t		variable_size, l_pos, r_pos;

	variable_size = strlen(variable);

	while (NULL != (p = strstr(*tmpl, variable)))
	{
		l_pos = p - *tmpl;
		r_pos = l_pos + variable_size - 1;

		zbx_replace_string(tmpl, p - *tmpl, &r_pos, value);
	}
}

static int	command_login(const char *epp_commands, const char *name, SSL *ssl, int *rtt, FILE *log_fd,
		const char *epp_user, const char *epp_passwd, char *err, size_t err_size)
{
	char		*tmpl = NULL, xml_value[XML_VALUE_BUF_SIZE], *data = NULL;
	size_t		data_len;
	zbx_timespec_t	start, end;
	int		ret = FAIL;

	if (SUCCEED != get_tmpl(epp_commands, name, &tmpl))
	{
		zbx_snprintf(err, err_size, "cannot load template \"%s\"", name);
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	zbx_tmpl_replace(&tmpl, "{TMPL_EPP_USER}", epp_user);
	zbx_tmpl_replace(&tmpl, "{TMPL_EPP_PASSWD}", epp_passwd);

	zbx_timespec(&start);

	if (SUCCEED != epp_send_message(ssl, tmpl, strlen(tmpl), log_fd))
	{
		zbx_snprintf(err, err_size, "cannot send command \"%s\"", name);
		*rtt = ZBX_EC_EPP_LOGINTO;
		goto out;
	}

	if (SUCCEED != epp_recv_message(ssl, &data, &data_len, log_fd))
	{
		zbx_snprintf(err, err_size, "cannot receive reply to command \"%s\"", name);
		*rtt = ZBX_EC_EPP_LOGINTO;
		goto out;
	}

	if (SUCCEED != get_xml_value(data, XML_PATH_RESULT_CODE, xml_value, sizeof(xml_value), log_fd))
	{
		zbx_snprintf(err, err_size, "no result code in reply");
		*rtt = ZBX_EC_EPP_LOGININVAL;
		goto out;
	}

	if (0 != strcmp(EPP_SUCCESS_CODE_GENERAL, xml_value))
	{
		zbx_snprintf(err, err_size, "invalid result code in reply to \"%s\": \"%s\" (expected \"%s\")",
				name, xml_value, EPP_SUCCESS_CODE_GENERAL);
		*rtt = ZBX_EC_EPP_LOGININVAL;
		goto out;
	}

	zbx_timespec(&end);
	*rtt = (end.sec - start.sec) * 1000 + (end.ns - start.ns) / 1000000;

	ret = SUCCEED;
out:
	zbx_free(data);
	zbx_free(tmpl);

	return ret;
}

static int	command_update(const char *epp_commands, const char *name, SSL *ssl, int *rtt, FILE *log_fd,
		const char *epp_testprefix, const char *domain, char *err, size_t err_size)
{
	char		*tmpl = NULL, xml_value[XML_VALUE_BUF_SIZE], *data = NULL, tsbuf[32], buf[ZBX_HOST_BUF_SIZE];
	size_t		data_len;
	time_t		now;
	zbx_timespec_t	start, end;
	int		ret = FAIL;

	if (SUCCEED != get_tmpl(epp_commands, name, &tmpl))
	{
		zbx_snprintf(err, err_size, "cannot load template \"%s\"", name);
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	time(&now);
	zbx_snprintf(tsbuf, sizeof(tsbuf), "%llu", now);

	zbx_snprintf(buf, sizeof(buf), "%s.%s", epp_testprefix, domain);

	zbx_tmpl_replace(&tmpl, "{TMPL_DOMAIN}", buf);
	zbx_tmpl_replace(&tmpl, "{TMPL_TIMESTAMP}", tsbuf);

	zbx_timespec(&start);

	if (SUCCEED != epp_send_message(ssl, tmpl, strlen(tmpl), log_fd))
	{
		zbx_snprintf(err, err_size, "cannot send command \"%s\"", name);
		*rtt = ZBX_EC_EPP_UPDATETO;
		goto out;
	}

	if (SUCCEED != epp_recv_message(ssl, &data, &data_len, log_fd))
	{
		zbx_snprintf(err, err_size, "cannot receive reply to command \"%s\"", name);
		*rtt = ZBX_EC_EPP_UPDATETO;
		goto out;
	}

	if (SUCCEED != get_xml_value(data, XML_PATH_RESULT_CODE, xml_value, sizeof(xml_value), log_fd))
	{
		zbx_snprintf(err, err_size, "no result code in reply");
		*rtt = ZBX_EC_EPP_UPDATEINVAL;
		goto out;
	}

	if (0 != strcmp(EPP_SUCCESS_CODE_GENERAL, xml_value))
	{
		zbx_snprintf(err, err_size, "invalid result code in reply to \"%s\": \"%s\" (expected \"%s\")",
				name, xml_value, EPP_SUCCESS_CODE_GENERAL);
		*rtt = ZBX_EC_EPP_UPDATEINVAL;
		goto out;
	}

	zbx_timespec(&end);
	*rtt = (end.sec - start.sec) * 1000 + (end.ns - start.ns) / 1000000;

	ret = SUCCEED;
out:
	zbx_free(data);
	zbx_free(tmpl);

	return ret;
}

static int	command_info(const char *epp_commands, const char *name, SSL *ssl, int *rtt, FILE *log_fd,
		const char *epp_testprefix, const char *domain, char *err, size_t err_size)
{
	char		*tmpl = NULL, xml_value[XML_VALUE_BUF_SIZE], *data = NULL, buf[ZBX_HOST_BUF_SIZE];
	size_t		data_len;
	zbx_timespec_t	start, end;
	int		ret = FAIL;

	if (SUCCEED != get_tmpl(epp_commands, name, &tmpl))
	{
		zbx_snprintf(err, err_size, "cannot load template \"%s\"", name);
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	zbx_snprintf(buf, sizeof(buf), "%s.%s", epp_testprefix, domain);

	zbx_tmpl_replace(&tmpl, "{TMPL_DOMAIN}", buf);

	zbx_timespec(&start);

	if (SUCCEED != epp_send_message(ssl, tmpl, strlen(tmpl), log_fd))
	{
		zbx_snprintf(err, err_size, "cannot send command \"%s\"", name);
		*rtt = ZBX_EC_EPP_INFOTO;
		goto out;
	}

	if (SUCCEED != epp_recv_message(ssl, &data, &data_len, log_fd))
	{
		zbx_snprintf(err, err_size, "cannot receive reply to command \"%s\"", name);
		*rtt = ZBX_EC_EPP_INFOTO;
		goto out;
	}

	if (SUCCEED != get_xml_value(data, XML_PATH_RESULT_CODE, xml_value, sizeof(xml_value), log_fd))
	{
		zbx_snprintf(err, err_size, "no result code in reply");
		*rtt = ZBX_EC_EPP_INFOINVAL;
		goto out;
	}

	if (0 != strcmp(EPP_SUCCESS_CODE_GENERAL, xml_value))
	{
		zbx_snprintf(err, err_size, "invalid result code in reply to \"%s\": \"%s\" (expected \"%s\")",
				name, xml_value, EPP_SUCCESS_CODE_GENERAL);
		*rtt = ZBX_EC_EPP_INFOINVAL;
		goto out;
	}

	zbx_timespec(&end);
	*rtt = (end.sec - start.sec) * 1000 + (end.ns - start.ns) / 1000000;

	ret = SUCCEED;
out:
	zbx_free(data);
	zbx_free(tmpl);

	return ret;
}

static int	command_logout(const char *epp_commands, const char *name, SSL *ssl, FILE *log_fd, char *err, size_t err_size)
{
	char	*tmpl = NULL, xml_value[XML_VALUE_BUF_SIZE], *data = NULL;
	size_t	data_len;
	int	ret = FAIL;

	if (SUCCEED != get_tmpl(epp_commands, name, &tmpl))
	{
		zbx_snprintf(err, err_size, "cannot load template \"%s\"", name);
		goto out;
	}

	if (SUCCEED != epp_send_message(ssl, tmpl, strlen(tmpl), log_fd))
	{
		zbx_snprintf(err, err_size, "cannot send command \"%s\"", name);
		goto out;
	}

	if (SUCCEED != epp_recv_message(ssl, &data, &data_len, log_fd))
	{
		zbx_snprintf(err, err_size, "cannot receive reply to command \"%s\"", name);
		goto out;
	}

	if (SUCCEED != get_xml_value(data, XML_PATH_RESULT_CODE, xml_value, sizeof(xml_value), log_fd))
	{
		zbx_snprintf(err, err_size, "no result code in reply");
		goto out;
	}

	if (0 != strcmp(EPP_SUCCESS_CODE_LOGOUT, xml_value))
	{
		zbx_snprintf(err, err_size, "invalid result code in reply to \"%s\": \"%s\" (expected \"%s\")",
				name, xml_value, EPP_SUCCESS_CODE_LOGOUT);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(data);
	zbx_free(tmpl);

	return ret;
}

static int	zbx_parse_epp_item(DC_ITEM *item, char *host, size_t host_size)
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

	ZBX_STRDUP(item->params, params);

	return SUCCEED;
}

static size_t	zbx_get_epp_items(const char *keyname, DC_ITEM *item, const char *domain, DC_ITEM **out_items,
		FILE *log_fd)
{
	char		*keypart, host[ZBX_HOST_BUF_SIZE];
	const char	*p;
	DC_ITEM		*in_items = NULL, *in_item;
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
		in_item = &in_items[i];

		ZBX_STRDUP(in_item->key, in_item->key_orig);
		in_item->params = NULL;

		if (SUCCEED != substitute_key_macros(&in_item->key, NULL, item, NULL, MACRO_TYPE_ITEM_KEY, NULL, 0))
		{
			/* problem with key macros, skip it */
			zbx_rsm_warnf(log_fd, "%s: cannot substitute key macros", in_item->key_orig);
			continue;
		}

		if (SUCCEED != zbx_parse_epp_item(in_item, host, sizeof(host)))
		{
			/* unexpected item key syntax, skip it */
			zbx_rsm_warnf(log_fd, "%s: unexpected key syntax", in_item->key);
			continue;
		}

		if (0 != strcmp(host, domain))
		{
			/* first parameter does not match expected domain name, skip it */
			zbx_rsm_warnf(log_fd, "%s: first parameter does not match host %s", in_item->key, domain);
			continue;
		}

		p = in_item->key + keypart_size;
		if (0 != strncmp(p, "ip[", 3) && 0 != strncmp(p, "rtt[", 4))
			continue;

		if (0 == out_items_num)
		{
			*out_items = zbx_malloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}
		else if (out_items_num == out_items_alloc)
		{
			out_items_alloc += 8;
			*out_items = zbx_realloc(*out_items, out_items_alloc * sizeof(DC_ITEM));
		}

		memcpy(&(*out_items)[out_items_num], in_item, sizeof(DC_ITEM));
		(*out_items)[out_items_num].key = in_item->key;
		(*out_items)[out_items_num].params = in_item->params;
		in_item->key = NULL;
		in_item->params = NULL;

		out_items_num++;
	}

	free_items(in_items, in_items_num);

	return out_items_num;
}

static void	zbx_set_epp_values(const char *ip, int rtt1, int rtt2, int rtt3, int value_ts, size_t keypart_size,
		const DC_ITEM *items, size_t items_num)
{
	size_t		i;
	const DC_ITEM	*item;
	const char	*p;
	char		cmd[64];

	for (i = 0; i < items_num; i++)
	{
		item = &items[i];
		p = item->key + keypart_size + 1;	/* skip "rsm.epp." part */

		if (NULL != ip && 0 == strncmp(p, "ip[", 3))
			zbx_add_value_str(item, value_ts, ip);
		else if ((ZBX_NO_VALUE != rtt1 || ZBX_NO_VALUE != rtt2 || ZBX_NO_VALUE != rtt3) &&
				0 == strncmp(p, "rtt[", 4))
		{
			*cmd = '\0';

			if (0 == get_param(item->params, 2, cmd, sizeof(cmd)) && '\0' != *cmd)
			{
				if (ZBX_NO_VALUE != rtt1 && 0 == strcmp("login", cmd))
					zbx_add_value_dbl(item, value_ts, rtt1);
				else if (ZBX_NO_VALUE != rtt2 && 0 == strcmp("update", cmd))
					zbx_add_value_dbl(item, value_ts, rtt2);
				else if (ZBX_NO_VALUE != rtt3 && 0 == strcmp("info", cmd))
					zbx_add_value_dbl(item, value_ts, rtt3);
			}
		}
	}
}

static int	zbx_ssl_attach_cert(SSL *ssl, char *cert, int cert_len, int *rtt, char *err, size_t err_size)
{
	BIO	*bio = NULL;
	X509	*x509 = NULL;
	int	ret = FAIL;

	*rtt = ZBX_EC_EPP_CRYPT;

	if (NULL == (bio = BIO_new_mem_buf(cert, cert_len)))
	{
		*rtt = ZBX_EC_INTERNAL;
		zbx_strlcpy(err, "out of memory", err_size);
		goto out;
	}

	if (NULL == (x509 = PEM_read_bio_X509(bio, NULL, NULL, NULL)))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	if (1 != SSL_use_certificate(ssl, x509))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != x509)
		X509_free(x509);

	if (NULL != bio)
		BIO_free(bio);

	return ret;
}

static int	zbx_ssl_attach_privkey(SSL *ssl, char *privkey, int privkey_len, int *rtt, char *err, size_t err_size)
{
	BIO	*bio = NULL;
	RSA	*rsa = NULL;
	int	ret = FAIL;

	*rtt = ZBX_EC_EPP_CRYPT;

	if (NULL == (bio = BIO_new_mem_buf(privkey, privkey_len)))
	{
		*rtt = ZBX_EC_INTERNAL;
		zbx_strlcpy(err, "out of memory", err_size);
		goto out;
	}

	if (NULL == (rsa = PEM_read_bio_RSAPrivateKey(bio, NULL, NULL, NULL)))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	if (1 != SSL_use_RSAPrivateKey(ssl, rsa))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	ret = SUCCEED;
out:
	if (NULL != rsa)
		RSA_free(rsa);

	if (NULL != bio)
		BIO_free(bio);

	return ret;
}

static char	*zbx_parse_time(char *str, size_t str_size, int *i)
{
	char	*p_end;
	char	c;
	size_t	block_size = 0;
	int	rv;

	p_end = str;

	while ('\0' != *p_end && block_size++ < str_size)
		p_end++;

	if (str == p_end)
		return NULL;

	c = *p_end;
	*p_end = '\0';

	rv = sscanf(str, "%u", i);
	*p_end = c;

	if (1 != rv)
		return NULL;


	return p_end;
}

static int	zbx_parse_asn1time(ASN1_TIME *asn1time, time_t *time, char *err, size_t err_size)
{
	struct tm	tm;
	char		buf[15], *p;
	int		ret = FAIL;

	if (V_ASN1_UTCTIME == asn1time->type && 13 == asn1time->length && 'Z' == asn1time->data[12])
	{
		memcpy(buf + 2, asn1time->data, asn1time->length - 1);

		if ('5' <= asn1time->data[0])
		{
			buf[0] = '1';
			buf[1] = '9';
		}
		else
		{
			buf[0] = '2';
			buf[1] = '0';
		}
	}
	else if (V_ASN1_GENERALIZEDTIME == asn1time->type && 15 == asn1time->length && 'Z' == asn1time->data[14])
	{
		memcpy(buf, asn1time->data, asn1time->length-1);
	}
	else
	{
		zbx_strlcpy(err, "unknown date format", err_size);
		goto out;
	}

	buf[14] = '\0';

	memset(&tm, 0, sizeof(tm));

	/* year */
	if (NULL == (p = zbx_parse_time(buf, 4, &tm.tm_year)) || '\0' == *p)
	{
		zbx_strlcpy(err, "invalid year", err_size);
		goto out;
	}

	/* month */
	if (NULL == (p = zbx_parse_time(p, 2, &tm.tm_mon)) || '\0' == *p)
	{
		zbx_strlcpy(err, "invalid month", err_size);
		goto out;
	}

	/* day of month */
	if (NULL == (p = zbx_parse_time(p, 2, &tm.tm_mday)) || '\0' == *p)
	{
		zbx_strlcpy(err, "invalid day of month", err_size);
		goto out;
	}

	/* hours */
	if (NULL == (p = zbx_parse_time(p, 2, &tm.tm_hour)) || '\0' == *p)
	{
		zbx_strlcpy(err, "invalid hours", err_size);
		goto out;
	}

	/* minutes */
	if (NULL == (p = zbx_parse_time(p, 2, &tm.tm_min)) || '\0' == *p)
	{
		zbx_strlcpy(err, "invalid minutes", err_size);
		goto out;
	}

	/* seconds */
	if (NULL == (p = zbx_parse_time(p, 2, &tm.tm_sec)) || '\0' != *p)
	{
		zbx_strlcpy(err, "invalid seconds", err_size);
		goto out;
	}

	tm.tm_year -= 1900;
	tm.tm_mon -= 1;

	*time = timegm(&tm);

	ret = SUCCEED;
out:
	return ret;
}

static int	zbx_get_cert_md5(X509 *cert, char **md5, char *err, size_t err_size)
{
	char		*data;
	BIO		*bio;
	size_t		len, sz;
	md5_state_t	state;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	int		i, ret = FAIL;

	if (NULL == (bio = BIO_new(BIO_s_mem())))
	{
		zbx_strlcpy(err, "out of memory", err_size);
		goto out;
	}

	if (1 != PEM_write_bio_X509(bio, cert))
	{
		zbx_strlcpy(err, "internal OpenSSL error while parsing server certificate", err_size);
		goto out;
	}

	len = BIO_get_mem_data(bio, &data);	/* "data" points to the cert data (no need to free), len - its length */

	md5_init(&state);
	md5_append(&state, (const md5_byte_t *)data, len);
	md5_finish(&state, hash);

	sz = MD5_DIGEST_SIZE * 2 + 1;
	*md5 = zbx_malloc(*md5, sz);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
		zbx_snprintf(&(*md5)[i << 1], sz - (i << 1), "%02x", hash[i]);

	ret = SUCCEED;
out:
	if (NULL != bio)
		BIO_free(bio);

	return ret;
}

static int	zbx_validate_cert(X509 *cert, const char *md5_macro, int *rtt, char *err, size_t err_size)
{
	time_t	now, not_before, not_after;
	char	*md5 = NULL;
	int	ret = FAIL;

	*rtt = ZBX_EC_EPP_SERVERCERT;

	/* get certificate validity dates */
	if (SUCCEED != zbx_parse_asn1time(X509_get_notBefore(cert), &not_before, err, err_size))
		goto out;

	if (SUCCEED != zbx_parse_asn1time(X509_get_notAfter(cert), &not_after, err, err_size))
		goto out;

	now = time(NULL);
	if (now > not_after)
	{
		zbx_strlcpy(err, "the certificate has expired", err_size);
		goto out;
	}

	if (now < not_before)
	{
		zbx_strlcpy(err, "the validity date is in the future", err_size);
		goto out;
	}

	if (SUCCEED != zbx_get_cert_md5(cert, &md5, err, err_size))
	{
		*rtt = ZBX_EC_INTERNAL;
		goto out;
	}

	if (0 != strcmp(md5_macro, md5))
	{
		zbx_snprintf(err, err_size, "MD5 sum set in a macro (%s) differs from what we got (%s)", md5_macro, md5);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(md5);

	return ret;
}

int	check_rsm_epp(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result)
{
	ldns_resolver		*res = NULL;
	char			domain[ZBX_HOST_BUF_SIZE], err[ZBX_ERR_BUF_SIZE], *value_str = NULL, *res_ip = NULL,
				*secretkey_enc_b64 = NULL, *secretkey_salt_b64 = NULL, *epp_passwd_enc_b64 = NULL,
				*epp_passwd_salt_b64 = NULL, *epp_privkey_enc_b64 = NULL, *epp_privkey_salt_b64 = NULL,
				*epp_user = NULL, *epp_passwd = NULL, *epp_privkey = NULL, *epp_cert_b64 = NULL,
				*epp_cert = NULL, *epp_commands = NULL, *epp_serverid = NULL, *epp_testprefix = NULL,
				*epp_servercertmd5 = NULL, *tmp;
	short			epp_port = 700;
	X509			*epp_server_x509 = NULL;
	const SSL_METHOD	*method;
	const char		*ip = NULL, *random_host;
	SSL_CTX			*ctx = NULL;
	SSL			*ssl = NULL;
	FILE			*log_fd = NULL;
	zbx_sock_t		sock;
	DC_ITEM			*items = NULL;
	size_t			items_num = 0;
	zbx_vector_str_t	epp_hosts, epp_ips;
	int			rv, i, epp_enabled, epp_cert_size, rtt, rtt1 = ZBX_NO_VALUE, rtt2 = ZBX_NO_VALUE,
				rtt3 = ZBX_NO_VALUE, rtt1_limit, rtt2_limit, rtt3_limit, ipv4_enabled, ipv6_enabled,
				ret = SYSINFO_RET_FAIL;

	memset(&sock, 0, sizeof(zbx_sock_t));
	sock.socket = ZBX_SOCK_ERROR;

	zbx_vector_str_create(&epp_hosts);
	zbx_vector_str_create(&epp_ips);

	/* first read the TLD */
	if (0 != get_param(params, 1, domain, sizeof(domain)) || '\0' == *domain)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "first key parameter missing"));
		goto out;
	}

	/* open log file */
	if (NULL == (log_fd = open_item_log(domain, ZBX_EPP_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if ('\0' == *epp_passphrase)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "EPP passphrase was not provided when starting proxy"
				" (restart proxy with --rsm option)"));
		goto out;
	}

	/* get EPP servers list */
	if (NULL == (value_str = get_param_dyn(params, 2)) || '\0' == *value_str)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "second key parameter missing"));
		goto out;
	}

	zbx_get_strings_from_list(&epp_hosts, value_str, ',');

	if (0 == epp_hosts.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cannot get EPP hosts from key parameter"));
		goto out;
	}

	/* make sure the service is enabled */
	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_EPP_ENABLED, &epp_enabled, 0, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (0 == epp_enabled)
	{
		zbx_rsm_info(log_fd, "EPP disabled on this probe");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_TLD_EPP_ENABLED, &epp_enabled, 0, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (0 == epp_enabled)
	{
		zbx_rsm_info(log_fd, "EPP disabled on this TLD");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_DNS_RESOLVER, &res_ip, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_EPP_LOGIN_RTT, &rtt1_limit, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_EPP_UPDATE_RTT, &rtt2_limit, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_EPP_INFO_RTT, &rtt3_limit, 1, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_ip_support(&item->host.hostid, &ipv4_enabled, &ipv6_enabled, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_USER, &epp_user, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_CERT, &epp_cert_b64, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_COMMANDS, &epp_commands, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_SERVERID, &epp_serverid, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_TESTPREFIX, &epp_testprefix, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_SERVERCERTMD5, &epp_servercertmd5, err,
			sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	/* get EPP password and salt */
	zbx_free(value_str);
	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_PASSWD, &value_str, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (NULL == (tmp = strchr(value_str, '|')))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "value of macro %s must contain separator |",
				ZBX_MACRO_EPP_PASSWD));
		goto out;
	}

	*tmp = '\0';
	tmp++;

	epp_passwd_enc_b64 = zbx_strdup(epp_passwd_enc_b64, value_str);
	epp_passwd_salt_b64 = zbx_strdup(epp_passwd_salt_b64, tmp);

	/* get EPP client private key and salt */
	zbx_free(value_str);
	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_PRIVKEY, &value_str, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (NULL == (tmp = strchr(value_str, '|')))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "value of macro %s must contain separator | (%s)",
				ZBX_MACRO_EPP_PRIVKEY, value_str));
		goto out;
	}

	*tmp = '\0';
	tmp++;

	epp_privkey_enc_b64 = zbx_strdup(epp_privkey_enc_b64, value_str);
	epp_privkey_salt_b64 = zbx_strdup(epp_privkey_salt_b64, tmp);

	/* get EPP passphrase and salt */
	zbx_free(value_str);
	if (SUCCEED != zbx_conf_str(&item->host.hostid, ZBX_MACRO_EPP_KEYSALT, &value_str, err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	if (NULL == (tmp = strchr(value_str, '|')))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "value of macro %s must contain separator |",
				ZBX_MACRO_EPP_KEYSALT));
		goto out;
	}

	*tmp = '\0';
	tmp++;

	secretkey_enc_b64 = zbx_strdup(secretkey_enc_b64, value_str);
	secretkey_salt_b64 = zbx_strdup(secretkey_salt_b64, tmp);

	/* get epp items */
	if (0 == (items_num = zbx_get_epp_items(keyname, item, domain, &items, log_fd)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "no EPP items found"));
		goto out;
	}

	/* create resolver */
	if (SUCCEED != zbx_create_resolver(&res, "resolver", res_ip, ZBX_RSM_TCP, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot create resolver: %s", err));
		goto out;
	}

	/* from this point item will not become NOTSUPPORTED */
	ret = SYSINFO_RET_OK;

	if (SUCCEED != rsm_ssl_init())
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_err(log_fd, "cannot initialize SSL library");
		goto out;
	}

	/* set SSLv2 client hello, also announce SSLv3 and TLSv1 */
	method = SSLv23_client_method();

	/* create a new SSL context */
	if (NULL == (ctx = SSL_CTX_new(method)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_err(log_fd, "cannot create a new SSL context structure");
		goto out;
	}

	/* disabling SSLv2 will leave v3 and TSLv1 for negotiation */
	SSL_CTX_set_options(ctx, SSL_OP_NO_SSLv2);

	/* create new SSL connection state object */
	if (NULL == (ssl = SSL_new(ctx)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_err(log_fd, "cannot create a new SSL context structure");
		goto out;
	}

	/* choose random host */
	i = zbx_random(epp_hosts.values_num);
	random_host = epp_hosts.values[i];

	/* resolve host to ips */
	if (SUCCEED != zbx_resolve_host(res, random_host, &epp_ips, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_EPP_NO_IP;
		zbx_rsm_errf(log_fd, "\"%s\": %s", random_host, err);
		goto out;
	}

	zbx_delete_unsupported_ips(&epp_ips, ipv4_enabled, ipv6_enabled);

	if (0 == epp_ips.values_num)
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL_IP_UNSUP;
		zbx_rsm_errf(log_fd, "EPP \"%s\": IP address(es) of host not supported by this probe", random_host);
		goto out;
	}

	/* choose random IP */
	i = zbx_random(epp_ips.values_num);
	ip = epp_ips.values[i];

	/* make the underlying TCP socket connection */
	if (SUCCEED != zbx_tcp_connect(&sock, NULL, ip, epp_port, ZBX_RSM_TCP_TIMEOUT))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_EPP_CONNECT;
		zbx_rsm_errf(log_fd, "cannot connect to EPP server %s:%d", ip, epp_port);
		goto out;
	}

	/* attach the socket descriptor to SSL session */
	if (1 != SSL_set_fd(ssl, sock.socket))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_err(log_fd, "cannot attach TCP socket to SSL session");
		goto out;
	}

	str_base64_decode_dyn(epp_cert_b64, strlen(epp_cert_b64), &epp_cert, &epp_cert_size);

	if (SUCCEED != zbx_ssl_attach_cert(ssl, epp_cert, epp_cert_size, &rtt, err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = rtt;
		zbx_rsm_errf(log_fd, "cannot attach client certificate to SSL session: %s", err);
		goto out;
	}

	if (SUCCEED != decrypt_ciphertext(epp_passphrase, strlen(epp_passphrase), secretkey_enc_b64,
			strlen(secretkey_enc_b64), secretkey_salt_b64, strlen(secretkey_salt_b64), epp_privkey_enc_b64,
			strlen(epp_privkey_enc_b64), epp_privkey_salt_b64, strlen(epp_privkey_salt_b64), &epp_privkey,
			err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_errf(log_fd, "cannot decrypt client private key: %s", err);
		goto out;
	}

	rv = zbx_ssl_attach_privkey(ssl, epp_privkey, strlen(epp_privkey), &rtt, err, sizeof(err));

	memset(epp_privkey, 0, strlen(epp_privkey));
	zbx_free(epp_privkey);

	if (SUCCEED != rv)
	{
		rtt1 = rtt2 = rtt3 = rtt;
		zbx_rsm_errf(log_fd, "cannot attach client private key to SSL session: %s", err);
		goto out;
	}

	/* try to SSL-connect, returns 1 on success */
	if (1 != SSL_connect(ssl))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_ssl_get_error(err, sizeof(err));
		zbx_rsm_errf(log_fd, "cannot build an SSL connection to %s:%d: %s", ip, epp_port, err);
		goto out;
	}

	/* get the remote certificate into the X509 structure */
	if (NULL == (epp_server_x509 = SSL_get_peer_certificate(ssl)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_EPP_SERVERCERT;
		zbx_rsm_errf(log_fd, "cannot get Server certificate from %s:%d", ip, epp_port);
		goto out;
	}

	if (SUCCEED != zbx_validate_cert(epp_server_x509, epp_servercertmd5, &rtt, err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = rtt;
		zbx_rsm_errf(log_fd, "Server certificate validation failed: %s", err);
		goto out;
	}

	zbx_rsm_info(log_fd, "Server certificate validation successful");

	zbx_rsm_infof(log_fd, "start EPP test (ip %s)", ip);

	if (SUCCEED != get_first_message(ssl, &rv, log_fd, epp_serverid, err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = rv;
		zbx_rsm_err(log_fd, err);
		goto out;
	}

	if (SUCCEED != decrypt_ciphertext(epp_passphrase, strlen(epp_passphrase), secretkey_enc_b64,
			strlen(secretkey_enc_b64), secretkey_salt_b64, strlen(secretkey_salt_b64), epp_passwd_enc_b64,
			strlen(epp_passwd_enc_b64), epp_passwd_salt_b64, strlen(epp_passwd_salt_b64), &epp_passwd,
			err, sizeof(err)))
	{
		rtt1 = rtt2 = rtt3 = ZBX_EC_INTERNAL;
		zbx_rsm_errf(log_fd, "cannot decrypt EPP password: %s", err);
		goto out;
	}

	rv = command_login(epp_commands, COMMAND_LOGIN, ssl, &rtt1, log_fd, epp_user, epp_passwd, err, sizeof(err));

	memset(epp_passwd, 0, strlen(epp_passwd));
	zbx_free(epp_passwd);

	if (SUCCEED != rv)
	{
		rtt2 = rtt3 = rtt1;
		zbx_rsm_err(log_fd, err);
		goto out;
	}

	if (SUCCEED != command_update(epp_commands, COMMAND_UPDATE, ssl, &rtt2, log_fd, epp_testprefix, domain,
			err, sizeof(err)))
	{
		rtt3 = rtt2;
		zbx_rsm_err(log_fd, err);
		goto out;
	}

	if (SUCCEED != command_info(epp_commands, COMMAND_INFO, ssl, &rtt3, log_fd, epp_testprefix, domain, err,
			sizeof(err)))
	{
		zbx_rsm_err(log_fd, err);
		goto out;
	}

	/* logout command errors should not affect the test results */
	if (SUCCEED != command_logout(epp_commands, COMMAND_LOGOUT, ssl, log_fd, err, sizeof(err)))
		zbx_rsm_err(log_fd, err);

	zbx_rsm_infof(log_fd, "end EPP test (ip %s):SUCCESS", ip);
out:
	if (0 != ISSET_MSG(result))
	{
		zbx_rsm_err(log_fd, result->msg);
	}
	else
	{
		/* set other EPP item values */
		if (0 != items_num)
			zbx_set_epp_values(ip, rtt1, rtt2, rtt3, item->nextcheck, strlen(keyname), items, items_num);

		/* set availability of EPP (up/down) */
		if (SUCCEED != rtt_result(rtt1, rtt1_limit) || SUCCEED != rtt_result(rtt2, rtt2_limit) ||
				SUCCEED != rtt_result(rtt3, rtt3_limit))
		{
			/* down */
			zbx_add_value_uint(item, item->nextcheck, 0);
		}
		else
		{
			/* up */
			zbx_add_value_uint(item, item->nextcheck, 1);
		}
	}

	free_items(items, items_num);

	zbx_free(epp_servercertmd5);
	zbx_free(epp_testprefix);
	zbx_free(epp_serverid);
	zbx_free(epp_commands);
	zbx_free(epp_user);
	zbx_free(epp_cert);
	zbx_free(epp_cert_b64);
	zbx_free(epp_privkey_salt_b64);
	zbx_free(epp_privkey_enc_b64);
	zbx_free(epp_passwd_salt_b64);
	zbx_free(epp_passwd_enc_b64);
	zbx_free(secretkey_salt_b64);
	zbx_free(secretkey_enc_b64);

	if (NULL != epp_server_x509)
		X509_free(epp_server_x509);

	if (NULL != ssl)
	{
		SSL_shutdown(ssl);
		SSL_free(ssl);
	}

	if (NULL != ctx)
		SSL_CTX_free(ctx);

	zbx_tcp_close(&sock);

	if (NULL != log_fd)
		fclose(log_fd);

	zbx_free(value_str);
	zbx_free(res_ip);

	zbx_vector_str_clean_and_destroy(&epp_ips);
	zbx_vector_str_clean_and_destroy(&epp_hosts);

	return ret;
}

static int	zbx_check_dns_connection(ldns_resolver **res, const char *ip, ldns_rdf *query_rdf, int reply_ms,
		int *dns_res, FILE *log_fd, int ipv4_enabled, int ipv6_enabled, char *err, size_t err_size)
{
	ldns_pkt	*pkt = NULL;
	ldns_rr_list	*rrset = NULL;
	int		ret = FAIL;

	if (NULL == *res)
	{
		if (SUCCEED != zbx_create_resolver(res, "root server", ip, ZBX_RSM_UDP, ipv4_enabled, ipv6_enabled,
				log_fd, err, err_size))
		{
			goto out;
		}
	}
	else if (SUCCEED != zbx_change_resolver(*res, "root server", ip, ipv4_enabled, ipv6_enabled, log_fd,
			err, sizeof(err)))
	{
		goto out;
	}

	/* not internal error */
	ret = SUCCEED;
	*dns_res = FAIL;

	/* set edns DO flag */
	ldns_resolver_set_dnssec(*res, true);

	if (NULL == (pkt = ldns_resolver_query(*res, query_rdf, LDNS_RR_TYPE_SOA, LDNS_RR_CLASS_IN, 0)))
	{
		zbx_rsm_errf(log_fd, "cannot connect to root server %s", ip);
		goto out;
	}

	ldns_pkt_print(log_fd, pkt);

	if (NULL == (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_SOA, LDNS_SECTION_ANSWER)))
	{
		zbx_rsm_warnf(log_fd, "no SOA records from %s", ip);
		goto out;
	}

	ldns_rr_list_deep_free(rrset);

	if (NULL == (rrset = ldns_pkt_rr_list_by_type(pkt, LDNS_RR_TYPE_RRSIG, LDNS_SECTION_ANSWER)))
	{
		zbx_rsm_warnf(log_fd, "no RRSIG records from %s", ip);
		goto out;
	}

	if (ldns_pkt_querytime(pkt) > reply_ms)
	{
		zbx_rsm_warnf(log_fd, "%s query RTT %d over limit (%d)", ip, ldns_pkt_querytime(pkt), reply_ms);
		goto out;
	}

	/* target succeeded */
	*dns_res = SUCCEED;
out:
	if (NULL != rrset)
		ldns_rr_list_deep_free(rrset);

	if (NULL != pkt)
		ldns_pkt_free(pkt);

	return ret;
}

int	check_rsm_probe_status(DC_ITEM *item, const char *keyname, const char *params, AGENT_RESULT *result)
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

	/* open probestatus log file */
	if (NULL == (log_fd = open_item_log(NULL, ZBX_PROBESTATUS_LOG_PREFIX, err, sizeof(err))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		return SYSINFO_RET_FAIL;
	}

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

	if (SUCCEED != zbx_conf_int(&item->host.hostid, ZBX_MACRO_PROBE_ONLINE_DELAY, &online_delay, 60,
			err, sizeof(err)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, err));
		goto out;
	}

	zbx_rsm_infof(log_fd, "IPv4:%s IPv6:%s", 0 == ipv4_enabled ? "DISABLED" : "ENABLED",
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

		zbx_get_strings_from_list(&ips4, value_str, ',');

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
				zbx_rsm_infof(log_fd, "%d successful results, IPv4 considered working", ok_servers);
				break;
			}
		}

		if (ok_servers != min_servers)
		{
			/* IP protocol check failed */
			zbx_rsm_warnf(log_fd, "status OFFLINE. IPv4 protocol check failed, %d out of %d root servers"
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

		zbx_get_strings_from_list(&ips6, value_str, ',');

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
				zbx_rsm_infof(log_fd, "%d successful results, IPv6 considered working", ok_servers);
				break;
			}
		}

		if (ok_servers != min_servers)
		{
			/* IP protocol check failed */
			zbx_rsm_warnf(log_fd, "status OFFLINE. IPv6 protocol check failed, %d out of %d root servers"
					" replied successfully, minimum required %d",
					ok_servers, ips6.values_num, min_servers);
			status = ZBX_EC_PROBE_OFFLINE;
			goto out;
		}
	}

	status = ZBX_EC_PROBE_ONLINE;
out:
	if (0 != ISSET_MSG(result))
		zbx_rsm_err(log_fd, result->msg);

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
					zbx_rsm_warnf(log_fd, "probe status successful for % seconds, still OFFLINE",
							now - probe_online_since);
					status = ZBX_EC_PROBE_OFFLINE;
				}
				else
				{
					zbx_rsm_warnf(log_fd, "probe status successful for % seconds, changing to ONLINE",
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
		zbx_vector_str_clean_and_destroy(&ips6);

	if (0 != ips4_init)
		zbx_vector_str_clean_and_destroy(&ips4);

	if (NULL != query_rdf)
		ldns_rdf_deep_free(query_rdf);

	return ret;
}
