package dns

import (
	"bytes"
	"encoding/json"
	"fmt"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/miekg/dns"
	"reflect"
	"strings"
	"time"
)

type dnsGetOptions struct {
	options
	flags map[string]bool
}

var dnsTypesGet = map[string]uint16{
	"None":       dns.TypeNone,
	"A":          dns.TypeA,
	"NS":         dns.TypeNS,
	"MD":         dns.TypeMD,
	"MF":         dns.TypeMF,
	"CNAME":      dns.TypeCNAME,
	"SOA":        dns.TypeSOA,
	"MB":         dns.TypeMB,
	"MG":         dns.TypeMG,
	"MR":         dns.TypeMR,
	"NULL":       dns.TypeNULL,
	"PTR":        dns.TypePTR,
	"HINFO":      dns.TypeHINFO,
	"MINFO":      dns.TypeMINFO,
	"MX":         dns.TypeMX,
	"TXT":        dns.TypeTXT,
	"RP":         dns.TypeRP,
	"AFSDB":      dns.TypeAFSDB,
	"X25":        dns.TypeX25,
	"ISDN":       dns.TypeISDN,
	"RT":         dns.TypeRT,
	"NSAPPTR":    dns.TypeNSAPPTR,
	"SIG":        dns.TypeSIG,
	"KEY":        dns.TypeKEY,
	"PX":         dns.TypePX,
	"GPOS":       dns.TypeGPOS,
	"AAAA":       dns.TypeAAAA,
	"LOC":        dns.TypeLOC,
	"NXT":        dns.TypeNXT,
	"EID":        dns.TypeEID,
	"NIMLOC":     dns.TypeNIMLOC,
	"SRV":        dns.TypeSRV,
	"ATMA":       dns.TypeATMA,
	"NAPTR":      dns.TypeNAPTR,
	"KX":         dns.TypeKX,
	"CERT":       dns.TypeCERT,
	"DNAME":      dns.TypeDNAME,
	"OPT":        dns.TypeOPT,
	"APL":        dns.TypeAPL,
	"DS":         dns.TypeDS,
	"SSHFP":      dns.TypeSSHFP,
	"RRSIG":      dns.TypeRRSIG,
	"NSEC":       dns.TypeNSEC,
	"DNSKEY":     dns.TypeDNSKEY,
	"DHCID":      dns.TypeDHCID,
	"NSEC3":      dns.TypeNSEC3,
	"NSEC3PARAM": dns.TypeNSEC3PARAM,
	"TLSA":       dns.TypeTLSA,
	"SMIMEA":     dns.TypeSMIMEA,
	"HIP":        dns.TypeHIP,
	"NINFO":      dns.TypeNINFO,
	"RKEY":       dns.TypeRKEY,
	"TALINK":     dns.TypeTALINK,
	"CDS":        dns.TypeCDS,
	"CDNSKEY":    dns.TypeCDNSKEY,
	"OPENPGPKEY": dns.TypeOPENPGPKEY,
	"CSYNC":      dns.TypeCSYNC,
	"ZONEMD":     dns.TypeZONEMD,
	"SVCB":       dns.TypeSVCB,
	"HTTPS":      dns.TypeHTTPS,
	"SPF":        dns.TypeSPF,
	"UINFO":      dns.TypeUINFO,
	"UID":        dns.TypeUID,
	"GID":        dns.TypeGID,
	"UNSPEC":     dns.TypeUNSPEC,
	"NID":        dns.TypeNID,
	"L32":        dns.TypeL32,
	"L64":        dns.TypeL64,
	"LP":         dns.TypeLP,
	"EUI48":      dns.TypeEUI48,
	"EUI64":      dns.TypeEUI64,
	"URI":        dns.TypeURI,
	"CAA":        dns.TypeCAA,
	"AVC":        dns.TypeAVC,

	"TKEY": dns.TypeTKEY,
	"TSIG": dns.TypeTSIG,
	//
	"IXFR":  dns.TypeIXFR,
	"AXFR":  dns.TypeAXFR,
	"MAILB": dns.TypeMAILB,
	"MAILA": dns.TypeMAILA,
	"ANY":   dns.TypeANY,

	"TA":       dns.TypeTA,
	"DLV":      dns.TypeDLV,
	"Reserved": dns.TypeReserved,
}

