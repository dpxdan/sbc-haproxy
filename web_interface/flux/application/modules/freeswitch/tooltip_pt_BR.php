<?php
$tooltip_data = array(
	/*Create Gateway (Basic Information)*/
	"gateway_form_name" => "Nome apropriado do gateway.",

	"gateway_form_sip_profile_id" => "Perfil SIP associado ao gateway.",

	"gateway_form_username" => "Nome de usuário para o gateway autenticado baseado em dispositivo.",

	"gateway_form_password" => "Senha para o gateway autenticado baseado em dispositivo.",

	"gateway_form_proxy" => "Host do gateway.",

	"gateway_form_outbound-proxy" => "Para a configuração de cluster, host do proxy SIP à frente.",

	"gateway_form_register" => "Se o gateway é autenticado por nome de usuário/senha ou por IP.",

	"gateway_form_caller-id-in-from" => "Substituir o usuário do 'invite' pelo ID de chamada do canal.",

	"gateway_form_caller_id_type" => "Tipo de Caller ID usado.",
		
	"gateway_form_caller_id_number" => "Formato conforme o tipo selecionado:</br><b>Único</b>: apenas um número.</br><b>Múltiplo</b>: números separados por vírgula.</br><b>Faixa</b>: numero_inicial:0000",

	"gateway_form_status" => "Status do gateway.",
	/*End*/

	/*Create Gateway (Optional Information)*/

	"gateway_form_from-domain" => "Domínio a ser usado no campo 'from': *opcional.",

	"gateway_form_from-user" => "Nome de usuário a ser usado no campo 'from': *opcional, o mesmo que o nome de usuário se em branco.",

	"gateway_form_realm" => "Domínio de autenticação: *opcional, o mesmo que o nome do gateway se em branco.",

	"gateway_form_extension-in-contact" => "Definir o valor personalizado como nome de usuário no campo de contato.",

	"gateway_form_extension" => "Definir valor como nome de usuário no contato.",

	"gateway_form_expire-seconds" => "Segundos para expirar o registro e re-registrar.",

	"gateway_form_register-transport" => "Protocolo de transporte para registro.",

	"gateway_form_contact-params" => "Parâmetros SIP adicionais a serem enviados no contato.",

	"gateway_form_ping" => "Segundos para enviar o ping de opções, falhas irão cancelar o registro e/ou marcar como inativo.",	

	"gateway_form_retry-seconds" => "Segundos antes de tentar novamente em caso de falha ou timeout.",	

	"gateway_form_register-proxy" => "Registro neste proxy: *opcional, o mesmo que o proxy se em branco.",	

	"gateway_form_channel" => "",	

	"gateway_form_dialplan_variable" => "Variável do plano de discagem.",
	/*End*/		

	// ---------------Freeswitch Server Section------------------------

	"fsserver_form_freeswitch_host" => "Detalhes do host Freeswitch.",

	"fsserver_form_freeswitch_password" => "Senha do Freeswitch.",

	"fsserver_form_freeswitch_port" => "Porta do Freeswitch.",

	"fsserver_form_cdrtype" => "CDR Type",
	
	"fsserver_form_freeswitch_cdr_log_dir" => "CDR Log Dir",
	
	"fsserver_form_freeswitch_cdr_url" => "CDR Log URL",

	/*Create Sip Devices*/
	"sipdevices_form_fs_username" => "Nome de usuário da extensão SIP.",

	"sipdevices_form_fs_password" => "Senha da extensão SIP.",

	"sipdevices_form_effective_caller_id_name" => "Apresentação do nome do chamador para a extensão.",

	"sipdevices_form_effective_caller_id_number" => "Apresentação do número do chamador para a extensão.",

	"sipdevices_form_reseller_id" => "Conta do revendedor à qual esta extensão pertence.",

	"sipdevices_form_accountcode" => "Conta do cliente à qual esta extensão pertence.",

	"sipdevices_form_status" => "Status da entrada adicionada.",

	"sipdevices_form_sip_profile_id" => "Perfil SIP ao qual esta extensão pertence.",

	"sipdevices_form_codec" => "Especificar o nome do codec que você deseja selecionar quando uma chamada chegar ao dispositivo SIP ou for feita usando o dispositivo SIP.",
	/*End*/

	/*Voicemail Options*/
	"sipdevices_form_voicemail_enabled" => "Ativar ou não o correio de voz.",

	"sipdevices_form_voicemail_password" => "Senha para acessar o correio de voz.",

	"sipdevices_form_voicemail_mail_to" => "Endereço de e-mail para receber notificações de correio de voz.",

	"sipdevices_form_voicemail_attach_file" => "Anexar ou não o correio de voz no e-mail.",

	"sipdevices_form_vm_keep_local_after_email" => "Após receber o correio de voz por e-mail, remover ou não a cópia do sistema.",

	"sipdevices_form_vm_send_all_message" => "Enviar todos os correios de voz por e-mail como notificação e/ou anexo.",
	/*End*/

	/*Create SIP Profile*/
	"form1_name" => "Nome do perfil SIP.",

	"form1_sip_port" => "Porta na qual o perfil do Freeswitch está escutando.",

	"form1_sip_ip" => "IP no qual o perfil do Freeswitch está escutando.",

	"form1_sipstatus" => "Status da entrada adicionada."
	/*End*/
);
?>
