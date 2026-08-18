-------------------------------------------------------------------------------------
-- Flux SBC - Unindo pessoas e negócios
--
-- Copyright (C) 2026 Flux Telecom
-- Daniel Paixao <daniel@flux.net.br>
-- Flux SBC Version 4.0 and above
-- License https://www.gnu.org/licenses/agpl-3.0.html
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as
-- published by the Free Software Foundation, either version 3 of the
-- License, or (at your option) any later version.
-- 
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
-- 
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------------------


function load_conf()
	local query = "SELECT name,value FROM "..TBL_CONFIG.." WHERE group_title IN ('global','opensips','callingcard','calls','InternationalPrefixes')"
	Logger.notice("[LOAD_CONF] Query :" .. query)
	local config = {}
	assert(dbh:query(query, function(u)
		config[u.name] = u.value
	end))
	return config
end

function get_cc_access_number(destination_number)
	local query = "SELECT access_number FROM accessnumber WHERE access_number = '"..destination_number.."' AND status=0 limit 1"
	Logger.notice("[get_cc_access_number] Query :" .. query)
	local cc_access_number
	assert(dbh:query(query, function(u)
		cc_access_number = u
	end))
	return cc_access_number
end

function get_speeddial_number(destination_number, accountid)
	local query = "SELECT A.number FROM "..TBL_SPEED_DIAL.." as A,"..TBL_USERS.." as B WHERE B.status=0 AND B.deleted=0 AND B.id=A.accountid AND A.speed_num =\""..destination_number.."\" AND A.accountid = '"..accountid.."' limit 1"
	Logger.notice("[CHECK_SPEEDDIAL] Query :" .. query)
	assert(dbh:query(query, function(u)
		speeddial = u
	end))
	if speeddial and speeddial ~= nil then
		return speeddial['number']
	else
		return destination_number
	end
end

function define_call_direction(destination_number, accountcode, config, callerid_number)
	local didinfo = check_did(destination_number, config, callerid_number)
	local sip2sipinfo
	if didinfo == nil then
		sip2sipinfo = check_local_call(destination_number, callerid_number)
	end
	if didinfo ~= nil then
		call_direction = "inbound"
	elseif sip2sipinfo ~= nil then
		call_direction = "local"
	else
		call_direction = "outbound"
	end
	return call_direction
end

function is_did(destination_number, config, callerid_number)
	local did_localization = nil
	local check_did_info = ""

	destination_number = check_local_number(destination_number, callerid_number)

	if config['did_global_translation'] ~= nil and config['did_global_translation'] ~= '' and tonumber(config['did_global_translation']) > 0 then
		did_localization = get_localization(config['did_global_translation'], 'O')
		if did_localization ~= nil then
			did_localization['number_originate'] = did_localization['number_originate']:gsub(" ", "")
			destination_number = do_number_translation(did_localization['number_originate'], destination_number)
		end
	end
	local query = "SELECT * FROM "..TBL_DIDS.." WHERE number =\""..destination_number.."\" AND (extensions <> '' OR status = 0) AND accountid > 0 LIMIT 1"
	Logger.notice("[IS_CHECK_DID] Query :" .. query)
	assert(dbh:query(query, function(u)
		check_did_info = u
	end))
	return check_did_info
end

function check_did(destination_number, config, callerid_number)
	local did_localization = nil
	if config['did_global_translation'] ~= nil and config['did_global_translation'] ~= '' and tonumber(config['did_global_translation']) > 0 then
		did_localization = get_localization(config['did_global_translation'], 'O')
		if did_localization ~= nil then
			did_localization['number_originate'] = did_localization['number_originate']:gsub(" ", "")
			destination_number = do_number_translation(did_localization['number_originate'], destination_number)
		end
	end

	destination_number = check_local_number(destination_number, callerid_number)

	local query = "SELECT A.id as id,A.number as did_number,B.id as accountid,B.number as account_code,B.pricelist_id as pricelist,A.number as did_number,A.connectcost,A.includedseconds,A.cost,A.inc,A.extensions,A.maxchannels,A.call_type,A.hg_type,A.city,A.province,A.init_inc,A.leg_timeout,A.status,A.country_id,A.call_type_vm_flag,A.reverse_rate,A.rate_group,C.`name` as price_name,A.area_code AS area_code,A.provider_id,A.bypass_media,A.sip_profile_id,(SELECT name FROM sip_profiles WHERE id=A.sip_profile_id AND status=0 LIMIT 1) AS sip_profile_name FROM "..TBL_DIDS.." AS A,"..TBL_USERS.." AS B,"..TBL_RATE_GROUP.." AS C WHERE B.status=0 AND B.deleted=0 AND B.id=A.accountid AND A.number =\"" ..destination_number .."\" LIMIT 1";
	Logger.notice("[CHECK_DID] Query :" .. query)
	assert(dbh:query(query, function(u)
		didinfo = u
		if did_localization ~= nil then
			did_localization['in_caller_id_originate'] = did_localization['in_caller_id_originate']:gsub(" ", "")
			didinfo['did_cid_translation'] = did_localization['in_caller_id_originate']
		else
			didinfo['did_cid_translation'] = ""
		end
	end))
	return didinfo
end

function check_call_type(userinfo, destination_number, number_loop, call_direction, config)
	local query = "SELECT A.id as id,A.number as did_number,B.id as accountid,B.number as account_code,B.pricelist_id as pricelist,A.number as did_number,A.connectcost,A.includedseconds,A.cost,A.inc,A.extensions,A.maxchannels,A.call_type,A.hg_type,A.city,A.province,A.init_inc,A.leg_timeout,A.status,A.country_id,A.call_type_vm_flag,A.reverse_rate,A.rate_group,C.`name` as price_name,A.area_code AS area_code, A.bypass_media AS bypass_media FROM "..TBL_DIDS.." AS A,"..TBL_USERS.." AS B,"..TBL_RATE_GROUP.." AS C WHERE B.status=0 AND B.deleted=0 AND A.rate_group = C.id AND B.id=A.accountid AND A.number =\""..destination_number.."\" LIMIT 1"
	Logger.notice("[CHECK_CALLTYPE] Query :" .. query)
	assert(dbh:query(query, function(u)
		didinfo = u
		if didinfo['reverse_rate'] == "1" then
			Logger.notice("[GET_DID_RATE] Reverse :" .. didinfo['reverse_rate'])
			Logger.notice("[GET_DID_PRICELIST_ID] Pricelist ID:" .. didinfo['rate_group'])
			Logger.notice("[GET_DID_PRICELIST_NAME] Pricelist Name:" .. didinfo['price_name'])
			Logger.notice("[GET_CUSTOMER_AREA_CODE] Area Code:" .. didinfo['area_code'])
			a = callerid_number
			area_number = string.sub(a, 1, 2)
			Logger.notice("[ENTRADA_AREA_CODE] Entrada Area Code:" .. area_number)
		end
	end))
	return didinfo
