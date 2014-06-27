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

#include "common.h"

#include "db.h"
#include "log.h"
#include "zbxmedia.h"
#include "zbxserver.h"


#include "../../zabbix_server/vmware/vmware.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#define ZBX_XML_HEADER_CONTENTTYPE		"Content-Type:text/xml; charset=utf-8"
#define	ZBX_XML_HEADER_SOAPACTION_CREATE	"SOAPAction:urn:HPD_Incident_Interface_Create_Monitor_WS/" \
						"HelpDesk_Submit_Service"
#define	ZBX_XML_HEADER_SOAPACTION_QUERY		"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Query_Service"
#define	ZBX_XML_HEADER_SOAPACTION_MODIFY	"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Modify_Service"


#define ZBX_SOAP_URL		"&webService=HPD_IncidentInterface_WS"
#define ZBX_SOAP_URL_CREATE	"&webService=HPD_Incident_Interface_Create_Monitor_WS"

#define ZBX_SOAP_XML_HEADER		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"

#define ZBX_SOAP_ENVELOPE_CREATE_OPEN	"<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""\
					" xmlns:urn=\"urn:HPD_Incident_Interface_Create_Monitor_WS\">"
#define ZBX_SOAP_ENVELOPE_CREATE_CLOSE	"</soapenv:Envelope>"

#define ZBX_SOAP_ENVELOPE_OPEN	"<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""\
					" xmlns:urn=\"urn:HPD_IncidentInterface_WS\">"
#define ZBX_SOAP_ENVELOPE_CLOSE	"</soapenv:Envelope>"


#define ZBX_SOAP_HEADER		"<soapenv:Header>"\
					"<urn:AuthenticationInfo>"\
					"<urn:userName>%s</urn:userName>"\
					"<urn:password>%s</urn:password>"\
					"</urn:AuthenticationInfo>"\
				"</soapenv:Header>"

#define ZBX_SOAP_BODY_OPEN	"<soapenv:Body>"
#define ZBX_SOAP_BODY_CLOSE	"</soapenv:Body>"

#define ZBX_HELPDESK_QUERY_SERVICE_OPEN		"<urn:HelpDesk_Query_Service>"
#define ZBX_HELPDESK_QUERY_SERVICE_CLOSE	"</urn:HelpDesk_Query_Service>"

#define ZBX_HELPDESK_MODIFY_SERVICE_OPEN	"<urn:HelpDesk_Modify_Service>"
#define ZBX_HELPDESK_MODIFY_SERVICE_CLOSE	"</urn:HelpDesk_Modify_Service>"

#define ZBX_REMEDY_FIELD_INCIDENT_NUMBER	"Incident_Number"
#define ZBX_REMEDY_FIELD_STATUS			"Status"
#define ZBX_REMEDY_FIELD_ACTION			"Action"
#define ZBX_REMEDY_FIELD_ASSIGNEE		"Assignee"

#define ZBX_REMEDY_ERROR_INVALID_INCIDENT	"ERROR (302)"

#define ZBX_REMEDY_STATUS_NEW			"New"
#define ZBX_REMEDY_STATUS_ASSIGNED		"Assigned"
#define ZBX_REMEDY_STATUS_RESOLVED		"Resolved"
#define ZBX_REMEDY_STATUS_CLOSED		"Closed"
#define ZBX_REMEDY_STATUS_CANCELLED		"Cancelled"
#define ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY	"Work_Info_Summary"

#define ZBX_REMEDY_ACTION_CREATE	"CREATE"
#define ZBX_REMEDY_ACTION_MODIFY	"MODIFY"

#define ZBX_REMEDY_CI_ID_FIELD		"tag"
#define ZBX_REMEDY_SERVICECLASS_FIELD	"serialno_b"

/* incident status for automatic event acknowledgement */
#define ZBX_REMEDY_ACK_UNKNOWN	0
#define ZBX_REMEDY_ACK_CREATE	1
#define ZBX_REMEDY_ACK_REOPEN	2
#define ZBX_REMEDY_ACK_UPDATE	3
#define ZBX_REMEDY_ACK_NONE	4

/* Service CI values for network and server services */
#define ZBX_REMEDY_SERVICECI_NETWORK		"Networks & Telecomms"
#define ZBX_REMEDY_SERVICECI_RID_NETWORK	"REGAA5V0BLLZRAMO2G4KO1499OT4JQ"
#define ZBX_REMEDY_SERVICECI_SERVER		"Server & Storage"
#define ZBX_REMEDY_SERVICECI_RID_SERVER		"OI-f9bee1dac03044f894ed43937bdc52dc"

/* defines current state of event processing - automated (alerts) or manual (frontend) */
#define ZBX_REMEDY_PROCESS_MANUAL	0
#define ZBX_REMEDY_PROCESS_AUTOMATED	1

extern int	CONFIG_REMEDY_SERVICE_TIMEOUT;

typedef struct
{
	char	*name;
	char	*value;
}
zbx_remedy_field_t;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	return size * nmemb;
}

/******************************************************************************
 *                                                                            *
 * Function: xml_escape_dyn                                                   *
 *                                                                            *
 * Purpose: replace <> symbols in string with &lt;&gt; so the resulting       *
 *          string can be written into xml field                              *
 *                                                                            *
 * Parameters: in     - [IN] the input string                                 *
 *                                                                            *
 * Return value: An allocated string containing escaped input string          *
 *                                                                            *
 * Comments: The caller must free the returned string after it has been used. *
 *                                                                            *
 ******************************************************************************/
static char	*xml_escape_dyn(const char *in)
{
	char		*out, *ptr_out;
	const char	*ptr_in;
	int		size = 0;

	if (NULL == in)
		return zbx_strdup(NULL, "");

	for (ptr_in = in; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
			case '>':
				size += 4;
				break;
			case '&':
				size += 5;
				break;
			case '"':
			case '\'':
				size += 6;
				break;
			default:
				size++;
		}
	}
	size++;

	out = zbx_malloc(NULL, size);

	for (ptr_out = out, ptr_in = in; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
				*ptr_out++ = '&';
				*ptr_out++ = 'l';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '>':
				*ptr_out++ = '&';
				*ptr_out++ = 'g';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '&':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'm';
				*ptr_out++ = 'p';
				*ptr_out++ = ';';
				break;
			case '"':
				*ptr_out++ = '&';
				*ptr_out++ = 'q';
				*ptr_out++ = 'u';
				*ptr_out++ = 'o';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '\'':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'p';
				*ptr_out++ = 'o';
				*ptr_out++ = 's';
				*ptr_out++ = ';';
				break;
			default:
				*ptr_out++ = *ptr_in;
		}

	}
	*ptr_out = '\0';

	return out;
}

