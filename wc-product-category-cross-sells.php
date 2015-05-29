<?php

/**
 * The Woocommerce Product Category Cross-Sells Plugin
 * 
 * @package WC Product Category Cross-Sells
 * @subpackage Main
 */

/**
 * Plugin Name:       Woocommerce Product Category Cross-Sells
 * Description:       Define Woocommerce cross-sells for products in a category
 * Plugin URI:        https://github.com/lmoffereins/wc-product-category-cross-sells/
 * Version:           1.0.2
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       wc-product-category-cross-sells
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/wc-product-category-cross-sells
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Product_Category_Cross_Sells' ) ) :
/**
 * The main plugin class
 *
 * @since 1.0.0
 */
final class WC_Product_Category_Cross_Sells {

	/**
	 * The term meta key for cross sell product ids
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $meta_key = '_crosssell_ids';

	/**
	 * Setup and return the singleton pattern
	 *
	 * @since 1.0.0
	 *
	 * @uses WC_Product_Category_Cross_Sells::setup_globals()
	 * @uses WC_Product_Category_Cross_Sells::setup_actions()
	 * @return The single WC_Product_Category_Cross_Sells
	 */
	public static function instance() {

		// Store instance locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new WC_Product_Category_Cross_Sells;
			$instance->setup_globals();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Prevent the plugin class from being loaded more than once
	 */
	private function __construct() { /* Nothing to do */ }

	/** Private methods *************************************************/

	/**
	 * Setup default class globals
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/
		
		$this->version    = '1.0.2';
		
		/** Paths *************************************************************/
		
		// Setup some base path and URL information
		$this->file       = __FILE__;
		$this->basename   = plugin_basename( $this->file );
		$this->plugin_dir = plugin_dir_path( $this->file );
		$this->plugin_url = plugin_dir_url ( $this->file );
		
		// Languages
		$this->lang_dir   = trailingslashit( $this->plugin_dir . 'languages' );
		
		/** Misc **************************************************************/

		$this->domain     = 'wc-product-category-cross-sells';
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		
		// Filter cross sell ids
		add_filter( 'get_post_metadata', array( $this, 'filter_cross_sell_ids' ), 10, 4 );

		// Admin: product category meta
		add_action( 'admin_enqueue_scripts',        array( $this, 'enqueue_scripts'      ), 20    );
		add_action( 'admin_print_scripts',          array( $this, 'print_scripts'        ), 20    );
		add_action( 'product_cat_add_form_fields',  array( $this, 'add_category_fields'  )        );
		add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_fields' ), 10, 2 );
		add_action( 'created_term',                 array( $this, 'save_category_fields' ), 10, 3 );
		add_action( 'edit_term',                    array( $this, 'save_category_fields' ), 10, 3 );
	}

	/** Public methods **************************************************/

	/**
	 * Return product category cross sell ids for products without defined cross sells
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_screen()
	 * @uses remove_filter()
	 * @uses current_filter()
	 * @uses get_post_meta()
	 * @uses add_filter()
	 * @uses get_the_terms()
	 * @uses get_woocommerce_term_meta()
	 * 
	 * @param null $retval Short-circuit value
	 * @param int $post_id Product ID
	 * @param string $meta_key Meta key
	 * @param bool $single Whether to return the first array value
	 * @return mixed|array Original value or cross sell ids
	 */
	public function filter_cross_sell_ids( $retval, $post_id, $meta_key, $single ) {

		// Bail when not filtering cross sells
		if ( '_crosssell_ids' != $meta_key )
			return $retval;

		// Bail when editing a product
		if ( is_admin() && isset( get_current_screen()->id ) && 'product' == get_current_screen()->id )
			return $retval;

		// Catch original post meta
		remove_filter( current_filter(), array( $this, __FUNCTION__ ), 10, 4 );
		$meta = get_post_meta( $post_id, $meta_key, true );
		add_filter( current_filter(), array( $this, __FUNCTION__ ), 10, 4 );

		// Bail when product has assigned cross sells
		if ( ! empty( $meta ) )
			return $retval;

		// Catch the product's categories
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$retval = array();

			// Collect cross sells per term
			foreach ( $terms as $term ) {
				$cross_sells = get_woocommerce_term_meta( $term->term_id, $this->meta_key );

				// Only append cross sells when defined
				if ( $cross_sells ) {
					$retval += (array) $cross_sells;
				}
			}

			// Return shuffled, wrapped in array
			shuffle( $retval );
			$retval = array( $retval );
		}

