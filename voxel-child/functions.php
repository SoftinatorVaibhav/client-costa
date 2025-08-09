<?php



function get_user_plan(){
    $user_ID = get_current_user_id();
    if($user_ID == 0 ){
        return 'null';
    }

    $meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
    $plan = get_user_meta($user_ID, $meta_key, true);
    $plan = json_decode($plan, true);
    if(!isset($plan['plan'])){
        update_user_meta($user_ID, $meta_key, 'default');
        return 'default';
    }
    return $plan['plan'];
}

function is_post_type($post_id) {
    $current_post_type = get_post_type($post_id);

    if ($current_post_type === 'activities') {
        return true;
    } else {
        return false;
    }
}


function get_traveler_discounts($user_id,$plan) {
    if ($user_id == 0) {
        return 0;
    }   

    if (!isset($plan) || $plan == 'default') {
        // Default plan or no plan set
        return 1;
    } elseif ($plan == 'ec-solotraveler') {
        // If the plan directly contains a number, return it
        return 1;
    }elseif (is_numeric($plan)) {
        // If the plan directly contains a number, return it
        return (int)$plan;
    } else {
        // Extract numbers from plan string if it's not directly numeric
        preg_match('/\d+/', $plan, $matches);
        return isset($matches[0]) ? (int)$matches[0] : 0; // Return the first number found or 0 if none found
    }
}


function enqueue_my_custom_script() {  
    $productDetails = [];
    $post_id = get_the_ID();
    $meta_values = get_post_meta($post_id);
    $user = \Voxel\current_user();
    $member = get_user_plan();
    $traveller_num = traveller_num(); 
    

    if (isset($meta_values['product'][0])) {       
        $product_data = json_decode($meta_values['product'][0], true);
        
        // Extract prices
        $adult_price            = isset($product_data['addons']['adult_total_price']['price']) ? $product_data['addons']['adult_total_price']['price'] : null;
        $child_price            = isset($product_data['addons']['child_total_price']['price']) ? $product_data['addons']['child_total_price']['price'] : null;
        $commission_discount    = isset($meta_values['commission_discount'][0]) ? $meta_values['commission_discount'][0] : 25;
        $net_rate_adult         = isset($meta_values['net_rate_adult'][0]) ? $meta_values['net_rate_adult'][0] : null;
        $net_rate_child         = isset($meta_values['net_rate_child'][0]) ? $meta_values['net_rate_child'][0] : null;
        
        // Data to pass to JavaScript
        $productDetails = [
            'post_id'                   => $post_id,
            'adult_price'               => $adult_price,
            'child_price'               => $child_price,
            'commission_discount'       => $commission_discount,
            'net_rate_adult'            => $net_rate_adult,
            'net_rate_child'            => $net_rate_child,          
        ];

    }
    
    if(is_user_logged_in()){
        $membership = $user->get_membership();       
        $user_id = get_current_user_id(); 
        // If user is a member, add membership details
        if ($member !='default' && $membership) {
            $productDetails['user_id'] = $user_id;
            $productDetails['membership_level'] = $traveller_num;
            $productDetails['membership_adult_quantity'] = isset($membership->plan->adults) ? $membership->plan->adults : 0;
            $productDetails['membership_child_quantity'] = isset($membership->plan->children) ? $membership->plan->children : 0;
        }
        
    }

    
    if ( is_singular('activities') ) { 
        
        wp_enqueue_script('my-custom-script', get_stylesheet_directory_uri() . '/js/custom.js', array('jquery'), '1.0.0', true);

        wp_localize_script('my-custom-script', 'userMembership', array(
            'member'=> $member,
            'productDetails'=>$productDetails
        ));
    }

    // Assuming your cart page slug is 'my-cart'
    if ((is_page('cart-summary') || is_page('cart'))) {

        wp_enqueue_script('cart-script', get_stylesheet_directory_uri() . '/js/cart-custom.js', array('jquery'), '1.0.0', true);

        wp_localize_script('cart-script', 'userCartMembership', array('member'=> $member,'productDetails'=>$productDetails));
        
    }

      if ((is_page('create-private-transfer'))) {

         wp_enqueue_script('custom-extra', get_stylesheet_directory_uri() . '/js/custom-extra.js', array('jquery'), '1.0.0', true);
   
        // wp_localize_script('chauffeur-custom-script', 'userMembership', array('member'=> $member,'traveller_num'=>$traveller_num));

     }

    // if ((is_page('create-private-transfer'))) {

    //     wp_enqueue_script('chauffeur-custom-script', get_stylesheet_directory_uri() . '/js/chauffeur-custom.js', array('jquery'), '1.0.0', true);
   
    //     wp_localize_script('chauffeur-custom-script', 'userMembership', array('member'=> $member,'traveller_num'=>$traveller_num));

    // }

    // Enqueue styles globally
    wp_enqueue_style('custom-style',  get_stylesheet_directory_uri() . '/style.css', array(), '1.0.0', 'all');

    wp_localize_script('cart-script', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    
    
}
add_action('wp_enqueue_scripts', 'enqueue_my_custom_script');

function get_product_details() {
    // Check if product_id is passed
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(['message' => 'Product ID missing']);
        wp_die();
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not logged in']);
        wp_die();
    }

    $product_id = intval($_POST['product_id']); // Sanitize input
    $productDetails = fetch_product_details_by_id($product_id);

    // Check if an error occurred
    if (isset($productDetails['error'])) {
        wp_send_json_error(['message' => $productDetails['error']]);
        wp_die();
    }

    // Send JSON response
    wp_send_json_success([
        'title' => get_the_title($product_id),
        'productDetails' => $productDetails
    ]);

    wp_die();
}

