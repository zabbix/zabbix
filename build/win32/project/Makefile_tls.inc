!IF "$(TLS)" != ""

!IF "$(TLS)" != "polarssl" && "$(TLS)" != "gnutls"
!ERROR Specified TLS target "$(TLS)" is not supported (supported: polarssl, gnutls).
!ENDIF

!IF "$(TLSINCDIR)" == ""
!ERROR Please specify directory containing TLS header files (e. g. TLSINCDIR=\somepath\include).
!ENDIF

!IF !EXISTS($(TLSINCDIR))
!ERROR Specified TLS include directory "$(TLSINCDIR)" does not exist.
!ENDIF

!IF "$(TLSLIB)" == ""
!ERROR Path to TLS library (TLSLIB) not specified (e. g. TLSLIB=\somepath\library.lib).
!ENDIF

!IF !EXISTS($(TLSLIB))
!ERROR Specified TLS library "$(TLSLIB)" does not exist.
!ENDIF

!IF "$(TLS)" == "polarssl"
CFLAGS = $(CFLAGS) /DHAVE_POLARSSL
!ELSEIF "$(TLS)" == "gnutls"
CFLAGS = $(CFLAGS) /DHAVE_GNUTLS
!ENDIF

INCS = $(INCS) /I "$(TLSINCDIR)"
OBJS = $(OBJS) ..\..\..\src\libs\zbxcrypto\tls.o
LIBS = $(LIBS) "$(TLSLIB)"

!ENDIF