end

function check_did_reseller(destination_number, userinfo, config)
	local number_translation
	number_translation = config['did_global_translation']
	destination_number = do_number_translation(number_translation, destination_number)
	local query = "SELECT A.id as id, A.number AS number,B.cost AS cost,B.connectcost AS connectcost,B.includedseconds AS includedseconds,B.inc AS inc,A.city AS city,A.province,A.call_type,A.hg_type,A.extensions AS extensions,A.maxchannels AS maxchannels,A.init_inc,A.bypass_media AS bypass_media FROM "..TBL_DIDS.." AS A,"..TBL_RESELLER_PRICING.." as B WHERE A.number = \""..destination_number.."\"  AND B.type = '1' AND B.reseller_id = \""..userinfo['reseller_id'].."\" AND B.note =\""..destination_number.."\""
	Logger.notice("[CHECK_DID_RESELLER] Query :" .. query)
	assert(dbh:query(query, function(u)
		didinfo = u
	end))
	return didinfo
end

function check_local_call(destination_number, callerid_number)
	destination_number = check_local_number(destination_number, callerid_number)
	Logger.notice("[CHECK_LOCAL_CALL] Check local call for: " .. destination_number)
	local query = "SELECT sip_devices.id as sip_id, sip_devices.username as username,accounts.number as accountcode,sip_devices.accountid as accountid,accounts.did_cid_translation as did_cid_translation,sip_devices.codec as sip_codec FROM "..TBL_SIP_DEVICES.." as sip_devices,"..TBL_USERS.." as  accounts WHERE accounts.status=0 AND accounts.deleted=0 AND accounts.id=sip_devices.accountid AND sip_devices.username=\""..destination_number.."\" limit 1"
	Logger.notice("[CHECK_LOCAL_CALL] Query :" .. query)
	assert(dbh:query(query, function(u)
		sip2sipinfo = u
	end))
	return sip2sipinfo
end

function doauthentication(destination_number, from_ip)
	return ipauthentication(destination_number, from_ip)
end

function ipauthentication(destination_number, from_ip)
	local query = "SELECT "..TBL_IP_MAP..".*, (SELECT number FROM "..TBL_USERS.." where id=accountid AND status=0 AND deleted=0) AS account_code FROM "..TBL_IP_MAP.." WHERE INET_ATON(\""..from_ip.."\") BETWEEN(INET_ATON(SUBSTRING_INDEX(`ip`, '/', 1)) & 0xffffffff ^((0x1 <<(32 -  SUBSTRING_INDEX(`ip`, '/', -1))) -1 )) AND(INET_ATON(SUBSTRING_INDEX(`ip`, '/', 1)) |((0x100000000 >> SUBSTRING_INDEX(`ip`,'/', -1)) -1))  AND \""..destination_number.."\"  LIKE CONCAT(prefix,'%') ORDER BY LENGTH(prefix) DESC LIMIT 1"
	Logger.notice("[IPAUTHENTICATION] Query :" .. query)
	local ipinfo
	assert(dbh:query(query, function(u)
		ipinfo = u
		ipinfo['type'] = 'acl'
	end))
	return ipinfo
end

function doauthorization(field_type, accountcode, call_direction, destination_number, callerid_number, number_loop_str, config)
	local callstart = os.date("!%Y-%m-%d %H:%M:%S")
	local query = "SELECT * FROM "..TBL_USERS.." WHERE "..field_type.." = \""..accountcode.."\" AND deleted = 0 limit 1"
	Logger.notice("[DOAUTHORIZATION] Query :" .. query)

	userinfo = nil
	assert(dbh:query(query, function(u)
		userinfo = u
	end))

	if userinfo ~= nil then
		userinfo['ACCOUNT_ERROR'] = ''

		if userinfo['charge_per_min'] == nil or userinfo['charge_per_min'] == '' then
			userinfo['charge_per_min'] = 0
		end
		if call_direction == 'local' and userinfo['local_call'] == '0' and tonumber(userinfo['charge_per_min']) <= 0 then
			userinfo['balance'] = (tonumber(userinfo['posttoexternal']) == 1) and 0 or 100
		end

		if tonumber(userinfo['status']) ~= 0 or tonumber(userinfo['deleted']) ~= 0 then
			Logger.notice("[DOAUTHORIZATION] ["..accountcode.."] Account is either Deactive/Expire or deleted..!!")
			userinfo['ACCOUNT_ERROR'] = 'ACCOUNT_INACTIVE_DELETED'
			return userinfo
		end
		if userinfo['expiry'] < callstart then
			if userinfo['expiry'] ~= '0000-00-00 00:00:00' then
				Logger.notice("[DOAUTHORIZATION] ["..accountcode.."] Account is expired..!!")
				userinfo['ACCOUNT_ERROR'] = 'ACCOUNT_EXPIRE'
				return userinfo
			end
		end
		balance = get_balance(userinfo, '', config)
		if balance < 0 then
			Logger.notice("[DOAUTHORIZATION] ["..accountcode.."] Insufficent balance ("..balance..") to make calls..!!")
		else
			if (call_direction == 'outbound') then
				number_loop_str = number_loop(destination_number, 'blocked_patterns')
				if (check_blocked_prefix(userinfo, destination_number, number_loop_str, 'outbound') == "false") then
					Logger.notice("[DOAUTHORIZATION] [" .. accountcode .. "] You are not allowed to dial number..!!");
					userinfo['ACCOUNT_ERROR'] = 'DESTINATION_BLOCKED'
					return userinfo
				end
			end
			if (call_direction == 'inbound') then
				number_loop_str = number_loop(callerid_number, 'blocked_patterns')
				if (check_blocked_prefix(userinfo, callerid_number, number_loop_str, 'inbound') == "false") then
					Logger.notice("[DOAUTHORIZATION] [" .. accountcode .. "] You are not allowed to receive number..!!");
					userinfo['ACCOUNT_ERROR'] = 'DESTINATION_BLOCKED'
					return userinfo
				end
			end
		end
	else
		Logger.notice("[DOAUTHORIZATION] ["..accountcode.."] Account is either Deactive/Expire or deleted..!!")
		userinfo = {}
		userinfo['ACCOUNT_ERROR'] = 'ACCOUNT_INACTIVE_DELETED'
		return userinfo
	end
	if userinfo['first_used'] == "0000-00-00 00:00:00" then
		update_first_used_account(userinfo)
	end

	if is_call_international then
		userinfo = is_call_international(userinfo)
	end

	return userinfo