// Hook into AJAX actions
add_action('wp_ajax_get_product_details', 'get_product_details');
add_action('wp_ajax_nopriv_get_product_details', 'get_product_details'); // Allow non-logged-in users


function fetch_product_details_by_id($product_id) {
    if (empty($product_id)) {
        return ['error' => 'Product ID is missing'];
    }

    $meta_values = get_post_meta($product_id); // Get meta values
    
    if (!isset($meta_values['product'][0])) {
        return ['error' => 'Product data not found'];
    }

    if (!is_user_logged_in()) {
        return ['error' => 'User not logged In']; 
    } 

    $user = \Voxel\current_user(); // Get current user
    $membership = $user->get_membership();
    $member = get_user_plan();
    $user_id = get_current_user_id();
    $traveller_num = traveller_num();


    $product_data = json_decode($meta_values['product'][0], true);

    // Extract prices
    $adult_price            = isset($product_data['addons']['adult_total_price']['price']) ? $product_data['addons']['adult_total_price']['price'] : null;
    $child_price            = isset($product_data['addons']['child_total_price']['price']) ? $product_data['addons']['child_total_price']['price'] : null;
    $commission_discount    = isset($meta_values['commission_discount'][0]) ? $meta_values['commission_discount'][0] : 25;
    $net_rate_adult         = isset($meta_values['net_rate_adult'][0]) ? $meta_values['net_rate_adult'][0] : null;
    $net_rate_child         = isset($meta_values['net_rate_child'][0]) ? $meta_values['net_rate_child'][0] : null;

    $productDetails = [
        'user_ID'               => $user_id,
        'post_id'               => $product_id,
        'adult_price'           => $adult_price,
        'child_price'           => $child_price,
        'commission_discount'   => $commission_discount,
        'net_rate_adult'        => $net_rate_adult,
        'net_rate_child'        => $net_rate_child,
    ];

    if ($member != 'default' && $membership) {
        $productDetails['membership_level'] = $traveller_num;
        $productDetails['membership_adult_quantity'] = isset($membership->plan->adults) ? $membership->plan->adults : 0;
        $productDetails['membership_child_quantity'] = isset($membership->plan->children) ? $membership->plan->children : 0;
    }

    return $productDetails;
}


