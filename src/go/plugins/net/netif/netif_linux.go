/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package netif

/*
#include <linux/wireless.h>
#include <linux/ethtool.h>
#include <linux/sockios.h>
#include <stdlib.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/types.h>
#include <string.h>
#include <unistd.h>

typedef struct
{
	int64_t	bitrate;
	int	autoneg;
	int	administrative_state;
	int	operational_state;
	int	wifiinfo;
	int	signal_level;
	int	link_quality;
	int	noise_level;
	char	ssid[IW_ESSID_MAX_SIZE + 1];
} ll_info_t;

int	get_ll_info(const char* interface, ll_info_t* info)
{
	int sockfd;
	struct iwreq wreq;
	struct iw_statistics stats;
	struct iw_range range;
	char buffer[IW_ESSID_MAX_SIZE + 1];
	struct ifreq ifr;
	struct ethtool_cmd ecmd;

	memset(info, 0, sizeof(ll_info_t));
	sockfd = socket(AF_INET, SOCK_DGRAM, 0);

	if (-1 == sockfd)
	{
		return -1;
	}

	memset(&wreq, 0, sizeof(struct iwreq));
	strncpy(wreq.ifr_name, interface, IFNAMSIZ - 1);
	wreq.u.data.pointer = (caddr_t) &stats;
	wreq.u.data.length = sizeof(struct iw_statistics);
	wreq.u.data.flags = 1;

	if (0 == ioctl(sockfd, SIOCGIWSTATS, &wreq))
	{
		memset(&range, 0, sizeof(struct iw_range));
		wreq.u.data.pointer = (caddr_t) &range;
		wreq.u.data.length = sizeof(struct iw_range);
		wreq.u.data.flags = 0;

		if (0 == ioctl(sockfd, SIOCGIWRANGE, &wreq))
		{
			info->wifiinfo = 1;

			if (0 != (stats.qual.updated & IW_QUAL_DBM))
				info->signal_level = stats.qual.level - 256;
			else
				info->signal_level = stats.qual.level;

			if (0 != range.max_qual.qual)
				info->link_quality = (stats.qual.qual * 100) / range.max_qual.qual;
			else
				info->link_quality = stats.qual.qual;

			if (0 != (stats.qual.updated & IW_QUAL_DBM))
				info->noise_level = stats.qual.noise - 256;
			else
				info->noise_level = stats.qual.noise;
		}
	}

	memset(buffer, 0, sizeof(buffer));
	wreq.u.essid.pointer = buffer;
	wreq.u.essid.length = IW_ESSID_MAX_SIZE;
	wreq.u.essid.flags = 0;

	if (0 == ioctl(sockfd, SIOCGIWESSID, &wreq))
	{
		strncpy(info->ssid, buffer, IW_ESSID_MAX_SIZE);
		info->ssid[IW_ESSID_MAX_SIZE] = '\0';
	}

	info->bitrate = -1;
	if (0 == ioctl(sockfd, SIOCGIWRATE, &wreq))
		info->bitrate = (int)wreq.u.bitrate.value / 1000000;

	memset(&ifr, 0, sizeof(ifr));
	memset(&ecmd, 0, sizeof(ecmd));
	ecmd.cmd = ETHTOOL_GSET;
	ifr.ifr_data = (char*)&ecmd;
	strncpy(ifr.ifr_name, interface, IFNAMSIZ - 1);

	if (0 == ioctl(sockfd, SIOCETHTOOL, &ifr))
		info->autoneg = ecmd.autoneg;

	memset(&ifr, 0, sizeof(ifr));
	strncpy(ifr.ifr_name, interface, IFNAMSIZ - 1);

	if (0 == ioctl(sockfd, SIOCGIFFLAGS, &ifr))
	{
		if (0 != (ifr.ifr_flags & IFF_UP))
			info->administrative_state = 1;
		else
			info->administrative_state = 2;

		if (0 != (ifr.ifr_flags & IFF_RUNNING))
			info->operational_state = 1;
		else
			info->operational_state = 2;
	}

	close(sockfd);
	return 0;
}
*/
import "C"

