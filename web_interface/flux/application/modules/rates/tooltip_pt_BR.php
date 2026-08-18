<?php
$tooltip_data = array(
	/*Create Origination Rate(Rate Information)*/

	"origination_rate_form_reseller_id" => "O revendedor é o proprietário dessas tarifas e vai adicionar essa tarifa para seu uso específico. O dropdown do revendedor também é usado para filtrar os dados do Grupo de Tarifas, que é o próximo campo.",

	"origination_rate_form_pricelist_id" => "Com base no valor do dropdown do revendedor, o grupo de tarifas disponível será listado aqui. Selecione qualquer grupo para o qual deseja configurar a tarifa.",

	"origination_rate_form_pattern" => "Prefixo da tarifa de origem. Exemplo: 91",

	"origination_rate_form_comment" => "Descrição da tarifa. Exemplo: Índia",

	"origination_rate_form_country_id" => "Selecione o país apropriado para o prefixo que você está configurando.",

	"origination_rate_form_call_type" => "Selecione o tipo de chamada.",

	"origination_rate_form_routing_type" => "O Tipo de Roteamento é o campo mais essencial deste formulário. Você pode escolher a estratégia de roteamento de chamadas para o cliente e, com base nisso, o sistema fará a terminação das chamadas.",

	"origination_rate_form_status" => "Selecione o status das Tarifas de Origem (Ativo/Inativo).",

	"origination_rate_form_trunk_id_new[1]" => "Forçar a chamada a ser roteada usando o Tronco 1.",

	"origination_rate_form_trunk_id_new[2]" => "Forçar a chamada a ser roteada usando o Tronco 2.",

	"origination_rate_form_trunk_id_new[3]" => "Forçar a chamada a ser roteada usando o Tronco 3.",

	"origination_rate_form_trunk_id" => "Forçar a chamada a ser roteada usando o Tronco.",
	/*End*/

	/*Billing Information Section*/
	"origination_rate_form_connectcost" => "Taxa de conexão a ser cobrada do cliente como mínimo quando a chamada for conectada.",

	"origination_rate_form_includedseconds" => "Definir quantos segundos serão gratuitos na duração da chamada para cada ligação.",

	"origination_rate_form_cost" => "Custo por minuto.",

	"origination_rate_form_init_inc" => "Primeira taxa de incremento para calcular o custo da chamada.",

	"origination_rate_form_inc" => "Taxa de incremento para calcular o custo da chamada. Exemplo: 60 para cobrar a cada minuto.",

	"origination_rate_form_effective_date" => "Data e hora a partir de quando a tarifa começará a ser aplicada.",
	/*End*/

	// --------------------Termination Rate Section---------------------------

	/*Create Termination Rate (Rate Information)*/
	"termination_rate_form_trunk_id" => "Tronco para o qual a tarifa de terminação está sendo adicionada.",

	"termination_rate_form_pattern" => "O Código/Prefixo da tarifa de terminação.",

	"termination_rate_form_comment" => "O país de destino e/ou tipo, i.e. Espanha-Móvel, Canadá-Fixo, etc.",

	"termination_rate_form_strip" => "Se o número coincidir com o início do número discado, será removido.",

	"termination_rate_form_prepend" => "Número a ser adicionado antes do número discado.",

	"termination_rate_form_status" => "Atividade do código.",
	/*End*/

	/*Create Termination Rate (Billing Information)*/
	"termination_rate_form_connectcost" => "Custo único quando a conexão da chamada é estabelecida.",

	"termination_rate_form_includedseconds" => "Quantidade de segundos que não serão cobrados/segundos gratuitos.",

	"termination_rate_form_cost" => "Custo por minuto.",

	"termination_rate_form_init_inc" => "Segundos mínimos cobrados na chamada.",

	"termination_rate_form_inc" => "Segundos subsequentes cobrados após o incremento inicial.",

	"termination_rate_form_precedence" => "Para múltiplas tarifas sob diferentes troncos com o mesmo 'Código', '0' tem a maior prioridade.",

	"termination_rate_form_effective_date" => "Data e hora a partir de quando a tarifa começará a ser aplicada.",
	/*End*/

	"import_origination_rate_pricelist_id" => "O grupo de tarifas ao qual as tarifas importadas estão atribuídas.",

	"import_origination_rate_trunk_id" => "Tronco a ser selecionado para aplicar forçadamente às tarifas importadas.",

	"import_termination_rate_trunk_id" => "O tronco ao qual as tarifas importadas estão atribuídas.",

	"import_termination_rate_mapper_trunk_id" => "O tronco ao qual as tarifas importadas estão atribuídas."
)
?>
