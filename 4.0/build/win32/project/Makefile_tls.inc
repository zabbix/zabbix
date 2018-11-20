!IF "$(TLS)" != ""

!	IF "$(TLS)" != "mbedtls" && "$(TLS)" != "gnutls" && "$(TLS)" != "openssl"
!		ERROR Specified TLS target "$(TLS)" is not supported (supported: mbedtls, gnutls, openssl).
!	ENDIF

!	IF "$(TLSINCDIR)" == ""
!		ERROR Please specify directory containing TLS header files (e. g. TLSINCDIR="\somepath\include").
!	ENDIF

!	IF !EXISTS("$(TLSINCDIR)")
!		ERROR Specified directory "$(TLSINCDIR)" does not exist.
!	ENDIF

!	IF "$(TLS)" == "openssl"
!		IF ("$(TLSLIBDIR)" != "" && "$(TLSLIB2)" != "") || ("$(TLSLIB)" != "" && "$(TLSLIB2)" == "") || ("$(TLSLIB)" == "" && "$(TLSLIB2)" != "")
!			ERROR Please specify either TLSLIBDIR or both TLSLIB and TLSLIB2 (e. g. TLSLIBDIR="\somepath\lib" or TLSLIB="\somepath\libeay32.lib" TLSLIB2="\somepath\ssleay32.lib").
!		ENDIF
!	ELSEIF "$(TLS)" == "gnutls"
!		IF "$(TLSLIB)" == ""
!			ERROR Please specify full path to static GnuTLS library (e. g. TLSLIB="\somepath\some.lib").
!		ENDIF
!	ELSE
!		IF ("$(TLSLIBDIR)" == "" && "$(TLSLIB)" == "") || ("$(TLSLIBDIR)" != "" && "$(TLSLIB)" != "")
!			ERROR Please specify only one of TLSLIBDIR or TLSLIB (e. g. TLSLIBDIR="\somepath\lib" or TLSLIB="\somepath\lib\some.lib").
!		ENDIF
!	ENDIF

!	IF "$(TLSLIBDIR)" != "" && !EXISTS("$(TLSLIBDIR)")
!		ERROR Specified directory "$(TLSLIBDIR)" does not exist.
!	ENDIF

!	IF "$(TLSLIBDIR)" != ""
!		IF "$(TLS)" == "openssl"
ADDMSG =		For OpenSSL 1.0.x you may specify path to libraries using TLSLIB="\somepath\libeay32.lib" TLSLIB2="\somepath\ssleay32.lib", for OpenSSL 1.1.x: TLSLIB="\somepath\libcrypto.lib" TLSLIB2="\somepath\libssl.lib"
!		ELSE
ADDMSG =		You may specify path to library using TLSLIB="\somepath\lib\some.lib"
!		ENDIF
!	ENDIF

	### mbed TLS ###
!	IF "$(TLS)" == "mbedtls"
!		IF "$(TLSLIBDIR)" != ""
TLSLIB =		$(TLSLIBDIR)\mbedtls.lib
!		ENDIF
CFLAGS =	$(CFLAGS) /DHAVE_POLARSSL
	### GnuTLS ###
!	ELSEIF "$(TLS)" == "gnutls"
CFLAGS =	$(CFLAGS) /DHAVE_GNUTLS
	### OpenSSL ###
!	ELSEIF "$(TLS)" == "openssl"
!		IF "$(TLSLIBDIR)" != ""
!			IF !EXISTS("$(TLSLIBDIR)\libcrypto.lib") && !EXISTS("$(TLSLIBDIR)\libssl.lib")
TLSLIB =			$(TLSLIBDIR)\libeay32.lib
TLSLIB2 =			$(TLSLIBDIR)\ssleay32.lib
!			ELSEIF !EXISTS("$(TLSLIBDIR)\libeay32.lib") && !EXISTS("$(TLSLIBDIR)\ssleay32.lib")
TLSLIB =			$(TLSLIBDIR)\libcrypto.lib
TLSLIB2 =			$(TLSLIBDIR)\libssl.lib
!			ELSE
!				ERROR TLSLIBDIR "$(TLSLIBDIR)" contains libraries from OpenSSL 1.0.x (libeay32.lib, ssleay32.lib) and 1.1.x (libcrypto.lib, libssl.lib). TLSLIBDIR cannot be used in this case. Use TLSLIB="$(TLSLIBDIR)\libeay32.lib" TLSLIB2="$(TLSLIBDIR)\ssleay32.lib" for OpenSSL 1.0.x or TLSLIB="$(TLSLIBDIR)\libcrypto.lib" TLSLIB2="$(TLSLIBDIR)\libssl.lib" for OpenSSL 1.1.x.
!			ENDIF
!		ENDIF
CFLAGS =	$(CFLAGS) /DHAVE_OPENSSL
!	ENDIF

!	IF !EXISTS("$(TLSLIB)")
!		ERROR TLS library "$(TLSLIB)" not found. $(ADDMSG)
!	ENDIF

!	IF "$(TLS)" == "openssl"
!		IF !EXISTS("$(TLSLIB2)")
!			ERROR TLS library "$(TLSLIB2)" not found. $(ADDMSG)
!		ENDIF
LIBS =		$(LIBS) "$(TLSLIB)" "$(TLSLIB2)"
!	ELSE
LIBS =		$(LIBS) "$(TLSLIB)"
!	ENDIF

INCS =	$(INCS) /I "$(TLSINCDIR)"
OBJS =	$(OBJS) ..\..\..\src\libs\zbxcrypto\tls.o

!ENDIF
