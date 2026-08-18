#!/usr/bin/lua
-- LOGGING
LOGLEVEL = "notice"
-- PROGNAME
PROGNAME = "Flux_SIP-Devices"
-- Global functions
local sound_path = "/usr/share/freeswitch/sounds/pt/BR/karina/"
-- Load config file
dofile("/var/lib/flux/flux.lua");
local script_path="";
if(SCRIPT_PATH ~= nil and SCRIPT_PATH ~='')then
	script_path = SCRIPT_PATH;
else
	script_path = "/usr/share/freeswitch/scripts/flux/";
end
-- Load CONSTANT file
dofile("/usr/share/freeswitch/scripts/flux/constant.lua");
-- Load json file to decode json string
JSON = (loadfile (script_path .."lib/JSON.lua"))();

-- Load utility file
dofile(script_path.."lib/flux.utility.lua");

-- Include Logger file to print messages
dofile(script_path.."lib/flux.logger.lua");

-- Include database connection file to connect database
dofile(script_path.."lib/flux.db.lua");

-- Call database connection
db_connect()

-- Include common functions file
dofile(script_path.."lib/flux.functions.lua");
dofile(script_path.."lib/flux.callingcard.functions.lua")

addon_path = script_path.."../addons.lua"
dofile(addon_path);

config = load_conf()

function logger(message)
  freeswitch.console_log(LOGLEVEL,"["..PROGNAME.."] "..message.."\n")
end

if session:ready() then
	on_busy_flag                  = session:getVariable("on_busy_flag");
	on_busy_destination           = session:getVariable("on_busy_destination");
	on_busy_destination_type      = session:getVariable("on_busy_destination_type")      or "2";
	no_answer_flag                = session:getVariable("no_answer_flag");
	no_answer_destination         = session:getVariable("no_answer_destination");
	no_answer_destination_type    = session:getVariable("no_answer_destination_type")    or "2";
	not_register_flag             = session:getVariable("not_register_flag");
	not_register_destination      = session:getVariable("not_register_destination");
	not_register_destination_type = session:getVariable("not_register_destination_type") or "2";
	opensips_flag                 = session:getVariable("opensips_flag");
	opensips_domain               = session:getVariable("opensips_domain");
	leg_timeout                   = session:getVariable("leg_timeout");
	sip_destination_number        = session:getVariable("sip_destination_number") or "";
	last_disposition              = session:getVariable("originate_disposition") or "REQUESTED_CHAN_UNAVAIL";
	last_sip_rates                = session:getVariable("origination_rates");
	variable_sip_to_host          = session:getVariable("variable_sip_to_host");
	variable_sip_to_port          = session:getVariable("internal_sip_port");
	userinfo_id                   = session:getVariable("userinfo_id");
	did_number                    = session:getVariable("did_number");
	user_domain                   = session:getVariable("user_domain");

	logger("This Is Last Dispositions ["..last_disposition.."] This Is SIP Number : ["..tostring(sip_destination_number).."]")

	-- Nenhuma bridge foi tentada (ex: loop DID cancelado antes da originacao)
	if last_disposition == "" then
		logger("Sem disposicao de originacao, encerrando")
		return
	end

	local routing_destination = ''
	local permission_flag     = ''
	local routing_type        = '2'  -- padrao: Ramal

	if last_disposition == "USER_NOT_REGISTERED" or last_disposition == "UNALLOCATED_NUMBER" then
		routing_destination = not_register_destination
		permission_flag     = not_register_flag
		routing_type        = not_register_destination_type
	end
	if last_disposition == "USER_BUSY" then
		routing_destination = on_busy_destination
		permission_flag     = on_busy_flag
		routing_type        = on_busy_destination_type
	end
	if last_disposition == "NO_USER_RESPONSE"
	or last_disposition == "CALL_REJECTED"
	or last_disposition == "NO_ANSWER"
	or last_disposition == "SUBSCRIBER_ABSENT"
	or last_disposition == "NORMAL_TEMPORARY_FAILURE"
	or last_disposition == "ALLOTTED_TIMEOUT" then
		routing_destination = no_answer_destination
		permission_flag     = no_answer_flag
		routing_type        = no_answer_destination_type
	end

	if (permission_flag == '' and sip_destination_number ~= '') then
		routing_destination = sip_destination_number
		permission_flag     = 1
		routing_type        = '2'  -- sip_destination_number e sempre Ramal
	end

	if(routing_destination ~= '' and permission_flag ~= '' and tonumber(permission_flag) == 0) then
		if tonumber(routing_type) == 3 then
			-- Verificar loop de encaminhamento DID
			local fwd_flag = session:getVariable("sip_routing_did_forwarded")
			if fwd_flag ~= nil and fwd_flag ~= '' then
				logger("Loop de encaminhamento DID detectado no fail-over, cancelando: "..tostring(routing_destination))
			else
				local did_extensions = nil
				local query = "SELECT extensions FROM dids"
				            .." WHERE number = '"..tostring(routing_destination).."'"
				            .." AND accountid = "..tostring(userinfo_id)
				            .." LIMIT 1"
				logger("Fail-over DID query: "..query)
				assert(dbh:query(query, function(u)
					did_extensions = u['extensions']
				end))
				if did_extensions ~= nil and did_extensions ~= '' then
					logger("Fail-over para DID: "..tostring(routing_destination).." -> "..tostring(did_extensions))
					session:setVariable("sip_routing_did_forwarded", "1")
					session:execute("transfer", did_extensions.." XML default")
					return
				else
					logger("DID nao encontrado, fail-over ignorado: "..tostring(routing_destination))
				end
			end
		elseif tonumber(routing_type) == 1 then
			-- PSTN: transfer para roteamento externo
			logger("Fail-over para PSTN: "..tostring(routing_destination))
			session:execute("transfer", routing_destination.." XML default")
			return
		else
			-- Ramal (type 2, padrao)
			local bridge = "{ignore_early_media=true"
			             ..",sip_h_P-did_number="..did_number
			             ..",sip_h_P-call_type='custom_forward'"
			             ..",sip_h_P-Accountcode="..userinfo_id
			             .."}user/"..routing_destination.."@"..variable_sip_to_host..":"..variable_sip_to_port
			session:execute("bridge", bridge)
		end
	end

	if last_disposition ~= "SUCCESS" then
		if tonumber(config['playback_audio_notification']) == 0 then
			session:streamFile("/usr/share/freeswitch/sounds/pt/BR/karina/flux-indisponivel.wav")
		end
		hangup_cause_disp = session:setVariable("hangup_cause", last_disposition)
		session:hangup(last_disposition)
	end

end