end

function check_local_number(destination_number, callerid_number)
	if is_valid_did(destination_number) then
		Logger.notice("[CHECK_LOCAL_NUMBER] DID Number: " .. destination_number)
		return destination_number
	end

	if string.len(destination_number) == 8 and callerid_number then
		Logger.notice("[CHECK_LOCAL_NUMBER] Local Call: " .. destination_number)
		local cn_callerid_number = string.sub(callerid_number, 1, 2)
		Logger.notice("[CHECK_LOCAL_NUMBER] CN of Caller Number: " .. cn_callerid_number)
		destination_number = cn_callerid_number .. destination_number
		Logger.notice("[CHECK_LOCAL_NUMBER] Formatted Number: " .. destination_number)
		_G['destination_number'] = destination_number
		Logger.notice("[CHECK_LOCAL_NUMBER] Global destination_number: " .. destination_number)
		_G['local_inbound_did'] = true
		Logger.notice("[CHECK_LOCAL_NUMBER] local_inbound_did = true")
	end

	return destination_number
end

function get_balance(userinfo, rates, config)
	local tmp_prefix = ''
	if get_international_balance_prefix then
		tmp_prefix = get_international_balance_prefix(userinfo)
	end

	balance = tonumber(userinfo['posttoexternal']) == 1 and tonumber(userinfo[tmp_prefix..'credit_limit']) or tonumber(userinfo[tmp_prefix..'balance'])
	if userinfo['type'] == '3' and call_direction == 'inbound' then
		balance = 10000
	end

	if fraud_check_balance_update then
		balance = fraud_check_balance_update(userinfo, balance, rates)
	end
	return balance
end

function update_first_used_account(userinfo)
	local callstart = os.date("!%Y-%m-%d %H:%M:%S")
	local query = "update "..TBL_USERS.." SET first_used = '"..callstart.."' where id = '"..userinfo['id'].."'"
	Logger.notice("[update_first_used_account] Query :" .. query)
	assert(dbh:query(query))
	return true
end

function check_blocked_prefix(userinfo,destination_number,number_loop,direction)
    local flag = "true"    
    if (direction == 'outbound') then    
    query = "SELECT * FROM "..TBL_BLOCK_PREFIX.." WHERE "..number_loop.." AND accountid = "..userinfo['id'].. " AND direction IN ('outbound', 'both') limit 1 ";    
    elseif (direction == 'inbound') then    
    query = "SELECT * FROM "..TBL_BLOCK_PREFIX.." WHERE "..number_loop.." AND accountid = "..userinfo['id'].. " AND direction IN ('inbound', 'both') limit 1 ";    
    else    
    query = "SELECT * FROM "..TBL_BLOCK_PREFIX.." WHERE "..number_loop.." AND accountid = "..userinfo['id'].. " limit 1 ";
    end    
    Logger.notice("[CHECK_BLOCKED_PREFIX] Query :" .. query)
    assert (dbh:query(query, function(u)
    	flag = "false"
    end))
    return flag
end

function get_localization(id, type)
	local localization = nil
	local query
	if type == "O" then
		query = "SELECT id,in_caller_id_originate,out_caller_id_originate,number_originate FROM "..TBL_LOCALIZATION.." WHERE id = "..id.." AND status=0 limit 1"
	elseif type == "T" then
		query = "SELECT id,out_caller_id_terminate,number_terminate FROM "..TBL_LOCALIZATION.." WHERE id=(SELECT localization_id from accounts where id = "..id..") AND status=0 limit 1"
	elseif type == "Trunk" then
		query = "SELECT id,out_caller_id_terminate,number_terminate,dst_base_cid FROM "..TBL_LOCALIZATION.." WHERE id=(SELECT localization_id from trunks where id = "..id..") AND status=0 limit 1"
	end
	Logger.notice("[GET_LOCALIZATION] Query :" .. query)
	assert(dbh:query(query, function(u)
		localization = u
	end))
	return localization
end

function do_number_translation(number_translation, destination_number)
	local tmp

	tmp = split(number_translation, ",")
	Logger.notice("[DONUMBERTRANSLATION] Before Localization CLI/DST : " .. number_translation)
	Logger.notice("[DONUMBERTRANSLATION] Before Localization CLI/DST : " .. destination_number)
	for tmp_key, tmp_value in pairs(tmp) do
		tmp_value = string.gsub(tmp_value, "\"", "")
		tmp_str = split(tmp_value, "/")
		if tmp_str[1] == '' or tmp_str[1] == nil then
			return destination_number
		end
		local prefix = string.sub(destination_number, 0, string.len(tmp_str[1]))
		if prefix == tmp_str[1] or tmp_str[1] == '*' then
			Logger.notice("[DONUMBERTRANSLATION] Before Localization CLI/DST : " .. destination_number)
			if tmp_str[2] ~= nil then
				if tmp_str[2] == '*' then
					destination_number = string.sub(destination_number, (string.len(tmp_str[1]) + 1))
				else
					if tmp_str[1] == '*' then
						destination_number = tmp_str[2] .. destination_number
					else
						destination_number = tmp_str[2] .. string.sub(destination_number, (string.len(tmp_str[1]) + 1))
					end
				end
			else
				destination_number = string.sub(destination_number, (string.len(tmp_str[1]) + 1))
			end
			Logger.notice("[DONUMBERTRANSLATION] After Localization CLI/DST : " .. destination_number)
		end
	end
	return destination_number
end

