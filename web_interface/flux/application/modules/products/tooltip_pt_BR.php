<?php
$tooltip_data = array(
	/*Basic Information*/
	"product_add_form_product_category" => "Para definir o tipo de produto, selecione a categoria apropriada. Pacotes é a categoria padrão para todos.",

	"product_add_form_product_name" => "Nome do pacote que deseja criar.",

	"product_add_form_country_id" => "Selecione o país para ajudar os usuários a identificar o produto quando estiverem procurando um produto específico para um país específico.",

	"product_add_form_product_description" => "Descrição do pacote.",

	"product_add_form_product_buy_cost" => "Definir o custo de compra para o revendedor ou outra entidade.

	O Custo de Compra é o preço de compra do produto sem impostos. Quando o cliente ou revendedor fizer o pedido deste produto, o valor do imposto será aplicado adicionalmente.",

	"product_add_form_can_purchase" => "Selecione se o cliente pode comprar este produto ou não. Se sim, cada cliente pode comprá-lo. Se não, nenhum cliente verá este pacote. Este pacote será atribuído a um cliente específico que o administrador pode designar.",

	"product_add_form_status" => "Selecione o status do pacote: ativo ou inativo.",
	/*End*/

	/*Product Details*/
	"product_add_form_can_resell" => "Defina sim se o revendedor pode revendê-lo ou não se o administrador criar o pacote para seu próprio uso e o revendedor não puder comprá-lo.

	Se o administrador quiser criar um pacote personalizado, deve definir 'Revendedor pode revender' como 'Não', para que esses produtos não sejam listados no portal do revendedor para revenda. Este produto personalizado será listado sob a conta do administrador para que ele possa atribuí-lo manualmente ao cliente.",

	"product_add_form_setup_fee" => "Taxa de instalação para o pacote.

	A Taxa de Instalação é um custo único que será aplicado na primeira vez que o usuário fizer o pedido.",

	"product_add_form_billing_type" => "Selecione o tipo de cobrança. Se for uma vez, o pacote será atribuído uma vez e depois encerrado. Se recorrente, o pacote será renovado conforme os dias de cobrança definidos.

	Uma Vez - O pacote 'Uma Vez' não será renovado automaticamente. Ele será encerrado após os dias definidos.

	Recorrente - O pacote será renovado automaticamente com base nos dias definidos em relação à data do pedido.

	Mensal - O pacote mensal será renovado automaticamente mensalmente em relação à data do pedido.",

	"product_add_form_free_minutes" => "Minutos gratuitos para o pacote.",

	"product_add_form_release_no_balance" => "Que ação o sistema deve tomar se, no momento da renovação do pacote, a conta do cliente não tiver fundos suficientes?

	SIM: O sistema liberará o pacote da conta do cliente se ele não tiver saldo suficiente para renovar este produto.

	NÃO: O sistema aplicará as mudanças na conta do cliente sem considerar o saldo e renovará o produto.",

	"product_add_form_commission" => "A comissão é definida pelo administrador. Se o revendedor revender o pacote do administrador, o valor da comissão a ser oferecido será definido aqui.",

	"product_add_form_price" => "Preço do pacote excluindo impostos. Certifique-se de que ele pode ser recorrente com base no Tipo de Cobrança selecionado.",

	"product_add_form_billing_days" => "Tempo total de disponibilidade para o pacote. O cliente pode usar este serviço pelos dias definidos, e depois será renovado ou encerrado.",

	"product_add_form_product_rate_group" => "Selecione o grupo de tarifas do cliente.",

	"product_add_form_pro_rate" => "As taxas aplicáveis serão baseadas no tipo selecionado para a rescisão prematura do produto.",

	"product_add_form_applicable_for" => "Pacote aplicável para Inbound, Outbound ou ambos.",

	"product_add_form_apply_on_existing_account" => "Atribuir este produto às contas existentes do grupo de tarifas selecionado ou não.",

	"product_add_form_product_rate_group[]" => "As contas existentes do grupo de tarifas selecionado podem ou não receber automaticamente este produto.",
	/*End*/

	/*DID Category*/
	"product_add_form_number" => "Número para criar o DID.",

	"product_add_form_provider_id" => "Selecione o provedor para o DID.",

	"product_add_form_city" => "Cidade para a qual o DID está sendo criado.",

	"product_add_form_province" => "Província para a qual o DID está sendo criado.",
	/*End*/

	/*DID Category(Product Details)*/
	"product_add_form_connectcost" => "Taxa de conexão a ser cobrada do cliente como mínimo quando a chamada for conectada.",

	"product_add_form_cost" => "Custo por minuto.",

	"product_add_form_inc" => "Taxa de incremento para calcular o custo da chamada. Exemplo: 60 para cobrar a cada minuto.",

	"product_add_form_maxchannels" => "Define quantas chamadas podem ocorrer ao mesmo tempo.",

	"product_add_form_includedseconds" => "Definir quantos segundos serão gratuitos na duração da chamada para cada ligação.",

	"product_add_form_init_inc" => "Segundos de cobrança subsequentes após o incremento inicial.",

	"product_add_form_leg_timeout" => "A chamada será automaticamente encerrada após os segundos de timeout de toque.",

	"product_add_form_bypass_media" => "Quando ativo, a mídia flui diretamente entre os endpoints, sem passar pelo FluxSBC. Não disponível quando a gravação de chamadas está ativa.",
	/*End*/

	/*Edit DID Category(Product Details)*/
	"product_edit_form_product_category" => "Para definir o tipo de produto, selecione a categoria apropriada. Pacotes é a categoria padrão para todos.",

	"product_edit_form_product_name" => "Nome do pacote que deseja criar.",

	"product_edit_form_country_id" => "Selecione o país para ajudar os usuários a identificar o produto quando estiverem procurando um produto específico para um país específico.",

	"product_edit_form_connectcost" => "Taxa de conexão a ser cobrada do cliente como mínimo quando a chamada for conectada.",

	"product_edit_form_number" => "Número para criar o DID.",

	"product_edit_form_provider_id" => "Selecione o provedor para o DID.",

	"product_edit_form_city" => "Cidade para a qual o DID está sendo criado.",

	"product_edit_form_province" => "Província para a qual o DID está sendo criado.",

	"product_edit_form_status" => "Selecione o status do pacote: ativo ou inativo.",

	"product_edit_form_cost" => "Custo por minuto.",

	"product_edit_form_inc" => "Taxa de incremento para calcular o custo da chamada. Exemplo: 60 para cobrar a cada minuto.",

	"product_edit_form_maxchannels" => "Define quantas chamadas podem ocorrer ao mesmo tempo.",

	"product_edit_form_includedseconds" => "Definir quantos segundos serão gratuitos na duração da chamada para cada ligação.",

	"product_add_edit_init_inc" => "Segundos de cobrança subsequentes após o incremento inicial.",

	"product_edit_form_leg_timeout" => "A chamada será automaticamente encerrada após os segundos de timeout de toque.",

	"product_edit_form_billing_days" => "Tempo total de disponibilidade para o pacote. O cliente pode usar este serviço pelos dias definidos, e depois será renovado ou encerrado.",

	"product_edit_form_billing_type" => "Selecione o tipo de cobrança. Se for uma vez, o pacote será atribuído uma vez e depois encerrado. Se recorrente, o pacote será renovado conforme os dias de cobrança definidos.",

	"product_edit_form_price" => "Preço do pacote excluindo impostos. Certifique-se de que ele pode ser recorrente com base no Tipo de Cobrança selecionado.",

	"product_edit_form_setup_fee" => "Taxa de instalação para o pacote.",

	"product_edit_form_product_buy_cost" => "Definir o custo de compra para o revendedor ou outra entidade.

	O Custo de Compra é o preço de compra do produto sem impostos. Quando o cliente ou revendedor fizer o pedido deste produto, o valor do imposto será aplicado adicionalmente.",

	"product_edit_form_init_inc" => "Segundos de cobrança subsequentes após o incremento inicial.",

	"product_edit_form_area_code" => "Código de área (DDD) do número. Usado para identificação regional do DID.",

	"product_edit_form_reverse_rate" => "Quando ativa, as chamadas recebidas neste DID geram cobrança. Utilizado para números com custo de entrada, como 0800 e similares.",

	"product_edit_form_rate_group" => "Grupo de tarifas aplicado quando a tarifação reversa está ativa. Define o custo cobrado por chamada recebida neste DID.",

	"product_edit_form_bypass_media" => "Quando ativo, a mídia flui diretamente entre os endpoints, sem passar pelo FluxSBC. Não disponível quando a gravação de chamadas está ativa.",
	/*End*/
);

?>