function calculate_rates($productDetails, $adult_quantity, $child_quantity, $member) {
    

    $membership_adult_quantity = isset($productDetails['membership_adult_quantity']) ? $productDetails['membership_adult_quantity'] : 0;
    $membership_child_quantity = isset($productDetails['membership_child_quantity']) ? $productDetails['membership_child_quantity'] : 0;

    if ($adult_quantity === 0 && $child_quantity === 0) {
        return null;
    }

    $calculatedRates = [];

    if ($member !== 'default' && !($adult_quantity === 0 && $child_quantity === 0)) {
        if ($adult_quantity <= $membership_adult_quantity && $child_quantity <= $membership_child_quantity) {

            $membershipDetails = array_merge($productDetails, [
                'adult_quantity' => $adult_quantity,
                'child_quantity' => $child_quantity
            ]);

            $calculatedRates = calculateMemberRates($membershipDetails);

        } else {
            $extra_adults = max(0, $adult_quantity - $membership_adult_quantity);
            $extra_children = max(0, $child_quantity - $membership_child_quantity);

            // Membership-allowed rates
            $applyMembershipDetails = array_merge($productDetails, [
                'adult_quantity' => min($adult_quantity, $membership_adult_quantity),
                'child_quantity' => min($child_quantity, $membership_child_quantity)
            ]);

            $memberRates = calculateMemberRates($applyMembershipDetails);

            // Non-membership extra charge rates
            $extraMemberDetails = array_merge($productDetails, [
                'adult_quantity' => $extra_adults,
                'child_quantity' => $extra_children
            ]);

            $nonMemberRates = calculateNonMemberRates($extraMemberDetails);

            // Merge both rates
            foreach ($memberRates as $key => $value) {
                $calculatedRates[$key] = (isset($memberRates[$key]) ? $memberRates[$key] : 0) + 
                                         (isset($nonMemberRates[$key]) ? $nonMemberRates[$key] : 0);
            }

            
        }
    } else if ($member === 'default') {
        $nonMembershipDetails = array_merge($productDetails, [
            'adult_quantity' => $adult_quantity,
            'child_quantity' => $child_quantity
        ]);

        $calculatedRates = calculateNonMemberRates($nonMembershipDetails);
    }

    return $calculatedRates; // Now function returns calculated rates
}


function calculateMemberRates($productDetails) {
    $everythingCostaRicaAdultRate = 0;
    $everythingCostaRicaChildRate = 0;
    $totalAdultCost = 0;
    $totalChildCost = 0;

    // Extract necessary values from product details
    $post_id = $productDetails['post_id'] ?? null;
    $membership_level = floatval($productDetails['membership_level'] ?? 0);
    $adult_price = floatval($productDetails['adult_price'] ?? 0);
    $child_price = floatval($productDetails['child_price'] ?? 0);
    $commission_discount = intval($productDetails['commission_discount'] ?? 25);
    $net_rate_adult = floatval($productDetails['net_rate_adult'] ?? 0);
    $net_rate_child = floatval($productDetails['net_rate_child'] ?? 0);
    $adult_quantity = intval($productDetails['adult_quantity'] ?? 0);
    $child_quantity = intval($productDetails['child_quantity'] ?? 0);
    $membership_adult_quantity = intval($productDetails['membership_adult_quantity'] ?? 0);
    $membership_child_quantity = intval($productDetails['membership_child_quantity'] ?? 0);

    // Convert commission_discount to percentage
    $commission_percentage = $commission_discount / 100;

    if ($adult_quantity > 0) {
        // Vendor Profit (same as Net Rate)
        $vendorProfitAdult = $net_rate_adult;
        $ourCommissionAdult = $adult_price - $vendorProfitAdult;

        // Apply 25% reduction in commission
        $finalCommissionAdult = $ourCommissionAdult - ($ourCommissionAdult * $commission_percentage);
        // Total cost for all adults
        $everythingCostaRicaAdultRate = $net_rate_adult + $finalCommissionAdult;            
        $totalAdultCost = $everythingCostaRicaAdultRate * $adult_quantity;
    }

    if ($child_quantity > 0) {
        // Vendor Profit (same as Net Rate)
        $vendorProfitChild = $net_rate_child;
        $ourCommissionChild = $child_price - $vendorProfitChild;

        // Apply 25% reduction in commission
        $finalCommissionChild = $ourCommissionChild - ($ourCommissionChild * $commission_percentage);
        // Total cost for all childs
        $everythingCostaRicaChildRate = $net_rate_child + $finalCommissionChild;
        $totalChildCost = $everythingCostaRicaChildRate * $child_quantity;
    }

    // Calculate deposit amount with membership discount applied
    $depositAmount = 
        (($everythingCostaRicaAdultRate - $net_rate_adult) * $adult_quantity) - 
        ((($everythingCostaRicaAdultRate - $net_rate_adult) * $membership_adult_quantity) * $membership_level) +
        (($everythingCostaRicaChildRate - $net_rate_child) * $child_quantity) - 
        ((($everythingCostaRicaChildRate - $net_rate_child) * $membership_child_quantity) * $membership_level);

    // Ensure deposit amount is never negative
    if ($child_quantity === 0) {
        $depositAmount = max(0, (($everythingCostaRicaAdultRate - $net_rate_adult) * $adult_quantity) -
            ((($everythingCostaRicaAdultRate - $net_rate_adult) * $membership_adult_quantity) * $membership_level));
    } else {
        $depositAmount = max(0, $depositAmount);
    }
    

    // Calculate Due on Arrival Amount
    $dueOnArrival = ($net_rate_adult * $adult_quantity) + ($net_rate_child * $child_quantity);
    $totalCostaRicaRate = $totalAdultCost + $totalChildCost;

    // Final calculated rates
    $rates = [
        "everything_costa_rica_adult_rate" => $everythingCostaRicaAdultRate,
        "everything_costa_rica_child_rate" => $everythingCostaRicaChildRate,
        "totalCostaRicaRate" => $totalCostaRicaRate,
        "deposit_amount" => $depositAmount,
        "due_on_arrival" => $dueOnArrival
    ];
    return $rates;
}


