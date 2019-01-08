!IF "$(TLS)" == ""
all: agent sender sender_dll get
!ELSE
all: agent sender get
!ENDIF

agent:
	nmake /f Makefile_agent

sender:
	nmake /f Makefile_sender

sender_dll:
	nmake /f Makefile_sender_dll

get:
	nmake /f Makefile_get

clean: agent_clean sender_clean sender_dll_clean get_clean

agent_clean:
	nmake /f Makefile_agent clean

sender_clean:
	nmake /f Makefile_sender clean

sender_dll_clean:
	nmake /f Makefile_sender_dll clean

get_clean:
	nmake /f Makefile_get clean