/******************************************************************************
 *                                                                            *
 * Function: xml_read_remedy_fields                                           *
 *                                                                            *
 * Purpose: reads the specified list of fields from Remedy Query Service      *
 *          response                                                          *
 *                                                                            *
 * Parameters: data       - [IN] the response data                            *
 *             fields     - [IN/OUT] the array of fields to read              *
 *             fields_num - [IN] the number of items in fields array          *
 *                                                                            *
 * Return value: The number of fields read                                    *
 *                                                                            *
 * Comments: This function allocates the values in fields array which must    *
 *           be freed afterwards with remedy_fields_clean_values() function.  *
 *                                                                            *
 ******************************************************************************/
static int	xml_read_remedy_fields(const char *data, zbx_remedy_field_t *fields, int fields_num)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i, fields_read = 0;

	if (NULL == data)
		goto out;

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	for (i = 0; i < fields_num; i++)
	{
		char	xmlPath[4096];

		zbx_snprintf(xmlPath, sizeof(xmlPath), "//*[local-name()='HelpDesk_Query_ServiceResponse']"
				"/*[local-name()='%s']", fields[i].name);

		zbx_free(fields[i].value);

		if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xmlPath, xpathCtx)))
			continue;

		if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		{
			nodeset = xpathObj->nodesetval;

			if (NULL != (val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
			{
				fields[i].value = zbx_strdup(NULL, (char *)val);
				xmlFree(val);
				fields_read++;
			}
		}
		xmlXPathFreeObject(xpathObj);
	}

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();
out:
	return fields_read;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_clean_values                                       *
 *                                                                            *
 * Purpose: releases field values allocated by xml_read_remedy_fields()       *
 *          function                                                          *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array to clean                *
 *             fields_num - [IN] the number of items in fields array          *
 *                                                                            *
 ******************************************************************************/
static void	remedy_fields_clean_values(zbx_remedy_field_t *fields, int fields_num)
{
	int	i;

	for (i = 0; i < fields_num; i++)
		zbx_free(fields[i].value);
}


/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_set_value                                          *
 *                                                                            *
 * Purpose: sets the specified field value in fields array                    *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array                         *
 *             fields_num - [IN] the number of items in fields array          *
 *             name       - [IN] the field name                               *
 *             value      - [IN] the field value                              *
 *                                                                            *
 ******************************************************************************/
static void	remedy_fields_set_value(zbx_remedy_field_t *fields, int fields_num, const char *name, const char *value)
{
	int	i;

	for (i = 0; i < fields_num; i++)
	{
		if (0 == strcmp(fields[i].name, name))
		{
			if (NULL != value)
			{
				/* zbx_strdup() frees old value if it's not NULL */
				fields[i].value = zbx_strdup(fields[i].value, value);
			}
			else
				zbx_free(fields[i].value);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_get_value                                          *
 *                                                                            *
 * Purpose: gets the specified field value from fields array                  *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array                         *
 *             fields_num - [IN] the number of items in fields array          *
 *             name       - [IN] the field name                               *
 *                                                                            *
 * Return value: the value of requested field or NULL if the field was not    *
 *               found.                                                       *
 *                                                                            *
 ******************************************************************************/
static const char	*remedy_fields_get_value(zbx_remedy_field_t *fields, int fields_num, const char *name)
{
	int	i;

	for (i = 0; i < fields_num; i++)
	{
		if (0 == strcmp(fields[i].name, name))
			return fields[i].value;
	}
	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_init_connection                                           *
 *                                                                            *
 * Purpose: initializes connection to the Remedy service                      *
 *                                                                            *
 * Parameters: easyhandle - [OUT] the CURL easy handle                        *
 *             headers    - [OUT] the CURL headers                            *
 *             url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the connection was initialized successfully        *
 *               FAIL - connection initialization failed, error contains      *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_init_connection(CURL **easyhandle, const struct curl_slist *headers, const char *url,
		const char *proxy, char **error)
{
	int	opt, timeout = CONFIG_REMEDY_SERVICE_TIMEOUT, ret = FAIL, err;

	if (NULL == (*easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "Cannot init cURL library");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_PROXY, proxy)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_create_ticket                                             *
 *                                                                            *
 * Purpose: creates new ticket in Remedy service                              *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             ...        - [IN] various ticket parameters                    *
 *             externalid - [OUT] the number of created incident in Remedy    *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticket creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the incident number and error description.  *
 *                                                                            *
 ******************************************************************************/
static int	remedy_create_ticket(const char *url, const char *proxy, const char *user, const char *password,
		const char *loginid, const char *service_name, const char *service_id, const char *ci,
		const char *ci_id, const char *summary, const char *notes, const char *impact, const char *urgency,
		const char *company, const char *serviceclass, char **externalid, char **error)
{
#	define ZBX_POST_REMEDY_CREATE_SERVICE								\
		ZBX_SOAP_ENVELOPE_CREATE_OPEN								\
		ZBX_SOAP_HEADER										\
		ZBX_SOAP_BODY_OPEN									\
		"<urn:HelpDesk_Submit_Service>"								\
			"<urn:Assigned_Group>Control center</urn:Assigned_Group>"			\
			"<urn:First_Name/>"								\
			"<urn:Impact>%s</urn:Impact>"							\
			"<urn:Last_Name/>"								\
			"<urn:Reported_Source>Systems Management</urn:Reported_Source>"			\
			"<urn:Service_Type>Infrastructure Event</urn:Service_Type>"			\
			"<urn:Status>New</urn:Status>"							\
			"<urn:Action>%s</urn:Action>"							\
			"<urn:Summary>%s</urn:Summary>"							\
			"<urn:Notes>%s</urn:Notes>"							\
			"<urn:Urgency>%s</urn:Urgency>"							\
			"<urn:ServiceCI>%s</urn:ServiceCI>"						\
			"<urn:ServiceCI_ReconID>%s</urn:ServiceCI_ReconID>"				\
			"<urn:HPD_CI>%s</urn:HPD_CI>"							\
			"<urn:HPD_CI_ReconID>%s</urn:HPD_CI_ReconID>"					\
			"<urn:Login_ID>%s</urn:Login_ID>"						\
			"<urn:Customer_Company>%s</urn:Customer_Company>"				\
			"<urn:CSC_INC></urn:CSC_INC>"							\
			"<urn:Service_Class>%s</urn:Service_Class>"					\
		"</urn:HelpDesk_Submit_Service>"							\
		ZBX_SOAP_BODY_CLOSE									\
		ZBX_SOAP_ENVELOPE_CREATE_CLOSE

	const char		*__function_name = "remedy_create_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, err, opt;
	char			*xml = NULL, *summary_esc = NULL, *notes_esc = NULL, *ci_esc = NULL,
				*service_url = NULL, *impact_esc, *urgency_esc, *company_esc, *service_name_esc,
				*service_id_esc, *user_esc = NULL, *password_esc = NULL, *ci_id_esc = NULL,
				*loginid_esc = NULL, *serviceclass_esc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL_CREATE, url);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_CREATE);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);
	summary_esc = xml_escape_dyn(summary);
	notes_esc = xml_escape_dyn(notes);
	ci_esc = xml_escape_dyn(ci);
	ci_id_esc = xml_escape_dyn(ci_id);
	impact_esc = xml_escape_dyn(impact);
	urgency_esc = xml_escape_dyn(urgency);
	service_name_esc = xml_escape_dyn(service_name);
	service_id_esc = xml_escape_dyn(service_id);
	company_esc = xml_escape_dyn(company);
	loginid_esc = xml_escape_dyn(loginid);
	serviceclass_esc = xml_escape_dyn(serviceclass);

	xml = zbx_dsprintf(xml, ZBX_POST_REMEDY_CREATE_SERVICE, user_esc, password_esc, impact_esc,
			ZBX_REMEDY_ACTION_CREATE, summary_esc, notes_esc, urgency_esc, service_name_esc,
			service_id_esc, ci_esc, ci_id_esc, loginid_esc, company_esc, serviceclass_esc);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	if (NULL == (*externalid = zbx_xml_read_value(page.data,
			ZBX_XPATH_LN2("HelpDesk_Submit_ServiceResponse", "Incident_Number"))))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve incident number from Remedy response");
		goto out;
	}

	ret = SUCCEED;
out:
	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(serviceclass_esc);
	zbx_free(loginid_esc);
	zbx_free(company_esc);
	zbx_free(service_id_esc);
	zbx_free(service_name_esc);
	zbx_free(urgency_esc);
	zbx_free(impact_esc);
	zbx_free(ci_id_esc);
	zbx_free(ci_esc);
	zbx_free(notes_esc);
	zbx_free(summary_esc);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? *externalid : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_query_ticket                                              *
 *                                                                            *
 * Purpose: reads the specified list of ticket fields from Remedy service     *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             externalid - [NI] the Remedy ticket id                         *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the request was made successfully                  *
 *               FAIL - the operation failed, error contains                  *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: This function allocates the values in fields array which must    *
 *           be freed afterwards with remedy_fields_clean_values() function.  *
 *                                                                            *
 *           The caller must free the error description if it was set.        *
 *                                                                            *
 *           If the requested incident number was not found the function      *
 *           sill returns SUCCEED, but the Incident_Number field in the       *
 *           fields array will be left NULL. If the incident was found the    *
 *           requested fields will be set from the response except            *
 *           the Incident_Number field, which will be copied from request.    *
 *                                                                            *
 ******************************************************************************/
static int	remedy_query_ticket(const char *url, const char *proxy, const char *user, const char *password,
		const char *externalid, zbx_remedy_field_t *fields, int fields_num, char **error)
{
#	define ZBX_POST_REMEDY_QUERY_SERVICE								\
		ZBX_SOAP_ENVELOPE_OPEN									\
		ZBX_SOAP_HEADER										\
		ZBX_SOAP_BODY_OPEN									\
		ZBX_HELPDESK_QUERY_SERVICE_OPEN								\
			"<urn:Incident_Number>%s</urn:Incident_Number>"					\
		ZBX_HELPDESK_QUERY_SERVICE_CLOSE							\
		ZBX_SOAP_BODY_CLOSE									\
		ZBX_SOAP_ENVELOPE_CLOSE

	const char		*__function_name = "remedy_query_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, opt, err;
	char			*xml = NULL, *service_url = NULL, *user_esc = NULL, *password_esc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() externalid:%s", __function_name, externalid);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL, url);

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_QUERY);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	xml = zbx_dsprintf(xml, ZBX_POST_REMEDY_QUERY_SERVICE, user_esc, password_esc, externalid);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
	{
		if (0 == strncmp(*error, ZBX_REMEDY_ERROR_INVALID_INCIDENT, sizeof(ZBX_REMEDY_ERROR_INVALID_INCIDENT)))
		{
			/* in the case of invalid incident number error we return SUCCEED with NULL */
			/* incident number field value                                              */
			zbx_free(*error);
			ret = SUCCEED;

			goto out;
		}
	}

	xml_read_remedy_fields(page.data, fields, fields_num);

	remedy_fields_set_value(fields, fields_num, ZBX_REMEDY_FIELD_INCIDENT_NUMBER, externalid);

	ret = SUCCEED;
out:
	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? "" : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_modify_ticket                                             *
 *                                                                            *
 * Purpose: modify Remedy service ticket                                      *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticekt creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The Incident_Number field must be set with the number of target  *
 *           ticket.                                                          *
 *                                                                            *
 ******************************************************************************/
static int	remedy_modify_ticket(const char *url, const char *proxy, const char *user, const char *password,
		zbx_remedy_field_t *fields, int fields_num, char **error)
{
	const char		*__function_name = "remedy_modify_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, err, opt, i;
	char			*xml = NULL, *service_url = NULL, *user_esc = NULL, *password_esc = NULL;
	size_t			xml_alloc = 0, xml_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL, url);

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_MODIFY);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	remedy_fields_set_value(fields, fields_num, ZBX_REMEDY_FIELD_ACTION, ZBX_REMEDY_ACTION_MODIFY);

	zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset,
			ZBX_SOAP_ENVELOPE_OPEN
			ZBX_SOAP_HEADER
			ZBX_SOAP_BODY_OPEN
			ZBX_HELPDESK_MODIFY_SERVICE_OPEN,
			user_esc, password_esc);

	for (i = 0; i < fields_num; i++)
	{
		if (NULL != fields[i].value)
		{
			char	*value;

			value = xml_escape_dyn(fields[i].value);

			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s>%s</urn:%s>", fields[i].name, value,
				fields[i].name);

			zbx_free(value);
		}
		else
			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s/>", fields[i].name);
	}

	zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset,
			ZBX_HELPDESK_MODIFY_SERVICE_CLOSE
			ZBX_SOAP_BODY_CLOSE
			ZBX_SOAP_ENVELOPE_CLOSE);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	ret = SUCCEED;
