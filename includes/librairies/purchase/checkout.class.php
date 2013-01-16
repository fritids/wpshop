<?php

/*	Check if file is include. No direct access possible with file url	*/
if ( !defined( 'WPSHOP_VERSION' ) ) {
	die( __('Access is not allowed by this way', 'wpshop') );
}

/**
 * Checkout
 *
 * The WPShop checkout class handles the checkout process, collecting user data and processing the payment.
 *
 * @class 		wpwhop_checkout
 * @package		WPShop
 * @category	Class
 * @author		Eoxia
 */

/* Instantiate the class from the shortcode */
function wpshop_checkout_init() {
	global $wpshop_checkout;
	$wpshop_checkout = new wpshop_checkout();
	return $wpshop_checkout->display_form();
}

class wpshop_checkout {

	var $div_register, $div_infos_register, $div_login, $div_infos_login = 'display:block;';
	var $creating_account = true;

	/** Constructor of the class
	* @return void
	*/
	function __construct () {
	}

	/** Affiche le formulaire de commande
	* @return void
	*/
	function display_form() {
		global $wpshop, $wpshop_account, $wpshop_cart, $civility, $wpshop_signup;
		$output = '';

		if ( !empty($_GET['action']) && ($_GET['action']=='cancel') ) {
			// On vide le panier
			$wpshop_cart->empty_cart();
			echo __('Your order has been succesfully cancelled.', 'wpshop');
			return false;
		}

		// Si le panier n'est pas vide
		if($wpshop_cart->is_empty() && empty($_POST['order_id'])) :
			$output .= '<p>'.__('Your cart is empty. Select product(s) before checkout.','wpshop').'</p>';
		else :
			$form_is_ok = $this->managePost();

			$user_id = get_current_user_id();

			// Cart type
			$cart_type = (!empty($_SESSION['cart']['cart_type']) && $_SESSION['cart']['cart_type']=='quotation') ? 'quotation' : 'cart';

			// On r�cup�re les m�thodes de paiements disponibles
			$paymentMethod = get_option('wpshop_paymentMethod', array());

			$_SESSION['order_id'] = !empty($_POST['order_id']) ? $_POST['order_id'] : (!empty($_SESSION['order_id']) ? $_SESSION['order_id'] : 0);

			if ( $form_is_ok && isset($_POST['takeOrder']) && $cart_type=='quotation') {
				$output .= '<p>'.__('Thank you ! Your quotation has been sent. We will respond to you as soon as possible.', 'wpshop').'</p>';
				// On vide le panier
				$wpshop_cart->empty_cart();
			}
			// PAYPAL
			elseif($form_is_ok && !empty($paymentMethod['paypal']) && isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='paypal') {
				wpshop_paypal::display_form($_SESSION['order_id']);
				// On vide le panier
				$wpshop_cart->empty_cart();
			}
			// CHECK
			elseif($form_is_ok && !empty($paymentMethod['checks']) && isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='check') {
				// On recupere les informations de paiements par cheque
				$paymentInfo = get_option('wpshop_paymentAddress', true);
				$output .= '<p>'.__('Thank you ! Your order has been placed and you will receive a confirmation email shortly.', 'wpshop').'</p>';
				$output .= '<p>'.__('You have to send the check with the good amount to the adress :', 'wpshop').'</p>';
				$output .= $paymentInfo['company_name'].'<br />';
				$output .= $paymentInfo['company_street'].'<br />';
				$output .= $paymentInfo['company_postcode'].', '.$paymentInfo['company_city'].'<br />';
				$output .= $paymentInfo['company_country'].'<br /><br />';
				$output .= '<p>'.__('Your order will be shipped upon receipt of the check.', 'wpshop').'</p>';

				// On vide le panier
				$wpshop_cart->empty_cart();
			}
			// CIC
			elseif(/*!empty($paymentMethod['cic']) && */ $form_is_ok && isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='cic') {
				wpshop_CIC::display_form($_SESSION['order_id']);
				// On vide le panier
				$wpshop_cart->empty_cart();
			}
			else {
				if ($user_id) {
					$tpl_component = array();

					/** Display customer addresses */
					$tpl_component['CHECKOUT_CUSTOMER_ADDRESSES_LIST'] = wpshop_account::display_addresses_dashboard();

					/** Display cart content	*/
					$tpl_component['CHECKOUT_SUMMARY_TITLE'] = ($cart_type=='quotation') ? __('Summary of the quotation','wpshop') : __('Summary of the order','wpshop');
					$tpl_component['CHECKOUT_CART_CONTENT'] = $wpshop_cart->display_cart(true);

					$tpl_component['CHECKOUT_TERM_OF_SALES'] = '';
					$option_page_id_terms_of_sale = get_option('wpshop_terms_of_sale_page_id');
					if ( !empty($option_page_id_terms_of_sale) ) {
						$input_def['type'] = 'checkbox';
						$input_def['id'] = $input_def['name'] = 'terms_of_sale';

						$input_def['options']['label']['custom'] = sprintf( __('I have read and I accept %sthe terms of sale%s', 'wpshop'), '<a href="' . get_permalink($option_page_id_terms_of_sale) . '">', '</a>');
						$tpl_component['CHECKOUT_TERM_OF_SALES'] = '<div class="infos_bloc" id="wpshop_terms_acceptation_box" >'.wpshop_form::check_input_type($input_def). '</div>';
					}

					/** Display available payment methods	*/
					$tpl_component['CHECKOUT_PAYMENT_METHODS'] = wpshop_payment::display_payment_methods_choice_form(true);

					$output .= wpshop_display::display_template_element('wpshop_checkout_page', $tpl_component);
					unset($tpl_component);
				}
				else {
 					$output .= '<div class="infos_bloc" id="infos_register" style="'.$this->div_infos_register.'">'.__('Already registered? <a href="#" class="checkoutForm_login">Please login</a>.','wpshop').'</div>';
 					$output .= '<div class="infos_bloc" id="infos_login" style="'.$this->div_infos_login.'">'.__('Not already registered? <a href="#" class="checkoutForm_login">Please register</a>.','wpshop').'</div>';

					// Bloc LOGIN
					$output .= '<div class="col1" id="login" style="'.$this->div_login.'">';
					$output .= $wpshop_account->display_login_form();
					$output .= '</div>';

					$output .= '<div class="col1" id="register" style="'.$this->div_register.'">';
					wpshop_signup::display_form();
					$output .= '</div>';
				}
			}
		endif;

		return $output;
	}

