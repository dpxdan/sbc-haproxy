-------------------------------------------------------------------------------------
-- Flux SBC - Unindo pessoas e negócios
--
-- Copyright (C) 2022 Flux Telecom
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

function split(str, pat)
   local t = {}  -- NOTE: use {n = 0} in Lua-5.0
   local fpat = "(.-)" .. pat
   local last_end = 1
   local s, e, cap = str:find(fpat, 1)
   while s do
      if s ~= 1 or cap ~= "" then
	 table.insert(t,cap)
      end
      last_end = e+1
      s, e, cap = str:find(fpat, last_end)
   end
   if last_end <= #str then
      cap = str:sub(last_end)
      table.insert(t, cap)
   end
   return t
end 

function generate_caller_id_from_range(range_str)
    if not range_str then
        return nil
    end

    local base, max_range = range_str:match("^(%d+):(%d+)$")
    if not base or not max_range then
        return nil
    end

    local base_length = #base
    if base_length ~= 10 and base_length ~= 11 then
        return nil
    end

    local suffix_length = 4

    local prefix = base:sub(1, base_length - suffix_length)
    local base_suffix = tonumber(base:sub(-suffix_length))

    max_range = tonumber(max_range)

    if not base_suffix or not max_range or max_range < base_suffix or max_range > 9999 then
        return nil
    end

    if not _G.__caller_id_seeded then
        math.randomseed(os.time() + math.random(1000))
        _G.__caller_id_seeded = true
    end

    local random_suffix = math.random(base_suffix, max_range)
    local formatted_suffix = string.format("%0" .. suffix_length .. "d", random_suffix)

    local caller_id = prefix .. formatted_suffix
    return caller_id
end


function explode2(div,str)
    if (div=='') then return false end
    local pos,arr = 0,{}
    for st,sp in function() return string.find(str,div,pos,true) end do
        table.insert(arr,string.sub(str,pos,st-1))
        pos = sp + 1
    end
    table.insert(arr,string.sub(str,pos))
    return arr
end

function trim(s)
		if (s) then
			return s:gsub("^%s+", ""):gsub("%s+$", "")
		end
	end
	
function explode ( seperator, str )
		local pos, arr = 0, {}
		if (seperator ~= nil and str ~= nil) then
			for st, sp in function() return string.find( str, seperator, pos, true ) end do -- for each divider found
				table.insert( arr, string.sub( str, pos, st-1 ) ) -- attach chars left of current divider
				pos = sp + 1 -- jump past current divider
			end
			table.insert( arr, string.sub( str, pos ) ) -- attach chars right of last divider
		end
		return arr
	end