func (o *dnsGetOptions) setFlags(flags string) error {
	o.flags = map[string]bool{
		"cdflag": false,
		"rdflag": true,
		"dnssec": false,
		"nsid":   false,
		"edns0":  true,
		"aaflag": false,
		"adflag": false,
	}

	flagsSplit := strings.Split(flags, ",")

	if len(flagsSplit) > len(o.flags) {
		return zbxerr.New(fmt.Sprintf("Too many flags supplied: %d", len(o.flags)))
	}

	// For checking the duplicates. struct{} is redundant, this is go's version of set.
	foundFlags := map[string]struct{}{}

	for _, i := range flagsSplit {
		_, ok := o.flags[i]
		if !ok {
			return zbxerr.New("Invalid flag supplied: " + i)
		}

		_, flagAlreadyParsed := foundFlags[i]

		if flagAlreadyParsed {
			return zbxerr.New("Duplicate flag supplied: " + i)
		} else {
			foundFlags[i] = struct{}{}
		}
	}

	flags = "," + flags

	if strings.Contains(flags, ",nsid") && strings.Contains(flags, "noedns0") {
		return zbxerr.New("Invalid flags combination, cannot use noedns0 and nsid together")
	}

	for key, val := range o.flags {
		flagIsNotPresent := strings.Contains(flags, ",no"+key)
		flagIsPresent := strings.Contains(flags, ","+key)

		if flagIsNotPresent && flagIsPresent {
			return zbxerr.New("Invalid flags combination, cannot use no" +
				key + " and " + key + " together")
		}

		if flagIsNotPresent {
			o.flags[key] = false
		} else if flagIsPresent {
			o.flags[key] = true
		} else {
			o.flags[key] = val
		}
	}

	return nil
}