import (
	"bufio"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"regexp"
	"strconv"
	"strings"
	"unsafe"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/std"
)

const (
	errorCannotFindIf     = "Cannot find information for this network interface in /proc/net/dev."
	errorCannotOpenNetDev = "Cannot open /proc/net/dev: %s"
)

var stdOs std.Os

var mapNetStatIn = map[string]uint{
	"bytes":      0,
	"packets":    1,
	"errors":     2,
	"dropped":    3,
	"overruns":   4,
	"frame":      5,
	"compressed": 6,
	"multicast":  7,
}

var mapNetStatOut = map[string]uint{
	"bytes":      8,
	"packets":    9,
	"errors":     10,
	"dropped":    11,
	"overruns":   12,
	"collisions": 13,
	"carrier":    14,
	"compressed": 15,
}

func init() {
	stdOs = std.NewOs()

	err := plugin.RegisterMetrics(
		&impl, "NetIf",
		"net.if.collisions", "Returns number of out-of-window collisions.",
		"net.if.in", "Returns incoming traffic statistics on network interface.",
		"net.if.out", "Returns outgoing traffic statistics on network interface.",
		"net.if.total", "Returns sum of incoming and outgoing traffic statistics on network interface.",
		"net.if.discovery", "Returns list of network interfaces. Used for low-level discovery.",
		"net.if.get", "Returns list of network interfaces with parameters.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) addStatNum(statName string, mapNetStat map[string]uint, statNums *[]uint) error {
	if statNum, ok := mapNetStat[statName]; ok {
		*statNums = append(*statNums, statNum)
	} else {
		return errors.New(errorInvalidSecondParam)
	}
	return nil
}

func (p *Plugin) getNetStats(networkIf, statName string, dir dirFlag) (result uint64, err error) {
	var statNums []uint

	if dir&dirIn != 0 {
		if err = p.addStatNum(statName, mapNetStatIn, &statNums); err != nil {
			return
		}
	}

	if dir&dirOut != 0 {
		if err = p.addStatNum(statName, mapNetStatOut, &statNums); err != nil {
			return
		}
	}

	file, err := stdOs.Open("/proc/net/dev")
	if err != nil {
		return 0, fmt.Errorf(errorCannotOpenNetDev, err)
	}
	defer file.Close()

	var total uint64
loop:
	for sLines := bufio.NewScanner(file); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")

		if len(dev) > 1 && networkIf == strings.TrimSpace(dev[0]) {
			stats := strings.Fields(dev[1])

			if len(stats) >= 16 {
				for _, statNum := range statNums {
					var res uint64

					if res, err = strconv.ParseUint(stats[statNum], 10, 64); err != nil {
						break loop
					}
					total += res
				}
				return total, nil
			}
			break
		}
	}
	err = errors.New(errorCannotFindIf)
	return
}

func (p *Plugin) getDevDiscovery() (netInterfaces []msgIfDiscovery, err error) {
	var f std.File
	if f, err = stdOs.Open("/proc/net/dev"); err != nil {
		return nil, fmt.Errorf(errorCannotOpenNetDev, err)
	}
	defer f.Close()

	netInterfaces = make([]msgIfDiscovery, 0)
	for sLines := bufio.NewScanner(f); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")
		if len(dev) > 1 {
			netInterfaces = append(netInterfaces, msgIfDiscovery{strings.TrimSpace(dev[0]), nil})
		}
	}

	return netInterfaces, nil
}

func (p *Plugin) fillNetIfGetParams(ifName, param string) (retvalue string) {
	path := fmt.Sprintf("/sys/class/net/%s/%s", ifName, param)

	data, err := os.ReadFile(path)
	if err != nil {
		return
	}

	value := strings.TrimSpace(string(data))

	return value
}

