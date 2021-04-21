<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if($gateway_error)
	echo $gateway_error;

$payment_paynet_url = plugins_url() . '/payment_paynet';
echo $form;

if(!$gateway_error){
?>

<script type="text/javascript">
jQuery( document ).ready(function() {
    jQuery(window).load(function() {
		jQuery(".paynetj-button").click();
	});
});
</script>
<?php } ?>