	/** Traite les donn�es re�us en POST
	 * @return void
	*/
	function managePost() {

		global $wpshop, $wpshop_account;
		// Cart type
		$cart_type = (!empty($_SESSION['cart']['cart_type']) && $_SESSION['cart']['cart_type']=='quotation') ? 'quotation' : 'cart';

		// Confirmation (derni�re �tape)
		if(isset($_POST['takeOrder'])) {
			// Test if a shipping and a billing address was choosen
			if ( !isset($_POST['billing_address']) ) {
				$wpshop->add_error(__('You must choose a billing address.', 'wpshop'));
			}
			else {
				// If a order_id is given, meaning that the order is already created and the user wants to process to a new payment
				$order_id = !empty($_POST['order_id']) && is_numeric($_POST['order_id']) ? $_POST['order_id'] : 0;

				if ($cart_type=='quotation') {
					$this->process_checkout($paymentMethod='quotation', $order_id);
				}
				// Paypal
				elseif(isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='paypal') {
					$this->process_checkout($paymentMethod='paypal', $order_id);
				}
				// Ch�que
				elseif(isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='check') {
					$this->process_checkout($paymentMethod='check', $order_id);
				}
				// Ch�que
				elseif(isset($_POST['modeDePaiement']) && $_POST['modeDePaiement']=='cic') {
					$this->process_checkout($paymentMethod='cic', $order_id);
				}
				else $wpshop->add_error(__('You have to choose a payment method to continue.', 'wpshop'));
			}
		}
		else {
			$this->div_login = $this->div_infos_login = 'display:none';
		}
		// Si il y a des erreurs, on les affiche seulement si le panier correspond a une commande
		if($cart_type=='cart' && $wpshop->error_count()>0) {
			echo $wpshop->show_messages();
			return false;
		}
		else return true;
	}