func (p *Plugin) getIfGet(regexExpr string) (result netIfResult, err error) {
	var f std.File
	var compiledRegex *regexp.Regexp

	if f, err = stdOs.Open("/proc/net/dev"); err != nil {
		return result, fmt.Errorf(errorCannotOpenNetDev, err)
	}
	defer f.Close()

	if regexExpr != "" {
		compiledRegex, err = regexp.Compile(regexExpr)
		if err != nil {
			err = fmt.Errorf("invalid regex expression '%s': %w", regexExpr, err)
			return
		}
	}

	result.Config = make([]ifConfigData, 0)
	result.Values = make([]IfValuesData, 0)

	for sLines := bufio.NewScanner(f); sLines.Scan(); {
		dev := strings.Split(sLines.Text(), ":")
		ifName := strings.TrimSpace(dev[0])

		if len(dev) <= 1 {
			continue
		}

		if compiledRegex != nil {
			if !compiledRegex.MatchString(ifName) {
				continue
			}
		}

		stats := strings.Fields(dev[1])
		if len(stats) < 16 {
			continue
		}

		cIface := C.CString(ifName)
		cInfo := (*C.ll_info_t)(C.malloc(C.sizeof_ll_info_t))

		llresult := C.get_ll_info(cIface, cInfo)
		if llresult != 0 {
			C.free(unsafe.Pointer(cIface))
			C.free(unsafe.Pointer(cInfo))
			continue
		}

		isAutonegEnabled := "off"
		if int(cInfo.autoneg) != 0 {
			isAutonegEnabled = "on"
		}

		var speedPtr *uint64
		if speedVal, errSpeed := strconv.ParseUint(p.fillNetIfGetParams(ifName, "speed"), 10, 64); errSpeed == nil {
			speedPtr = &speedVal
		}

		var typePtr *uint64
		if typeVal, errType := strconv.ParseUint(p.fillNetIfGetParams(ifName, "type"), 10, 64); errType == nil {
			typePtr = &typeVal
		}

		var carrierPtr *uint64
		if carrierVal, errCarrier := strconv.ParseUint(p.fillNetIfGetParams(ifName, "carrier"), 10, 64); errCarrier == nil {
			carrierPtr = &carrierVal
		}

		var operstatusPtr *string
		if int(cInfo.operational_state) == 1 {
			val := "up"
			operstatusPtr = &val
		} else if int(cInfo.operational_state) == 2 {
			val := "down"
			operstatusPtr = &val
		}

		var administrativePtr *string
		if int(cInfo.administrative_state) == 1 {
			val := "up"
			administrativePtr = &val
		} else if int(cInfo.administrative_state) == 2 {
			val := "down"
			administrativePtr = &val
		}

		result.Config = append(result.Config, ifConfigData{
			Ifname:      ifName,
			Ifalias:     p.fillNetIfGetParams(ifName, "ifalias"),
			Ifmac:       p.fillNetIfGetParams(ifName, "address"),
			IfOperState: operstatusPtr,
			IfAdmState:  administrativePtr,
		})

		var levelPtr *int64
		var qualityPtr *int64
		var noiselevelPtr *int64

		if int(cInfo.wifiinfo) == 1 {
			levelVal := int64(cInfo.signal_level)
			levelPtr = &levelVal
			qualityVal := int64(cInfo.link_quality)
			qualityPtr = &qualityVal
			noiselevelVal := int64(cInfo.noise_level)
			noiselevelPtr = &noiselevelVal
		}

		var inBytes, inPackets, inErrors, inDropped, inOverruns, inFrame, inCompressed, inMulticast uint64
		var outBytes, outPackets, outErrors, outDropped, outOverruns, outCollisions, outCarrier,
			outCompressed uint64

		inBytes, _ = strconv.ParseUint(stats[0], 10, 64)
		inPackets, _ = strconv.ParseUint(stats[1], 10, 64)
		inErrors, _ = strconv.ParseUint(stats[2], 10, 64)
		inDropped, _ = strconv.ParseUint(stats[3], 10, 64)
		inOverruns, _ = strconv.ParseUint(stats[4], 10, 64)
		inFrame, _ = strconv.ParseUint(stats[5], 10, 64)
		inCompressed, _ = strconv.ParseUint(stats[6], 10, 64)
		inMulticast, _ = strconv.ParseUint(stats[7], 10, 64)

		outBytes, _ = strconv.ParseUint(stats[8], 10, 64)
		outPackets, _ = strconv.ParseUint(stats[9], 10, 64)
		outErrors, _ = strconv.ParseUint(stats[10], 10, 64)
		outDropped, _ = strconv.ParseUint(stats[11], 10, 64)
		outOverruns, _ = strconv.ParseUint(stats[12], 10, 64)
		outCollisions, _ = strconv.ParseUint(stats[13], 10, 64)
		outCarrier, _ = strconv.ParseUint(stats[14], 10, 64)
		outCompressed, _ = strconv.ParseUint(stats[15], 10, 64)

		valSSID := C.GoString((*C.char)(unsafe.Pointer(&cInfo.ssid[0])))
		var ssidPtr *string

		if valSSID != "" {
			ssidPtr = &valSSID
		}
		var bitratePtr *int64
		bitrateVal := int64(cInfo.bitrate)
		if bitrateVal > 0 {
			bitratePtr = &bitrateVal
		}

		result.Values = append(result.Values, IfValuesData{
			Ifname:  ifName,
			Ifalias: p.fillNetIfGetParams(ifName, "ifalias"),
			Ifmac:   p.fillNetIfGetParams(ifName, "address"),
			Iftype:  typePtr,

			In: IfStatistics{
				Ifbytes:   &inBytes,
				Ifpackets: &inPackets,
				Iferrors:  &inErrors,
				Ifdropped: &inDropped,

				Ifoverrruns:  &inOverruns,
				Ifframe:      &inFrame,
				Ifcompressed: &inCompressed,
				Ifmulticast:  &inMulticast,
			},
			Out: IfStatistics{
				Ifbytes:   &outBytes,
				Ifpackets: &outPackets,
				Iferrors:  &outErrors,
				Ifdropped: &outDropped,

				Ifoverrruns:  &outOverruns,
				Ifcollisions: &outCollisions,
				Ifcarrier:    &outCarrier,
				Ifcompressed: &outCompressed,
			},
			Ifcarrier:     carrierPtr,
			Ifnegotiation: isAutonegEnabled,
			Ifduplex:      p.fillNetIfGetParams(ifName, "duplex"),
			Ifspeed:       speedPtr,
			Ifslevel:      levelPtr,
			Iflquality:    qualityPtr,
			Ifnoiselevel:  noiselevelPtr,
			Ifssid:        ssidPtr,
			Ifbitrate:     bitratePtr,
		})

		C.free(unsafe.Pointer(cIface))
		C.free(unsafe.Pointer(cInfo))
	}

	return result, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var direction dirFlag
	var mode string

	switch key {
	case "net.if.discovery":
		if len(params) > 0 {
			return nil, errors.New(errorParametersNotAllowed)
		}
		var devices []msgIfDiscovery
		if devices, err = p.getDevDiscovery(); err != nil {
			return
		}
		var b []byte
		if b, err = json.Marshal(devices); err != nil {
			return
		}
		return string(b), nil
	case "net.if.get":
		if len(params) > 1 {
			return nil, errors.New(errorTooManyParams)
		}
		expression := ""
		if len(params) > 0 {
			expression = params[0]
		}
		var devices netIfResult
		if devices, err = p.getIfGet(expression); err != nil {
			return
		}
		var b []byte
		if b, err = json.Marshal(devices); err != nil {
			return
		}
		return string(b), nil
	case "net.if.collisions":
		if len(params) > 1 {
			return nil, errors.New(errorTooManyParams)
		}

		if len(params) < 1 || params[0] == "" {
			return nil, errors.New(errorEmptyIfName)
		}
		return p.getNetStats(params[0], "collisions", dirOut)
	case "net.if.in":
		direction = dirIn
	case "net.if.out":
		direction = dirOut
	case "net.if.total":
		direction = dirIn | dirOut
	default:
		/* SHOULD_NEVER_HAPPEN */
		return nil, errors.New(errorUnsupportedMetric)
	}

	if len(params) < 1 || params[0] == "" {
		return nil, errors.New(errorEmptyIfName)
	}

	if len(params) > 2 {
		return nil, errors.New(errorTooManyParams)
	}

	if len(params) == 2 && params[1] != "" {
		mode = params[1]
	} else {
		mode = "bytes"
	}

	return p.getNetStats(params[0], mode, direction)
}