function calculateNonMemberRates($productDetails) {
    $everythingCostaRicaAdultRate = 0;
    $everythingCostaRicaChildRate = 0;
    $totalAdultCost = 0;
    $totalChildCost = 0;

    // Extract necessary values
    $post_id = $productDetails['post_id'] ?? null;
    $adult_price = floatval($productDetails['adult_price'] ?? 0);
    $child_price = floatval($productDetails['child_price'] ?? 0);
    $commission_discount = intval($productDetails['commission_discount'] ?? 25);
    $net_rate_adult = floatval($productDetails['net_rate_adult'] ?? 0);
    $net_rate_child = floatval($productDetails['net_rate_child'] ?? 0);
    $adult_quantity = intval($productDetails['adult_quantity'] ?? 0);
    $child_quantity = intval($productDetails['child_quantity'] ?? 0);

    // Convert commission_discount to percentage
    $commission_percentage = $commission_discount / 100;

    if ($adult_quantity > 0) {
        // Vendor Profit (same as Net Rate)
        $vendorProfitAdult = $net_rate_adult;
        $ourCommissionAdult = $adult_price - $vendorProfitAdult;

        // Apply 25% reduction in commission
        $finalCommissionAdult = $ourCommissionAdult - ($ourCommissionAdult * $commission_percentage);
        // Total cost for all adults
        $everythingCostaRicaAdultRate = $net_rate_adult + $finalCommissionAdult;            
        $totalAdultCost = $everythingCostaRicaAdultRate * $adult_quantity;
    }

    if ($child_quantity > 0) {
        // Vendor Profit (same as Net Rate)
        $vendorProfitChild = $net_rate_child;
        $ourCommissionChild = $child_price - $vendorProfitChild;

        // Apply 25% reduction in commission
        $finalCommissionChild = $ourCommissionChild - ($ourCommissionChild * $commission_percentage);
        // Total cost for all childs
        $everythingCostaRicaChildRate = $net_rate_child + $finalCommissionChild;
        $totalChildCost = $everythingCostaRicaChildRate  * $child_quantity;
    }

    // Correct Deposit Amount Calculation
    $depositAmount = 
        (($everythingCostaRicaAdultRate - $net_rate_adult) * $adult_quantity) +
        (($everythingCostaRicaChildRate - $net_rate_child) * $child_quantity);

    // Ensure deposit amount is never negative
    $depositAmount = max(0, $depositAmount);

    // Calculate Due on Arrival Amount
    $dueOnArrival = ($net_rate_adult * $adult_quantity) + ($net_rate_child * $child_quantity);
    $totalCostaRicaRate = $totalAdultCost + $totalChildCost ;

    // Final calculated rates
    $rates = [
       "everything_costa_rica_adult_rate" => $everythingCostaRicaAdultRate,
        "everything_costa_rica_child_rate" => $everythingCostaRicaChildRate,
        "totalCostaRicaRate" => $totalCostaRicaRate,
        "deposit_amount" => $depositAmount,
        "due_on_arrival" => $dueOnArrival
    ];

    return $rates;
}



function custom_get_current_user_id() {
    $user_id = get_current_user_id();
    return $user_id;
}

function traveller_num(){
    $plan = get_user_plan();
    if($plan ===0){
    	return 0;
    }
    else if($plan == 'default' || $plan == 'ec-solotraveler'){
        return 1;
    }else{
        preg_match('/\d+/', $plan, $matches);
        $x = $matches[0];
        return $x;
    }
}


function update_custom_user_meta_offer($customer_id){
    $discounts = (int) get_user_meta($customer_id, 'offer_available', true);
    $offer_used = get_user_meta($customer_id, 'offer_used', true);

    update_user_meta($customer_id, 'last_offer', 'NO');

    if ($discounts > 0 && $offer_used == 'NO') {

        if($discounts == 1){
            update_user_meta($customer_id, 'offer_used', 'YES');
            update_user_meta($customer_id, 'last_offer', 'YES');
            update_user_meta($customer_id, 'previous_plan_num', traveller_num());
        }

        update_user_meta($customer_id, 'offer_available', $discounts - 1);   

    }

}