out:
	remedy_fields_clean_values(fields, ARRSIZE(fields));

	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? "" : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_ticketid_by_eventid                                   *
 *                                                                            *
 * Purpose: gets id of the ticket directly linked to the specified event      *
 *                                                                            *
 * Parameters: eventid      - [IN] event id                                   *
 *                                                                            *
 * Return value: the ticket id or NULL if no ticket was directly linked to    *
 *               the specified event                                          *
 *                                                                            *
 * Comments: The returned ticked id must be freed later by the caller.        *
 *                                                                            *
 ******************************************************************************/
static char	*remedy_get_ticketid_by_eventid(zbx_uint64_t eventid)
{
	const char	*__function_name = "remedy_get_ticket_by_eventid";
	DB_RESULT	result;
	DB_ROW		row;
	char		*ticketid = NULL;

	/* first check if the event is linked to an incident */
	result = DBselect("select externalid from ticket"
			" where eventid=" ZBX_FS_UI64
			" order by clock,ticketid desc", eventid);

	if (NULL != (row = DBfetch(result)))
		ticketid = zbx_strdup(NULL, row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, (NULL != ticketid ? ticketid : "FAIL"));

	return ticketid;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_ticketid_by_triggerid                                 *
 *                                                                            *
 * Purpose: gets id of the last ticket linked to an event generated by the    *
 *          specified trigger                                                 *
 *                                                                            *
 * Parameters: triggerid      - [IN] trigger id                               *
 *                                                                            *
 * Return value: the ticket id or NULL if no ticket was found                 *
 *                                                                            *
 * Comments: The returned ticked id must be freed later by the caller.        *
 *                                                                            *
 ******************************************************************************/
static char	*remedy_get_ticketid_by_triggerid(zbx_uint64_t triggerid)
{
	const char	*__function_name = "remedy_get_ticket_by_triggerid";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *ticketid = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() " ZBX_FS_UI64, __function_name, triggerid);

	/* find the latest ticket id which was created for the specified trigger */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select externalid,clock from ticket"
				" where triggerid=" ZBX_FS_UI64
				" order by clock,ticketid desc",
				triggerid);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
		ticketid = zbx_strdup(NULL, row[0]);

	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, (NULL != ticketid ? ticketid : "FAIL"));

	return ticketid;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_service_by_host                                       *
 *                                                                            *
 * Purpose: gets remedy service linked to the specified host                  *
 *                                                                            *
 * Parameters: hostid       - [IN] the host id                                *
 *             group_name   - [IN] the name of value mapping containing       *
 *                               mapping of host group names to Remedy        *
 *                               names                                        *
 *             service_name - [OUT] the corresponding service name            *
 *             service_id   - [OUT] the corresponding service reconciliation  *
 *                                  id                                        *
 *                                                                            *
 * Comments: The Service CI is linked to the hosts with a help of host groups.*
 *           All hosts in the group defined in Remedy media configuration     *
 *           (service mapping) are linked to Network & Telecoms Service CI,   *
 *           while the rest of hosts are linked to Server & Storage Service   *
 *           CI.                                                              *
 *                                                                            *
 ******************************************************************************/