function get_call_maxlength(userinfo, destination_number, call_direction, number_loop, config, didinfo, callerid_number)
	local maxlength = 0
	local rates
	local rate_group
	local xml_rates
	local tmp = {}
	if call_direction == "inbound" and didinfo['reverse_rate'] ~= nil and didinfo['reverse_rate'] == "0" then
		Logger.notice("[DID PRICE] PRICE!!!")
		userinfo['pricelist_id'] = didinfo['rate_group']
		userinfo['reverse_rate'] = didinfo['reverse_rate']
	end
	Logger.notice("[get_call_maxlength] get_pricelists!!!")
	rate_group = get_pricelists(userinfo, destination_number, number_loop, call_direction)

	if rate_group == nil then
		Logger.notice("[FIND_MAXLENGTH] Rate group not found or Inactive!!!")
		return 'ORIGINATION_RATE_NOT_FOUND'
	end
	if (call_direction == "local" and config['free_inbound'] ~= nil) or (call_direction == "inbound" and userinfo['reverse_rate'] == nil) then
		Logger.notice("[call_direction] local!!!")
		rates = {}
		rates['pattern'] = '^'..destination_number..".*"
		if call_direction == "local" then
			rates['comment'] = "Local"
		else
			rates['comment'] = destination_number
		end
		rates['inc'] = 6
		rates['init_inc'] = 30
		rates['call_type'] = 0
		Logger.notice("[FIND_MAXLENGTH] free_inbound!!!" .. config['free_inbound'])
		rates['cost'] = userinfo['charge_per_min']
		Logger.notice("[FIND_MAXLENGTH] charge_per_min!!!" .. userinfo['charge_per_min'])
		if userinfo['charge_per_min'] == "" then
			rates['cost'] = 0
		end
		rates['includedseconds'] = 0
		rates['connectcost'] = 0
		rates['id'] = 0
		if didinfo ~= nil then
			rates['country_id'] = didinfo['country_id']
		else
			rates['country_id'] = 28
		end
		if rates['custom_call_type'] == '' or rates['custom_call_type'] == nil then
			rates['custom_call_type'] = "0"
		end
	else
		Logger.notice("[rates] get_rates!!!")
		rates = get_rates(userinfo, destination_number, number_loop, call_direction, config, callerid_number)
		if rates == nil then
			Logger.notice("[FIND_MAXLENGTH] Rates not found!!!")
			return 'ORIGINATION_RATE_NOT_FOUND'
		end
		if call_direction == "inbound" then
			rates['calltype'] = rates['call_type']
			rates['custom_call_type'] = rates['call_type']
			if rates['city'] ~= '' and rates['province'] ~= "" and rates['province'] ~= nil then
				rates['comment'] = rates['city'] .. " " .. rates['province']
			else
				rates['comment'] = destination_number
			end
		end
		if rates['custom_call_type'] == nil and call_direction == "local" then
			rates['custom_call_type'] = "Local"
		end
		if tonumber(rate_group['markup']) > 0 then
			Logger.notice("Markup : " .. rate_group['markup'])
			rates['cost'] = rates['cost'] + ((rate_group['markup'] * rates['cost']) / 100)
		end
		if rates['calltype'] ~= '' and rates['call_type'] ~= "" then
			calltype = rates['calltype']
			userinfo['calltype'] = rates['calltype']
			Logger.notice("CallType : " .. rates['calltype'])
		end
		if rates['custom_call_type'] == '' or rates['custom_call_type'] == nil then
			rates['custom_call_type'] = "0"
		end
		Logger.notice("=============== Rates Information get_call_maxlength ===================")
		Logger.notice("ID : " .. rates['id'])
		Logger.notice("Connectcost : " .. rates['connectcost'])
		Logger.notice("Includedseconds : " .. rates['includedseconds'])
		Logger.notice("Cost : " .. rates['cost'])
		Logger.notice("Comment : " .. rates['comment'])
		Logger.notice("CallType : " .. rates['call_type'])
		Logger.notice("Pattern : " .. rates['pattern'])
		if rate_group['check_carrier'] ~= nil then
			Logger.notice("check_carrier: " .. rate_group['check_carrier'])
			if rate_group['check_carrier'] == '1' then
				rates['check_carrier'] = rate_group['check_carrier']
			end
		end
		if rate_group['routing_type'] ~= nil and rate_group['routing_type'] ~= '' then
			rates['routing_type'] = rate_group['routing_type']
		end
		if rates['custom_call_type'] ~= nil then
			Logger.notice("Custom CallType: " .. rates['custom_call_type'])
		end
		Logger.notice("Country Id : " .. rates['country_id'])
		Logger.notice("Accid : " .. userinfo['id'])
		if rates['trunk_id'] ~= nil then
			Logger.notice("Trunk ID: " .. rates['trunk_id'])
		end
		if rate_group['routing_type'] ~= nil then
			Logger.notice("Routing type: " .. rate_group['routing_type'])
			rates['routing_type'] = rate_group['routing_type']
		else
			if rates['routing_type'] ~= nil then
				rate_group['routing_type'] = rates['routing_type']
			else
				rates['routing_type'] = '0'
				rate_group['routing_type'] = '0'
			end
		end
		if rates['routing_type'] ~= nil then
			Logger.notice("Routing type: " .. rates['routing_type'])
		end
		Logger.notice("================================================================")
	end
	if call_direction == "inbound" then
		if didinfo['accountid'] == nil then
			didinfo['accountid'] = userinfo['id']
		end
		if didinfo['reverse_rate'] == '0' then
			if didinfo['idCadup'] == nil then
				didinfo['idCadup'] = 0
			end
			if didinfo['carrier_rn1'] == nil then
				didinfo['carrier_rn1'] = 0
			end
			call_type_rate = didinfo['rate_group']
			if call_type_rate == nil then
				Logger.notice("[FIND_DID_RATE] DID Rate group not found or Inactive!!!")
				return 'ORIGINATION_RATE_NOT_FOUND'
			end
			if didinfo['pattern'] == nil then
				didinfo['pattern'] = callerid_number
			end
			xml_rates = "ID:"..rates['id'].."|CODE:"..rates['pattern'].."|DESTINATION:"..rates['custom_call_type'].."|CONNECTIONCOST:"..rates['connectcost'].."|INCLUDEDSECONDS:"..rates['includedseconds'].."|IDCADUP:"..didinfo['idCadup'].."|CT:"..didinfo['call_type'].."|COST:"..rates['cost'].."|INC:"..rates['inc'].."|INITIALBLOCK:"..rates['init_inc'].."|RATEGROUP:"..rate_group['id'].."|MARKUP:"..rate_group['markup'].."|CI:"..rates['country_id'].."|ACCID:"..didinfo['accountid']
			Logger.notice("[xml_rates-586] xml_rates " .. xml_rates)
		else
			xml_rates = "ID:"..rates['id'].."|CODE:"..rates['pattern'].."|DESTINATION:"..rates['custom_call_type'].."|CONNECTIONCOST:"..rates['connectcost'].."|INCLUDEDSECONDS:"..rates['includedseconds'].."|CT:"..rates['custom_call_type'].."|COST:"..rates['cost'].."|INC:"..rates['inc'].."|INITIALBLOCK:"..rates['init_inc'].."|RATEGROUP:"..rate_group['id'].."|MARKUP:"..rate_group['markup'].."|CI:"..rates['country_id'].."|ACCID:"..didinfo['accountid']
			Logger.notice("[xml_rates-589] xml_rates " .. xml_rates)
		end
	else
		if tonumber(rates['inc']) == 0 or rates['inc'] == "" then
			rates['inc'] = rate_group['inc']
		end
		if tonumber(rates['init_inc']) == 0 or rates['init_inc'] == "" then
			rates['init_inc'] = rate_group['initially_increment']
		end
		if rates['init_inc'] == nil then
			rates['init_inc'] = 0
		end
		if rates['custom_call_type'] == nil then
			Logger.notice("[FIND_DID_RATE] DID Rate group not found or Inactive!!!")
			rates['custom_call_type'] = rates['call_type']
		end
		if rates['country_id'] == nil then
			rates['country_id'] = 28
		end
		xml_rates = "ID:"..rates['id'].."|CODE:"..rates['pattern'].."|DESTINATION:"..rates['comment'].."|CONNECTIONCOST:"..rates['connectcost'].."|INCLUDEDSECONDS:"..rates['includedseconds'].."|CT:"..rates['custom_call_type'].."|COST:"..rates['cost'].."|INC:"..rates['inc'].."|INITIALBLOCK:"..rates['init_inc'].."|RATEGROUP:"..rate_group['id'].."|MARKUP:"..rate_group['markup'].."|CI:"..rates['country_id'].."|ACCID:"..userinfo['id']
		Logger.notice("[xml_rates-603] xml_rates " .. xml_rates)
	end

	balance = get_balance(userinfo, '', config)
	Logger.notice("[FIND_MAXLENGTH] Your " .. balance .. " balance Accountid " .. userinfo['id'] .. " !!!")
	if balance < (rates['connectcost'] + rates['cost']) and tonumber(package_id) == 0 then
		Logger.notice("[FIND_MAXLENGTH] Your balance is not sufficent to dial " .. destination_number .. " !!!")
		return 'NO_SUFFICIENT_FUND'
	end
	if tonumber(rates['cost']) > 0 then
		maxlength = (balance - rates['connectcost']) / rates['cost']
		if config['call_max_length'] and (tonumber(maxlength) > tonumber(config['call_max_length'])) then
			maxlength = config['call_max_length']
			Logger.notice("[FIND_MAXLENGTH] Limiting call to config max length " .. maxlength .. " mins!")
		end
	else
		Logger.notice("[FIND_MAXLENGTH] Call is free - assigning max length!!! :: " .. config['max_free_length'])
		if config['call_max_length'] and (tonumber(config['max_free_length']) > tonumber(config['call_max_length'])) then
			maxlength = config['call_max_length']
		else
			maxlength = config['max_free_length']
		end
	end

	tmp[1] = maxlength
	tmp[2] = rates
	tmp[3] = xml_rates

	return tmp
