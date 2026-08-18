<?php
$tooltip_data = array(
	/*Create Rate Group(Basic Section)*/
	"pricing_form_reseller_id" => "O revendedor é a entidade principal deste Grupo de Tarifas e é o proprietário deste grupo.",

	"pricing_form_name" => "Nome do Grupo de Tarifas usado para identificação.",

	"pricing_form_routing_prefix" => "Se você oferece roteamento baseado em prefixo para seus clientes, pode definir o prefixo neste campo. Esse prefixo será usado para rotear chamadas com base nos prefixos discados.",

	"pricing_form_status" => "Selecione o status do grupo de tarifas (Ativo/Inativo).",

	"pricing_form_pricelist_id_admin" => "Se o Prefixo de Roteamento estiver configurado, com base no grupo de tarifas do administrador selecionado, o roteamento da chamada será gerado e as tarifas serão aplicadas.",

	"pricing_form_check_carrier" => "Se o módulo CADUP estiver configurado, o roteamento para os troncos será baseado na operadora configurada nestes.",
	/*End*/

	/*Create Rate Group(Billing Section)*/
	"pricing_form_markup" => "Encargos adicionais serão aplicáveis ao custo da chamada. Exemplo: Se um markup de 10% for definido no grupo de tarifas e o cliente fizer uma chamada de $1, o sistema cobrará 10% extra sobre $1, resultando em $1,1.",

	"pricing_form_initially_increment" => "Bloco inicial de incremento usado para calcular a cobrança das chamadas.",

	"pricing_form_inc" => "Taxa de incremento para calcular o custo da chamada. Exemplo: 60 para cobrar a cada minuto. Esse incremento será útil quando o incremento não estiver definido na tarifa de origem.",

	"pricing_form_routing_type" => "O FluxSBC oferece vários tipos de estratégias de roteamento que você pode definir aqui.",

	"pricing_form_trunk_id" => "Selecione os troncos para LCR e roteamento. Se nenhum tronco for selecionado, os clientes que possuem o mesmo grupo de tarifas não poderão fazer chamadas de saída.",
	/*End*/

	/*Create Duplicate Rate Group Information*/
	"pricing_duplicate_form_name" => "Por favor, informe o nome do novo Grupo de Tarifas. Ele será usado apenas para identificar o Grupo de Tarifas.",

	"pricing_duplicate_form_pricelist_id" => "Aqui você precisa selecionar o Grupo de Tarifas do qual deseja copiar todos os dados, incluindo as configurações do Grupo de Tarifas com as tarifas alocadas."
	/*End*/
)
?>