	/** Enregistre la commande dans la bdd apr�s que les champs aient �t� valid�, ou que l'utilisateur soit connect�
	 * @param int $user_id=0 : id du client passant commande. Par d�faut 0 pour un nouveau client
	 * @return void
	*/
	function process_checkout($paymentMethod='paypal', $order_id=0) {
		global $wpdb, $wpshop, $wpshop_cart;

		if (is_user_logged_in()) :
			$user_id = get_current_user_id();

			// If the order is already created in the db
			if(!empty($order_id) && is_numeric($order_id)) {
				$order = get_post_meta($order_id, '_order_postmeta', true);
				if(!empty($order)) {
					if($order['customer_id'] == $user_id) {
						$order['payment_method'] = $paymentMethod;
						// On enregistre la commande
						update_post_meta($order_id, '_order_postmeta', $order);
						update_post_meta($order_id, '_wpshop_order_customer_id', $user_id);
						update_post_meta($order_id, '_wpshop_payment_method', $paymentMethod);
					}
					else $wpshop->add_error(__('You don\'t own the order', 'wpshop'));
				}
				else $wpshop->add_error(__('The order doesn\'t exist.', 'wpshop'));
			}
			else
			{
				$order_data = array(
					'post_type' => WPSHOP_NEWTYPE_IDENTIFIER_ORDER,
					'post_title' => sprintf(__('Order - %s','wpshop'), mysql2date('d M Y\, H:i:s', current_time('mysql', 0), true)),
					'post_status' => 'publish',
					'post_excerpt' => !empty($_POST['order_comments']) ? $_POST['order_comments'] : '',
					'post_author' => $user_id,
					'comment_status' => 'closed'
				);

				// Cart items
				$order_items = array();
				$order_tva = array();

				//$cart = (array)$wpshop_cart->cart;
				$cart = (array)$_SESSION['cart'];

				$download_codes = array();

				// Nouvelle commande
				$order_id = wp_insert_post($order_data);
				$_SESSION['order_id'] = $order_id;

				// Cr�ation des codes de t�l�chargement si il y a des produits t�l�chargeable dans le panier
				foreach($cart['order_items'] as $c) {
					$product = wpshop_products::get_product_data($c['item_id']);
					if(!empty($product['is_downloadable_'])) {
						$download_codes[$c['item_id']] = array('item_id' => $c['item_id'], 'download_code' => uniqid('', true));
					}
				}
				if(!empty($download_codes)) update_user_meta($user_id, '_order_download_codes_'.$order_id, $download_codes);

				// Informations de commande � stocker
				$currency = wpshop_tools::wpshop_get_currency(true);
				$order = array_merge(array(
					'order_key' => NULL,
					'customer_id' => $user_id,
					'order_status' => 'awaiting_payment',
					'order_date' => current_time('mysql', 0),
					'order_payment_date' => null,
					'order_shipping_date' => null,
					'payment_method' => $paymentMethod,
					'order_invoice_ref' => '',
					'order_currency' => $currency
				), $cart);

				// Si c'est un devis
				if ( $paymentMethod == 'quotation' ) {
					$order['order_temporary_key'] = wpshop_orders::get_new_pre_order_reference();
				}
				else {
					$order['order_key'] = wpshop_orders::get_new_order_reference();
				}

				// On enregistre la commande
				update_post_meta($order_id, '_order_postmeta', $order);

				update_post_meta($order_id, '_wpshop_order_customer_id', $order['customer_id']);
				update_post_meta($order_id, '_wpshop_order_shipping_date', $order['order_shipping_date']);
				update_post_meta($order_id, '_wpshop_order_status', $order['order_status']);
				update_post_meta($order_id, '_wpshop_order_payment_date', $order['order_payment_date']);
				update_post_meta($order_id, '_wpshop_payment_method', $order['payment_method']);

				/*	Set custmer information for the order	*/
				wpshop_orders::set_order_customer_addresses($user_id, $order_id, $_POST['shipping_address'], $_POST['billing_address']);

				/*	Notify the customer as the case	*/
				$user_info = get_userdata($user_id);
				$email = $user_info->user_email;
				$first_name = $user_info->user_firstname ;
				$last_name = $user_info->user_lastname;
				// Envoie du message de confirmation de commande au client
				wpshop_tools::wpshop_prepared_email($email, 'WPSHOP_ORDER_CONFIRMATION_MESSAGE', array('customer_first_name' => $first_name, 'customer_last_name' => $last_name, 'order_date' => current_time('mysql', 0)));
			}

		endif;
	}

}