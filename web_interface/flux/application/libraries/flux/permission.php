<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2022 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// Flux SBC Version 4.0 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################
if (! defined ( 'BASEPATH' ))
	exit ( 'No direct script access allowed' );
class Permission {
	function __construct($library_name = '') {
		$this->CI = & get_instance ();
		$this->CI->load->model ( "db_model" );
		$this->CI->load->library ( 'session' );
		$this->CI->load->library ( 'flux_log' );
	}
	
	function get_module_access($user_type)
  {
    $CI = &get_instance();

    // Obtém permissões de módulo para o tipo de usuário
    $where = array('userlevelid' => $user_type);
    $query = $CI->db_model->getSelect("module_permissions", "userlevels", $where);

    if ($query->num_rows() === 0) {
        return false;
    }

    $row = $query->row_array();
    $modules_str = $row['module_permissions'];
    $modules_ids = array_filter(explode(',', $modules_str));

    // Busca módulos permitidos
    if (!empty($modules_ids)) {
        $CI->db->where_in('id', $modules_ids);
    }
    $menu_arr = $CI->db_model->select("*", "menu_modules", "", "priority", "asc");

    // Sessão do usuário
    $accountinfo = $CI->session->userdata('accountinfo');
    $permissioninfo = $CI->session->userdata('permissioninfo');
    $logintype = $CI->session->userdata('logintype');

    $menu_list = array();
    $permited_modules = array();
    $label_arr = array();

    if ($menu_arr && $menu_arr->num_rows() > 0) {
        foreach ($menu_arr->result_array() as $menu) {
            $menu_label = $menu['menu_label'];
            $module_name = $menu['module_name'];

            // Regras específicas de bloqueio
    if ($accountinfo['posttoexternal'] == '1' && in_array($menu_label, array('TopUp', 'Send Credit')) || $module_name == 'refill') {
            continue;
    }

            if (!isset($label_arr[$menu_label]) && $menu_label !== 'Configuration') {
                $label_arr[$menu_label] = $menu_label;
                $menu_image = $menu['menu_image'] ?: 'Home.png';

                $menu_list[$menu['menu_title']][$menu['menu_subtitle']][] = array(
                    'menu_label'  => trim($menu_label),
                    'module_url'  => trim($menu['module_url']),
                    'module'      => trim($module_name),
                    'menu_image'  => trim($menu_image)
                );
            }

            // Controle de permissões
            $module_url_explode = explode('/', $menu['module_url']);
            $main_module = $module_url_explode[0] ?? '';
            $sub_module = $module_url_explode[1] ?? '';

            $sub_parts = explode('_', $sub_module);
            $sub_prefix = trim($sub_parts[0] ?? '');
            
            $menu_list_log = array(
                    'module_url_explode'  => $module_url_explode,
                    'main_module'  => $main_module,
                    'sub_module'      => $sub_module                    
                );
            
//            $this->CI->flux_log->write_log('menu_list_log', json_encode($menu_list_log));	

            $has_list_perm = isset($permissioninfo[$main_module][$sub_module]['list'])
                             && $permissioninfo[$main_module][$sub_module]['list'] == 0;

            $login_type = $permissioninfo['login_type'] ?? $logintype;

            if (
                $has_list_perm ||
                in_array($login_type, array('-1', '0', '1', '2')) ||
                ($login_type == '1' && $sub_module == 'resellersrates_list') ||
                ($has_list_perm && $login_type == '1')
            ) {
                $permited_modules[] = $sub_prefix;
            }
        }
    }

    // Atualiza sessão
    $CI->session->set_userdata('permited_modules', serialize($permited_modules));
    $CI->session->set_userdata('menuinfo', serialize($menu_list));

    return true;
}

	private function _check_record_permission($table_name,$where_condition=array(),$select_field= 'id')
	{
		$this->CI->db->select($select_field);
		return (array)$this->CI->db->get_where($table_name,$where_condition)->first_row();
	}
	
	function check_web_record_permission($id,$table_name,$redirect_url,$check_function_access=false,$dependencies=array())
	{
		$accountinfo = $this->CI->session->userdata('accountinfo') ;
		$reseller_id = $accountinfo['type'] == 1 || $accountinfo['type'] == 5 ? $accountinfo['id'] : 0 ;
		$permission_result =array();
		if(!empty($dependencies)){
			$table_row =$this->_check_record_permission($table_name,array('id'=>$id),$dependencies['field_name']);
			if(!empty($table_row)){
				$permission_result = $this->_check_record_permission($dependencies['parent_table'],array('id'=>$table_row[$dependencies['field_name']],'reseller_id'=>$reseller_id));	
			}
		}
		else if(!$check_function_access){
			$permission_result  = $this->_check_record_permission($table_name,array('id'=>$id,'reseller_id'=>$reseller_id));
		}
		if( $reseller_id > 0 && ((empty($permission_result) || $check_function_access) )){
				$this->permission_redirect_url($redirect_url);
		}	
	}
	
	function permission_redirect_url($redirect_url)
	{
		$this->CI->session->set_flashdata ( 'flux_notification','Permission Denied!');
		redirect(base_url().$redirect_url);
	}
	
	function customer_web_record_permission($id,$table,$redirect_url)
	{
		$accountinfo = $this->CI->session->userdata('accountinfo');
		$query_result = $this->_check_record_permission($table,array("accountid"=>$accountinfo['id'],"id"=>$id));
		if(empty($query_result)){
			$this->permission_redirect_url($redirect_url);
		}
	}
	
	function validate_multiple_delete_access($ids,$table,$redirect_url,$accountid="accountid",$subadmin_flag=false,$field_name="id",$parent_customer="")
	{
		$accountinfo = $this->CI->session->userdata('accountinfo');
		if($accountinfo['type'] != '-1' && $accountinfo['type'] != '2'){
			$this->CI->db->select($field_name);
			if($accountinfo['type'] == 1){
				$where = "`".$accountid."` = ".$accountinfo['id']." and ".$field_name." IN ($ids)";
			}else{
				if($parent_customer != ''){
					$where = "`".$accountid."` = ".$accountinfo['reseller_id']." and ".$field_name." IN ($ids)";
				}else{
					$where = "`".$accountid."` = ".$accountinfo['id']." and ".$field_name." IN ($ids)";
				}
			}
			$this->CI->db->where($where);
			$query = $this->CI->db->get($table);
			$new_ids='';
			if($query->num_rows() > 0){
				$result=$query->result_array();
				foreach ($result as $key => $value) {
					$new_ids .= "'".$value[$field_name]."',";
				}
				$new_ids=rtrim($new_ids,',');
			}
			return $new_ids;
		}else{
			return $ids;
		}
	}
}
?> 
