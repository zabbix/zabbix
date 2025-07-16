package redis

import (
	"fmt"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
)

const (
	Required FieldRequirement = iota
	Forbidden
	Optional
)

type FieldRequirement int

// gives a map which describes what field combinations are allowed depending on TLSConnect value.
func getTLSValidationRules(tlsConnect string) map[comms.TLSConfigSetting]FieldRequirement {
	switch comms.TLSConnectionType(tlsConnect) {
	case comms.NoTLS, comms.Insecure:
		return map[comms.TLSConfigSetting]FieldRequirement{
			comms.TLSCAFile:     Forbidden,
			comms.TLSServerName: Forbidden,
			comms.TLSCertFile:   Forbidden,
			comms.TLSKeyFile:    Forbidden,
		}
	case comms.VerifyCA:
		return map[comms.TLSConfigSetting]FieldRequirement{
			comms.TLSCAFile:     Required,
			comms.TLSServerName: Required,
			comms.TLSCertFile:   Forbidden,
			comms.TLSKeyFile:    Forbidden,
		}
	case comms.VerifyFull:
		return map[comms.TLSConfigSetting]FieldRequirement{
			comms.TLSCAFile:     Required,
			comms.TLSServerName: Required,
			comms.TLSCertFile:   Required,
			comms.TLSKeyFile:    Required,
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
		session.TLSConnect = string(comms.NoTLS)
	}

	validationRules := getTLSValidationRules(session.TLSConnect)
	if validationRules == nil {
		return errs.New(fmt.Sprintf("invalid TLSConnect value: %s", session.TLSConnect))
	}

	return validateTLSFieldsByRules(session, validationRules)
}

func validateTLSFieldsByRules(session Session, validationRules map[comms.TLSConfigSetting]FieldRequirement) error {
	tlsFields := getTLSFieldValues(session)

	for fieldName, fieldRequirement := range validationRules {
		fieldValue := tlsFields[fieldName]

		switch fieldRequirement {
		case Required:
			if fieldValue == "" {
				return errs.New(fmt.Sprintf("%s is required", fieldName))
			}
		case Forbidden:
			if fieldValue != "" {
				return errs.New(fmt.Sprintf("%s is forbidden", fieldName))
			}
		case Optional: //no action.
		default:
		}
	}

	return nil
}

func getTLSFieldValues(session Session) map[comms.TLSConfigSetting]string {
	return map[comms.TLSConfigSetting]string{
		comms.TLSCAFile:     session.TLSCAFile,
		comms.TLSServerName: session.TLSServerName,
		comms.TLSCertFile:   session.TLSCertFile,
		comms.TLSKeyFile:    session.TLSKeyFile,
	}
}
