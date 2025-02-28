<?php
/*
  Plugin Name: Paynet Ödeme Kuruluşu A.Ş. Payment Gateway
  Plugin URI:  https://www.paynet.com.tr/
  Description: Paynet Ödeme Kuruluşu A.Ş. provides 6 populer bank gateways in one service. This free opensource plugin allows you accept payment by credit cards via PayNet gateway

 */
if (!defined('ABSPATH')) {
	exit;
}
include( plugin_dir_path(__FILE__) . 'includes/PaynetClass.php');
/* Define the database prefix */
global $wpdb;
/* Paynet All Load */
add_action('plugins_loaded', 'init_payment_paynet_gateway_class', 0);
function init_payment_paynet_gateway_class()
{
	if (!class_exists('WC_Payment_Gateway'))
		return;
	class payment_paynet extends WC_Payment_Gateway
	{
		/*
		 * 	__construct function
		 */
		function __construct()
		{
			$this->id = "payment_paynet";
			$this->method_title = __("Pay by CreditCard");
			$this->method_description = __("Pay by CreditCard");
			$this->title = __("Pay via CreditCard");
			$this->icon = null;
			$this->has_fields = true;
			$this->supports = array('default_credit_card_form');
			$this->init_form_fields();
			$this->init_settings();
			$this->terms = get_option('payment_paynet_terms');
			$this->version = 1.0;
			$this->id_payment = 32;
			$this->key_payment = 'c8be48db180b7824ddc63fa7bde50244';
			$this->curversionurl = 'http://api.payment.net/license/';
			foreach ($this->settings as $setting_key => $value)
				$this->$setting_key = $value;
			//Register the style
			add_action('admin_enqueue_scripts', array($this, 'register_payment_paynet_admin_styles'));
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_thankyou_' . $this->id, array($this, 'receipt_page'));
			add_action( 'woocommerce_before_thankyou', 'after_successful_order_page');
			if (is_admin())
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			//Paynet SETTINGS
			$paynet_settings = get_option("woocommerce_payment_paynet_settings");
			if($paynet_settings)
				foreach ($paynet_settings as $k => $v)
					$this->{$k} = $v;
					//print_r($this);
					//exit;
		}
// End __construct()
		public function register_payment_paynet_admin_styles()
		{
			wp_enqueue_style('admin-styles', plugins_url() . '/woocommerce/assets/css/admin.css');
			wp_register_style('payment_paynet-admin', plugins_url('css/payment_paynet-admin.css', __FILE__));
			wp_enqueue_style('payment_paynet-admin');
		}
		public function admin_options()
		{
			if (isset($_POST['confirm_payment_paynet_register']) AND $_POST['confirm_payment_paynet_register']) {
				update_option('payment_paynet_terms', true);
				$this->registerPaynet();
			}
			if (!get_option('payment_paynet_terms')) {
				include(dirname(__FILE__) . '/includes/terms.php');
				return;
			}
			//echo '<pre>' . print_r($this->registerPaynet(), true) . '</pre>';
			$payment_paynet_url = plugins_url() . '/payment_paynet/';
			echo '<table class="form-table"><img src="' . $payment_paynet_url . 'img/paynet_logo.png" width="150px"/>';
			echo '<h2>Paynet Ödeme Ayarları</h2><hr/>';
			$this->generate_settings_html();
			$paynet_rates = $this->getRates();
			if ($paynet_rates->code != 0) {
				$installments = '<div id="message" class="updated woocommerce-message inline">
			<h3>Taksit bilgileri alınamıyor !</h3> Üstteki forma girdiğiniz bilgileri kontrol ediniz. (' 
			. $paynet_rates->code .') '.$paynet_rates->message .'
			 <br/><hr/><small><code>' . print_r($paynet_rates, true).'</code></small><br/>-</div>';
			 $installments = '';
			}
			else
				$installments = PaynetTools::getAdminInstallments(100, $paynet_rates->data);
			echo "</table><hr/><h1>Taksit Seçenekleri </h1>(Paynet hesabınızdan otomatik alınmıştır.)<hr/> ";
			echo $installments;
			include(dirname(__FILE__) . '/includes/payment_paynet-help-about.php');
		}
		/* 	Admin Panel Fields */
		public function init_form_fields()
		{	
			$categories = [];
			
			if(get_bloginfo('version')>="4.5.0"){		
				$categories = get_terms(array(
					'taxonomy'   => "product_cat",
					'number'     => false,
					'orderby'    => 'name',
					'order'      => 'ASC'
				));
			}
			else{
				$categories = get_terms('product_cat', array(
					'number'     => false,
					'order'      => 'name',
					'orderby'    => 'ASC'
				));
			}
		
			$category_names = [];
			foreach ($categories as $category) {
				$category_names[$category->term_id] = $category->name;
			}
		
			$this->form_fields = array(
				'enabled' => array(
					'title' => 'Aktif',
					'label' => 'Eklentiyi aktif yap',
					'type' => 'checkbox',
					'default' => 'yes',
				),
				'title' => array(
					'title' => __('Title', 'woocommerce'),
					'type' => 'text',
					'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
					'default' => __('Pay with Credit Card', 'woocommerce'),
					'desc_tip' => true,
				),
				'logo_url' => array(
					'title' => __('Ödeme Ekranı Logosu', 'woocommerce'),
					'type' => 'text',
					'description' => __('Please use link over SSL like httpS://yourstore.com/logo.png', 'woocommerce'),
					'default' => plugins_url('/img/pay-icon.png', __FILE__),
					'desc_tip' => true,
				),
				'description' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('Payment method description that the customer will see on your website.', 'woocommerce'),
					'default' => __('Pay with Credit Card.', 'woocommerce'),
					'desc_tip' => true,
				),
				'datakey' => array(
					'title' => 'Data-Key (Firma açık anahtarı)',
					'type' => 'text',
					'desc_tip' => 'Firma publishable key. destek@paynet.com.tr adresinden test ve canlı sistemi için temin edebilirsiniz.',
				),
				'secretkey' => array(
					'title' => 'Secret Key (Firma gizli anahtarı)',
					'type' => 'text',
					'desc_tip' => 'Firma secret key. destek@paynet.com.tr adresinden test ve canlı sistemi için temin edebilirsiniz.',
				),
				'dataagent' => array(
					'title' => 'Cari Kodu (opsiyonel)',
					'type' => 'text',
					'desc_tip' => 'Ödemeyi alanın ya da yapanın firmadaki cari hesap kodu. 
					Bu alanda firma bayisi cari hesap kodu ya da bayinin Paynet altındaki kodu gönderilebilir. 
					Firma kendi bayi kodunu kullanacak ise Paynet sisteminde ilgili tanımın yapılması gerekiyor.',
				),
				'add_commission' => array(
					'title' => 'Taksit Komisyonu',
					'label' => 'Komisyonnu toplam tutara ekle',
					'type' => 'select',
					'default' => false,
					'options' => array(
						true => 'Komisyon oranını müşteriye yansıt',
						false => 'Komisyon oranını mağaza karşılayacak',
					),
					'desc_tip' => 'Komisyon tutarının müşterinizin sepetine eklenip eklenmeyeceği belirlenir. ',
				),
						
				'ratio_code' => array(
				'title' => 'Oran Kodu',
				'type' => 'text',
				'desc_tip' => '
				 Bayiye ait oran kodu girilecek alan.
				',
				),			
				'installments' => array(
				'title' => 'Taksit ',
				'type' => 'text',
				'desc_tip' => '				
				Eğer müşterinize sadece belirli taksitler ile alış veriş yapmasına izin vermek istiyorsanız, 
				virgül ile ayırarak izinli taksit bilgisini gönderebilirsiniz. 0,3,4,6 gibi.
				',
				),
				
				'force_tds' => array(
					'title' => '3D Secure zorunlu',
					'type' => 'select',
					'default' => false,
					'desc_tip' => '3DS ile alışverişte müşterinizin cep telefonuna SMS doğrulama kodu gider',
					'options' => array(
						true => 'Evet',
						false => 'Hayır',
					)
				),
				'test_mode' => array(
					'title' => 'Test Modu',
					'type' => 'select',
					'default' => 'prod',
					'options' => array(
						'prod' => 'Kapalı (Mağaza online)',
						'test' => 'Açık (Ödemeler gerçekten alınmayacak)',
					),
				),
				'no_instalment_categories' => array(
					'title' => 'Taksit Yapılmayacak Kategoriler (opsiyonel) ',
					'type' => 'multiselect',
					'options' => $category_names,
					'desc_tip' => 'Taksit yapılmasını istemediğiniz kategorileri seçiniz. Çoklu seçim yapmak için CTRL tuşunu kullanabilirsiniz.',
				)
			);
		}
// End init_form_fields()
		public function process_payment($order_id)
		{
			global $woocommerce;
			$order = new WC_Order($order_id);
			if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url(true);
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
			}
			return array(
				'result' => 'success',
				'redirect' => $checkout_payment_url,
			);
		}
//END process_payment
		public function validate_fields()
		{
			return isset($_POST['payment_paynet-card-number']) && isset($_POST['payment_paynet-card-name']) && isset($_POST['payment_paynet-card-expiry']) && isset($_POST['payment_paynet-card-cvc']) && isset($_POST['payment_paynet_selected_installment']);
		}
//END validate_fields
		/* OVERRIDE */
		public function credit_card_form($args = array(), $fields = array())
		{
			global $wp;
			//print_r($wp->query_vars);
			wp_register_script('custom-script', plugins_url('/js/custom-script.js', __FILE__));
		}
		public function createCheckoutEmbedForm($id_order)
		{
			try {
				$paynet = new PaynetClient($this->secretkey, $this->test_mode);
			} catch (PaynetException $e) {
				return $e->getMessage();
			}
			
			$jsurl = 'https://pj.paynet.com.tr/public/js/paynet.min.js';
		if( $this->test_mode == 'test')
			$jsurl = 'https://pts-pj.paynet.com.tr/public/js/paynet.js';
			
			$order = new WC_Order($id_order);
			
			$txt = '
				     <form action="" method="post" name="checkout-form" id="checkout-form">
	            <script type="text/javascript"
	                    class="paynet-button"
						data-platform_id="WOOCOMMERCE"
	                    src="'.$jsurl.'"
	                    data-key="' . $this->datakey . '"
	                    data-amount= '.PaynetTools::FormatWithoutDecimalSeperator($order->get_total()).'
	                    data-image="'.$this->logo_url.'"
	                    data-button_label="'.__('Ödemeyi Tamamla', 'woocommerce').'"
	                    data-agent="'.$this->dataagent.'"
	                    data-add_commission_amount="'.($this->add_commission ? 'true': 'false').'"
						data-installments="'.($this->installments).'"
						data-no_instalment="'.($this->noInstalmentForOrder($id_order) ? 'true': 'false').'"
						data-ratio_code="'.$this->ratio_code.'"
	                    data-tds_required="'.(isset($this->force_tds) && $this->force_tds ? 'true' : 'false').'"
	                    data-pos_type="5">
	            </script>
	      </form>
			';
			return $txt;
		}
		
		private function noInstalmentForOrder($order_id)
		{
			$order = new WC_Order($order_id);
			
			$no_instalment = false;
			
			foreach($order->get_items() as $item_id => $item){
				
				$product = $item->get_product();
				
				foreach($product->category_ids as $category_id){
					
					if(is_array($this->no_instalment_categories) && in_array($category_id, $this->no_instalment_categories)){
						$no_instalment = true;
					}
				}
			}
			
			return $no_instalment;
		}
		
		/**
		 * Generates secure key
		 */
		private function getKey($key)
		{
			return md5('payment' . $key);
		}
		public function getRates($price = 100, $use_cache = false)
		{
			if($use_cache){
				if(	$cache = get_option('payment_paynet_rates_cache'))
					return $cache;
			}
			try {
				$paynet = new PaynetClient($this->secretkey, $this->test_mode);
				$ratioParameters=new RatioParameters();
				$ratioParameters->ratio_code=$this->ratio_code;
				$rates = $paynet->GetRatios($ratioParameters);
			} catch (PaynetException $e) {
				return $e->getMessage();
			}
			update_option('payment_paynet_rates_cache', $rates);
			return $rates;
		}
		/*
		 * Post CC data to Paynet gateWay
		 */
		public function post2Paynet($order_id)
		{
			global $woocommerce;
			if (version_compare(get_bloginfo('version'), '4.5', '>='))
				wp_get_current_user();
			else
				get_currentuserinfo();
			$order = new WC_Order($order_id);
			$ip = $_SERVER['REMOTE_ADDR'];
			$user_meta = get_user_meta(get_current_user_id());
			$prices = PaynetClient::calculatePrices($order->get_total(), $this->rates);
			$ins = (int) $_POST['payment_paynet_selected_installment']; // BUNA BAK*********
			$amount = $order->get_total();
			$user_id = get_current_user_id();
			$amount_pay = (float) $prices[key($prices)]['installments'][$ins]['total']; //?????
			$currency = (string) $order->get_currency();
			$installment = $ins;
			$orderid = 'ETIC_' . $order_id . "-" . time();
			$expire_date = explode('/', $_POST['payment_paynet-card-expiry']);
			
			$gateway_url = $this->payment_paynet_tdmode == 'off' ? _Paynet_API_URL_ : _Paynet_3D_URL_;
			$result = json_decode($this->curlPostExt(json_encode($paynet), $gateway_url, true));
			if (!$result OR $result == NULL) {
				$record['result_code'] = 'CURL-LOAD_ERROR';
				$record['result_message'] = 'WebServis Error ';
				return $record;
			}
			if (isset($result->ResultCode) AND $result->ResultCode == "Success") {
				if ($this->payment_paynet_tdmode != 'off')
					header("Location:" . $result->Data);
				if (isset($result->Data->IsSuccessful) AND $result->Data->IsSuccessful) {
					$record['result_code'] = '99';
					$record['result_message'] = $result->ResultCode;
					$record['result'] = true;
					return $record;
				}
				$record['result_code'] = isset($result->Data->ResultCode) ? $result->Data->ResultCode : 'UKN-01';
				$record['result_message'] = isset($result->Data->ResultMessage) ? $result->Data->ResultMessage : 'Hata açıklaması bulunamadı';
				return $record;
			}
			$record['result_code'] = $result->ResultCode;
			$record['result_message'] = $result->ResultMessage != '' ? $result->ResultMessage : __('Payment Failed');
			return $record;
		}
		public function receipt_page($orderid)
		{
			$order = new WC_Order($orderid);
			$gateway_error = false;
			if(isset($_REQUEST['session_id']) AND isset($_REQUEST["token_id"])){			
				try{
				
					//Paynet secret keyi nesneye aktar
					$paynet = new PaynetClient($this->secretkey, $this->test_mode);
					$chargeParams = new ChargeParameters();
					$chargeParams->session_id = $_REQUEST["session_id"];
					$chargeParams->token_id = $_REQUEST["token_id"];
				    $chargeParams->amount = PaynetTools::FormatWithoutDecimalSeperator($order->get_total());					
					$chargeParams->add_comission_amount = $this->add_commission ? 'true': 'false';	
					$chargeParams->tds_required = $this->force_tds ? 'true' : 'false';											
					$chargeParams->ratio_code = $this->ratio_code;
					$chargeParams->installments = $this->installments;
					$chargeParams->no_instalment = $this->noInstalmentForOrder($orderid);
								
					//Charge işlemini çalıştırır
					$result = $paynet->ChargePost($chargeParams);					
                     $paynet = new PaynetClient($this->secretkey, $this->test_mode);
					 
					if($result->is_succeed == true){
						if ($this->add_commission) {
							if($result->end_user_comission==0){
							$order_fee = new stdClass();
							$order_fee->id = 'komisyon-farki';
							$order_fee->name = 'Kredi kartı komisyon farkı '. $result->instalment. ' taksit '. $result->end_user_comission;
							$order_fee->amount = $result->end_user_comission;
							$order_fee->taxable = $result->comission_tax ? true : false;
							$order_fee->tax = $order_fee->taxable ? $result->comission_tax : 0;
							$order_fee->tax_data = array();
							$order_fee->tax_class = '';
							$order->add_fee($order_fee);
							$order->calculate_totals(true);
							}
						    else{						
							$order_fee = new stdClass();
							$order_fee->id = 'komisyon-farki';
							$order_fee->name = 'Son kullanıcı komisyon tutarı '.$result->end_user_comission;
							$order_fee->amount = $result->end_user_comission;
							$order_fee->taxable = $result->comission_tax ? true : false;
							$order_fee->tax = $order_fee->taxable ? $result->comission_tax : 0;
							$order_fee->tax_data = array();
							$order_fee->tax_class = '';
							$order->add_fee($order_fee);
							$order->calculate_totals(true);
						
						       }
							}					
						else
						{
							$order_fee = new stdClass();
							$order_fee->id = 'komisyon-farki';
							$order_fee->name =  $result->instalment. ' taksit ';
							$order_fee->amount = 0;
							$order_fee->taxable = $result->comission_tax ? true : false;
							$order_fee->tax = $order_fee->taxable ? $result->comission_tax : 0;
							$order_fee->tax_data = array();
							$order_fee->tax_class = '';
							$order->add_fee($order_fee);
							$order->calculate_totals(true);

						}					                						
						$order->update_status('processing', __('Processing Paynet payment', 'woocommerce'));
						$order->add_order_note('Ödeme Paynet ile tamamlandı. İşlem no: #' . $result->xact_id. ' Tutar ' . $result->amount . ' Taksit: ' .  $result->installment . ' Ödenen:' . $result->net_amount);
						update_post_meta($orderid, '_xact_id',$result->xact_id);
						update_post_meta($orderid, 'total_amount',$result->amount);
						update_post_meta($orderid, '_instalment',$result->instalment);
						update_post_meta($orderid, '_plus_installment',$result->plus_installment);
						$order->payment_complete();
						WC()->cart->empty_cart();
						wp_redirect($this->get_return_url($order));
					} else {
						$order->update_status('pending', 'Pending payment', 'payment-payment');
						$gateway_error = __('Your bank responsed:') . '(' . $result->code . ') ' . $result->message.' '.$this->paynet_error_message;
					}
				}
				catch (PaynetException $e)
				{
					$gateway_error = $e->getMessage();
				}
			}
			
			if ( !$order->is_paid())
			{	
				$form = $this->createCheckoutEmbedForm($orderid);
				include(dirname(__FILE__) . '/includes/embed.php');
			}
		}
		
		private function registerPaynet()
		{
			$d = $_SERVER['HTTP_HOST'];
			if (substr($d, 0, 4) == " www.")
				$d = substr($d, 4);
			$data = array();
			$data['product_id'] = $this->id_payment;
			$data['product_version'] = $this->version;
			$data['product_name'] = 'Woocommerce Paynet SanalPOS';
			$data['key_payment'] = $this->key_payment;
			$data['merchant_email'] = get_option('admin_email');
			$data['merchant_domain'] = $d;
			$data['merchant_ip'] = $_SERVER['SERVER_ADDR'];
			$data['merchant_name'] = get_option('blogname');
			$data['merchant_version'] = WOOCOMMERCE_VERSION;
			$data['merchant_software'] = 'Woocommerce';
			$data['hash_key'] = md5($d . $this->version);
			return json_decode($this->CurlPostExt(array("q" => json_encode($data)), $this->curversionurl));
		}
		private function curlPostExt($data, $url, $json = false)
		{
			$ch = curl_init(); // initialize curl handle
			curl_setopt($ch, CURLOPT_URL, $url); // set url to post to
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
			if ($json)
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30); // times out after 4s
			curl_setopt($ch, CURLOPT_POST, 1); // set POST method
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // add POST fields
			if ($result = curl_exec($ch)) { // run the whole process
				curl_close($ch);
				return $result;
			}
		}
		function getTransactionDetails($xact_id)
		{
			try{
				$paynet = new PaynetClient($this->secretkey, $this->test_mode);
				$param = new TransactionDetailParameters();
				$param->xact_id = $xact_id;
			}
			catch (PaynetException $e)
			{
				echo $e->getMessage();
			}
			return $paynet->GetTransactionDetail($param);
		}
	}
	//END Class Paynet
	function payment_paynet($methods)
	{
		$methods[] = 'payment_paynet';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'payment_paynet');
	add_filter('woocommerce_product_tabs', 'payment_paynet_installment_tab');
	add_action('woocommerce_order_actions_start', 'payment_paynet_order_details');
	
	function paynet_enqueue_style_after_wc(){
        wp_enqueue_style( 'my-style', plugins_url().'/payment_paynet/css/product_tab.css', array(), '2.0' );
	}
	add_action( 'wp_enqueue_scripts', 'paynet_enqueue_style_after_wc', 20 );
	function payment_paynet_order_details($id)
	{
		$order = New WC_Order($id);
		if ($order->get_payment_method() != 'payment_paynet')
			return;
		$status = $order->get_status();
		//$skip_statuses = array('cancelled', 'refunded', 'pending payment');
		if ($status == 'cancelled' OR $status == 'refunded')
			return;
		
		$xid = get_post_meta($id, '_xact_id', true);
		if(!$xid OR $xid == 1 OR is_array($xid))
			return;
		$payment_paynet = New payment_paynet();
		
		$transaction = $payment_paynet->getTransactionDetails($xid);
		
		$tr = $transaction->Data[0];
		//print_r($tr);
		?>
		<div class="align-center" align="center">
			<br/><img src="<?php echo plugins_url() ?>/payment_paynet/img/paynet_logo.png" width="150px"/>
			<h3><i class="icon-credit-card"></i> Ödeme Detayları </h3>
			<span class="badge"><?php echo $tr->reference_code ?></span>
		<table class="widefat fixed centered center">
			<tr>
				<td>Ödenen Tutar</td><td> <?php echo $tr->amount.' '.$tr->currency ?></td>
			</tr>
			<tr>
				<td>Net Tutar</td><td> <?php echo $tr->netAmount.' '.$tr->currency ?></td>
			</tr>
			<tr>
				<td>Komisyon Oranı </td><td> &percnt; <?php echo $tr->ratio*100 .' '.$tr->payment_string?></td>
			</tr>
			<tr>
				<td>Tarih </td><td> <?php echo $tr->xact_date ?></td>
			</tr>
			<tr>
				<td>IP </td><td> <?php echo $tr->ipaddress ?></td>
			</tr>
			<tr>
				<td>Sonuç </td><td> <?php echo $tr->message ?> <?php echo $tr->is_tds ? '3D ile ödendi' : '' ?></td>
			</tr>
			<tr>
				<td>Kredi Kartı</td><td> <?php
		echo $tr->card_no
		. '<br/> ' . $tr->card_holder
		. '<br/>'. $tr->card_type.' '.$tr->bank_id;
		?></td>
			</tr>
		</table>
		</div>
		<hr/>
		<?php
	}
	function payment_paynet_installment_tab($tabs)
	{
		// Adds the new tab
		$tabs['test_tab'] = array(
			'title' => __('Taksit Seçenekleri', 'woocommerce'),
			'priority' => 50,
			'callback' => 'payment_paynet_installment_tab_content'
		);
		return $tabs;
	}
	function payment_paynet_installment_tab_content()
	{
		global $woocommerce;
		global $product;
		
		$settings = get_option("woocommerce_payment_paynet_settings");
		
		if(is_array($settings) && array_key_exists("no_instalment_categories", $settings))
		{
			foreach($product->category_ids as $category_id){
				if(is_array($settings["no_instalment_categories"]) && in_array($category_id, $settings["no_instalment_categories"])){
					echo "<div class='no_instalment_info'>Bu ürüne taksit uygulanamamaktadır.</div>";
					return;
				}
			}
		}
		
		$rates = new payment_paynet();
		
		$installments = PaynetTools::getProductInstallments((float) $product->get_price(), $rates->getRates(null, true)->data);
		echo $installments;
	}
}
function after_successful_order_page($order_id) {
    if (!$order_id) {
        return;
    }

    $total_amount = get_post_meta($order_id, 'total_amount', true);
    $instalment = get_post_meta($order_id, '_instalment', true);
    $plus_instalment = get_post_meta($order_id, '_plus_installment', true);

    if ($instalment == 0) {
        $instalment_display = "Tek Çekim";
    } elseif ($plus_instalment > 0) {
        $instalment_display = "$instalment + $plus_instalment";
    } else {
        $instalment_display = "$instalment";
    }

    $total_instalments = $instalment + $plus_instalment;
    $monthly_payment = ($total_instalments > 0) ? number_format($total_amount / $total_instalments, 2, ',', '.') . " ₺" : "";
	echo "<script>
    document.addEventListener('DOMContentLoaded', function() {

        var tables = [
            document.querySelector('.wc-block-order-confirmation-totals__table'),
            document.querySelector('section.woocommerce-order-details table')
        ];

        tables.forEach(function(table) {
            if (table) {
                // Önceki tablo içeriğini temizle
                table.innerHTML = '';

                // Yeni <tbody> oluştur
                var newTbody = document.createElement('tbody');

                // Karttan Çekilen Tutar
                var row1 = document.createElement('tr');
                row1.innerHTML = '<th class=\"wc-block-order-confirmation-totals__label\" scope=\"row\">Karttan Çekilen Tutar:</th>' +
                                '<td class=\"wc-block-order-confirmation-totals__total\">" . number_format($total_amount, 2, ',', '.') . " ₺</td>';
                newTbody.appendChild(row1);

                // Aylık Ödeme (Eğer toplam taksit sıfırdan büyükse)
                if ($total_instalments > 0) {
                    var row2 = document.createElement('tr');
                    row2.innerHTML = '<th class=\"wc-block-order-confirmation-totals__label\" scope=\"row\">Aylık Ödeme:</th>' +
                                    '<td class=\"wc-block-order-confirmation-totals__total\">" . number_format(($total_amount / $total_instalments), 2, ',', '.') . " ₺</td>';
                    newTbody.appendChild(row2);
                }

                // Taksit Sayısı
                var row3 = document.createElement('tr');
                row3.innerHTML = '<th class=\"wc-block-order-confirmation-totals__label\" scope=\"row\">Taksit Sayısı:</th>' +
                                '<td class=\"wc-block-order-confirmation-totals__total\">" . ($plus_instalment > 0 ? $instalment . ' + ' . $plus_instalment : $instalment) . "</td>';
                newTbody.appendChild(row3);

                // Yeni <tbody>'yi tabloya ekle
                table.appendChild(newTbody);
            }
        });
    });
</script>";

}