end

function get_rates(userinfo, destination_number, number_loop, call_direction, config, callerid_number)
	local rates_info
	Logger.notice("[GET_RATES] call_direction :" .. call_direction)
	if call_direction == "inbound" and userinfo['reverse_rate'] ~= nil and userinfo['reverse_rate'] ~= "0" then
		Logger.notice("[GET_RATES] callerid_number :" .. callerid_number)
		rates_info = check_did(destination_number, config, callerid_number)
	else
		local query = "SELECT "..TBL_CALL_TYPE..".call_type as calltype, "..TBL_ORIGINATION_RATES..".call_type as custom_call_type, "..TBL_ORIGINATION_RATES..".* FROM "..TBL_ORIGINATION_RATES..","..TBL_CALL_TYPE.." WHERE ("..TBL_ORIGINATION_RATES..".call_type = "..TBL_CALL_TYPE..".id  OR routes.comment = calltype.call_type) AND "..number_loop.." AND "..TBL_ORIGINATION_RATES..".status = 0 AND (pricelist_id = "..userinfo['pricelist_id'].." OR accountid="..userinfo['id']..")  ORDER BY accountid DESC,LENGTH(pattern) DESC,cost DESC LIMIT 1"
		Logger.notice("[GET_RATES] Query :" .. query)
		assert(dbh:query(query, function(u)
			rates_info = u
			calltype_custom = rates_info['custom_call_type']
			calltype = rates_info['calltype']
			rates_info['call_type'] = calltype
			rates_info['call_type_custom'] = calltype_custom
			Logger.notice("[GET_RATES] CallType Custom :" .. calltype_custom)
			Logger.notice("[GET_RATES] calltype Rates:" .. calltype)
			Logger.notice("[GET_RATES] rates_info :" .. rates_info['call_type'])
			Logger.notice("[GET_RATES] rates_info_custom :" .. rates_info['call_type_custom'])
		end))
	end
	return rates_info
end

function get_pricelists(userinfo, destination_number, number_loop, call_direction)
	local query = "select * from "..TBL_RATE_GROUP.." WHERE id = "..userinfo['pricelist_id'].." AND status = 0"
	local rategroup_info
	Logger.notice("[GET_PRICELIST_INFO] Query :" .. query)
	assert(dbh:query(query, function(u)
		rategroup_info = u
	end))
	if rategroup_info ~= nil then
		if rategroup_info['initially_increment'] == nil or rategroup_info['initially_increment'] == '0' then
			rategroup_info['initially_increment'] = 1
		end
		if rategroup_info['inc'] == nil or rategroup_info['inc'] == '0' then
			rategroup_info['inc'] = 1
		end
	end
	return rategroup_info
end

