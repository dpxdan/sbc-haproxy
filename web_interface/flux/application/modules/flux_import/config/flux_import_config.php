<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['flux_import_csv_fields'] = [
    'nm_razao_social',
    'nu_cpfcnpj',
    'tipo_cliente',
    'vendedor',
    'conta',
    'numero',
    'status_terminal',
    'dominio',
    'classificacao',
    'operadora',
    'nu_qtd_canais',
    'origem_terminal',
    'tipo_terminal',
    'cidade_uf',
    'cod_ibge',
    'dt_ativacao',
    'dt_desativacao',
    'ip',
    'ddd_sys',
    'CNL',
];

$config['flux_import_status_map'] = [
    'Ativo'   => 0,
    'Inativo' => 1,
];