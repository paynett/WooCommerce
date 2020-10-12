<?php
/*
Plugin Name: PAYNET Ödeme Kuruluşu A.Ş. Payment Gateway
Plugin URI:   https://www.paynet.com.tr/
Description: PAYNET Ödeme Kuruluşu A.Ş. provides 6 populer bank gateways in one service. Now you can use this plugin with woocommerence
Version:     1.0
*/
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
$payment_paynet_url = plugins_url() . '/payment_paynet/';
?>
<hr/>
<br/>
    <div class="panel">
	<br/>
        <div class="row">
            <div class="col-md-2 text-center">
				<img src="<?php echo $payment_paynet_url ?>img/paynet_logo.png" class="img-responsive" />
			</div>
            <div class="col-md-2 text-center">
			</div>
            <div class="col-md-3 text-center" align="center">
                <a href="https://odeme.paynet.com.tr/" class="btn button-primary"  target="_blank;"> Hesabınıza giriş yapın</a>
            </div>
        </div>
		</div>
         
 
    </div>