static void	remedy_get_service_by_host(zbx_uint64_t hostid, const char *group_name, char **service_name,
		char **service_id)
{
	const char	*__function_name = "remedy_get_service_by_host";
	char		*group_name_esc;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s", __function_name, group_name);

	group_name_esc = DBdyn_escape_string(group_name);

	result = DBselect(
			"select g.name"
			" from groups g,hosts_groups hg"
			" where hg.hostid=" ZBX_FS_UI64
				" and g.groupid=hg.groupid"
				" and g.name='%s'",
			hostid, group_name_esc);

	/* If the host group name matches the specified service mapping value from remedy configuration */
	/* use predefined network service CI. Otherwise use predefined server service CI.               */
	if (NULL != (row = DBfetch(result)))
	{
		*service_name = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_NETWORK);
		*service_id = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_RID_NETWORK);
	}
	else
	{
		*service_name = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_SERVER);
		*service_id = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_RID_SERVER);
	}

	DBfree_result(result);

	zbx_free(group_name_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s (%s)", __function_name, *service_name, *service_id);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_acknowledge_event                                         *
 *                                                                            *
 * Purpose: acknowledges event with appropriate message                       *
 *                                                                            *
 * Parameters: eventid       - [IN] the event to acknowledge                  *
 *             userid        - [IN] the user the alert is assigned to         *
 *             ticketnumber  - [IN] the number of corresponding incident      *
 *             status        - [IN] the incident status, see                  *
 *                                  ZBX_REMEDY_ACK_* defines                  *
 *                                                                            *
 * Return value: SUCCEED - the event was acknowledged                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	remedy_acknowledge_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *ticketnumber,
		int status)
{
	const char	*__function_name = "remedy_acknowledge_event";

	int		ret = FAIL;
	char		*sql = NULL, *message, *message_esc;
	size_t		sql_offset = 0, sql_alloc = 0;
	zbx_uint64_t	ackid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (status)
	{
		case ZBX_REMEDY_ACK_CREATE:
			message = zbx_dsprintf(NULL, "Created a new incident %s", ticketnumber);
			break;
		case ZBX_REMEDY_ACK_REOPEN:
			message = zbx_dsprintf(NULL, "Reopened resolved incident %s", ticketnumber);
			break;
		case ZBX_REMEDY_ACK_UPDATE:
			message = zbx_dsprintf(NULL, "Updated new or assigned incident %s", ticketnumber);
			break;
		default:
			goto out;
	}

	ackid = DBget_maxid("acknowledges");
	message_esc = DBdyn_escape_string(message);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "insert into acknowledges"
			" (acknowledgeid,userid,eventid,clock,message) values"
			" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s');\n",
			ackid, userid, eventid, time(NULL), message_esc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update events set acknowledged=1"
			" where eventid=" ZBX_FS_UI64, eventid);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);
	zbx_free(message_esc);
	zbx_free(message);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_register_ticket                                           *
 *                                                                            *
 * Purpose: registers external ticket to zabbix event                         *
 *                                                                            *
 * Parameters: ticketnumber   - [IN] the ticket number                        *
 *             eventid        - [IN] the linked event id                      *
 *             triggerid      - [IN] the event trigger id                     *
 *                                                                            *
 ******************************************************************************/
