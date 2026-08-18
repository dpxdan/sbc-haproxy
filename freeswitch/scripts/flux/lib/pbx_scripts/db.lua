
-- Conexão com o banco de dados de leitura
local function apply_read_consistency(dbh)
	if DB_SYNC_WAIT ~= "TRUE" then
		return
	end
	pcall(function()
		dbh:query("SET SESSION wsrep_sync_wait=1")
	end)
end

function db_read()

	local dsn = ODBC_DSN_RO or ODBC_DSN
	local dbh = freeswitch.Dbh("odbc://"..dsn..":"..DB_USERNAME..":"..DB_PASSWD.."");
	if not dbh:connected() then
		freeswitch.consoleLog("notice", "Falha ao conectar ao banco de dados de leitura\n")
		return false
	end
	apply_read_consistency(dbh)
	return dbh
end


function db_write()
	local dbh_write = freeswitch.Dbh("odbc://"..ODBC_DSN..":"..DB_USERNAME..":"..DB_PASSWD.."");
	if not dbh_write:connected() then
  		freeswitch.consoleLog("notice", "Falha ao conectar ao banco de dados de escrita\n")
  		return false
	end
	return dbh_write
end