function package_calculation(destination_number, userinfo, call_direction)
	local package_act_id = userinfo['id']
	if call_direction == 'inbound' then
		Logger.notice("[GET_PACKAGE_INFO] call_direction :" .. call_direction)
		if didinfo and didinfo['accountid'] ~= '' then
			Logger.notice("[GET_PACKAGE_INFO] DID_ACCOUNTID :" .. didinfo['accountid'])
			package_act_id = didinfo['accountid']
		end
	end
	local tmp = {}
	local remaining_sec
	local package_maxlength
	custom_destination = number_loop(destination_number, "patterns")
	local package_info_arr = {}
	local i = 1
	local query = "SELECT *,P.id as package_id,P.product_id as product_id FROM "..TBL_PACKAGE_CALL.." as P inner join "..TBL_PACKAGE_PATTERN.." as PKGPTR on P.product_id = PKGPTR.product_id WHERE "..custom_destination.." AND accountid = "..package_act_id.." and is_terminated = 0 ORDER BY LENGTH(PKGPTR.patterns) DESC"
	Logger.notice("[GET_PACKAGE_INFO] Query :" .. query)
	assert(dbh:query(query, function(u)
		package_info_arr[i] = u
		i = i + 1
	end))

	if package_info_arr and package_info_arr ~= nil then
		for package_info_key, package_info in pairs(package_info_arr) do
			local used_seconds = 0
			Logger.notice("Package ID : " .. package_info['package_id'])
			Logger.notice("Product ID : " .. package_info['product_id'])
			Logger.notice("Package Type : " .. package_info['applicable_for'] .. " Call Direction : " .. call_direction .. " [0:inbound,1:outbound,2:both]")
			if (package_info['applicable_for'] == "0" and call_direction == "inbound") or (package_info['applicable_for'] == "1" and call_direction == "outbound") or (package_info['applicable_for'] == "2") then
				local counter_info = get_counters(userinfo, package_info, package_act_id)
				if counter_info == nil or counter_info['used_seconds'] == nil then
					used_seconds = 0
				else
					used_seconds = counter_info['used_seconds']
				end
				remaining_minutes = (tonumber(package_info['free_minutes'] * 60) - tonumber(used_seconds)) / 60
				Logger.notice("Remaining minutes : " .. remaining_minutes)
				if remaining_minutes > 0 then
					if tonumber(balance) <= 0 then
						userinfo['balance'] = 100
						userinfo['credit_limit'] = 200
						Logger.notice("Actual Balance : " .. balance)
						Logger.notice("Allocating static balance for package calls, Balance : " .. userinfo['balance'] .. ", Credit limit : " .. userinfo['credit_limit'])
					end
					userinfo['ACCOUNT_ERROR'] = ''
					package_maxlength = remaining_minutes
					package_id = package_info['package_id']
					Logger.notice("package_id : " .. package_id)
					break
				end
			end
		end
	end
	tmp[1] = userinfo
	tmp[2] = package_maxlength
	return tmp
end

function get_counters(userinfo, package_info, package_act_id)
	local counter_info
	local query_counter = "SELECT used_seconds FROM "..TBL_COUNTERS.."  WHERE  accountid = "..package_act_id.." AND package_id = "..package_info['package_id'].." AND status=1 LIMIT 1"
	Logger.notice("[GET_COUNTER_INFO] Query :" .. query_counter)
	assert(dbh:query(query_counter, function(u)
		counter_info = u
	end))
	return counter_info
end

function get_carrier_rates(destination_number, number_loop_str, ratecard_id, rate_carrier_id, check_carrier, routing_type, trunk_id)
	local carrier_rates = {}
	if trunk_id == '' or trunk_id == nil then
		local trunk_id = 0
	end
	local query
	if check_carrier ~= '' or check_carrier ~= nil then
		if check_carrier == "1" then
			Logger.notice("[GET_CARRIER_RATES_TRUNKS] check_carrier:" .. check_carrier)
		end
	end
	Logger.notice("[GET_CARRIER_RATES_TRUNKS] rate_carrier_id:" .. rate_carrier_id)
	Logger.notice("[GET_CARRIER_RATES_TRUNKS] trunk_id:" .. trunk_id)
	if tonumber(routing_type) <= 3 then
		Logger.notice("[GET_CARRIER_RATES_TRUNKS] routing_type default:" .. routing_type)
		query = "SELECT TK.id as trunk_id,TK.name as trunk_name,TK.sip_cid_type,TK.precedence as trunk_priority,TR.precedence as rate_priority,TK.codec,GW.name as path,GW.dialplan_variable,GW.caller_id_type,GW.caller_id_number,TK.tech, TK.dialed_modify as failover_route,TK.provider_id,TR.init_inc,TK.status,TK.maxchannels,TK.cps,TK.leg_timeout,TR.pattern,TR.id as outbound_route_id,TR.connectcost,TR.call_type,TR.comment,TR.includedseconds,TR.cost,TR.inc,TR.prepend,TR.strip,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id) as path1,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id1) as path2 FROM (select * from "..TBL_TERMINATION_RATES.." order by LENGTH (pattern) DESC) as TR, "..TBL_TRUNKS.." as TK,"..TBL_GATEWAYS.." as GW WHERE GW.status=0 AND GW.id= TK.gateway_id AND TK.status=0 AND TK.id= TR.trunk_id AND "..number_loop_str.." AND TR.status = 0 "
	elseif routing_type == "4" then
		Logger.notice("[GET_CARRIER_RATES_TRUNKS] routing_type 4 Carrier:" .. routing_type)
		query = "SELECT TK.id as trunk_id,TK.name as trunk_name,TK.sip_cid_type,TK.precedence as trunk_priority,TR.precedence as rate_priority,TK.codec,GW.name as path,GW.dialplan_variable,GW.caller_id_type,GW.caller_id_number,TK.dialed_modify as failover_route,TK.tech,TK.carrier_id,TK.provider_id,TR.init_inc,TK.status,TK.maxchannels,TK.cps,TK.leg_timeout,TR.pattern,TR.id as outbound_route_id,TR.connectcost,TR.call_type,TR.comment,TR.includedseconds,TR.cost,TR.inc,TR.prepend,TR.strip,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id) as path1,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id1) as path2 FROM (select * from "..TBL_TERMINATION_RATES.." order by LENGTH (pattern) DESC) as TR, "..TBL_TRUNKS.." as TK,"..TBL_GATEWAYS.." as GW WHERE GW.status=0 AND GW.id= TK.gateway_id AND TK.status=0 AND TK.id= TR.trunk_id AND "..number_loop_str.." AND TR.status = 0 "
	else
		Logger.notice("[GET_CARRIER_RATES_TRUNKS] routing_type :" .. routing_type)
		query = "SELECT TK.id as trunk_id,TK.name as trunk_name,TK.sip_cid_type,TK.precedence as trunk_priority,TR.precedence as rate_priority,TK.codec,GW.name as path,GW.dialplan_variable,GW.caller_id_type,GW.caller_id_number,TK.tech, TK.dialed_modify as failover_route,TK.provider_id,TR.init_inc,TK.status,TK.maxchannels,TK.cps,TK.leg_timeout,TR.pattern,TR.id as outbound_route_id,TR.connectcost,TR.comment,TR.call_type,TR.includedseconds,TR.cost,TR.inc,TR.prepend,TR.strip,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id) as path1,(select name from "..TBL_GATEWAYS.." where status=0 AND id = TK.failover_gateway_id1) as path2 FROM "..TBL_TERMINATION_RATES.." as TR,"..TBL_TRUNKS.." as TK,"..TBL_GATEWAYS.." as GW WHERE GW.status=0 AND GW.id= TK.gateway_id AND TK.status=0 AND TK.id= TR.trunk_id AND "..number_loop_str.." AND TR.status = 0 "
	end
	if rate_carrier_id and rate_carrier_id ~= nil and tonumber(rate_carrier_id) ~= 0 then
		if tonumber(rate_carrier_id) == 0 and tonumber(routing_type) <= 3 then
			query = query .. " AND TR.trunk_id is not null "
		elseif tonumber(rate_carrier_id) == 0 and tonumber(routing_type) == 4 then
			query = query .. " AND TR.trunk_id is not null AND TK.dialed_modify = 1"
		elseif string.len(rate_carrier_id) == 5 and tonumber(routing_type) == 4 then
			query = query .. " AND (TK.tech = " .. rate_carrier_id .. " OR TK.dialed_modify = 1)"
		else
			query = query .. " AND TK.id IN (" .. trunk_id .. ") "
		end
	else
		trunk_ids = {}
		local query_trunks = "SELECT GROUP_CONCAT(trunk_id) as ids FROM "..TBL_ROUTING.." WHERE pricelist_id=" .. ratecard_id .. " ORDER by id asc"
		Logger.notice("[GET_CARRIER_RATES_TRUNKS] Query :" .. query_trunks)
		assert(dbh:query(query_trunks, function(u)
			trunk_ids = u
		end))
		if trunk_ids['ids'] == "" or trunk_ids['ids'] == nil then
			trunk_ids['ids'] = 0
		end
		if trunk_ids['ids'] == 0 then
			query = query .. " AND TR.trunk_id is not null"
		else
			query = query .. " AND TR.trunk_id IN (" .. trunk_ids['ids'] .. ")"
		end
	end
	if routing_type == "1" then
		query = query .. " ORDER by TR.cost ASC,TR.precedence ASC, TK.precedence"
	elseif routing_type == "2" then
		query = query .. " ORDER by LENGTH (pattern) DESC,TR.precedence DESC, TK.precedence DESC"
	elseif routing_type == "4" then
		query = query .. " ORDER BY CASE WHEN TK.tech = " .. rate_carrier_id .. " THEN 0 ELSE 1 END, LENGTH (pattern) DESC,TK.tech DESC, TR.cost ASC,TR.precedence ASC, TK.precedence"
	else
		query = query .. " ORDER by LENGTH (pattern) DESC,TR.cost ASC,TR.precedence ASC, TK.precedence"
	end
	Logger.notice("[GET_CARRIER_RATES_TRUNKS] Query :" .. query)
	local i = 1
	local carrier_ignore_duplicate = {}
	assert(dbh:query(query, function(u)
		if carrier_ignore_duplicate[u['trunk_id']] == nil then
			carrier_rates[i] = u
			i = i + 1
			carrier_ignore_duplicate[u['trunk_id']] = true
		end
	end))
	return carrier_rates