		return $retval;
	}

	/**
	 * Enqueue scripts in the admin
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_screen()
	 * @uses wp_enqueue_script()
	 */
	public function enqueue_scripts() {

		// Bail when not adding/editing product categories
		if ( 'edit-product_cat' != get_current_screen()->id )
			return;

		// Enqueue required scripts
		wp_enqueue_script( 'ajax-chosen' );
	}

	/**
	 * Enqueue scripts in the admin
	 *
	 * @since 1.0.0
	 *
	 * @link woocommerce/assets/js/admin/meta-boxes.js
	 *
	 * @uses get_current_screen()
	 */
	public function print_scripts() {

		// Bail when not adding/editing product categories
		if ( 'edit-product_cat' != get_current_screen()->id )
			return;

		?><script>
			jQuery(document).ready( function( $ ) {

				// Chosenify select elements
				jQuery( 'select.ajax_chosen_select_products' ).ajaxChosen({
					method: 'GET',
					url: '<?php echo admin_url( "admin-ajax.php" ); ?>',
					dataType: 'json',
					afterTypeDelay: 100,
					data: {
						action:   'woocommerce_json_search_products',
						security: '<?php echo wp_create_nonce( "search-products" ); ?>'
					}
				}, function( data ) {
					var terms = [];
					$.each( data, function( i, val ) {
						terms[i] = val;
					});
					return terms;
				});
			});
		</script>

		<style>
			#crosssell_ids_chosen {
				width: 90% !important;
			}
		</style>

		<?php
	}

	/**
	 * Output the add-new category meta field
	 *
	 * @since 1.0.0
	 *
	 * @uses WC_Product_Category_Cross_Sells::select_cross_sells()
	 */
	public function add_category_fields() { ?>

		<div class="form-field term-cross-sells-wrap">
			<label for="cross_sells"><?php _e( 'Cross-Sells', 'woocommerce' ); ?></label>
			<?php $this->select_cross_sells(); ?>
			<p><?php _e( 'The default cross-sells for products in this category.', 'wc-product-category-cross-sells' ); ?></p>
		</div>

		<?php
	}

	/**
	 * Output the edit category meta field
	 *
	 * @since 1.0.0
	 *
	 * @uses WC_Product_Category_Cross_Sells::select_cross_sells()
	 * 
	 * @param object $term Term data
	 * @param string $taxonomy Taxonomy name
	 */
	public function edit_category_fields( $term, $taxonomy ) { ?>

		<tr class="form-field term-cross-sells-wrap">
			<th scope="row" valign="top"><label><?php _e( 'Cross-Sells', 'woocommerce' ); ?></label></th>
			<td>
				<?php $this->select_cross_sells( $term->term_id ); ?>
				<p class="description"><?php _e( 'The default cross-sells for products in this category.', 'wc-product-category-cross-sells' ); ?></p>
			</td>
		</tr>

		<?php
	}

	/**
	 * Display the input field to select cross sell ids
	 *
	 * @since 1.0.0
	 * 
	 * @see WC_Meta_Box_Product_Data::output()
	 *
	 * @uses get_woocommerce_term_meta()
	 * @uses wc_get_product()
	 * @uses WC_Product::get_formatted_name()
	 * 
	 * @param int $term_id Optional. Term ID
	 */
	public function select_cross_sells( $term_id = 0 ) {

		// Get the term's meta
		$crosssell_ids = get_woocommerce_term_meta( $term_id, $this->meta_key, true );
		$product_ids   = ! empty( $crosssell_ids ) ? array_map( 'absint',  $crosssell_ids ) : array(); ?>

		<select id="crosssell_ids" name="crosssell_ids[]" class="ajax_chosen_select_products" multiple="multiple" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
			<?php foreach ( $product_ids as $product_id ) : if ( $product = wc_get_product( $product_id ) ) : ?>
			<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo esc_html( $product->get_formatted_name() ); ?></option>
			<?php endif; endforeach; ?>
		</select> 

		<img class="help_tip" data-tip='<?php _e( 'Cross-sells are products which you promote in the cart, based on the current product.', 'woocommerce' ) ?>' src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png" height="16" width="16" />

		<?php
	}

	/**
	 * Save product category meta on term update
	 *
	 * @since 1.0.0
	 *
	 * @uses update_woocommerce_term_meta()
	 *
	 * @param int $term_id Term ID being saved
	 * @param int $tt_id
	 * @param string $taxonomy Taxonomy name
	 */
	public function save_category_fields( $term_id, $tt_id, $taxonomy ) {

		// Update posted values
		if ( isset( $_POST['crosssell_ids'] ) ) {
			update_woocommerce_term_meta( $term_id, $this->meta_key, array_map( 'intval', $_POST['crosssell_ids'] ) );
		}
	}
}

/**
 * Return single instance of this main plugin class
 *
 * @since 1.0.0
 * 
 * @return WC_Product_Category_Cross_Sells
 */
function wc_product_category_cross_sells() {
	return WC_Product_Category_Cross_Sells::instance();
}

// Initiate on Woocommerce init
add_action( 'woocommerce_init', 'wc_product_category_cross_sells' );

endif; // class_exists
