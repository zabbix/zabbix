package redis

import (
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/tlsconfig"
)

const (
	fieldMustBeEmpty    = true
	fieldMustNotBeEmpty = false
)

func getTLSValidationRules(tlsConnect string) map[string]bool {
	switch tlsConnect {
	case tlsconfig.NoTLS, tlsconfig.Insecure:
		return map[string]bool{
			tlsconfig.FieldTLSCAFile:     fieldMustBeEmpty,
			tlsconfig.FieldTLSServerName: fieldMustBeEmpty,
			tlsconfig.FieldTLSCertFile:   fieldMustBeEmpty,
			tlsconfig.FieldTLSKeyFile:    fieldMustBeEmpty,
		}
	case tlsconfig.VerifyCA:
		return map[string]bool{
			tlsconfig.FieldTLSCAFile:     fieldMustNotBeEmpty,
			tlsconfig.FieldTLSServerName: fieldMustNotBeEmpty,
			tlsconfig.FieldTLSCertFile:   fieldMustBeEmpty,
			tlsconfig.FieldTLSKeyFile:    fieldMustBeEmpty,
		}
	case tlsconfig.VerifyFull:
		return map[string]bool{
			tlsconfig.FieldTLSCAFile:     fieldMustNotBeEmpty,
			tlsconfig.FieldTLSServerName: fieldMustNotBeEmpty,
			tlsconfig.FieldTLSCertFile:   fieldMustNotBeEmpty,
			tlsconfig.FieldTLSKeyFile:    fieldMustNotBeEmpty,
		}
	default:
		return nil
	}
}

func validateRequiredFields(session Session) error {
	requiredFields := map[string]string{
		"URI": session.URI,
		//"User":     session.User,
		"Password": session.Password,
	}

	for fieldName, fieldValue := range requiredFields {
		if fieldValue == "" {
			return errs.New(fmt.Sprintf("session.%s is required", fieldName))
		}
	}

	return nil
}

func validateSession(session Session) error {
	err := validateRequiredFields(session)
	if err != nil {
		return errs.Wrap(err, "base fields validation failed")
	}

	err = validateTLSConfiguration(session)
	if err != nil {
		return errs.Wrap(err, "TLS validation failed")
	}

	return nil
}

func validateTLSConfiguration(session Session) error {
	if session.TLSConnect == "" {
		session.TLSConnect = tlsconfig.NoTLS
	}

	validationRules := getTLSValidationRules(session.TLSConnect)
	if validationRules == nil {
		return errs.New(fmt.Sprintf("invalid TLSConnect value: %s", session.TLSConnect))
	}

	return validateTLSFieldsByRules(session, validationRules)
}

func validateTLSFieldsByRules(session Session, validationRules map[string]bool) error {
	tlsFields := getTLSFieldValues(session)

	for fieldName, mustBeEmpty := range validationRules {
		fieldValue := tlsFields[fieldName]

		if mustBeEmpty && fieldValue != "" {
			return errs.New(fmt.Sprintf("%s must be empty for %s TLS mode", fieldName, session.TLSConnect))
		}

		if !mustBeEmpty && fieldValue == "" {
			return errs.New(fmt.Sprintf("%s is required for %s TLS mode", fieldName, session.TLSConnect))
		}
	}

	return nil
}

func getTLSFieldValues(session Session) map[string]string {
	return map[string]string{
		tlsconfig.FieldTLSCAFile:     session.TLSCAFile,
		tlsconfig.FieldTLSServerName: session.TLSServerName,
		tlsconfig.FieldTLSCertFile:   session.TLSCertFile,
		tlsconfig.FieldTLSKeyFile:    session.TLSKeyFile,
	}
}
