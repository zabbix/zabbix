!IF "$(TLS)" != ""

!IF "$(TLSINCDIR)" == ""
!ERROR TLS include directory TLSINCDIR not specified (e. g. TLSINCDIR=\tmp\mbedtls-1.3.10\include).
!ENDIF

!IF !EXISTS($(TLSINCDIR))
!ERROR Specified TLS include directory "$(TLSINCDIR)" does not exist.
!ENDIF

!IF "$(TLSLIBDIR)" == ""
!ERROR TLS library directory TLSLIBDIR not specified (e. g. TLSLIBDIR=\tmp\mbedtls-1.3.10\library).
!ENDIF

!IF !EXISTS($(TLSLIBDIR))
!ERROR Specified TLS library directory "$(TLSLIBDIR)" does not exist.
!ENDIF

!IF "$(TLS)" == "polarssl"
TLSLIBRARY = $(TLSLIBDIR)\mbedtls.lib
CFLAGS = $(CFLAGS) /DHAVE_POLARSSL
!ELSE
!ERROR Specified TLS target "$(TLS)" is not supported (supported: polarssl).
!ENDIF

!IF !EXISTS($(TLSLIBRARY))
!ERROR TLS library "$(TLSLIBRARY)" not found.
!ENDIF

INCS = $(INCS) /I $(TLSINCDIR)
OBJS = $(OBJS) ..\..\..\src\libs\zbxcrypto\tls.o
LIBS = $(LIBS) $(TLSLIBRARY)

!ENDIF