func parseParamsGet(params []string) (o dnsGetOptions, err error) {
	switch len(params) {
	case seventhParam:
		err = o.setFlags(params[seventhParam-1])
		if err != nil {
			return
		}

		fallthrough
	case sixthParam:
		err = o.setProtocol(params[sixthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case fifthParam:
		err = o.setCount(params[fifthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case fourthParam:
		err = o.setTimeout(params[fourthParam-1])
		if err != nil {
			return
		}

		fallthrough
	case thirdParam:
		err = o.setDNSTypeGet(params[thirdParam-1])
		if err != nil {
			return
		}

		fallthrough
	case secondParam:
		o.name = params[secondParam-1]

		fallthrough
	case firstParam:
		err = o.setIP(params[firstParam-1])
		if err != nil {
			return o, zbxerr.New(fmt.Sprintf("invalid fist parameter, %s", err.Error()))
		}

		fallthrough
	case noneParam:
		err = o.setDefaults()
		if err != nil {
			return
		}
	default:
		err = zbxerr.ErrorTooManyParameters

		return
	}

	return
}

func reverseMap(m map[string]uint16) map[interface{}]string {
	n := make(map[interface{}]string, len(m))
	for k, v := range m {
		n[v] = k
	}

	return n
}

var dnsTypesGetReverse = reverseMap(dnsTypesGet)

var dnsClassesGet = map[uint16]string{
	1:   "IN",
	3:   "CH",
	4:   "HS",
	254: "NONE",
	255: "ANY",
}

var dnsExtraQuestionTypesGet = map[uint16]string{
	251: "IXFR",
	252: "AXFR",
	253: "MAILB",
	254: "MAILA",
	255: "ANY",
}

func insertAtEveryNthPosition(s string, n int, r rune) string {
	var buffer bytes.Buffer
	var n1 = n - 1
	var l1 = len(s) - 1
	for i, rune := range s {
		buffer.WriteRune(rune)
		if i%n == n1 && i != l1 {
			buffer.WriteRune(r)
		}
	}

	return buffer.String()
}

func parseClassInHeader(isOPT bool, fieldValue *any, fieldName *string) {
	if !isOPT {
		classValue, ok := (*fieldValue).(uint16)
		if ok {
			mappedClass, ok2 := dnsClassesGet[classValue]

			if ok2 {
				*fieldValue = mappedClass
			}
		}
	} else {
		*fieldName = "udp_payload"
	}
}

func parseHeader(header reflect.Value, result map[string]interface{}, isOPT bool) {
	for i := 0; i < header.NumField(); i++ {
		fieldValue := header.Field(i).Interface()
		fieldName := strings.ToLower(header.Type().Field(i).Name)

		if fieldName == "rrtype" {
			fieldName = "type"
			mappedFieldValue, ok := dnsTypesGetReverse[fieldValue]
			if ok {
				fieldValue = mappedFieldValue
			}
		} else if fieldName == "class" {
			parseClassInHeader(isOPT, &fieldValue, &fieldName)
		} else if "ttl" == fieldName && isOPT {
			fieldName = "extended_rcode"
		}
		result[fieldName] = fieldValue
	}
}

func parseRest(RRFieldButNotHeader reflect.Value, fieldType reflect.StructField, isOPT bool,
	result map[string]interface{}) {
	fieldInterface := RRFieldButNotHeader.Interface()
	fieldName := strings.ToLower(fieldType.Name)

	if isOPT && fieldName == "option" {
		fieldName = "options"
		optionResults := make([]interface{}, 0)
		EDNS0Field, isEDNS0Field := fieldInterface.([]dns.EDNS0)

		if isEDNS0Field {
			for _, edns0FieldNextPart := range EDNS0Field {
				optionResult := make(map[string]interface{})
				edns0_NSIDField, isEDNS0_NSIDField := edns0FieldNextPart.(*dns.EDNS0_NSID)

				if isEDNS0_NSIDField {
					optionResult["code"] = edns0_NSIDField.Code
					nsidValue := edns0_NSIDField.Nsid
					const numOfDigitsTogetherInNSID = 2
					nsidValue = insertAtEveryNthPosition(nsidValue, numOfDigitsTogetherInNSID, ' ')
					optionResult["nsid"] = nsidValue
					optionResults = append(optionResults, optionResult)
				}
			}
		}
		result[fieldName] = optionResults
	} else {
		result[fieldName] = fieldInterface
	}
}

func parseRRs(in []dns.RR, source string) map[string][]interface{} {
	result := make(map[string][]interface{})

	for _, rrNext := range in {
		resultPart := make(map[string]interface{})

		rrNextValue := reflect.ValueOf(rrNext)

		// OPT can exist only in additional section
		_, isOPT := rrNextValue.Interface().(*dns.OPT)

		// if it is a pointer - dereference it
		if rrNextValue.Kind() == reflect.Ptr {
			rrNextValue = rrNextValue.Elem()
		}

		resFieldAggregatedValues := make(map[string]interface{})

		for i := 0; i < rrNextValue.NumField(); i++ {
			fieldTypeOfrrNextValue := rrNextValue.Type().Field(i)

			if fieldTypeOfrrNextValue.Name == "Hdr" {
				parseHeader(rrNextValue.Field(i), resultPart, isOPT)
			} else {
				parseRest(rrNextValue.Field(i), fieldTypeOfrrNextValue, isOPT,
					resFieldAggregatedValues)
			}
		}

		resultPart["rdata"] = resFieldAggregatedValues
		result[source] = append(result[source], resultPart)
	}

	return result
}

func parseRespQuestion(respQuestion []dns.Question) map[string][]interface{} {
	result := make(map[string][]interface{})
	resultPart := make(map[string]interface{})

	// RFC allows to have multiple questions, however DNS library describes
	// it almost never happens, so it says it will fail if there is more than 1,
	// so safe to assumed  there will be exactly 1 question.
	q := respQuestion[0]
	resultPart["qname"] = q.Name

	qTypeMapped, ok := dnsTypesGetReverse[q.Qtype]

	if !ok {
		qTypeMapped, ok = dnsExtraQuestionTypesGet[q.Qtype]
		if !ok {
			resultPart["qtype"] = q.Qtype
		} else {
			resultPart["qtype"] = qTypeMapped
		}
	} else {
		resultPart["qtype"] = qTypeMapped
	}

	qClassMapped, ok2 := dnsClassesGet[q.Qclass]
	if !ok2 {
		resultPart["qclass"] = q.Qclass
	} else {
		resultPart["qclass"] = qClassMapped
	}

	result["question_section"] = append(result["question_section"], resultPart)

	return result
}

func parseRespFlags(rh dns.MsgHdr) map[string]interface{} {
	result := make(map[string]interface{})
	answer_flags := make([]string, 0)

	if rh.Authoritative {
		answer_flags = append(answer_flags, "AA")
	}

	if rh.Truncated {
		answer_flags = append(answer_flags, "TC")
	}

	if rh.RecursionDesired {
		answer_flags = append(answer_flags, "RD")
	}

	if rh.RecursionAvailable {
		answer_flags = append(answer_flags, "RA")
	}

	if rh.AuthenticatedData {
		answer_flags = append(answer_flags, "AD")
	}

	if rh.CheckingDisabled {
		answer_flags = append(answer_flags, "CD")
	}

	result["flags"] = answer_flags

	return result
}

var dnsRespCodesMappingGet = map[int]string{
	0:  "NOERROR",
	1:  "FORMERR",
	2:  "SERVFAIL",
	3:  "NXDOMAIN",
	4:  "NOTIMP",
	5:  "REFUSED",
	6:  "YXDOMAIN",
	7:  "YXRRSET",
	8:  "NXRRSET",
	9:  "NOTAUTH",
	10: "NOTZONE",
	16: "BADSIG/BADVERS",
	17: "BADKEY",
	18: "BADTIME",
	19: "BADMODE",
	20: "BADNAME",
	21: "BADALG",
	22: "BADTRUNC",
	23: "BADCOOKIE",
}

func parseRespCode(rh dns.MsgHdr) map[string]interface{} {
	result := make(map[string]interface{})

	rCodeMapped, ok := dnsRespCodesMappingGet[rh.Rcode]

	if ok {
		result["response_code"] = rCodeMapped
	} else {
		result["response_code"] = rh.Rcode
	}

	return result
}

const (
	noErrorResponseCodeFinalJsonResult    = 0
	communicationErrorCodeFinalJsonResult = -1
	jsonParsingErrorCodeFinalJsonResult   = -2
)

func exportDnsGet(params []string) (result interface{}, err error) {
	options, err := parseParamsGet(params)
	if err != nil {
		return "", err
	}
	timeBeforeQuery := time.Now()

	var resp *dns.Msg
	for i := 1; i <= options.count; i++ {
		resp, err = runQueryGet(&options)

		if err != nil {
			continue
		}

		break
	}

	if err != nil {
		networkErrorMessageBlock := make(map[string]interface{})

		networkErrorMessageBlock["zbx_error_code"] = communicationErrorCodeFinalJsonResult
		networkErrorMessageBlock["zbx_error_msg"] = "Communication error: " + err.Error()

		resultJson, err := json.Marshal(networkErrorMessageBlock)
		if err != nil {
			// There is communication error, however we failed to parse it
			// return the original communivation error as regular error.
			return nil, err
		}

		return string(resultJson), nil
	}

	timeDNSResponseReceived := time.Since(timeBeforeQuery).Seconds()
	queryTimeSection := make(map[string]interface{})
	queryTimeSection["query_time"] = fmt.Sprintf("%.2f", timeDNSResponseReceived)

	// Now, resp from the DNS library is ready to be processed:
	//    type Msg struct {
	//    MsgHdr
	//    Compress bool       `json:"-"`
	//    Question []Question // Holds the RR(s) of the question section.
	//    Answer   []RR       // Holds the RR(s) of the answer section.
	//    Ns       []RR       // Holds the RR(s) of the authority section.
	//    Extra    []RR       // Holds the RR(s) of the additional section.
	//    }
	//
	// This gets parsed, with some new data attached. At the end, large JSON response,
	// consisting of several sections is returned:
	// 1) Meta-data: zbx_error_code and query_time - internally generated,
	//               not coming from the DNS library
	// 2) MsgHdr data: response_code and flags
	// 3) Question, Answer section, Ns and Extra sections data, mostly untouched,
	//    but formatted to make it more consistent with other Zabbix JSON returning items.

	parsedFlagsSection := parseRespFlags(resp.MsgHdr)
	parsedResponseCode := parseRespCode(resp.MsgHdr)
	parsedQuestionSection := parseRespQuestion(resp.Question)
	parsedAnswerSection := parseRRs(resp.Answer, "answer_section")
	parsedAuthoritySection := parseRRs(resp.Ns, "authority_section")
	parsedAdditionalSection := parseRRs(resp.Extra, "additional_section")

	// Almost complete since it is not marshaled yet and without
	// zbx_error_code (and possibly zbx_error_msg).
	almostCompleteResultBlock := []interface{}{
		parsedFlagsSection,
		parsedResponseCode,
		queryTimeSection,
		parsedQuestionSection,
	}

	if len(parsedAnswerSection) != 0 {
		almostCompleteResultBlock = append(almostCompleteResultBlock, parsedAnswerSection)
	}
	if len(parsedAuthoritySection) != 0 {
		almostCompleteResultBlock = append(almostCompleteResultBlock, parsedAuthoritySection)
	}
	if len(parsedAdditionalSection) != 0 {
		almostCompleteResultBlock = append(almostCompleteResultBlock, parsedAdditionalSection)
	}

	// Check if the result can be marshaled first and if not:
	// 1) return appropriate error encoded in json
	// 2) if it also fails, return regular error.
	//
	// If original marshal succeeds - need to attach the success
	// response to the original result. If this results into error
	// in subsequent marshall - then return regular error.

	_, errResultParse := json.Marshal(almostCompleteResultBlock)

	if errResultParse != nil {
		resultFailParse := make(map[string]interface{})
		resultFailParse["zbx_error_code"] = jsonParsingErrorCodeFinalJsonResult
		resultFailParse["zbx_error_msg"] = errResultParse.Error()
		resultJsonFailParse, errFailParse := json.Marshal(resultFailParse)

		if errFailParse != nil {
			return nil, errFailParse
		}

		return string(resultJsonFailParse), nil
	}

	resultOkParse := make(map[string]interface{})
	resultOkParse["zbx_error_code"] = noErrorResponseCodeFinalJsonResult
	almostCompleteResultBlock = append(almostCompleteResultBlock, resultOkParse)

	finalResultJson, errFinalResultParse := json.Marshal(almostCompleteResultBlock)
	if errFinalResultParse != nil {
		return nil, errFinalResultParse
	}

	return string(finalResultJson), nil
}

func (o *dnsGetOptions) setDNSTypeGet(dnsType string) error {
	if dnsType == "" {
		return nil
	}

	t, ok := dnsTypesGet[strings.ToUpper(dnsType)]
	if !ok {
		return zbxerr.New(fmt.Sprintf("invalid third parameter, unknown dns type %s", dnsType))
	}

	o.dnsType = t

	return nil
}

func runQueryGet(o *dnsGetOptions) (*dns.Msg, error) {
	resolver := o.ip
	domain := o.name
	net := o.protocol
	record := o.dnsType
	timeout := o.timeout
	flags := o.flags

	c := new(dns.Client)
	c.Net = net
	c.DialTimeout = timeout
	c.ReadTimeout = timeout
	c.WriteTimeout = timeout

	if record == dns.TypePTR {
		rdomain, revAddrErr := dns.ReverseAddr(domain)

		if revAddrErr != nil {
			return nil, revAddrErr
		}

		domain = rdomain
	}

	m := &dns.Msg{
		MsgHdr: dns.MsgHdr{
			Authoritative:     flags["aaflag"],
			AuthenticatedData: flags["adflag"],
			CheckingDisabled:  false,
			RecursionDesired:  flags["rdflag"],
			Opcode:            dns.OpcodeQuery,
			Rcode:             dns.RcodeSuccess,
		},
		Question: make([]dns.Question, 1),
	}

	m.Question[0] = dns.Question{Name: dns.Fqdn(domain), Qtype: record, Qclass: dns.ClassINET}

	if flags["dnssec"] || flags["nsid"] {
		o := &dns.OPT{
			Hdr: dns.RR_Header{
				Name:   ".",
				Rrtype: dns.TypeOPT,
			},
		}
		if flags["dnssec"] {
			o.SetDo()
			o.SetUDPSize(dns.DefaultMsgSize)
		}
		if flags["nsid"] {
			e := &dns.EDNS0_NSID{
				Code: dns.EDNS0NSID,
			}
			o.Option = append(o.Option, e)
			// NSD will not return nsid when the udp message size is too small
			o.SetUDPSize(dns.DefaultMsgSize)
		}

		m.Extra = append(m.Extra, o)
	}

	r, _, err := c.Exchange(m, resolver)

	return r, err
}
