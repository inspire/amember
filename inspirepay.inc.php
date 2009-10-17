<?php

if (!defined('INCLUDED_AMEMBER_CONFIG')) 
    die("Direct access to this location is not allowed");
 
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: inspirepay payment plugin
*    FileName $RCSfile: inspirepay.inc.php,v $
*    Release: @VERSION@ ($Revision: 1.1.2.17 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

global $config;
require_once($config['root_dir']."/plugins/payment/cc_core/cc_core.inc.php");

class payment_inspirepay extends amember_payment {
    function do_payment($payment_id, $member_id, $product_id,
            $price, $begin_date, $expire_date, &$vars){
        return cc_core_do_payment('inspirepay', $payment_id, $member_id, $product_id,
            $price, $begin_date, $expire_date, $vars);
    }
    function get_cancel_link($payment_id){
        global $db;                            
        return cc_core_get_cancel_link('inspirepay', $payment_id);
    }
    function get_plugin_features(){
        return array(
            'title' => 'Inspire Pay',
            'description' => 'Credit card payment',
            'phone' => 2,
            'code' => 1,
            'name_f' => 2
        );
    }
    function void_transaction($payment_id, &$log){
        $vars = array(
            "type" => 'void',
            "username"    => $this->config['login'],
            "password"    => $this->config['pass'],
            "transactionid" => $payment_id,
        );
        $log[] = $vars;
        $res = $this->run_transaction($vars);
        return $res;
    }
    function run_transaction($vars){
        foreach ($vars as $kk=>$vv){
            $v = urlencode($vv);
            $k = urlencode($kk);
            $vars1[] = "$k=$v";
        }
        $vars1 = join('&', $vars1);
        $ret = cc_core_get_url("https://secure.inspiregateway.net/api/transact.php", $vars1);
        parse_str($ret, $res);
        return $res;
    }
    /*************************************************************
      cc_bill - do real cc bill
    ***************************************************************/
    function cc_bill($cc_info, $member, $amount, 
        $currency, $product_description, 
        $charge_type, $invoice, $payment){
        global $config;
        $log = array();
        //////////////////////// cc_bill /////////////////////////

        srand(time());
        if ($charge_type == CC_CHARGE_TYPE_TEST) 
            $amount = "1.00";
         $vars = array(
            "type" => 'sale',
            "username"    => $this->config['login'],
            "password"    => $this->config['pass'],
            "orderid" => $payment['payment_id'],
            "amount" =>   $amount,
            "orderdescription" => $product_description
         );
         if($charge_type == CC_CHARGE_TYPE_RECURRING){
             $vars['customer_vault_id'] = $member['data']['inspirepay_customer_vault_id'];
         }else{
             $vars +=array(
                 "ccnumber" => $cc_info['cc_number'],
                 "ccexp" => $cc_info['cc-expire'],
                 "email"    =>    $member['email'],
                 "firstname" =>  $cc_info['cc_name_f'],
                 "lastname"  =>  $cc_info['cc_name_l'],
                 "address1" =>  $cc_info['cc_street'],
                 "city" =>     $cc_info['cc_city'],
                 "state" =>    $cc_info['cc_state'],
                 "zip" =>      $cc_info['cc_zip'],
                 "country" =>  $cc_info['cc_country'],
                 "ipaddress" => $member['remote_addr']  ? $member['remote_addr'] : $_SERVER['REMOTE_ADDR'],
                 "phone"   => $cc_info['cc_phone']
             );
             if ($cc_info['cc_code'])
                 $vars['cvv'] = $cc_info['cc_code'];
         }
        
        // prepare log record
        $vars_l = $vars; 
        if($vars['ccnumber'])
            $vars_l['ccnumber'] = $cc_info['cc'];
        if ($vars['cvv'])
            $vars_l['cvv'] = preg_replace('/./', '*', $vars['cvv']);
        $log[] = $vars_l;
        /////
        $res = $this->run_transaction($vars);
        $log[] = $res;

        if ($res['response'] == '1'){   
            if ($charge_type == CC_CHARGE_TYPE_TEST)
                $this->void_transaction($res['transactionid'], $log);
            return array(CC_RESULT_SUCCESS, "", $res['transactionid'], $log);
        } elseif ($res['response'] == '2') {
            return array(CC_RESULT_DECLINE_PERM, $res['responsetext'], "", $log);
        } else {
            return array(CC_RESULT_INTERNAL_ERROR, $res['responsetext'], "", $log);
        }
    }
    function save_cc_info($cc_info, $member){
        if(!$member['data']['inspirepay_customer_vault_id']){
            $this->add_customer($member, $cc_info);
        }else{
            $this->update_customer($member, $cc_info);
        }
    }
    function add_customer($member, $cc_info){
        global $db;
        $vars = array(
            'customer_vault'    =>  'add_customer',
            'username'          =>  $this->config['login'],
            'password'          =>  $this->config['pass'],
            'ccnumber' => $cc_info['cc_number'],
            'ccexp' => $cc_info['cc-expire'],
            'email'    =>    $member['email'],
            'firstname' =>  $cc_info['cc_name_f'],
            'lastname'  =>  $cc_info['cc_name_l'],
            'address1' =>  $cc_info['cc_street'],
            'city' =>     $cc_info['cc_city'],
            'state' =>    $cc_info['cc_state'],
            'zip' =>      $cc_info['cc_zip'],
            'country' =>  $cc_info['cc_country'],
            'ipaddress' => $member['remote_addr']  ? $member['remote_addr'] : $_SERVER['REMOTE_ADDR'],
            'phone'   => $cc_info['cc_phone']
        );

        if ($cc_info['cc_code'])
            $vars['cvv'] = $cc_info['cc_code'];

        $resp = $this->run_transaction($vars);
        if($resp['response'] == 1 && $resp['customer_vault_id']){
            $member['data']['inspirepay_customer_vault_id'] = $resp['customer_vault_id'];
            $db->update_user($member['member_id'], $member);
        }
        return $resp;
    }

    function update_customer($member, $cc_info){
        global $db;
        $vars = array(
            'customer_vault'    =>  'update_customer',
            'customer_vault_id' =>  $member['data']['inspirepay_customer_vault_id'],
            'username'          =>  $this->config['login'],
            'password'          =>  $this->config['pass'],
            'ccnumber' => $cc_info['cc_number'],
            'ccexp' => $cc_info['cc-expire'],
            'email'    =>    $member['email'],
            'firstname' =>  $cc_info['cc_name_f'],
            'lastname'  =>  $cc_info['cc_name_l'],
            'address1' =>  $cc_info['cc_street'],
            'city' =>     $cc_info['cc_city'],
            'state' =>    $cc_info['cc_state'],
            'zip' =>      $cc_info['cc_zip'],
            'country' =>  $cc_info['cc_country'],
            'ipaddress' => $member['remote_addr']  ? $member['remote_addr'] : $_SERVER['REMOTE_ADDR'],
            'phone'   => $cc_info['cc_phone']
        );

        if ($cc_info['cc_code'])
            $vars['cvv'] = $cc_info['cc_code'];

        $resp = $this->run_transaction($vars);
        return $resp;
    }

}



function inspirepay_get_member_links($user){
    return cc_core_get_member_links('inspirepay', $user);
}

function inspirepay_rebill(){
    return cc_core_rebill('inspirepay');
}

cc_core_init('inspirepay');

add_member_field("inspirepay_customer_vault_id", "Inspire Pay Customer Vault ID", "text");
