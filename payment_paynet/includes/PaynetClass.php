<?php
/**
 *
 * Bu yardımcı sınıf ile Paynet API'yi PHP projelerinizde kolayca kullanabilirsiniz
 * 
 * */


class PaynetClient
{
	
	private $apikey;
	private $payneturl;
	private $json_result;
	
	
	//adreslerin sonu slaş ile bitmeli
	const testurl = 'https://pts-api.paynet.com.tr/';
	const liveurl = 'https://api.paynet.com.tr/';
	
	
	
	/**
	 * Yapıcı metod, secret keyi girmek için kullanılıyor
	 * @param string $apikey
	 * @param bool $isLive Canlı için true, test için false girilmeli
	 */
	public function __construct ($apikey = false, $mode = 'prod')
	{
		$this->apikey = $apikey ? $apikey : get_option('payment_paynet_secretkey');
		$this->payneturl = $mode == 'test' ? self::testurl : self::liveurl;
		return $this;
	}


	/**
	 * Paynet apisinden sorgu yapıp cevabı JSON olarak alan metod
	 * @param string $adres_eki 
	 * @param stdClass $data 
	 * @return mixed sunucudan alınan JSON
	 */
    private function LoadJson($adres_eki, $data)
    {
		if(self::IsFileGetContentsActive())
		{
			$options = array(
							'http' => array(
							'header'  =>"Accept: application/json; charset=UTF-8\r\n".
										"Content-type: application/json; charset=UTF-8\r\n".
										"Authorization: Basic ".$this->apikey,
							'method'  => 'POST',
							'content' => json_encode($data),
							'ignore_errors' => true,
							'ssl'=>array(
								"verify_peer"=>false,
								"verify_peer_name"=>false
							)
						)
			);
			
			if (!function_exists('stream_context_create'))
			{
				die("Sunucunuz stream_context_create() fonksiyonunu desteklememektedir...");
			}
			
			$context  = stream_context_create($options);
			$result = json_decode(file_get_contents($this->payneturl.$adres_eki, false, $context));
			
			if($result == null)
			{
				Throw new PaynetException($this->payneturl." adresine bağlanılamadı...");	
			}
			else
			{
				return $result;
			}
		}	
		
		else if (self::IsCurlActive())
		{	
			$data_string = json_encode($data);                                                                                   
			$address = $this->payneturl.$adres_eki;
			$ch = curl_init($address);  
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSLVERSION, 6);	
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //****
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Authorization: Basic '.$this->apikey,	
				'Content-Type: application/json',
				'Accept: application/json; charset=UTF-8',			
				'Content-Length: ' . strlen($data_string))                                                                       
			);                                                                                                                   																															 
			$result = curl_exec($ch);
			
			if($result == false)
			{
				Throw new PaynetException($this->payneturl." adresine şu sebeple bağlanılamadı: ".curl_error($ch));
			}
			
			else
			{
				return json_decode($result);
			}
		}
			
		else
		{
			Throw new PaynetException("Sunucunuzda cURL veya file_get_contents() desteklememektedir.");	
		}
	}	
	
	/*
	* Sunucuda allow_url_fopen seçeneğinin açık olup olmadığını kontrol eder.
	*/
	public function IsFileGetContentsActive() 
	{	
		if( ini_get('allow_url_fopen'))
		{
			return true; 
		}
		else
		{		
			return false;		 
		}		
	}
	
    /*
	*  cURL extensionının yüklü olup olmadığını kontrol eder.
	*/
	public function IsCurlActive()
	{
		if(in_array ('curl', get_loaded_extensions()))
		{
			return true;
		}
		else 
		{
			return false;
		}
	}
	
	/**
	 * Karttan çekim işlemini yapan metod
	 * @param ChargeParameters $param
	 * @return ChargeResponse
	 */
	public function ChargePost(ChargeParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/charge',$param);
        $sonuc = new ChargeResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	
	

	
	
	/**
	 * 
	 * @param CheckTransactionParameters $param
	 * @return CheckTransactionResponse
	 */
	public function CheckTransaction(CheckTransactionParameters $param)
	{
		$this->json_result = $this->LoadJson("v1/transaction/check", $param);
		$this->json_result = $this->json_result->Data[0];
		$sonuc = new CheckTransactionResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
		
	
	
	
	
	/**
	 * 
	 * @param TransactionDetailParameters $param
	 * @return TransactionDetailResponse
	 */
	public function GetTransactionDetail(TransactionDetailParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/detail',$param);
		$sonuc = new TransactionDetailResponse();
		$sonuc->FillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	
	
	
	
	
	
	
	public function ListTransaction(TransactionListParameters $param)
	{
		$this->json_result = $this->LoadJson('v1/transaction/list', $param);
		$sonuc = new TransactionListResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	
	
	
	/**
	 * Mail veya sms ile ödeme seçemekleri için link üreten servis...
	 * @param MailOrderParameters $params
	 * @return MailOrderResult
	 */
	public function CreateMailOrder(MailOrderParameters $params)
	{
		$this->json_result = $this->LoadJson('v1/mailorder/create', $params);
		$sonuc = new MailOrderResult();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	
	/**
	 * Oran tablosunu getiren servis
	 * @param RatioParameters $params
	 * @return RatioResponse
	 */
	public function GetRatios(RatioParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/ratio/Get", $params);
		$sonuc = new RatioResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	
	/**
	 * İşlemi işaretleyen servis
	 * @param MarkTransferParameters $params
	 * @return boolean
	 */
	public function MarkTransferred(MarkTransferParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/transaction/mark_transferred", $params);
		return $this->json_result->code == "1";
	}
	
	

	/**
	 * 
	 * @param ReversedRequestParameters $params
	 * @return ReversedRequestResponse
	 */
	public function ReversedRequest(ReversedRequestParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/transaction/reversed_request", $params);
		$sonuc = new ReversedRequestResponse();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	

	
	
	/**
	 * 
	 * @param AutologinParameters $params
	 * @return AutologinResult
	 */
	public function AutoLogin(AutologinParameters $params)
	{
		$this->json_result = $this->LoadJson("v1/agent/autologin", $params);
		$sonuc = new AutologinResult();
		$sonuc->fillFromJson($this->json_result);
		return $sonuc;
	}
	
	
	
	/**
	 * Bir işlem sonucunda sunucudan alınan JSON nesnesini table olarak ekrana yazdırır
	 */
	public function PrintResult()
	{
		if($this->json_result!=null)
		{
			
			echo "<hr>SUNUCU YANITI<br><table>";
			foreach($this->json_result as $property => $value)
			{
				echo "<tr>\r\n";
					echo "<td>".$property."</td>\r\n";
					echo "<td>".$value."</td>\r\n";
				echo "</tr>\r\n";
			}
			echo "<table>";
		}
		else
			echo "Sonuç değişkeni boş";
	}
	
	
	
	
	/**
	 *Bir servisin cevap olarak gönderdiği Json nesnesini düz yazı olarak ekrana yazar. 
	 */
	public function PrintJson()
	{
		echo json_encode($this->json_result);
	}
	
}





/**
 * Suncudan dönen cevaplar için base sınıfı
 * @author proje
 */
class Result extends fillFromJson_
{
	public $object_name;
	public $code;
	public $message;
}




/**
 * FillFromJson metodunu diğer sınıflara eklemek için 
 * @author proje
 *
 */
class fillFromJson_
{
	/**
	 * Json olarak alınan bilgileri oluşturulmuş nesneye yükler.
	 * @param jsonObject $json
	 */
	function fillFromJson($json)
	{
		foreach($this as $property=>$value)
		{
			if(isset($json->$property))
			{
				//Eğer property bir dizi ise ve ilk elemanı sınıf ismi olarak tanımlanmışsa
				if(is_array($this->$property))
				{
					$array = $this->$property;
					if(count($array) && is_string($array[0]))
					{
						//Çocuk sınıfın adını döndür ve diziyi temizle
						$child_class_name = array_pop($this->$property);
	
						//Json'daki herbir dizi elemenı için ayrı nesne oluşturulacak
						foreach($json->$property as $data)
						{
							//Çocuk nesneyi oluştur ve içeriğini doldur
							$child_obj = new $child_class_name;
							$child_obj->fillFromJson($data);
							array_push($this->$property, $child_obj);
						}
					}
				}
				//dizi değilse değer ataması yapmak yeterli
				else
				{
					$this->$property = $json->$property;
				}
			}
		}
	}
}



/**
 * Charge servisi için request parametreleri
 * @author proje
 *
 */
class ChargeParameters
{
	public $session_id;
	public $token_id;
	public $reference_no = "";
	public $transaction_type = 1;
	public $add_comission_amount="false";
	public $no_instalment="false";
	public $tds_required='true';
	public $installments="";
	public $ratio_code="";
	public $amount;
}




/**
 * ChargePost metodundan dönecek sonuç nesne için
 * @author proje
 *
 */
class ChargeResponse extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $is_tds;
	public $agent_id;
	public $user_id;
	public $email;
	public $phone;
	public $bank_id;
	public $instalment;
	public $card_no_masked;
	public $card_holder;
	public $amount;
	public $net_amount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $paynet_error_id;
	public $paynet_error_message;
	public $bank_error_id;
	public $bank_error_message;
	public $bank_error_short_desc;
	public $bank_error_long_desc;
	public $agent_reference_no;
	public $ratio;
	public $ratio_code; 
	public $end_user_comission;
	public $plus_installment;


}




/**
 * GetRatios() metodunun parametreleri
 * @author proje
 * 
 */
class RatioParameters
{
	public $pos_type = 5;
	public $bin;
	public $amount;
	public $addcommission_to_amount = true;
    public $ratio_code; 
	public $plus_installment;

}




/**
 * GetRatios() metodunun dönüş sınıfı
 * @author proje
 *
 */
//Ratio servisinin cevabı
class RatioResponse extends Result
{
    public $data = array('Banks');//banka  listesi
}



/**
 * Banks sınıfının ratio dizinin elemanları
 * @author proje
 *
 */

class Ratios extends fillFromJson_
{
    public $ratio;
    public $instalment_key;
    public $instalment;
    public $instalment_amount;
    public $total_net_amount;
    public $total_amount;
    public $commision;
    public $commision_tax;
    public $desc;
	public $ratio_code;
    public $plus_installment;	

}



/**
 * RatioResponse sınıfındaki data dizisinin elemanları
 * @author proje
 *
 */
class Banks extends fillFromJson_
{
    public $bank_id;
    public $bank_logo;
    public $bank_name;
	public $plus_installment;
    public $ratio = array('Ratios');
}





/**
 * CheckTransaction metoduna gönderilecek paramatre
 * @author proje
 * 
 */
class CheckTransactionParameters
{
	public $xact_id;
	public $reference_no;
}




/**
 * CheckTransaction metodunun sonucunda dönecek sınıf
 * @author proje
 *
 */
class CheckTransactionResponse extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $agent_id;
	public $is_tds;
	public $bank_id;
	public $instalment;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $ratio;
	public $ratio_code;
	public $amount;
	public $netAmount;
	public $comission;
	public $comission_tax;
	public $end_user_comission;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $xact_transaction_id;
	public $email;
	public $phone;
	public $note;
	public $agent_reference;
	

}




/**
 * GetTransactionDetail metodunun parametreleri
 * @author proje
 *
 */
class TransactionDetailParameters
{
	public $xact_id;
	public $reference_no;
	
}




/**
 * GetTransactionDetail metodunun dönüşü
 * @author proje
 *
 */
class TransactionDetailResponse extends Result
{
	public $Data = array('TransactionDetail');
}




/**
 * GetTransactionDetail metodunun dönüşündeki satırlar
 * @author proje
 *
 */
class TransactionDetail extends Result
{
	public $xact_id;
	public $xact_date;
	public $transaction_type;
	public $pos_type;
	public $agent_id;
	public $is_tds;
	public $bank_id;
	public $instalment;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $ratio;
	public $ratio_code; 
	public $end_user_comission;
	public $amount;
	public $netAmount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $is_succeed;
	public $reversed;
	public $reversed_xact_id;
	public $xact_transaction_id;
	public $email;
	public $phone;
	public $note;
	public $agent_reference;
	public $company_amount;
	public $company_commission;
	public $company_commission_with_tax;
	public $company_net_amount;
	public $agent_amount;
	public $agent_commission;
	public $agent_commission_with_tax;
	public $agent_net_amount;
	public $company_cost_ratio;
	public $company_pay_ratio;
	public $xact_type_desc;
	public $bank_name;
	public $payment_string;
	public $pos_type_desc;
	public $agent_name;
	public $company_name;
	public $instalment_text;
	public $ipaddress;
	public $client_id;
	

	
}





/**
 * ListTransaction metodunun parametreleri
 * @author proje
 *
 */
class TransactionListParameters
{
	public $agent_id;
	public $bank_id;
	public $datab;
	public $datbi;
	public $show_unsucceed;
	public $limit;
	public $ending_before;
	public $starting_after;
	
	public function __construct()
	{
		$this->agent_id = "";
		$this->bank_id = "";
		$this->show_unsucceed = true;
		$this->limit = 1000;
		$this->ending_before = 0;
		$this->starting_after = 0;
		$this->datab = date('Y-m-d', strtotime('-10 days', strtotime(date("Y-m-d"))));
		$this->datbi = date('Y-m-d', strtotime('+1 days', strtotime(date("Y-m-d"))));
	}
	
}




/**
 * ListTransaction sonucunda dönecek nesne
 * @author proje
 *
 */
class TransactionListResponse extends fillFromJson_
{
	public $companyCode;
	public $companyName;
	
	public $total;
	public $total_count;
	
	public $limit;
	public $ending_before;
	public $starting_after;
	public $object_name;
	public $has_more;


	public $Data = array('TransactionListData');
}




/**
 * TransactionListResponse'daki data dizisinin satırlar
 * @author proje
 *
 */
class TransactionListData extends fillFromJson_
{
	public $companyCode;
	public $companyName;
	public $agent_id;
	public $agent_referans_no;
	public $agent_name;
	public $xact_id;
	public $xact_date;
	public $is_tds;
	public $bank_id;
	public $bank_name;
	public $card_no;
	public $card_holder;
	public $card_type;
	public $card_type_name;
	public $authorization_code;
	public $reference_code;
	public $order_id;
	public $postype_desc;
	public $xact_type;
	public $xacttype_desc;
	public $fiscal_period_id;
	public $sector_id;
	public $sectorid_desc;
	public $merchant_id;
	public $channel_name;
	public $ipaddress;
	public $client_id;
	public $xact_transaction_id;
	public $terminal_id;

	public $is_succeed;
	public $reversed;
	public $is_reconcile;
	public $is_payup;
	public $is_onchargeback;
	public $is_transferred;

	public $reversed_xact_id;
	public $channel_id;
	public $pos_type;
	public $instalment;

	public $amount;
	public $net_amount;
	public $comission;
	public $comission_tax;
	public $currency;
	public $ratio;
	public $end_user_comission; 

	public $user_id;
	public $xact_time;
	public $xact_note;
	public $xact_agent_reference;
	public $company_pay_ratio;

	public $company_cost_ratio;

	public $ana_firma_brut_alacak;
	public $ana_firma_komisyonu;
	public $ana_firma_komisyonu_kdv_dahil;
	public $ana_firma_odenecek_net_tutar;
	public $bayi_brut_alacak;
	public $bayi_komisyonu;
	public $bayi_komisyonu_kdv_dahil;
	public $bayiye_odenecek_net_tutar;

	public $cp_mfi_vdate;
	public $cp_mfi_vdate_day;
	public $ap_mfi_vdate;
	public $ap_mfi_vdate_day;

}



/**
 * MarkTransferred() metodu için parametreler
 * @author proje
 *
 */
class MarkTransferParameters
{
	public $xact_id;
	public $document_no;
	public $amount;
	public $currency;
	public $exchange_rate;
}




/**
 * CreateMailOrder() metodunun parametreleri
 * @author proje
 *
 */

class MailOrderParameters
{
	public $pos_type;
	public $addcomission_to_amount;
	public $agent_id;
	public $name_surname;
	public $user_name;
	public $amount;
	public $email;
	public $send_mail;
	public $phone;
	public $send_sms;
	public $expire_date;
	public $note;
	public $agent_note;
	public $reference_no;
	public $succeed_url;
	public $error_url;
	public $confirmation_url;
	public $send_confirmation_mail;
	public $multi_payment;
	
	
	public function __construct()
	{
		$this->pos_type = 5;
		$this->addcomission_to_amount = true;
		$this->multi_payment = true;
		$this->send_confirmation_email = true;
		$this->send_mail = false;
		$this->send_sms = false;
		$this->phone = "";
		$this->email = "";
		$this->succeed_url = "";
		$this->error_url = "";
		$this->confirmation_url = "";
		$this->expire_date = 24;
	}
}






/**
 * CreateMailOrder() metodunun sonucu
 * @author proje
 *
 */
class MailOrderResult extends Result
{
	public $url;
}



/**
 * ReversedRequest() için parametreler
 * @author agitk
 *
 */
class ReversedRequestParameters
{
	public $xact_id;
	public $amount;
	public $succeedUrl;
}


/**
 * ReversedRequest() metodundan dönecek nesne, bu nesne Result ile aynı içerikli olduğu için (şimdilik) ekleme yapmaya gerek yok
 * @author proje
 *
 */
class ReversedRequestResponse extends Result
{
}

/**
 * Autologin() metodunun parametresi
 * @author proje
 *
 */
class AutologinParameters
{
	public $userName;
	public $agentID;
}




/**
 * Autologin() metodunun dönüş nesnesi
 * @author proje
 *
 */
class AutologinResult extends Result
{
	public $url;
}



/**
 * Hata oluştuğunda dönecek nesne
 * @author proje
 *
 */
class PaynetException extends Exception
{
}

class PaynetTools {
	
	public static function FormatWithDecimalSeperator($amount)
	{
		if(!is_numeric($amount))
		{
			Throw new PaynetException("Tutar sayısal değer olmalı.");		
		}

		$amount = number_format($amount, 2, ',', '');
		return $amount;
	}
	
	public static function FormatWithoutDecimalSeperator($amount)
	{
		if(!is_numeric($amount))
		{
			Throw new PaynetException("Tutar sayısal değer olmalı.");		
		}

		$amount = round($amount*100);
	    return $amount;
	}
	
		
	public static function getProductInstallments($price, $rates)
    {
       $return = '';
        foreach ($rates as $k => $v) {
            if(!$v->ratio OR count($v->ratio) < 2)
                continue;
            $return .= '
			<div class="payment_paynet_banks_block" align="center" style="">
			<img src="' . $v->bank_logo .'">';
            $return .= '<table class="payment_paynet_banks_table table" style="">
						<tr>
							<td>Ay</td>
							<td>Taksit</td>
							<td>Toplam</td>
						
						</tr>';
            foreach ($v->ratio as $r) {								   
                if($r->instalment == 0 ) 
					$r->instalment = 1;
			
				if($r->instalment == 1) 
							continue;
									
				if($r->plus_installment >0) {
					$return .= 				 	
				'<tr>			
					<td>'.$r->instalment.'  <span style="color:red">(+</span><span style="color:red">  '.$r->plus_installment.' </span><span style="color:red">) </span> </td>
				    <td class="' . $k . '">' . number_format(($price*(1/(1-$r->ratio)))/($r->instalment+ $r->plus_installment), 2 ). '</td>
					<td class="' . $k . '">' . number_format($price*(1/(1-$r->ratio)), 2) . '</td>				    
				</tr>';
				}
				else{
					$return .= 				 	
				'<tr>				
					<td>'.$r->instalment.' </td>
					<td class="' . $k . '">' . number_format(($price*(1/(1-$r->ratio)))/($r->instalment+ $r->plus_installment), 2 ). '</td>
					<td class="' . $k . '">' . number_format($price*(1/(1-$r->ratio)), 2) . '</td>				    
				</tr>';
				}               
            }
            $return .= '</table></div>';
        }
        return $return.'<div style="clear:both"></div>';
    }
	
	public static function getAdminInstallments($price, $rates)
    {
        $return = '';
        foreach ($rates as $k => $v) {
            if(!$v->ratio)
                continue;
									
            $return .= '
			<div class="inst_block" align="center" style="">
			<img src="' . $v->bank_logo .'">';
            $return .= '<table class="inst_table table" style="">
						<tr>
							<td>Taksit</td>
							<td>Oran(%)</td>
						</tr>';
            foreach ($v->ratio as $r) {
                if($r->instalment == 0 ) 
					$r->instalment = 1;
				
				
				if($r->plus_installment >0) 
				{				
					$return .= '<tr>
					<td>'.$r->instalment.'  <span style="color:red">(+</span><span style="color:red">  '.$r->plus_installment.' </span><span style="color:red">) </span> </td>
					<td class="' . $k . '">' . number_format(100*$r->ratio, 2) . '</td>
					</tr>';
				}
				
				else{ $return .= '<tr>
					<td>' . $r->instalment . '</td>
					<td class="' . $k . '">' . number_format(100*$r->ratio, 2) . '</td>

				</tr>';}
										
            }
            $return .= '</table></div>';
        }
        return $return.'<div style="clear:both"></div>';
    }
}

?>
