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
class Dashboard_model extends CI_Model {
	function __construct() {
		parent::__construct ();
	}
	function get_recent_recharge() {
		$accountinfo = $this->session->userdata ( 'accountinfo' );
		$userlevel_logintype = $this->session->userdata ( 'userlevel_logintype' );
		
		$where_arr = array (
				'payment_by' => - 1 
		);
		if ($userlevel_logintype == 1) {
			$where_arr = array (
					'payment_by' => $accountinfo ['id'] 
			);
		}
		if ($userlevel_logintype == 0 || $userlevel_logintype == 3) {
			$where_arr = array (
					'accountid' => $accountinfo ['id'] 
			);
		}
		$this->db->where ( $where_arr );
		$this->db->select ( 'id,accountid,credit,payment_date' );
		$this->db->limit ( 12 );
		$this->db->order_by ( 'payment_date', 'desc' );
		return $this->db->get ( 'payments' );
	}
	function get_call_statistics($table, $parent_id, $start_date = '', $end_date = '', $group_flag = true) {
		$this->db->select ( "sum(total_calls) as sum,
                           SUM(total_answered_call) as answered,
                           MAX(mcd) AS mcd,
                           SUM(billseconds) AS duration,
                           SUM(total_fail_call) as failed,
                           SUM(billseconds) as billable,
                           sum(debit-cost) as profit,
                           sum(debit) as debit,
                           sum(cost) as cost,
                           SUM(total_answered_call) as completed,
                           DAY(calldate) as day", false );
		$this->db->where ( 'calldate >=', $start_date . " 00:00:00" );
		$this->db->where ( 'calldate <=', $end_date . " 23:59:59" );
		$this->db->where ( 'reseller_id', $parent_id );
		if ($group_flag)
			$this->db->group_by ( "DAY(calldate)" );
		$result = $this->db->get ( $table );
		return $result;
	}
	function get_customer_maximum_callminutes($start_date, $end_date) {
		$start_date = $start_date . " 00:00:00";
		$end_date = $end_date . " 23:59:59";
		$accountinfo = $this->session->userdata ( 'accountinfo' );
		$parent_id = ($accountinfo ['type'] == 1) ? $accountinfo ['id'] : 0;
		if ($this->session->userdata ( 'userlevel_logintype' ) != 0 && $this->session->userdata ( 'userlevel_logintype' ) != 3) {
			$where = "reseller_id ='$parent_id'";
		} else {
			$where = "accountid ='$parent_id'";
		}
		$where = $where . " AND calldate >= '" . $start_date . "' AND  calldate <= '" . $end_date . "'";
		$select_query = "SELECT sum( billseconds ) AS billseconds,account_id FROM (cdrs_day_by_summary) WHERE $where group by account_id order by sum(billseconds) desc limit 10";
		return $this->db->query ( $select_query );
	}	
	function get_customer_maximum_callcount($start_date, $end_date) {
		$start_date = $start_date . " 00:00:00";
		$end_date = $end_date . " 23:59:59";
		$accountinfo = $this->session->userdata ( 'accountinfo' );
		$parent_id = ($accountinfo ['type'] == 1) ? $accountinfo ['id'] : 0;
		if ($this->session->userdata ( 'userlevel_logintype' ) != 0 && $this->session->userdata ( 'userlevel_logintype' ) != 3) {
			$where = "reseller_id ='$parent_id'";
		} else {
			$where = "accountid ='$parent_id'";
		}
		$where = $where . " AND calldate >= '" . $start_date . "' AND  calldate <= '" . $end_date . "'";
	  $select_query = "SELECT SUM(total_calls) as call_count, `account_id` FROM (`cdrs_day_by_summary`) WHERE $where GROUP BY `account_id` ORDER BY `call_count` desc LIMIT 10";
	  
		return $this->db->query ( $select_query );
	}	
	function get_customer_maximum_countryminutes($start_date, $end_date) {
		$start_date = $start_date . " 00:00:00";
		$end_date = $end_date . " 23:59:59";
		$accountinfo = $this->session->userdata ( 'accountinfo' );
		$parent_id = ($accountinfo ['type'] == 1) ? $accountinfo ['id'] : 0;
		if ($this->session->userdata ( 'userlevel_logintype' ) != 0 && $this->session->userdata ( 'userlevel_logintype' ) != 3) {
			$where = "reseller_id ='$parent_id'";
		} else {
			$where = "accountid ='$parent_id'";
		}
		$where = $where . " AND cdrs.calltype = calltype.call_type AND callstart >= '" . $start_date . "' AND  callstart <= '" . $end_date . "'";
		$select_query = "SELECT sum( billseconds ) AS billseconds,calltype.id AS call_type_id FROM cdrs,calltype WHERE $where group by call_type,calltype.id order by sum(billseconds) desc limit 10";
		
		return $this->db->query ( $select_query );
	}	
	function get_customer_maximum_countrycount($start_date, $end_date) {
		$start_date = $start_date . " 00:00:00";
		$end_date = $end_date . " 23:59:59";
		$accountinfo = $this->session->userdata ( 'accountinfo' );
		$parent_id = ($accountinfo ['type'] == 1) ? $accountinfo ['id'] : 0;
		if ($this->session->userdata ( 'userlevel_logintype' ) != 0 && $this->session->userdata ( 'userlevel_logintype' ) != 3) {
			$where = "reseller_id ='$parent_id'";
		} else {
			$where = "accountid ='$parent_id'";
		}
		$where = $where . " AND cdrs.calltype = calltype.call_type AND callstart >= '" . $start_date . "' AND  callstart <= '" . $end_date . "'";
	  $select_query = "SELECT COUNT(calltype) as call_count, call_type,calltype.id AS call_type_id FROM cdrs,calltype WHERE $where GROUP BY call_type,calltype.id ORDER BY 1 desc LIMIT 10";
	  
		return $this->db->query ( $select_query );
	}
	function get_low_balance_accounts($reseller_id = 0, $limit = 5)
	{
		$where = "notify_flag = 0 AND deleted = 0 AND status = 0"
		       . " AND ((posttoexternal = 0 AND balance <= notify_credit_limit)"
		       . " OR (posttoexternal = 1 AND credit_limit - balance <= notify_credit_limit))";
	
		$this->db->where_in('type', array(0, 1, 3));
		if ($reseller_id > 0) {
			$this->db->where('reseller_id', $reseller_id);
		}
		$this->db->limit($limit);
		return $this->db_model->select('*', 'accounts', $where, 'id', 'DESC');
	}
	
	function get_summary_stats($reseller_id, $start_date, $end_date)
	{
		$sql = "SELECT
		            SUM(total_calls) AS total_calls,
		            SUM(debit) AS total_debit,
		            SUM(cost) AS total_cost,
		            SUM(debit - cost) AS profit,
		            MAX(mcd) AS mcd,
		            IFNULL(ROUND(100.0 * SUM(total_answered_call) / SUM(total_calls), 2), 0) AS ASR,
		            CASE WHEN SUM(total_answered_call) > 0
		                 THEN SUM(billseconds) / SUM(total_answered_call)
		                 ELSE 0 END AS ACD
		        FROM cdrs_day_by_summary
		        WHERE reseller_id = ?
		          AND calldate >= ?
		          AND calldate <= ?";
	
		return $this->db->query($sql, array($reseller_id, $start_date, $end_date));
	}
	
	function get_count($table, $date_field, $start_date, $end_date, $reseller_id, $extra_where = '')
	{
		$sql = "SELECT COUNT(*) AS count FROM {$table}
		        WHERE {$date_field} >= ? AND {$date_field} <= ? AND reseller_id = ?";
	
		if ($extra_where !== '') {
			$sql .= ' AND ' . $extra_where;
		}
	
		return $this->db->query($sql, array($start_date, $end_date, $reseller_id));
	}
	
	function get_sum($table, $sum_field, $date_field, $start_date, $end_date, $reseller_id)
	{
		$sql = "SELECT SUM({$sum_field}) AS total FROM {$table}
		        WHERE {$date_field} >= ? AND {$date_field} <= ? AND reseller_id = ?";
	
		return $this->db->query($sql, array($start_date, $end_date, $reseller_id));
	}
	
	function get_trunk_stats($start_date, $end_date, $reseller_id = null)
	{
		$sql = "SELECT
		            trunk_id,
		            COUNT(*) AS attempts,
		            SUM(CASE WHEN billseconds > 0 THEN 1 ELSE 0 END) AS completed,
		            AVG(billseconds) AS acd,
		            SUM(billseconds) AS duration,
		            SUM(provider_call_cost) AS cost
		        FROM cdrs
		        WHERE provider_id > 0
		          AND callstart >= ?
		          AND callstart <= ?";
	
		$params = array($start_date, $end_date);
	
		if ($reseller_id !== null) {
			$sql .= " AND reseller_id = ?";
			$params[] = $reseller_id;
		}
	
		$sql .= " GROUP BY trunk_id ORDER BY attempts DESC";
	
		return $this->db->query($sql, $params);
	}
	
	function get_error_code_stats($start_date, $end_date, $parent_id, $scope_field = 'reseller_id', $account_id = null)
	{
		$sql = "SELECT disposition, COUNT(*) AS call_count
		        FROM cdrs
		        WHERE callstart >= ?
		          AND callstart <= ?
		          AND {$scope_field} = ?";
	
		$params = array(
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59',
			$parent_id
		);
	
		if ($account_id !== null && (int)$account_id > 0) {
			$sql .= " AND accountid = ?";
			$params[] = (int)$account_id;
		}
	
		$sql .= " GROUP BY disposition ORDER BY call_count DESC";
	
		return $this->db->query($sql, $params);
	}
	
	function get_ddd_stats($start_date, $end_date, $parent_id, $scope_field = 'reseller_id', $account_id = null)
	{
		$sql = "SELECT
		            CASE WHEN callednum LIKE '0%'
		                 THEN SUBSTRING(callednum, 2, 2)
		                 ELSE SUBSTRING(callednum, 1, 2)
		            END AS ddd,
		            COUNT(*) AS call_count
		        FROM cdrs
		        WHERE callstart >= ?
		          AND callstart <= ?
		          AND call_direction = 'outbound'
		          AND (LENGTH(callednum) = 11 OR LENGTH(callednum) = 12)
		          AND {$scope_field} = ?";
	
		$params = array(
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59',
			$parent_id
		);
	
		if ($account_id !== null && (int)$account_id > 0) {
			$sql .= " AND accountid = ?";
			$params[] = (int)$account_id;
		}
	
		$sql .= " GROUP BY ddd ORDER BY call_count DESC";
	
		return $this->db->query($sql, $params);
	}
	
	function get_accounts_list($reseller_id = null, $limit = 500)
	{
		$this->db->select('id, number, company_name, first_name, last_name');
		$this->db->from('accounts');
		$this->db->where('deleted', 0);
	
		if ($reseller_id !== null) {
			$this->db->where_in('type', array(0, 3));
			$this->db->where('reseller_id', $reseller_id);
		} else {
			$this->db->where_in('type', array(0, 1, 3));
		}
	
		$this->db->order_by('company_name', 'ASC');
		$this->db->order_by('first_name', 'ASC');
		$this->db->limit($limit);
	
		return $this->db->get();
	}
}
?>