static void	remedy_register_ticket(const char *ticketnumber, zbx_uint64_t eventid, zbx_uint64_t triggerid,
		int is_new)
{
	zbx_uint64_t	ticketid;
	char 		*ticketnumber_esc;

	ticketid = DBget_maxid_num("ticket", 1);
	ticketnumber_esc = DBdyn_escape_string(ticketnumber);

	DBexecute("insert into ticket (ticketid,externalid,eventid,triggerid,clock,new) values"
					" (" ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)",
					ticketid, ticketnumber_esc, eventid, triggerid, time(NULL), is_new);
	zbx_free(ticketnumber_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_clean_mediatype                                           *
 *                                                                            *
 * Purpose: releases resource allocated to store mediatype properties         *
 *                                                                            *
 * Parameters: media   - [IN] the mediatype data                              *
 *                                                                            *
 ******************************************************************************/
static void	remedy_clean_mediatype(DB_MEDIATYPE *media)
{
	zbx_free(media->description);
	zbx_free(media->exec_path);
	zbx_free(media->gsm_modem);
	zbx_free(media->smtp_server);
	zbx_free(media->smtp_helo);
	zbx_free(media->smtp_email);
	zbx_free(media->username);
	zbx_free(media->passwd);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_mediatype                                             *
 *                                                                            *
 * Purpose: reads the first active remedy media type from database            *
 *                                                                            *
 * Parameters: media   - [IN] the mediatype data                              *
 *                                                                            *
 * Return value: SUCCEED - the media type was read successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: This function allocates memory to store mediatype properties     *
 *           which must be freed later with remedy_clean_mediatype() function.*
 *                                                                            *
 ******************************************************************************/
static int	remedy_get_mediatype(DB_MEDIATYPE *media)
{
	const char	*__function_name = "remedy_get_mediatype";
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select smtp_server,smtp_helo,smtp_email,username,passwd,mediatypeid,exec_path"
				" from media_type"
				" where type=%d and status=%d",
				MEDIA_TYPE_REMEDY, MEDIA_TYPE_STATUS_ACTIVE);

	if (NULL != (row = DBfetch(result)))
	{
		media->description = NULL;
		media->gsm_modem = NULL;
		media->smtp_server = zbx_strdup(media->smtp_server, row[0]);
		media->smtp_helo = zbx_strdup(media->smtp_helo, row[1]);
		media->smtp_email = zbx_strdup(media->smtp_email, row[2]);
		media->username = zbx_strdup(media->username, row[3]);
		media->passwd = zbx_strdup(media->passwd, row[4]);
		ZBX_STR2UINT64(media->mediatypeid, row[5]);
		media->exec_path = zbx_strdup(media->exec_path, row[6]);

		ret = SUCCEED;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_ticket_creation_time                                  *
 *                                                                            *
 * Purpose: retrieves the creation time of the specified ticket               *
 *                                                                            *
 * Parameters: externalid    - [IN] the ticket external id                    *
 *                                                                            *
 * Return value: the ticket creation time in seconds or 0 if the ticket was   *
 *               not found                                                    *
 *                                                                            *
 ******************************************************************************/
static int	remedy_get_ticket_creation_time(const char *externalid)
{
	int		clock = 0;
	DB_RESULT	result;
	DB_ROW		row;
	char		*incident_number;

	incident_number = DBdyn_escape_string(externalid);

	/* read the incident creation time */
	result = DBselect("select clock from ticket where externalid='%s' and new=1",
			incident_number);

	zbx_free(incident_number);

	if (NULL != (row = DBfetch(result)))
		clock = atoi(row[0]);

	DBfree_result(result);

	return clock;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_last_ticketid                                         *
 *                                                                            *
 * Purpose: retrieves either the ticket directly linked to the specified      *
 *          event or the last ticket created in response to the event         *
 *          source trigger                                                    *
 *                                                                            *
 * Parameters: eventid         - [IN] the event                               *
 *             incident_number - [OUT] the linked incident number             *
 *                                                                            *
 * Return value: SUCCEED - the incident was retrieved successfully            *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: This function allocates memory to store incident number          *
 *           which must be freed later.                                       *
 *                                                                            *
 ******************************************************************************/
static int	remedy_get_last_ticketid(zbx_uint64_t eventid, char **externalid)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == (*externalid = remedy_get_ticketid_by_eventid(eventid)))
	{
		zbx_uint64_t	triggerid;

		/* get the event source trigger id */
		result = DBselect("select objectid from events"
				" where source=%d"
					" and object=%d"
					" and eventid=" ZBX_FS_UI64,
					EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, eventid);

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(triggerid, row[0]);

			*externalid = remedy_get_ticketid_by_triggerid(triggerid);
		}

		DBfree_result(result);
	}

	return NULL != *externalid ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_process_event                                             *
 *                                                                            *
 * Purpose: processes event by either creating, reopening or just updating    *
 *          an incident in Remedy service                                     *
 *                                                                            *
 * Parameters: eventid    - [IN] the event to process                         *
 *             userid     - [IN] the user processing the event                *
 *             loginid    - [IN] the Remedy loginid field (Customer)          *
 *             subject    - [IN] the message subject                          *
 *             message    - [IN] the message contents                         *
 *             media      - [IN] the media object containing Remedy service   *
 *                               and ticket information                       *
 *             state      - [IN] the processing state automatic/manual -      *
 *                               (ZBX_REMEDY_PROCESS_*).                      *
 *                               During manual processing events aren't       *
 *                               acknowledged and the message is used instead *
 *                               of subject when updating incident.           *
 *             ticket     - [OUT] the updated/created ticket data (optional)  *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the alert was processed successfully               *
 *               FAIL - alert processing failed, error contains               *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_process_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *loginid, const char *subject,
		const char *message, const DB_MEDIATYPE *media, int state, zbx_ticket_t *ticket, char **error)
{
#define ZBX_EVENT_REMEDY_WARNING	0
#define ZBX_EVENT_REMEDY_CRITICAL	1

#define ZBX_REMEDY_DEFAULT_SERVICECI	""

/* the number of fields at the end of fields array used only to query data */
/* and should not be passed to modify function                             */
#define ZBX_REMEDY_QUERY_FIELDS		1

	const char	*__function_name = "remedy_process_event";
	int		ret = FAIL, acknowledge_status = ZBX_REMEDY_ACK_UNKNOWN, event_value, trigger_severity,
			is_registered = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, hostid;
	const char	*status;
	char		*incident_number = NULL, *incident_status = NULL, *trigger_expression = NULL;

	zbx_remedy_field_t	fields[] = {
			{"Categorization_Tier_1", NULL},
			{"Categorization_Tier_2", NULL},
			{"Categorization_Tier_3", NULL},
			{"Closure_Manufacturer", NULL},
			{"Closure_Product_Category_Tier1", NULL},
			{"Closure_Product_Category_Tier2", NULL},
			{"Closure_Product_Category_Tier3", NULL},
			{"Closure_Product_Model_Version", NULL},
			{"Closure_Product_Name", NULL},
			{"Company", NULL},
			{"Summary", NULL},
			{"Notes", NULL},
			{"Impact", NULL},
			{"Manufacturer", NULL},
			{"Product_Categorization_Tier_1", NULL},
			{"Product_Categorization_Tier_2", NULL},
			{"Product_Categorization_Tier_3", NULL},
			{"Product_Model_Version", NULL},
			{"Product_Name", NULL},
			{"Reported_Source", NULL},
			{"Resolution", NULL},
			{"Resolution_Category", NULL},
			{"Resolution_Category_Tier_2", NULL},
			{"Resolution_Category_Tier_3", NULL},
			{"Resolution_Method", NULL},
			{"Service_Type", NULL},
			{ZBX_REMEDY_FIELD_STATUS, NULL},
			{"Urgency", NULL},
			{ZBX_REMEDY_FIELD_ACTION, NULL},
			{"Work_Info_Summary", NULL},
			{"Work_Info_Notes", NULL},
			{"Work_Info_Type", NULL},
			{"Work_Info_Date", NULL},
			{"Work_Info_Source", NULL},
			{"Work_Info_Locked", NULL},
			{"Work_Info_View_Access", NULL},
			{ZBX_REMEDY_FIELD_INCIDENT_NUMBER, NULL},
			{"Status_Reason", NULL},
			{"ServiceCI", NULL},
			{"ServiceCI_ReconID", NULL},
			{"HPD_CI", NULL},
			{"HPD_CI_ReconID", NULL},
			{"HPD_CI_FormName", NULL},
			{"z1D_CI_FormName", NULL},
			{"WorkInfoAttachment1Name", NULL},
			{"WorkInfoAttachment1Data", NULL},
			{"WorkInfoAttachment1OrigSize", NULL},
			{ZBX_REMEDY_FIELD_ASSIGNEE, NULL},
	};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select e.value,t.priority,t.triggerid,t.expression from events e,triggers t"
				" where e.eventid=" ZBX_FS_UI64
					" and e.source=%d"
					" and e.object=%d"
					" and t.triggerid=e.objectid",
				eventid, EVENT_SOURCE_TRIGGERS,  EVENT_OBJECT_TRIGGER);

	if (NULL == (row = DBfetch(result)))
		goto out;

	event_value = atoi(row[0]);
	trigger_severity = atoi(row[1]);
	ZBX_STR2UINT64(triggerid, row[2]);
	trigger_expression = zbx_strdup(NULL, row[3]);

	/* get a ticket directly linked to the event or the latest linked to event generated by the same trigger */
	if (NULL == (incident_number = remedy_get_ticketid_by_eventid(eventid)))
		incident_number = remedy_get_ticketid_by_triggerid(triggerid);
	else
		is_registered = 1;

	if (NULL != incident_number && SUCCEED != remedy_query_ticket(media->smtp_server, media->smtp_helo,
			media->username, media->passwd, incident_number, fields, ARRSIZE(fields), error))
	{
		goto out;
	}

	if (TRIGGER_VALUE_OK != event_value)
	{
		char		*service_name = NULL, *service_id = NULL, *severity_name = NULL;
		int		remedy_event;
		char		*impact_map[] = {"3-Moderate/Limited", "2-Significant/Large"};
		char		*urgency_map[] = {"3-Medium", "2-High"};
		zbx_uint64_t	functionid;

		if (NULL != incident_number)
		{
			if (NULL == (status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS)))
			{
				*error = zbx_dsprintf(*error, "Incident %s query did no return status field",
						incident_number);
				goto out;
			}

			/* check if the ticket should be reopened */
			if (0 == strcmp(status, ZBX_REMEDY_STATUS_RESOLVED))
			{
				acknowledge_status = ZBX_REMEDY_ACK_REOPEN;
				incident_status = zbx_strdup(NULL, ZBX_REMEDY_STATUS_ASSIGNED);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS,
						ZBX_REMEDY_STATUS_ASSIGNED);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						ZBX_REMEDY_PROCESS_AUTOMATED == state ? subject : message);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);

				goto out;
			}

			/* if ticket is still being worked on, add work info */
			if (0 != strcmp(status, ZBX_REMEDY_STATUS_CLOSED) &&
					0 != strcmp(status, ZBX_REMEDY_STATUS_CANCELLED))
			{
				acknowledge_status = ZBX_REMEDY_ACK_UPDATE;
				incident_status = zbx_strdup(NULL, status);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						ZBX_REMEDY_PROCESS_AUTOMATED == state ? subject : message);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);

				goto out;
			}
		}

		/* create a new ticket */

		if (SUCCEED != get_N_functionid(trigger_expression, 1, &functionid, NULL))
		{
			*error = zbx_strdup(*error, "Failed to extract function id from the trigger expression");
			goto out;
		}

		DBfree_result(result);

		/* find the host */
		result = DBselect("select h.host,h.hostid,hi." ZBX_REMEDY_CI_ID_FIELD "," ZBX_REMEDY_SERVICECLASS_FIELD
				" from items i,functions f,hosts h left join host_inventory hi"
					" on hi.hostid=h.hostid"
				" where f.functionid=" ZBX_FS_UI64
					" and f.itemid=i.itemid"
					" and i.hostid=h.hostid",
				functionid);

		if (NULL == (row = DBfetch(result)))
		{
			*error = zbx_strdup(*error, "Failed find host of the trigger expression");
			goto out;
		}

		if (SUCCEED == DBis_null(row[2]))
		{
			*error = zbx_dsprintf(NULL, "Host inventory is not enabled for the host '%s'", row[0]);
			goto out;
		}

		if ('\0' == *row[2])
		{
			*error = zbx_dsprintf(NULL, "Host '%s' inventory Recon ID field (" ZBX_REMEDY_CI_ID_FIELD
					") is not set", row[0]);
			goto out;
		}

		if ('\0' == *row[3])
		{
			*error = zbx_dsprintf(NULL, "Host '%s' inventory Service Class field ("
					ZBX_REMEDY_SERVICECLASS_FIELD ") is not set", row[0]);
			goto out;
		}

		/* map trigger severity */
		switch (trigger_severity)
		{
			case TRIGGER_SEVERITY_WARNING:
				remedy_event = ZBX_EVENT_REMEDY_WARNING;
				break;
			case TRIGGER_SEVERITY_AVERAGE:
			case TRIGGER_SEVERITY_HIGH:
			case TRIGGER_SEVERITY_DISASTER:
				remedy_event = ZBX_EVENT_REMEDY_CRITICAL;
				break;
			default:
				if (SUCCEED != DCget_trigger_severity_name(trigger_severity, &severity_name))
					severity_name = zbx_dsprintf(severity_name, "[%d]", trigger_severity);

				*error = zbx_dsprintf(*error, "Unsupported trigger severity: %s", severity_name);
				zbx_free(severity_name);

				goto out;
		}

		ZBX_STR2UINT64(hostid, row[1]);

		remedy_get_service_by_host(hostid, media->smtp_email, &service_name, &service_id);

		zbx_free(incident_number);

		acknowledge_status = ZBX_REMEDY_ACK_CREATE;
		incident_status = zbx_strdup(NULL, ZBX_REMEDY_STATUS_NEW);

		ret = remedy_create_ticket(media->smtp_server, media->smtp_helo, media->username, media->passwd,
				loginid, service_name, service_id, row[0], row[2], subject, message,
				impact_map[remedy_event], urgency_map[remedy_event], media->exec_path, row[3],
				&incident_number, error);

		zbx_free(service_name);
		zbx_free(service_id);
	}
	else
	{
		if (NULL == incident_number)
		{
			/* trigger without an associated ticket was switched to OK state */
			ret = SUCCEED;
			goto out;
		}

		incident_status = zbx_strdup(NULL, remedy_fields_get_value(fields, ARRSIZE(fields),
				ZBX_REMEDY_FIELD_STATUS));

		if (0 == strcmp(incident_status, ZBX_REMEDY_STATUS_RESOLVED) ||
				0 == strcmp(incident_status, ZBX_REMEDY_STATUS_CLOSED) ||
				0 == strcmp(incident_status, ZBX_REMEDY_STATUS_CANCELLED))
		{
			/* don't update already resolved, closed or canceled incidents */
			ret = SUCCEED;
			goto out;
		}

		acknowledge_status = ZBX_REMEDY_ACK_NONE;

		remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
				ZBX_REMEDY_PROCESS_AUTOMATED == state ? subject : message);

		ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username, media->passwd, fields,
				ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);
	}
