#!/bin/sh

# default parameter values
URL=
USERNAME=
PASSWORD=
INCIDENT=
PROXY=


display_usage()
{
	echo "Remedy service connection testing utility."
	echo "Usage: test-remedy-connection [--url=<url>] [--proxy=<proxy>] [--username=<user name>]" \
		"[--password=<password>] [--incident=<incident>]"
	echo "    where:  <url>   - the Remedy service url"
	echo "            <proxy> - <[protocol://][user:password@]proxyhost[:port]>"
	echo "        <user name> - the Remedy user name"
	echo "        <password>  - the Remedy user password"
	echo "        <incident>  - an existing Remedy incident number"
	exit 0
}

if ! type curl > /dev/null ; then
	echo "The curl command line utility is required for this script to run."
	echo "Please check if you have curl package installed."
	exit 1
fi

while [ $# -ne 0 ]; do
	case $1 in
		--url=*)
			URL=${1#*=}
			;;
		--username=*)
			USERNAME=${1#*=}
			;;
		--password=*)
			PASSWORD=${1#*=}
			;;
		--incident=*)
			INCIDENT=${1#*=}
			;;
		--proxy=*)
			PROXY=${1#*=}
			;;
		--help|-h)
			display_usage
			;;
	esac
	shift
done


SOAP_ENVELOPE_OPEN="<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\"\
	xmlns:urn=\"urn:HPD_IncidentInterface_WS\">"
	
SOAP_ENVELOPE_CLOSE="</soapenv:Envelope>"

SOAP_HEADER="<soapenv:Header><urn:AuthenticationInfo><urn:userName>$USERNAME</urn:userName>\
	<urn:password>$PASSWORD</urn:password></urn:AuthenticationInfo></soapenv:Header>"

SOAP_BODY_OPEN="<soapenv:Body>"
SOAP_BODY_CLOSE="</soapenv:Body>"

HELPDESK_QUERY_SERVICE_OPEN="<urn:HelpDesk_Query_Service>"
HELPDESK_QUERY_SERVICE_CLOSE="</urn:HelpDesk_Query_Service>"

SOAP_REQUEST="<urn:Incident_Number>$INCIDENT</urn:Incident_Number>"

DATA="$SOAP_ENVELOPE_OPEN $SOAP_HEADER $SOAP_BODY_OPEN $HELPDESK_QUERY_SERVICE_OPEN\
	$SOAP_REQUEST $HELPDESK_QUERY_SERVICE_CLOSE $SOAP_BODY_CLOSE $SOAP_ENVELOPE_CLOSE"

if [ -n "$PROXY" ]; then
	PROXY_SWITCH="-x"
fi

curl -d "$DATA" -H "Content-Type:text/xml; charset=utf-8" \
	-H "SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Query_Service"  \
	"$PROXY_SWITCH" "$PROXY" "$URL&webService=HPD_IncidentInterface_WS"

