#!/bin/bash

# Credenciais do banco de dados
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-$1}"
DB_NAME="${DB_NAME:-flux}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# Diretório de migrações
MIGRATIONS_DIR="database/updates/"
LOG_TABLE="sql_migration_history"

# Verificar se a tabela de log de migrações existe, se não, criar
mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" -N -B -e "
CREATE TABLE IF NOT EXISTS $LOG_TABLE (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sql_file_name VARCHAR(255),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);"

# Loop sobre arquivos de migração pendentes
for FILE in $(ls $MIGRATIONS_DIR*.sql| sort -t '-' -k 4,4n -k 3,3n -k 2,2n); do
  # Pegar o nome do arquivo
  FILENAME=$(basename "$FILE")
  
  # Verificar se o arquivo já foi aplicado
  QUERY=$(printf "SELECT COUNT(*) FROM %s WHERE sql_file_name = '%s';" "$LOG_TABLE" "$FILENAME")
  APPLIED=$(mysql --user="$DB_USER" -p"$DB_PASS" --host="$DB_HOST" --port="$DB_PORT" --protocol=TCP "$DB_NAME" -N -B -e "$QUERY")
  
  # Se não foi aplicado, execute
  if [ "$APPLIED" -eq 0 ]; then
    echo "Applying migration: $FILENAME"
    mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" < "$FILE"
    
    # Registrar a migração no banco
    mysql -u "$DB_USER" -p"$DB_PASS" -h "$DB_HOST" -P "$DB_PORT" --protocol=TCP "$DB_NAME" -e "
    INSERT INTO $LOG_TABLE (sql_file_name) VALUES ('$FILENAME');"
  else
    echo "Migration $FILENAME already applied. Skipping."
  fi
done