out:
	DBfree_result(result);

	if (SUCCEED == ret)
	{
		int	is_new;

		is_new = ZBX_REMEDY_ACK_CREATE == acknowledge_status ? 1 : 0;

		if (ZBX_REMEDY_ACK_UNKNOWN != acknowledge_status)
		{
			DBbegin();

			if (state == ZBX_REMEDY_PROCESS_AUTOMATED && ZBX_REMEDY_ACK_NONE != acknowledge_status)
				remedy_acknowledge_event(eventid, userid, incident_number, acknowledge_status);

			if (0 == is_registered || 1 == is_new)
				remedy_register_ticket(incident_number, eventid, triggerid, is_new);

			DBcommit();
		}

		if (NULL != ticket)
		{
			const char	*assignee;

			ticket->eventid = eventid;
			ticket->is_new = is_new;
			if (NULL != incident_status)
				ticket->status = zbx_strdup(NULL, incident_status);
			if (NULL != incident_number)
				ticket->ticketid = zbx_strdup(NULL, incident_number);

			assignee = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_ASSIGNEE);
			if (NULL != assignee)
				ticket->assignee = zbx_strdup(NULL, assignee);
		}
	}

	zbx_free(incident_number);
	zbx_free(incident_status);
	zbx_free(trigger_expression);

	remedy_fields_clean_values(fields, ARRSIZE(fields));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_process_alert                                         *
 *                                                                            *
 * Purpose: processes an alert by either creating, reopening or just updating *
 *          an incident in Remedy service                                     *
 *                                                                            *
 * Parameters: alert      - [IN] the alert to process                         *
 *             media      - [IN] the media object containing Remedy service   *
 *                               and ticket information                       *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the alert was processed successfully               *
 *               FAIL - alert processing failed, error contains               *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_remedy_process_alert(const DB_ALERT *alert, const DB_MEDIATYPE *mediatype, char **error)
{
	const char	*__function_name = "zbx_remedy_process_alert";

	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = remedy_process_event(alert->eventid, alert->userid, alert->sendto, alert->subject,
		alert->message, mediatype, ZBX_REMEDY_PROCESS_AUTOMATED, NULL, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_query_events                                          *
 *                                                                            *
 * Purpose: retrieves status of Remedy incidents associated to the specified  *
 *          events                                                            *
 *                                                                            *
 * Parameters: eventids   - [IN] the events to query                          *
 *             tickets    - [OUT] the incident data                           *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the operation was completed successfully.          *
 *                         Per event query status can be determined by        *
 *                         inspecting ticketids contents.                     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_remedy_query_events(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets, char **error)
{
	const char		*__function_name = "zbx_remedy_query_events";

	int			ret = FAIL, i;
	DB_MEDIATYPE		mediatype = {0};

	zbx_remedy_field_t	fields[] = {
			{ZBX_REMEDY_FIELD_STATUS, NULL},
			{ZBX_REMEDY_FIELD_ASSIGNEE, NULL}
	};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != remedy_get_mediatype(&mediatype))
	{
		*error = zbx_dsprintf(*error, "Failed to find apropriate media type");
		goto out;
	}

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_ticket_t	*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));

		ticket->eventid = eventids->values[i];

		if (SUCCEED == remedy_get_last_ticketid(ticket->eventid, &ticket->ticketid) &&
				SUCCEED == remedy_query_ticket(mediatype.smtp_server, mediatype.smtp_helo,
				mediatype.username, mediatype.passwd, ticket->ticketid, fields, ARRSIZE(fields),
				&ticket->error))
		{
			const char *status, *assignee;

			status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS);
			assignee = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_ASSIGNEE);

			if (NULL != status)
				ticket->status = zbx_strdup(NULL, status);

			if (NULL != assignee)
				ticket->assignee = zbx_strdup(NULL, assignee);

			ticket->clock = remedy_get_ticket_creation_time(ticket->ticketid);
		}

		zbx_vector_ptr_append(tickets, ticket);
	}

	ret = SUCCEED;

	remedy_clean_mediatype(&mediatype);
	remedy_fields_clean_values(fields, ARRSIZE(fields));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_acknowledge_events                                    *
 *                                                                            *
 * Purpose: acknowledges events in Remedy service with specified message      *
 *          subjects and contents                                             *
 *                                                                            *
 * Parameters: userid        - [IN] the user acknowledging events             *
 *             acknowledges  - [IN] the event acknowledgment data             *
 *             tickets       - [OUT] the incident data                        *
 *             error         - [OUT] the error description                    *
 *                                                                            *
 * Return value: SUCCEED - the events were acknowledged successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_remedy_acknowledge_events(zbx_uint64_t userid, zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets,
		char **error)
{
	const char	*__function_name = "zbx_remedy_acknowledge_events";

	int		i, ret = FAIL;
	DB_MEDIATYPE	mediatype = {0};
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != remedy_get_mediatype(&mediatype))
	{
		*error = zbx_dsprintf(*error, "Failed to find apropriate media type");
		goto out;
	}

	result = DBselect("select sendto from media"
			" where mediatypeid=" ZBX_FS_UI64
				" and userid=" ZBX_FS_UI64,
				mediatype.mediatypeid, userid);

	if (NULL == (row = DBfetch(result)))
	{
		*error = zbx_dsprintf(*error, "Failed to find apropriate media type for current user");
		DBfree_result(result);
		goto out;
	}

	for (i = 0; i < acknowledges->values_num; i++)
	{
		zbx_acknowledge_t	*ack = acknowledges->values[i];
		zbx_ticket_t		*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));

		ticket->eventid = ack->eventid;

		if (SUCCEED == remedy_process_event(ack->eventid, userid, row[0], ack->subject, ack->message,
				&mediatype, ZBX_REMEDY_PROCESS_MANUAL, ticket, &ticket->error))
		{
			ticket->clock = remedy_get_ticket_creation_time(ticket->ticketid);
		}

		zbx_vector_ptr_append(tickets, ticket);
	}

	DBfree_result(result);

	ret = SUCCEED;