end

function get_carrier_out(userinfo, cn_dest_number, carrier_dest_number)
	Logger.notice("[FUNCTION - get_carrier_out]")
	carrier_destination = number_loop(carrier_dest_number, "pattern")
	Logger.notice("[GET_CARRIER_OUT] carrier_destination :" .. carrier_destination)
	Logger.notice("[GET_CARRIER_OUT] cn_dest_number :" .. cn_dest_number)
	Logger.notice("[GET_CARRIER_OUT] carrier_dest_number :" .. carrier_dest_number)
	local carrier_info
	local query = "SELECT view_carriers.idCadup,view_carriers.nomePrestadora,view_carriers.rn1,carrier_routing.id as carrier_route_id,view_carriers.tipo,view_carriers.cn,view_carriers.prefixo,view_carriers.cn_prefix,view_carriers.pattern,view_carriers.route_pattern,view_carriers.nomeLocalidade,view_carriers.areaLocal,view_carriers.codArea,view_carriers.uf,carrier_routing.reseller_id,carrier_routing.accountid,carrier_routing.call_count,carrier_routing.status,carrier_routing.carrier_name,carrier_routing.carrier_id,carrier_routing.carrier_rn1 FROM "..TBL_CARRIER_RATES..", "..TBL_CARRIER_ROUTES.." WHERE "..TBL_CARRIER_RATES..".rn1 = "..TBL_CARRIER_ROUTES..".carrier_rn1 AND "..TBL_CARRIER_RATES..".nomePrestadora = "..TBL_CARRIER_ROUTES..".carrier_name AND cn = "..cn_dest_number.." AND "..carrier_destination.." GROUP BY carrier_id ORDER BY cn_prefix LIMIT 1"
	Logger.notice("[GET_CARRIER_OUT] Query :" .. query)
	assert(dbh:query(query, function(u)
		carrier_info = u
	end))
	return carrier_info
end

function get_carrier_in(didinfo, cn_dest_number, carrier_dest_number)
	Logger.notice("[FUNCTION - get_carrier_in]")
	carrier_destination = number_loop(carrier_dest_number, "pattern")
	Logger.notice("[GET_CARRIER_IN] carrier_destination :" .. carrier_destination)
	Logger.notice("[GET_CARRIER_IN] cn_dest_number :" .. cn_dest_number)
	Logger.notice("[GET_CARRIER_IN] carrier_dest_number :" .. carrier_dest_number)
	local carrier_info
	local query = "SELECT view_carriers.idCadup,view_carriers.nomePrestadora,view_carriers.rn1,carrier_routing.id as carrier_route_id,view_carriers.tipo,view_carriers.cn,view_carriers.prefixo,view_carriers.cn_prefix,view_carriers.pattern,view_carriers.route_pattern,view_carriers.nomeLocalidade,view_carriers.areaLocal,view_carriers.codArea,view_carriers.uf,carrier_routing.reseller_id,carrier_routing.accountid,carrier_routing.call_count,carrier_routing.status,carrier_routing.carrier_name,carrier_routing.carrier_id,carrier_routing.carrier_rn1 FROM "..TBL_CARRIER_RATES..", "..TBL_CARRIER_ROUTES.." WHERE "..TBL_CARRIER_RATES..".rn1 = "..TBL_CARRIER_ROUTES..".carrier_rn1 AND "..TBL_CARRIER_RATES..".nomePrestadora = "..TBL_CARRIER_ROUTES..".carrier_name AND cn = "..cn_dest_number.." AND "..carrier_destination.." GROUP BY carrier_id ORDER BY cn_prefix LIMIT 1"
	Logger.notice("[GET_CARRIER_IN] Query :" .. query)
	assert(dbh:query(query, function(u)
		carrier_info = u
	end))
	return carrier_info
