<?php

if (!defined('INCLUDED_AMEMBER_CONFIG')) 
    die("Direct access to this location is not allowed");
 
$notebook_page = 'Inspire Pay Gateway';
config_set_notebook_comment($notebook_page, 'Inspire Pay configuration');
if (file_exists($rm = dirname(__FILE__)."/readme.txt"))
    config_set_readme($notebook_page, $rm);
global $config;
require_once($config['root_dir']."/plugins/payment/cc_core/cc_core.inc.php");

add_config_field('payment.inspirepay.login', 'Inspire Pay  login',
    'text', "",
    $notebook_page, 
    '');
add_config_field('payment.inspirepay.pass', 'Inspire Pay  password',
    'text', "",
    $notebook_page, 
    '');

cc_core_add_config_items('inspirepay', $notebook_page);
?>