out:
	remedy_clean_mediatype(&mediatype);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_ticket                                                  *
 *                                                                            *
 * Purpose: frees the ticket data                                             *
 *                                                                            *
 * Parameters: ticket   - [IN] the ticket to free                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_ticket(zbx_ticket_t *ticket)
{
	zbx_free(ticket->ticketid);
	zbx_free(ticket->status);
	zbx_free(ticket->error);
	zbx_free(ticket->assignee);
	zbx_free(ticket);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_acknowledge                                             *
 *                                                                            *
 * Purpose: frees the acknowledgment data                                     *
 *                                                                            *
 * Parameters: ack   - [IN] the acknowledgment to free                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_acknowledge(zbx_acknowledge_t *ack)
{
	zbx_free(ack->subject);
	zbx_free(ack->message);
	zbx_free(ack);
}

#else

int	zbx_remedy_process_alert(const DB_ALERT *alert, const DB_MEDIATYPE *mediatype, char **error)
{
	*error = zbx_dsprintf(*error, "Zabbix server is built without Remedy ticket support");
	return FAIL;
}

int	zbx_remedy_query_events(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets, char **error)
{
	*error = zbx_dsprintf(*error, "Zabbix server is built without Remedy ticket support");
	return FAIL;
}

int	zbx_remedy_acknowledge_events(zbx_uint64_t userid, zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets,
		char **error)
{
	*error = zbx_dsprintf(*error, "Zabbix server is built without Remedy ticket support");
	return FAIL;
}

void	zbx_free_ticket(zbx_ticket_t *ticket)
{
}

void	zbx_free_acknowledge(zbx_acknowledge_t *ack)
{
}

#endif