end

function get_override_callerid(userinfo, callerid_name, callerid_number)
	local callerid = {}
	local query = "SELECT callerid_name as cid_name,callerid_number as cid_number,accountid FROM "..TBL_ACCOUNTS_CALLERID.." WHERE accountid = "..userinfo['id'].." AND status=0 LIMIT 1"
	Logger.notice("[GET_OVERRIDE_CALLERID] Query :" .. query)
	assert(dbh:query(query, function(u)
		callerid = u
	end))
	if callerid['cid_number'] ~= nil and callerid['cid_number'] ~= '' then
		callerid['cid_number'] = callerid['cid_number']
		callerid['cid_name'] = callerid['cid_name']
	end
	return callerid
end

function number_loop(destination_number, code, skip_tild)
	local number_len = string.len(destination_number)
	if code == nil then
		code = "pattern"
	end
	number_loop_str = '('
	while number_len > 0 do
		number_loop_str = number_loop_str .. code .. " = '"
		if skip_tild == nil then
			number_loop_str = number_loop_str .. "^"
		end
		number_loop_str = number_loop_str .. string.sub(destination_number, 0, number_len)
		if skip_tild == nil then
			number_loop_str = number_loop_str .. ".*"
		end
		if skip_tild == "*" then
			number_loop_str = number_loop_str .. "*"
		end
		number_loop_str = number_loop_str .. "' OR "
		number_len = number_len - 1
	end
	number_loop_str = number_loop_str .. code .. " ='--')"
	return number_loop_str
end

function plus_destination_number(destination_number)
	destination_number = destination_number:gsub("%s+", "")
	local dnumber = destination_number
	local dfirst = string.match(dnumber, "^(.)")
	if dfirst == "+" then
		dnumber = "\\" .. dnumber
	end
	return dnumber
end

function cn_destination_number(destination_number)
	destination_number = destination_number:gsub("%s+", "")
	local dnumber = destination_number
	local dfirst = string.match(dnumber, "^(.)")
	if dfirst ~= "0" then
		dnumber = "0" .. dnumber
	else
		dnumber = dnumber
	end
	return dnumber
end

function regex_cmd(destination_number, pattern, replace)
	Logger.notice("[regex_pattern] FUNCTION")
	Logger.notice("[regex_pattern] DESTINATION_NUMBER: " .. destination_number)
	Logger.notice("[regex_pattern] PATTERN: " .. pattern)
	local rgx_pattern = pattern
	local regex_number = destination_number:gsub("%s+", "")
	local api = freeswitch.API()
	if rgx_pattern == nil then
		rgx_pattern = "unknown"
	end
	if replace == nil then
		rgx_replace = ""
	else
		rgx_replace = "|$" .. replace
	end
	if rgx_pattern == "num_pattern_0" then
		regex_pattern = "^0([1-9][1-9])(\\d{7,20})$"
	elseif rgx_pattern == "num_pattern_cn" then
		regex_pattern = "^([1-9][1-9])(\\d{7,8})$"
	elseif rgx_pattern == "unknown" then
		regex_pattern = "^(?:55)?(\\d{2})([2-9]\\d{3,4})(\\d{4})$"
	elseif rgx_pattern == "num_local_regex" then
		regex_pattern = "^(\\d{4,5})(\\d{4})$"
	elseif rgx_pattern == "num_regex_pattern" then
		regex_pattern = "^(0([1-9][1-9]))(\\d{7,20})$"
	elseif rgx_pattern == "num_pattern_local" then
		regex_pattern = "^([2-9]\\d{3})(\\d{4})$"
	elseif rgx_pattern == "num_pattern_any" then
		regex_pattern = "^(\\d+)$"
	else
		regex_pattern = "^([0-9]\\d{1,2})([2-9]\\d{3,4})(\\d{4})$"
	end
	if api:execute("regex", "" .. regex_number .. "|" .. regex_pattern) == "true" then
		cmd = "" .. regex_number .. "|" .. regex_pattern .. "" .. rgx_replace
		local result = trim(api:execute("regex", cmd))
		Logger.notice("[regex_pattern] regex_number: " .. regex_number)
		Logger.notice("[regex_pattern] regex_pattern: " .. regex_pattern)
		Logger.notice("[regex_pattern] rgx_replace: " .. rgx_replace)
		Logger.notice("[regex_pattern] regex: " .. cmd)
		Logger.notice("[regex_pattern] result: " .. result)
		regex_number = result
	else
		regex_number = "false"
		Logger.notice("[regex_pattern] regex_number: " .. regex_number)
	end
	return regex_number
end

function get_parentid(userinfoid)
	local parentid = 0
	local query = "SELECT reseller_id FROM "..TBL_USERS.." WHERE id = " .. userinfoid
	Logger.notice("[GET RESELLERID] Query :" .. query)
	assert(dbh:query(query, function(u)
		parent = u
		if parent['reseller_id'] ~= nil and parent['reseller_id'] ~= '' then
			parentid = tonumber(parent['reseller_id'])
		end
	end))
	return parentid
end

function load_addon_list()
	local query = "SELECT package_name FROM addons"
	Logger.notice("[LOAD_ADDON_CONF] Query :" .. query)
	local addon_list = {}
	assert(dbh:query(query, function(u)
		addon_list[u.package_name] = u.package_name
	end))
	return addon_list
end

function get_sip_codec(sip_user_name)
	local query = "SELECT sip_devices.codec as sip_codec  FROM "..TBL_SIP_DEVICES.." as sip_devices,"..TBL_USERS.." as  accounts WHERE accounts.status=0 AND accounts.deleted=0 AND accounts.id=sip_devices.accountid AND sip_devices.username=\""..sip_user_name.."\" limit 1"
	Logger.notice("[sip_codec_for_outbound] Query :" .. query)
	local sip_codec_for_outbound
	assert(dbh:query(query, function(u)
		sip_codec_for_outbound = u
	end))
	if sip_codec_for_outbound == nil then
		return ""
	else
		return sip_codec_for_outbound['sip_codec']
	end
end

function is_valid_did(num)
	local check = nil
	Logger.notice("[IS_VALID_DID] num :" .. num)
	local query = "SELECT id FROM "..TBL_DIDS.." WHERE number=\"" .. num .. "\" LIMIT 1"
	Logger.warning("[IS_VALID_DID] Query :" .. query)
	dbh:query(query, function(u)
		check = u
	end)
	return check ~= nil
end