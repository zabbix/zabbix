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


#include "../../zabbix_server/vmware/vmware.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#define ZBX_XML_HEADER_CONTENTTYPE		"Content-Type:text/xml; charset=utf-8"
#define	ZBX_XML_HEADER_SOAPACTION_CREATE	"SOAPAction:urn:HPD_IncidentInterface_Create_WS/HelpDesk_Submit_Service"
#define	ZBX_XML_HEADER_SOAPACTION_QUERY		"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Query_Service"
#define	ZBX_XML_HEADER_SOAPACTION_MODIFY	"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Modify_Service"


#define ZBX_SOAP_URL		"&webService=HPD_IncidentInterface_WS"
#define ZBX_SOAP_URL_CREATE	"&webService=HPD_IncidentInterface_Create_WS"

#define ZBX_SOAP_XML_HEADER		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"

#define ZBX_SOAP_ENVELOPE_CREATE_OPEN	"<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""\
					" xmlns:urn=\"urn:HPD_IncidentInterface_Create_WS\">"
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
 * Return Value: An allocated string containing escaped input string          *
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
				*ptr_out++ = 'o';
				*ptr_out++ = 'u';
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
 *             headers    - [OUT] the CURL headers                            *
 *             fields     - [IN/OUT] the array of fields to read              *
 *             fields_num - [IN] the number of items in fields array          *
 *                                                                            *
 * Return Value: The number of fields read                                    *
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
			/* zbx_strdup() frees old value if it's not NULL */
			if (NULL == value)
				zbx_free(fields[i].value);
			else
				fields[i].value = zbx_strdup(fields[i].value, value);
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
 * Return Value: SUCCEED - the connection was initialized successfully        *
 *               FAIL - connection initialization failed, error contains      *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_init_connection(CURL **easyhandle, const struct curl_slist *headers, const char *url,
		const char *proxy, char **error)
{
	int			opt, timeout = 10, ret = FAIL, err;

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

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, CURLOPT_PROXY, proxy)) ||
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
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *                               disable proxy                                *
 *             ...        - [IN] various ticket parameters                    *
 *             externalid - [OUT] the number of created incident in Remedy    *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return Value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticekt creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_create_ticket(const char *url, const char *proxy, const char *user, const char *password,
		const char *loginid, const char *service_name, const char *service_id, const char *ci,
		const char *ci_id, const char *summary, const char *notes, const char *impact, const char *urgency,
		const char *company, char **externalid, char **error)
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
		"</urn:HelpDesk_Submit_Service>"							\
		ZBX_SOAP_BODY_CLOSE									\
		ZBX_SOAP_ENVELOPE_CREATE_CLOSE

	const char		*__function_name = "remedy_create_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, err, opt;
	char			*xml = NULL, *summary_esc = NULL, *notes_esc = NULL, *ci_esc = NULL,
				*service_url = NULL, *impact_esc, *urgency_esc, *company_esc, *service_name_esc,
				*service_id_esc, *user_esc = NULL, *password_esc = NULL, *ci_id_esc = NULL;

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

	xml = zbx_dsprintf(xml, ZBX_POST_REMEDY_CREATE_SERVICE, user_esc, password_esc, impact_esc,
			ZBX_REMEDY_ACTION_CREATE, summary_esc, notes_esc, urgency_esc, service_name_esc,
			service_id_esc, ci_esc, ci_id_esc, loginid, company_esc);

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
	zbx_free(impact_esc);
	zbx_free(urgency_esc);
	zbx_free(service_id_esc);
	zbx_free(service_name_esc);
	zbx_free(company_esc);
	zbx_free(ci_id_esc);
	zbx_free(ci_esc);
	zbx_free(notes_esc);
	zbx_free(summary_esc);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s externalid:%s", __function_name, zbx_result_string(ret),
			*externalid);

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
 *             ticketid   - [NI] the Remedy ticket id                         *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return Value: SUCCEED - the request was made successfully                  *
 *               FAIL - the operation failed, error contains                  *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: This function allocates the values in fields array which must    *
 *           be freed afterwards with remedy_fields_clean_values() function.  *
 *                                                                            *
 *           The caller must free the error description if it was set.        *
 *                                                                            *
 *           If the requested incident number was not found the function      *
 *           sill returns SUCCESS, but the Incident_Number field in the       *
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_modify_ticket                                             *
 *                                                                            *
 * Purpose: modify Remedy service ticket                                      *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return Value: SUCCEED - the ticket was created successfully                *
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
			ZBX_SOAP_ENVELOPE_OPEN							\
			ZBX_SOAP_HEADER 							\
			ZBX_SOAP_BODY_OPEN							\
			ZBX_HELPDESK_MODIFY_SERVICE_OPEN,
			user_esc, password_esc);

	for (i = 0; i < fields_num; i++)
	{
		if (NULL != fields[i].value)
		{
			char	*value = NULL;

			value = xml_escape_dyn(fields[i].value);
			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s>%s</urn:%s>", fields[i].name, value,
				fields[i].name);

			zbx_free(value);
		}
		else
		{
			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s/>", fields[i].name);
		}
	}

	zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset,
			ZBX_HELPDESK_MODIFY_SERVICE_CLOSE					\
			ZBX_SOAP_BODY_CLOSE							\
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

	zabbix_log(LOG_LEVEL_DEBUG, "[WDN] modify: %s", xml);
	zabbix_log(LOG_LEVEL_DEBUG, "[WDN] response: %s", page.data);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_read_ticket                                               *
 *                                                                            *
 * Purpose: reads a Remedy service ticket                                     *
 *                                                                            *
 * Parameters: triggerid  - [IN] the trigger that generated event             *
 *             url        - [IN] the Remedy service URL                       *
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
 * Return Value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticekt creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_read_ticket(zbx_uint64_t triggerid, const char *url, const char *proxy, const char *user,
		const char *password, zbx_remedy_field_t *fields, int fields_num, char **error)
{
	const char	*__function_name = "remedy_read_ticket";
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* find the latest ticket id which was created for the specified trigger */
	result = DBselect("select t.externalid,t.clock from ticket t,events e"
				" where e.source=%d"
					" and e.object=%d"
					" and e.objectid=" ZBX_FS_UI64
					" and e.eventid=t.eventid"
				" order by t.clock desc",
				EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, triggerid);

	if (NULL != (row = DBfetch(result)))
		ret = remedy_query_ticket(url, proxy, user, password, row[0], fields, fields_num, error);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_service_by_host                                       *
 *                                                                            *
 * Purpose: gets remedy service associated to the specified host              *
 *                                                                            *
 * Parameters: hostid       - [IN] the host id                                *
 *             valuemap     - [IN] the name of value mapping containing       *
 *                               mapping of host group names to Remedy        *
 *                               names                                        *
 *             service_name - [OUT] the corresponding service name            *
 *             service_id   - [OUT] the corresponding service  reconciliation *
 *                                  id                                        *
 *                                                                            *
 * Comments: The Zabbix host association with corresponding Remedy service is *
 *           done through a specific value mapping that maps Zabbix host      *
 *           group names to respective Remedy services in format:             *
 *               <group name> => <service name>:<service reconciliation id>   *
 *           The name of this mapping must be stored in Remedy media type.    *
 *                                                                            *
 ******************************************************************************/
static void	remedy_get_service_by_host(zbx_uint64_t hostid, const char *valuemap, char **service_name,
		char **service_id)
{
	char		*valuemap_esc;
	DB_RESULT	result;
	DB_ROW		row;

	valuemap_esc = DBdyn_escape_string(valuemap);

	result = DBselect(
			"select m.newvalue"
			" from mappings m,valuemaps vm,groups g,hosts_groups hg"
			" where hg.hostid=" ZBX_FS_UI64
				" and g.groupid=hg.groupid"
				" and g.name=m.value"
				" and m.valuemapid=vm.valuemapid"
				" and vm.name='%s'",
			hostid, valuemap_esc);

	if (NULL != (row = DBfetch(result)))
	{
		char	*ptr;

		if (NULL != (ptr = strrchr(row[0], ':')))
		{
			*ptr++ = '\0';
			*service_name = zbx_strdup(*service_name, row[0]);
			*service_id = zbx_strdup(*service_id, ptr);

			goto out;
		}

	}

	*service_name = zbx_strdup(NULL, "");
	*service_id = zbx_strdup(NULL, "");

out:
	DBfree_result(result);

	zbx_free(valuemap_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_process_alert                                             *
 *                                                                            *
 * Purpose: processes alert by either creating or closing ticket in Remedy    *
 *          service                                                           *
 *                                                                            *
 * Parameters: alert      - [IN] the alert to process                         *
 *             media      - [IN] the media object containing Remedy service   *
 *                               and ticket information                       *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return Value: SUCCEED - the alert was processed successfully               *
 *               FAIL - alert processing failed, error contains               *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
int	remedy_process_alert(DB_ALERT *alert, DB_MEDIATYPE *media, char **error)
{
#define ZBX_EVENT_REMEDY_WARNING	0
#define ZBX_EVENT_REMEDY_CRITICAL	1

#define ZBX_REMEDY_DEFAULT_SERVICECI	""

	const char	*__function_name = "remedy_process_alert";
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, hostid;
	const char	*status;

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
	};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select e.value,t.priority,t.triggerid,h.host,h.hostid,hi." ZBX_REMEDY_CI_ID_FIELD
			" from triggers t,hosts h,items i,functions f,events e,host_inventory hi"
			" where e.eventid=" ZBX_FS_UI64
				" and e.source=%d"
				" and e.object=%d"
				" and e.objectid=t.triggerid"
				" and t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and hi.hostid=h.hostid",
				alert->eventid, EVENT_SOURCE_TRIGGERS,  EVENT_OBJECT_TRIGGER);

	if (NULL == (row = DBfetch(result)))
		goto out;

	ZBX_STR2UINT64(triggerid, row[2]);

	/* retrieve the last incident triggered by the event source trigger */
	if (FAIL == remedy_read_ticket(triggerid, media->smtp_server, media->smtp_helo, media->username, media->passwd,
			fields, ARRSIZE(fields), error))
	{
		goto out;
	}

	if (TRIGGER_VALUE_OK != atoi(row[0]))
	{
		char	*ticketnumber, *service_name = NULL, *service_id = NULL;
		int	remedy_event;
		char	*impact_map[] = {"3-Medium", "2-Significant/Large"};
		char	*urgency_map[] = {"3-Moderate/Limited", "2-High"};

		/* check if the ticket should be reopened */
		if (NULL != remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_INCIDENT_NUMBER))
		{
			status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS);

			if (0 == strcmp(status, ZBX_REMEDY_STATUS_RESOLVED))
			{
				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS,
						ZBX_REMEDY_STATUS_ASSIGNED);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						alert->subject);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields), error);
				goto out;
			}

			/* if ticket is still being worked on, add work info */
			if (0 != strcmp(status, ZBX_REMEDY_STATUS_CLOSED) &&
					0 != strcmp(status, ZBX_REMEDY_STATUS_CANCELLED))
			{
				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						alert->subject);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields), error);
				goto out;
			}
		}

		/* create a new ticket */
		switch (atoi(row[1]))
		{
			case  TRIGGER_SEVERITY_WARNING:
				remedy_event = ZBX_EVENT_REMEDY_WARNING;
				break;
			case  TRIGGER_SEVERITY_AVERAGE:
			case  TRIGGER_SEVERITY_HIGH:
			case  TRIGGER_SEVERITY_DISASTER:
				remedy_event = ZBX_EVENT_REMEDY_CRITICAL;
				break;
			default:
				goto out;
		}

		ZBX_STR2UINT64(hostid, row[4]);

		remedy_get_service_by_host(hostid, media->smtp_email, &service_name, &service_id);

		if (SUCCEED == (ret = remedy_create_ticket(media->smtp_server, media->smtp_helo, media->username,
				media->passwd, alert->sendto, service_name, service_id, row[3], row[5], alert->subject,
				alert->message, impact_map[remedy_event], urgency_map[remedy_event], media->exec_path,
				&ticketnumber, error)))
		{
			zbx_uint64_t	ticketid;
			char		*ticketnumber_dyn;

			ticketid = DBget_maxid_num("ticket", 1);
			ticketnumber_dyn = DBdyn_escape_string(ticketnumber);

			if (ZBX_DB_OK > DBexecute("insert into ticket (ticketid,externalid,eventid,clock) values"
					" (" ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ",%d)",
					ticketid, ticketnumber_dyn, alert->eventid, time(NULL)))
			{
				ret = FAIL;
			}

			zbx_free(ticketnumber_dyn);
			zbx_free(ticketnumber);
		}

		zbx_free(service_name);
		zbx_free(service_id);
	}
	else
	{
		if (NULL == remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_INCIDENT_NUMBER))
		{
			/* trigger without associated ticket was switched to OK state */
			ret = SUCCEED;
			goto out;
		}

		status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS);

		if (0 == strcmp(status, ZBX_REMEDY_STATUS_RESOLVED) ||
				0 == strcmp(status, ZBX_REMEDY_STATUS_CLOSED) ||
				0 == strcmp(status, ZBX_REMEDY_STATUS_CANCELLED))
		{
			/* don't update already resolved, closed or canceled incidents */
			ret = SUCCEED;
			goto out;
		}

		remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
				alert->subject);

		ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username, media->passwd, fields,
				ARRSIZE(fields), error);
	}

out:

	DBfree_result(result);

	remedy_fields_clean_values(fields, ARRSIZE(fields));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#else

int	remedy_process_alert(DB_ALERT *alert, DB_MEDIATYPE *media, char **error)
{
	*error = zbx_dsprintf(*error, "Zabbix server is built without Remedy ticket support");
	return FAIL;
}

#endif
