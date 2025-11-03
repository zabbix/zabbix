!	IF "$(CURL)" == "yes"
!		IF "$(CURLINCDIR)" == ""
CURLINCDIR = C:\Zabbix\x64\curl\include
!		ENDIF
!		IF "$(CURLLIBDIR)" == ""
CURLLIBDIR = C:\Zabbix\x64\curl\lib
!		ENDIF
!	ENDIF

!	IF "$(CURLINCDIR)" != ""
!		IF EXISTS("$(CURLINCDIR)\curl\curl.h")
CFLAGS =	$(CFLAGS) /DHAVE_LIBCURL /DCURL_STATICLIB
INCS =	$(INCS) /I "$(CURLINCDIR)"
!		ELSE
!			ERROR CURLINCDIR "$(CURLINCDIR)" cannot find "$(CURLINCDIR)\curl\curl.h"
!		ENDIF
!	ENDIF

!	IF "$(CURLLIBDIR)" != ""
!		IF EXISTS("$(CURLLIBDIR)\libcurl_a.lib")
LIBS =	$(LIBS) "$(CURLLIBDIR)\libcurl_a.lib" Crypt32.lib Normaliz.lib Secur32.lib
!		ELSEIF EXISTS("$(CURLLIBDIR)\libcurl.lib")
LIBS =	$(LIBS) "$(CURLLIBDIR)\libcurl.lib" Crypt32.lib Normaliz.lib Secur32.lib
!		ELSEIF EXISTS("$(CURLLIBDIR)\libcurl_imp.lib") # else if CURL was installed with vcpkg
LIBS =	$(LIBS) "$(CURLLIBDIR)\libcurl_imp.lib" $(LIBS)
!		ELSE
!			ERROR CURLLIBDIR "$(CURLLIBDIR)" cannot find libcurl_a.lib, libcurl.lib or libcurl_imp.lib
!		ENDIF
!	ENDIF
