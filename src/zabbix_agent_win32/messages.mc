;/****************************************************************************
; Messages for Zabbix Win32 Agent
;****************************************************************************/
;
;#ifndef _messages_h_
;#define _messages_h_
;

MessageIdTypedef=DWORD

MessageId=1
SymbolicName=MSG_AGENT_STARTED
Language=English
Zabbix Win32 Agent started
.

MessageId=
SymbolicName=MSG_AGENT_SHUTDOWN
Language=English
Zabbix Win32 Agent stopped
.

MessageId=
SymbolicName=MSG_PDH_OPEN_QUERY_FAILED
Language=English
Call to PdhOpenQuery() failed: %1
.

MessageId=
SymbolicName=MSG_PDH_ADD_COUNTER_FAILED
Language=English
Unable to add performance counter "%1" to query: %2
.

MessageId=
SymbolicName=MSG_PDH_COLLECT_QUERY_DATA_FAILED
Language=English
Call to PdhCollectQueryData() failed for [%1]: %2
.

MessageId=
SymbolicName=MSG_USERDEF_COUNTER_FAILED
Language=English
Unable to add user-defined counter "%1" (expanded to "%2") to query: %3
.

MessageId=
SymbolicName=MSG_COLLECTOR_INIT_OK
Language=English
Collector thread initialized successfully
.

MessageId=
SymbolicName=MSG_BIG_PROCESSING_TIME
Language=English
Processing took more then %1 milliseconds (%2 milliseconds)
.

MessageId=
SymbolicName=MSG_COMMAND_TIMEOUT
Language=English
Timeout occured waiting for server command
.

MessageId=
SymbolicName=MSG_RECV_ERROR
Language=English
Error receiving data from socket: %1
.

MessageId=
SymbolicName=MSG_REQUEST_TIMEOUT
Language=English
Timed out while processing request. Requested parameter is "%1"
.

MessageId=
SymbolicName=MSG_SOCKET_ERROR
Language=English
Unable to open socket: %1
.

MessageId=
SymbolicName=MSG_BIND_ERROR
Language=English
Unable to bind socket: %1
.

MessageId=
SymbolicName=MSG_LISTEN_ERROR
Language=English
Error listening on socket: %1
.

MessageId=
SymbolicName=MSG_SELECT_ERROR
Language=English
Unable to select socket: %1
.

MessageId=
SymbolicName=MSG_ACCEPT_ERROR
Language=English
Unable to accept incoming connection: %1
.

MessageId=
SymbolicName=MSG_NO_FUNCTION
Language=English
Unable to resolve symbol "%1"
.

MessageId=
SymbolicName=MSG_NO_DLL
Language=English
Unable to get handle to "%1"
.

MessageId=
SymbolicName=MSG_UNEXPECTED_ATTRIBUTE
Language=English
Internal error: unexpected process attribute code %1 in GetProcessAttribute()
.

MessageId=
SymbolicName=MSG_UNEXPECTED_TYPE
Language=English
Internal error: unexpected type code %1 in GetProcessAttribute()
.

MessageId=
SymbolicName=MSG_SERVICE_STOPPED
Language=English
Service stoppped
.

MessageId=
SymbolicName=MSG_FILE_MAP_FAILED
Language=English
Unable to create mapping for file "%1": %2
.

MessageId=
SymbolicName=MSG_MAP_VIEW_FAILED
Language=English
MapViewOfFile("%1") failed: %2
.

MessageId=
SymbolicName=MSG_UNEXPECTED_IRC
Language=English
Internal error: unexpected iRC=%1 in ProcessCommand("%2")
.

MessageId=
SymbolicName=MSG_TOO_MANY_ERRORS
Language=English
Too many consecutive errors on accept() call
.

MessageId=
SymbolicName=MSG_LOAD_FAILED
Language=English
Failed to load subagent module "%1": %2
.

MessageId=
SymbolicName=MSG_NO_ENTRY_POINTS
Language=English
Unable to find all required exportable functions in subagent module "%1"
.

MessageId=
SymbolicName=MSG_SUBAGENT_INIT_FAILED
Language=English
Subagent "%1" initialization failed (initialization function return code %2)
.

MessageId=
SymbolicName=MSG_SUBAGENT_LOADED
Language=English
Subagent "%1" loaded successfully
.

MessageId=
SymbolicName=MSG_CREATE_PROCESS_FAILED
Language=English
Unable to create process "%1": %2
.

MessageId=
SymbolicName=MSG_CREATE_TMP_FILE_FAILED
Language=English
Unable to create temporary file: %1
.

MessageId=
SymbolicName=MSG_OPEN_TMP_FILE_FAILED
Language=English
Unable to open existing temporary file: %1
.

MessageId=
SymbolicName=MSG_DNS_LOOKUP_FAILED
Language=English
DNS lookup failed for host %1
.

MessageId=
SymbolicName=MSG_ACTIVE_CHECKS
Language=English
Active checks [%1]
.

MessageId=
SymbolicName=MSG_GET_COMPUTER_NAME_FAILED
Language=English
GetComputerName failed: %1
.

MessageId=
SymbolicName=MSG_LOOKUP_FAILED
Language=English
PdhLookupPerfNameByIndex failed: %1
.

MessageId=
SymbolicName=MSG_DEBUG_INFO
Language=English
%1 Debug Info: %2
.

MessageId=
SymbolicName=MSG_INFORMATION
Language=English
Information: %1 %2
.

MessageId=
SymbolicName=MSG_PDH_GET_RAW_DATA_FAILED
Language=English
Call to PdhGetRawCounterValue() failed for [%1]: %2
.

;#endif
