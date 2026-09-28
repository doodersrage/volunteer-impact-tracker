<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Opportunity" custom post type volunteers log hours against.
 */
class COMMONSCRIBE_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . COMMONSCRIBE_CPT_OPPORTUNITY, array( __CLASS__, 'save_meta' ) );
		add_filter( 'manage_' . COMMONSCRIBE_CPT_OPPORTUNITY . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . COMMONSCRIBE_CPT_OPPORTUNITY . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Opportunities', 'commonscribe-volunteer-log' ),
			'singular_name'      => __( 'Opportunity', 'commonscribe-volunteer-log' ),
			'add_new_item'       => __( 'Add New Opportunity', 'commonscribe-volunteer-log' ),
			'edit_item'          => __( 'Edit Opportunity', 'commonscribe-volunteer-log' ),
			'new_item'           => __( 'New Opportunity', 'commonscribe-volunteer-log' ),
			'view_item'          => __( 'View Opportunity', 'commonscribe-volunteer-log' ),
			'search_items'       => __( 'Search Opportunities', 'commonscribe-volunteer-log' ),
			'not_found'          => __( 'No opportunities found', 'commonscribe-volunteer-log' ),
			'all_items'          => __( 'Opportunities', 'commonscribe-volunteer-log' ),
			'menu_name'          => __( 'Opportunities', 'commonscribe-volunteer-log' ),
		);

		register_post_type(
			COMMONSCRIBE_CPT_OPPORTUNITY,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_in_menu'    => 'commonscribe-volunteers',
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-groups',
				'supports'        => array( 'title', 'editor' ),
				'has_archive'     => true,
				'rewrite'         => array( 'slug' => 'volunteer-opportunities' ),
				'capability_type' => 'post',
			)
		);
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'commonscribe_opportunity_details',
			__( 'Opportunity Details', 'commonscribe-volunteer-log' ),
			array( __CLASS__, 'render_meta_box' ),
			COMMONSCRIBE_CPT_OPPORTUNITY,
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'commonscribe_save_opportunity_meta', 'commonscribe_opportunity_meta_nonce' );
		$date     = get_post_meta( $post->ID, '_commonscribe_date', true );
		$location = get_post_meta( $post->ID, '_commonscribe_location', true );
		$capacity = get_post_meta( $post->ID, '_commonscribe_capacity', true );
		?>
		<p>
			<label for="commonscribe_date"><strong><?php esc_html_e( 'Date', 'commonscribe-volunteer-log' ); ?></strong></label><br>
			<input type="date" id="commonscribe_date" name="commonscribe_date" value="<?php echo esc_attr( $date ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="commonscribe_location"><strong><?php esc_html_e( 'Location', 'commonscribe-volunteer-log' ); ?></strong></label><br>
			<input type="text" id="commonscribe_location" name="commonscribe_location" value="<?php echo esc_attr( $location ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="commonscribe_capacity"><strong><?php esc_html_e( 'Volunteer Capacity (optional)', 'commonscribe-volunteer-log' ); ?></strong></label><br>
			<input type="number" min="0" id="commonscribe_capacity" name="commonscribe_capacity" value="<?php echo esc_attr( $capacity ); ?>" style="width:100%;">
		</p>
		<?php
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['commonscribe_opportunity_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['commonscribe_opportunity_meta_nonce'] ) ), 'commonscribe_save_opportunity_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['commonscribe_date'] ) ) {
			$date = commonscribe_sanitize_date( sanitize_text_field( wp_unslash( $_POST['commonscribe_date'] ) ) );
			if ( $date ) {
				update_post_meta( $post_id, '_commonscribe_date', $date );
			} else {
				delete_post_meta( $post_id, '_commonscribe_date' );
			}
		}
		if ( isset( $_POST['commonscribe_location'] ) ) {
			update_post_meta( $post_id, '_commonscribe_location', sanitize_text_field( wp_unslash( $_POST['commonscribe_location'] ) ) );
		}
		if ( isset( $_POST['commonscribe_capacity'] ) ) {
			update_post_meta( $post_id, '_commonscribe_capacity', absint( $_POST['commonscribe_capacity'] ) );
		}
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['commonscribe_date']     = __( 'Date', 'commonscribe-volunteer-log' );
				$new['commonscribe_location'] = __( 'Location', 'commonscribe-volunteer-log' );
				$new['commonscribe_hours']    = __( 'Approved Hours', 'commonscribe-volunteer-log' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'commonscribe_date' === $column ) {
			$date = get_post_meta( $post_id, '_commonscribe_date', true );
			echo $date ? esc_html( $date ) : '&mdash;';
		} elseif ( 'commonscribe_location' === $column ) {
			$location = get_post_meta( $post_id, '_commonscribe_location', true );
			echo $location ? esc_html( $location ) : '&mdash;';
		} elseif ( 'commonscribe_hours' === $column ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API.
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(hours) FROM %i WHERE opportunity_id = %d AND status = 'approved'",
					$wpdb->prefix . COMMONSCRIBE_TABLE_HOURS,
					$post_id
				)
			);
			echo esc_html( $total ? number_format_i18n( (float) $total, 2 ) : '0' );
		}
	}
}
