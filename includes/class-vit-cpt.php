<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Opportunity" custom post type volunteers log hours against.
 */
class VIT_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . VIT_CPT_OPPORTUNITY, array( __CLASS__, 'save_meta' ) );
		add_filter( 'manage_' . VIT_CPT_OPPORTUNITY . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . VIT_CPT_OPPORTUNITY . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Opportunities', 'volunteer-impact-tracker' ),
			'singular_name'      => __( 'Opportunity', 'volunteer-impact-tracker' ),
			'add_new_item'       => __( 'Add New Opportunity', 'volunteer-impact-tracker' ),
			'edit_item'          => __( 'Edit Opportunity', 'volunteer-impact-tracker' ),
			'new_item'           => __( 'New Opportunity', 'volunteer-impact-tracker' ),
			'view_item'          => __( 'View Opportunity', 'volunteer-impact-tracker' ),
			'search_items'       => __( 'Search Opportunities', 'volunteer-impact-tracker' ),
			'not_found'          => __( 'No opportunities found', 'volunteer-impact-tracker' ),
			'all_items'          => __( 'Opportunities', 'volunteer-impact-tracker' ),
			'menu_name'          => __( 'Opportunities', 'volunteer-impact-tracker' ),
		);

		register_post_type(
			VIT_CPT_OPPORTUNITY,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_in_menu'    => 'vit-volunteers',
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
			'vit_opportunity_details',
			__( 'Opportunity Details', 'volunteer-impact-tracker' ),
			array( __CLASS__, 'render_meta_box' ),
			VIT_CPT_OPPORTUNITY,
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'vit_save_opportunity_meta', 'vit_opportunity_meta_nonce' );
		$date     = get_post_meta( $post->ID, '_vit_date', true );
		$location = get_post_meta( $post->ID, '_vit_location', true );
		$capacity = get_post_meta( $post->ID, '_vit_capacity', true );
		?>
		<p>
			<label for="vit_date"><strong><?php esc_html_e( 'Date', 'volunteer-impact-tracker' ); ?></strong></label><br>
			<input type="date" id="vit_date" name="vit_date" value="<?php echo esc_attr( $date ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="vit_location"><strong><?php esc_html_e( 'Location', 'volunteer-impact-tracker' ); ?></strong></label><br>
			<input type="text" id="vit_location" name="vit_location" value="<?php echo esc_attr( $location ); ?>" style="width:100%;">
		</p>
		<p>
			<label for="vit_capacity"><strong><?php esc_html_e( 'Volunteer Capacity (optional)', 'volunteer-impact-tracker' ); ?></strong></label><br>
			<input type="number" min="0" id="vit_capacity" name="vit_capacity" value="<?php echo esc_attr( $capacity ); ?>" style="width:100%;">
		</p>
		<?php
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['vit_opportunity_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vit_opportunity_meta_nonce'] ) ), 'vit_save_opportunity_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['vit_date'] ) ) {
			$date = vit_sanitize_date( wp_unslash( $_POST['vit_date'] ) );
			if ( $date ) {
				update_post_meta( $post_id, '_vit_date', $date );
			} else {
				delete_post_meta( $post_id, '_vit_date' );
			}
		}
		if ( isset( $_POST['vit_location'] ) ) {
			update_post_meta( $post_id, '_vit_location', sanitize_text_field( wp_unslash( $_POST['vit_location'] ) ) );
		}
		if ( isset( $_POST['vit_capacity'] ) ) {
			update_post_meta( $post_id, '_vit_capacity', absint( $_POST['vit_capacity'] ) );
		}
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['vit_date']     = __( 'Date', 'volunteer-impact-tracker' );
				$new['vit_location'] = __( 'Location', 'volunteer-impact-tracker' );
				$new['vit_hours']    = __( 'Approved Hours', 'volunteer-impact-tracker' );
			}
		}
		return $new;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'vit_date' === $column ) {
			$date = get_post_meta( $post_id, '_vit_date', true );
			echo $date ? esc_html( $date ) : '&mdash;';
		} elseif ( 'vit_location' === $column ) {
			$location = get_post_meta( $post_id, '_vit_location', true );
			echo $location ? esc_html( $location ) : '&mdash;';
		} elseif ( 'vit_hours' === $column ) {
			global $wpdb;
			$table = $wpdb->prefix . VIT_TABLE_HOURS;
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(hours) FROM {$table} WHERE opportunity_id = %d AND status = 'approved'",
					$post_id
				)
			);
			echo esc_html( $total ? number_format_i18n( (float) $total, 2 ) : '0' );
		}
	}
}