function order_custom_status($order_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'vx_orders'; // Adjust the table name as needed
    $record_id = $order_id; // ID of the record you want to update
    $new_value = 'pending_approval'; // The new value you want to set
    $column_name = 'status';
    $custom_booking_amount = get_post_meta($order_id, 'custom_booking_amount', true);
    if($custom_booking_amount == 0){
        $result = $wpdb->update(
            $table_name,
            [ $column_name => $new_value ], // Column to update
            [ 'id' => $record_id ] // Condition to find the row to update
        );
    }
    
}

function email_debug_data($data) {
   //wp_mail('pinky@softinator.com', 'Debug Information', print_r($data, true));
    //wp_mail('ankj0511@gmail.com', 'Debug Information', print_r($data, true));
}

function email_debug_datav($data) {
    //wp_mail('vaibhav.softinator@gmail.com', 'Debug Information', print_r($data, true));
}

add_filter( 'voxel/roles/vendor/is_safe_for_registration', '__return_true' );

add_filter( 'voxel/dynamic-tags/modifiers', function( $modifiers ) {
class Get_Address_Parts extends \Voxel\Dynamic_Tags\Base_Modifier {
public function get_label(): string {
return 'Get address parts';
}
public function get_key(): string {
return 'get_address_parts';
}
public function get_arguments(): array {
return [
'parts' => [
'type' => \Voxel\Form_Models\Text_Model::class,
'label' => _x( 'Enter parts, e.g. 1,3', 'modifiers', 'voxel-backend' ),
'classes' => 'x-col-12',
],
];
}
public function apply( $value, $args, $group ) {
$parts = explode( ', ', $value );
$requested = array_filter( array_map( 'intval', explode( ',', $args[0] ?? '' ) ) );
return join( ', ', array_filter( array_map( function( $i ) use ( $parts ) {
return $i < 0 ? ( $parts[ count( $parts ) + $i ] ?? null ) : ( $parts[ $i - 1 ] ?? null );
}, $requested ) ) );
}
}
$modifiers['get_address_parts'] = \Get_Address_Parts::class;
return $modifiers;
} );


add_filter( 'voxel/dynamic-data/modifiers', function( $modifiers ) {
	class Get_Text_Parts extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {
		public function get_label(): string {
			return 'Get text parts';
		}

		public function get_key(): string {
			return 'get_text_parts';
		}

		protected function define_args(): void {
			$this->define_arg( [
				'type' => 'text',
				'label' => _x( 'Enter parts, e.g. 1,3', 'modifiers', 'voxel-backend' ),
			] );

			$this->define_arg( [
				'type' => 'text',
				'label' => _x( 'Delimiter (use a custom delimiter instead of a comma)', 'modifiers', 'voxel-backend' ),
					'placeholder' => ', ',
			] );
		}

		public function apply( string $value ) {
			$delimiter = ! empty( $this->get_arg(1) ) ? (string) $this->get_arg(1) : ', ';
			$parts = explode( $delimiter, $value );
			$requested = array_filter( array_map( 'intval', explode( ',', $this->get_arg(0) ?: '' ) ) );

			return join( $delimiter, array_filter( array_map( function( $i ) use ( $parts ) {
				return $i < 0 ? ( $parts[ count( $parts ) + $i ] ?? null ) : ( $parts[ $i - 1 ] ?? null );
			}, $requested ) ) );
		}
	}

	$modifiers['get_text_parts'] = \Get_Text_Parts::class;
	return $modifiers;
} );

add_shortcode( 'video_duration', function () {
    $att_id = get_post_meta( get_the_ID(), 'video-upload-file', true );
    if ( ! $att_id ) { return ''; }

    $meta = wp_get_attachment_metadata( $att_id );
    if ( ! empty( $meta['length_formatted'] ) ) {
        return esc_html( $meta['length_formatted'] );
    }
    if ( ! empty( $meta['length'] ) ) {
        return esc_html( gmdate( 'i:s', (int) $meta['length'] ) );
    }
    return '';
} );
/* Temporary shortcode: [show_meta id="41541"] */
add_shortcode( 'show_meta', function ( $a ) {
    $id   = (int) ( $a['id'] ?? 0 );
    $meta = get_post_meta( $id, '_wp_attachment_metadata', true );
    return '<pre>'.esc_html( print_r( $meta, true ) ).'</pre>';
});
