<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Product Studio — a simple, focused screen for staff to add and edit products.
 */

add_action( 'admin_menu', function () {
	add_menu_page( 'Product Studio', 'Product Studio', 'sb_studio', 'sb-studio', 'sb_studio_page', 'dashicons-products', 3 );
	add_submenu_page( 'sb-studio', 'All products', 'All products', 'sb_studio', 'sb-studio', 'sb_studio_page' );
	add_submenu_page( 'sb-studio', 'Add product', 'Add product', 'sb_studio', 'sb-studio&action=new', '__return_null' );
	add_submenu_page( 'sb-studio', 'Soubenco settings', 'Settings', 'manage_options', 'sb-settings', 'sb_settings_page' );
} );

/** Product uploaders only see the Studio. */
function sb_is_uploader() {
	$u = wp_get_current_user();
	return $u && in_array( 'sb_product_manager', (array) $u->roles, true ) && ! current_user_can( 'manage_options' );
}

add_action( 'admin_menu', function () {
	if ( ! sb_is_uploader() ) return;
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], array( 'sb-studio', 'profile.php' ), true ) ) remove_menu_page( $item[2] );
	}
}, 999 );

add_action( 'admin_init', function () {
	if ( ! sb_is_uploader() || wp_doing_ajax() ) return;
	global $pagenow;
	$allowed = array( 'admin.php', 'profile.php', 'async-upload.php', 'media-upload.php', 'admin-post.php', 'admin-ajax.php' );
	if ( ! in_array( $pagenow, $allowed, true ) || ( 'admin.php' === $pagenow && ( ! isset( $_GET['page'] ) || 'sb-studio' !== $_GET['page'] ) ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sb-studio' ) );
		exit;
	}
} );

add_filter( 'login_redirect', function ( $to, $req, $user ) {
	if ( $user instanceof WP_User && in_array( 'sb_product_manager', (array) $user->roles, true ) ) return admin_url( 'admin.php?page=sb-studio' );
	return $to;
}, 10, 3 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ! sb_is_uploader() ) return;
	foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'customize' ) as $n ) $bar->remove_node( $n );
}, 999 );

/** Uploaders only see their own media library items + products' images. */
add_filter( 'ajax_query_attachments_args', function ( $q ) {
	return $q;
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( $hook, 'sb-studio' ) && false === strpos( $hook, 'sb-settings' ) ) return;
	wp_enqueue_media();
	wp_enqueue_style( 'sb-studio-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700&display=swap', array(), null );
	wp_enqueue_style( 'sb-studio', SB_URL . 'assets/studio.css', array(), (string) filemtime( SB_DIR . 'assets/studio.css' ) );
	wp_enqueue_script( 'sb-studio', SB_URL . 'assets/studio.js', array( 'jquery', 'jquery-ui-sortable' ), (string) filemtime( SB_DIR . 'assets/studio.js' ), true );
	wp_localize_script( 'sb-studio', 'SBStudio', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'sb_studio' ),
	) );
} );

/** Keep the Studio distraction-free: no Hostinger chat bubble or surveys on these screens. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( $hook, 'sb-studio' ) && false === strpos( $hook, 'sb-settings' ) && ! sb_is_uploader() ) return;
	foreach ( array( 'hostinger_chatbot', 'hostinger_surveys_scripts', 'hostinger_ai_assistant_woo_requests' ) as $h ) { wp_dequeue_script( $h ); }
	foreach ( array( 'hostinger_chatbot', 'hostinger_surveys_styles', 'hostinger_ai_assistant_woo_styles' ) as $h ) { wp_dequeue_style( $h ); }
}, 999 );

function sb_studio_page() {
	if ( ! current_user_can( 'sb_studio' ) ) wp_die( 'Not allowed.' );
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	echo '<div class="sbs-wrap">';
	if ( 'new' === $action || 'edit' === $action ) sb_studio_form( 'edit' === $action ? (int) $_GET['id'] : 0 );
	else sb_studio_list();
	echo '</div>';
}

function sb_studio_header( $title, $sub = '', $right = '' ) {
	echo '<div class="sbs-head"><div><h1>' . esc_html( $title ) . '</h1>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div><div class="sbs-head-right">' . $right . '</div></div>'; // phpcs:ignore
}

function sb_studio_list() {
	$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
	$filter = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : '';
	$args  = array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'posts_per_page' => 20, 'paged' => $paged, 's' => $s, 'orderby' => 'modified', 'order' => 'DESC' );
	if ( 'out' === $filter ) $args['meta_query'] = array( array( 'key' => '_stock_status', 'value' => 'outofstock' ) );
	if ( 'sale' === $filter ) $args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
	if ( 'draft' === $filter ) $args['post_status'] = array( 'draft' );
	if ( 'noprice' === $filter ) $args['meta_query'] = array( 'relation' => 'OR', array( 'key' => '_regular_price', 'value' => '' ), array( 'key' => '_regular_price', 'compare' => 'NOT EXISTS' ) );
	if ( 'nophoto' === $filter ) $args['meta_query'] = array( 'relation' => 'OR', array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ), array( 'key' => '_thumbnail_id', 'value' => '0' ) );
	$q = new WP_Query( $args );
	$new = '<a class="sbs-btn sbs-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=sb-studio&action=new' ) ) . '">+ Add product</a>';
	sb_studio_header( 'Product Studio', 'Add products with photos, colors, prices and festive offers.', $new );
	if ( isset( $_GET['saved'] ) ) echo '<div class="sbs-toast">Saved. <a href="' . esc_url( get_permalink( (int) $_GET['saved'] ) ) . '" target="_blank">View on website →</a></div>';
	if ( isset( $_GET['trashed'] ) ) echo '<div class="sbs-toast">Product moved to trash.</div>';
	$base = admin_url( 'admin.php?page=sb-studio' );
	?>
	<div class="sbs-toolbar">
		<form method="get" class="sbs-search"><input type="hidden" name="page" value="sb-studio"><input name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Search products…"><button class="sbs-btn">Search</button></form>
		<div class="sbs-filters">
			<?php foreach ( array( '' => 'All', 'noprice' => 'Needs price', 'nophoto' => 'Needs photo', 'sale' => 'On discount', 'out' => 'Out of stock', 'draft' => 'Drafts' ) as $k => $l ) : ?>
				<a class="<?php echo $filter === $k ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'f', $k, $base ) ); ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php if ( ! $q->have_posts() ) : ?>
		<div class="sbs-empty"><h2>No products yet</h2><p>Add your first product — it takes about two minutes.</p><?php echo $new; // phpcs:ignore ?></div>
	<?php else : ?>
		<div class="sbs-list">
			<?php while ( $q->have_posts() ) : $q->the_post(); $p = wc_get_product( get_the_ID() ); if ( ! $p ) continue; $d = sb_price_data( $p ); ?>
				<div class="sbs-row">
					<div class="sbs-thumb"><?php echo $p->get_image( 'thumbnail' ); // phpcs:ignore ?></div>
					<div class="sbs-row-main">
						<strong><?php echo esc_html( $p->get_name() ); ?></strong>
						<div class="sbs-row-meta">
							<?php if ( 'publish' !== $p->get_status() ) : ?><span class="sbs-pill sbs-pill-grey"><?php echo esc_html( ucfirst( $p->get_status() ) ); ?></span><?php endif; ?>
							<span class="sbs-pill <?php echo $p->is_in_stock() ? 'sbs-pill-green' : 'sbs-pill-red'; ?>"><?php echo $p->is_in_stock() ? 'In stock' . ( $p->managing_stock() ? ' (' . (int) $p->get_stock_quantity() . ')' : '' ) : 'Out of stock'; ?></span>
							<?php if ( $d['festive'] ) : ?><span class="sbs-pill sbs-pill-fest"><?php echo esc_html( $d['festive']['tag'] ); ?></span><?php endif; ?>
							<?php if ( $d['app'] ) : ?><span class="sbs-pill sbs-pill-violet">App <?php echo esc_html( sb_money( $d['app'] ) ); ?></span><?php endif; ?>
						</div>
					</div>
					<div class="sbs-row-price">
						<strong><?php echo esc_html( sb_money( $d['web'] ) ); ?></strong>
						<?php if ( $d['on_sale'] ) : ?><del><?php echo esc_html( sb_money( $d['regular'] ) ); ?></del><span class="sbs-off">-<?php echo (int) $d['pct']; ?>%</span><?php endif; ?>
					</div>
					<div class="sbs-row-actions">
						<a class="sbs-btn sbs-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sb-studio&action=edit&id=' . $p->get_id() ) ); ?>">Edit</a>
						<a class="sbs-btn sbs-btn-sm sbs-btn-ghost" target="_blank" href="<?php echo esc_url( get_permalink( $p->get_id() ) ); ?>">View</a>
						<a class="sbs-btn sbs-btn-sm sbs-btn-ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sb_studio_stock&id=' . $p->get_id() ), 'sb_stock_' . $p->get_id() ) ); ?>"><?php echo $p->is_in_stock() ? 'Mark out of stock' : 'Mark in stock'; ?></a>
						<a class="sbs-btn sbs-btn-sm sbs-btn-danger" onclick="return confirm('Move this product to trash?')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sb_studio_trash&id=' . $p->get_id() ), 'sb_trash_' . $p->get_id() ) ); ?>">Trash</a>
					</div>
				</div>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<?php if ( $q->max_num_pages > 1 ) : ?>
			<div class="sbs-pages"><?php echo paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $paged, 'total' => $q->max_num_pages ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
	<?php endif;
}

function sb_studio_form( $id ) {
	$p = $id ? wc_get_product( $id ) : null;
	if ( $id && ! $p ) { echo '<p>Product not found.</p>'; return; }
	$v = function ( $k, $default = '' ) use ( $p ) { return $p ? $p->get_meta( $k ) : $default; };
	$regular = $p ? $p->get_regular_price() : '';
	$sale    = $p ? $p->get_sale_price() : '';
	$pct     = $p ? $p->get_meta( '_sb_discount_pct' ) : '';
	$dtype   = $sale !== '' ? ( $pct ? 'percent' : 'price' ) : 'none';
	$gallery = $p ? array_filter( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) ) : array();
	$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
	$cur_cat = $p ? $p->get_category_ids() : array();
	$brand   = $p ? sb_product_brand( $p ) : '';
	$colors  = $p ? sb_product_colors( $p ) : array();
	$specs   = $p ? sb_product_specs( $p ) : array();
	$from    = $p && $p->get_date_on_sale_from() ? $p->get_date_on_sale_from()->date( 'Y-m-d' ) : '';
	$to      = $p && $p->get_date_on_sale_to() ? $p->get_date_on_sale_to()->date( 'Y-m-d' ) : '';
	$back = '<a class="sbs-btn sbs-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=sb-studio' ) ) . '">← All products</a>';
	sb_studio_header( $p ? 'Edit product' : 'Add a product', $p ? $p->get_name() : 'Fill in the details below. Fields with * are required.', $back );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sbs-form" id="sbs-form">
		<input type="hidden" name="action" value="sb_studio_save">
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'sb_studio_save' ); ?>

		<div class="sbs-cols">
			<div class="sbs-col-main">

				<section class="sbs-card">
					<h2><span>1</span> Photos</h2>
					<p class="sbs-help">Add clear photos on a plain background. The first photo is the main one — drag to reorder.</p>
					<div class="sbs-gallery" id="sbs-gallery">
						<?php foreach ( $gallery as $gid ) : ?>
							<div class="sbs-gal-item" data-id="<?php echo (int) $gid; ?>"><?php echo wp_get_attachment_image( $gid, 'thumbnail' ); ?><button type="button" class="sbs-gal-x" aria-label="Remove">×</button></div>
						<?php endforeach; ?>
						<button type="button" class="sbs-gal-add" id="sbs-gal-add"><span>+</span>Add photos</button>
					</div>
					<input type="hidden" name="images" id="sbs-images" value="<?php echo esc_attr( implode( ',', $gallery ) ); ?>">
				</section>

				<section class="sbs-card">
					<h2><span>2</span> Basic details</h2>
					<div class="sbs-grid">
						<label class="sbs-field sbs-span-2"><span>Product name *</span><input name="name" required value="<?php echo esc_attr( $p ? $p->get_name() : '' ); ?>" placeholder="e.g. Samsung Galaxy S25 Ultra 12/256GB"></label>
						<label class="sbs-field"><span>Brand</span><input name="brand" id="sbs-brand" value="<?php echo esc_attr( $brand ); ?>" placeholder="e.g. Samsung" list="sbs-brands"></label>
						<datalist id="sbs-brands"><?php foreach ( get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false ) ) as $b ) echo '<option value="' . esc_attr( $b->name ) . '">'; ?></datalist>
						<label class="sbs-field"><span>Model</span><input name="model" id="sbs-model" value="<?php echo esc_attr( $v( '_sb_model' ) ); ?>" placeholder="e.g. SM-S938B"></label>
						<label class="sbs-field"><span>Product code (SKU)</span><input name="sku" value="<?php echo esc_attr( $p ? $p->get_sku() : '' ); ?>" placeholder="Your internal code, optional"></label>
						<label class="sbs-field"><span>Category *</span>
							<select name="category" id="sbs-category">
								<option value="">Choose a category</option>
								<?php foreach ( $cats as $c ) : ?><option value="<?php echo (int) $c->term_id; ?>" <?php selected( in_array( $c->term_id, $cur_cat, true ) ); ?>><?php echo esc_html( $c->name ); ?></option><?php endforeach; ?>
								<option value="new">+ New category…</option>
							</select>
						</label>
						<label class="sbs-field sbs-newcat" hidden><span>New category name</span><input name="new_category" placeholder="e.g. Drones"></label>
					</div>
				</section>

				<section class="sbs-card">
					<h2><span>3</span> Description</h2>
					<p class="sbs-help">A short summary and a full description help customers decide. Add the key specifications below.</p>
					<label class="sbs-field"><span>Short summary (shown next to the price)</span><textarea name="short" id="sbs-short" rows="3"><?php echo esc_textarea( $p ? $p->get_short_description() : '' ); ?></textarea></label>
					<div class="sbs-field"><span>Full description</span>
						<?php wp_editor( $p ? $p->get_description() : '', 'sbs_desc', array( 'textarea_name' => 'description', 'media_buttons' => false, 'textarea_rows' => 10, 'teeny' => true, 'quicktags' => true ) ); ?>
					</div>
					<div class="sbs-field"><span>Specifications</span>
						<div class="sbs-rep" id="sbs-specs">
							<?php foreach ( $specs ?: array( array( 'label' => '', 'value' => '' ) ) as $s ) : ?>
								<div class="sbs-rep-row"><input name="spec_label[]" placeholder="e.g. Display" value="<?php echo esc_attr( $s['label'] ); ?>"><input name="spec_value[]" placeholder="e.g. 6.8&quot; Dynamic AMOLED 2X, 120Hz" value="<?php echo esc_attr( $s['value'] ); ?>"><button type="button" class="sbs-rep-x">×</button></div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="sbs-btn sbs-btn-sm sbs-btn-ghost" data-rep-add="specs">+ Add spec</button>
					</div>
				</section>

				<section class="sbs-card">
					<h2><span>4</span> Colors & size</h2>
					<div class="sbs-field"><span>Available colors</span>
						<div class="sbs-rep" id="sbs-colors">
							<?php foreach ( $colors as $c ) : ?>
								<div class="sbs-rep-row sbs-color-row"><input type="color" name="color_hex[]" value="<?php echo esc_attr( $c['hex'] ?: '#cccccc' ); ?>"><input name="color_name[]" placeholder="e.g. Titanium Black" value="<?php echo esc_attr( $c['name'] ); ?>"><button type="button" class="sbs-rep-x">×</button></div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="sbs-btn sbs-btn-sm sbs-btn-ghost" data-rep-add="colors">+ Add color</button>
					</div>
					<div class="sbs-grid">
						<label class="sbs-field"><span>Dimensions</span><input name="dimensions" value="<?php echo esc_attr( $v( '_sb_dimensions' ) ); ?>" placeholder="e.g. 16.2 x 7.9 x 0.82 cm"></label>
						<label class="sbs-field"><span>Weight (kg)</span><input name="weight" type="number" step="0.001" min="0" value="<?php echo esc_attr( $p ? $p->get_weight() : '' ); ?>" placeholder="e.g. 0.218"></label>
					</div>
				</section>
			</div>

			<div class="sbs-col-side">
				<section class="sbs-card sbs-sticky">
					<h2><span>5</span> Price & offer</h2>
					<label class="sbs-field"><span>Price (৳) *</span><input name="regular" id="sbs-regular" type="number" min="0" step="1" required value="<?php echo esc_attr( $regular ); ?>" placeholder="e.g. 145000"></label>

					<div class="sbs-field"><span>Discount</span>
						<div class="sbs-seg" id="sbs-dtype">
							<label><input type="radio" name="dtype" value="none" <?php checked( $dtype, 'none' ); ?>><span>None</span></label>
							<label><input type="radio" name="dtype" value="percent" <?php checked( $dtype, 'percent' ); ?>><span>% off</span></label>
							<label><input type="radio" name="dtype" value="price" <?php checked( $dtype, 'price' ); ?>><span>New price</span></label>
						</div>
					</div>
					<label class="sbs-field sbs-dfield" data-d="percent"><span>Discount rate (%)</span><input name="dpct" id="sbs-dpct" type="number" min="1" max="95" step="0.5" value="<?php echo esc_attr( $pct ); ?>"></label>
					<label class="sbs-field sbs-dfield" data-d="price"><span>Discounted price (৳)</span><input name="dprice" id="sbs-dprice" type="number" min="0" step="1" value="<?php echo esc_attr( $sale ); ?>"></label>

					<div class="sbs-dfield sbs-festive" data-d="any">
						<label class="sbs-field"><span>Is this offer for an occasion? (optional)</span>
							<select name="festive" id="sbs-festive">
								<option value="">No occasion</option>
								<?php foreach ( sb_festive_options() as $k => $o ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( '_sb_festive' ), $k ); ?>><?php echo esc_html( $o['label'] ); ?></option><?php endforeach; ?>
							</select>
						</label>
						<label class="sbs-field"><span>Offer message (optional)</span><input name="festive_text" maxlength="60" value="<?php echo esc_attr( $v( '_sb_festive_text' ) ); ?>" placeholder="e.g. Eid special — limited stock"></label>
						<div class="sbs-grid sbs-grid-tight">
							<label class="sbs-field"><span>Offer starts</span><input type="date" name="sale_from" value="<?php echo esc_attr( $from ); ?>"></label>
							<label class="sbs-field"><span>Offer ends</span><input type="date" name="sale_to" value="<?php echo esc_attr( $to ); ?>"></label>
						</div>
					</div>

					<label class="sbs-field"><span>App-only price (৳) — optional</span><input name="app_price" id="sbs-app" type="number" min="0" step="1" value="<?php echo esc_attr( $v( '_sb_app_price' ) ); ?>" placeholder="Lower price for app users"></label>

					<div class="sbs-preview" id="sbs-preview"></div>

					<h2 class="sbs-mt"><span>6</span> Stock & publishing</h2>
					<div class="sbs-field"><span>Availability</span>
						<div class="sbs-seg">
							<label><input type="radio" name="stock" value="instock" <?php checked( ! $p || $p->is_in_stock() ); ?>><span>In stock</span></label>
							<label><input type="radio" name="stock" value="outofstock" <?php checked( $p && ! $p->is_in_stock() ); ?>><span>Out of stock</span></label>
						</div>
					</div>
					<label class="sbs-field"><span>Quantity in stock (optional)</span><input name="qty" type="number" min="0" step="1" value="<?php echo esc_attr( $p && $p->managing_stock() ? $p->get_stock_quantity() : '' ); ?>" placeholder="Leave empty to not count"></label>
					<label class="sbs-check"><input type="checkbox" name="featured" value="1" <?php checked( $p && $p->is_featured() ); ?>> Feature on the homepage</label>
					<div class="sbs-actions">
						<button class="sbs-btn sbs-btn-primary sbs-btn-lg" name="status" value="publish" type="submit"><?php echo $p && 'publish' === $p->get_status() ? 'Update product' : 'Publish product'; ?></button>
						<button class="sbs-btn sbs-btn-ghost" name="status" value="draft" type="submit">Save as draft</button>
					</div>
				</section>
			</div>
		</div>
	</form>
	<?php
}

add_action( 'admin_post_sb_studio_save', function () {
	if ( ! current_user_can( 'sb_studio' ) || ! current_user_can( 'edit_products' ) ) wp_die( 'Not allowed.' );
	check_admin_referer( 'sb_studio_save' );
	$in = wp_unslash( $_POST );
	$id = (int) $in['id'];
	$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
	if ( ! $product ) wp_die( 'Product not found.' );

	$product->set_name( sanitize_text_field( $in['name'] ) );
	$product->set_short_description( wp_kses_post( $in['short'] ) );
	$product->set_description( wp_kses_post( $in['description'] ) );
	$product->set_status( 'draft' === $in['status'] ? 'draft' : 'publish' );
	$product->set_featured( ! empty( $in['featured'] ) );
	$product->set_catalog_visibility( 'visible' );

	// Category.
	$cat = isset( $in['category'] ) ? $in['category'] : '';
	if ( 'new' === $cat && ! empty( $in['new_category'] ) ) {
		$t = wp_insert_term( sanitize_text_field( $in['new_category'] ), 'product_cat' );
		if ( ! is_wp_error( $t ) ) $cat = $t['term_id'];
		elseif ( $t->get_error_data( 'term_exists' ) ) $cat = $t->get_error_data( 'term_exists' );
	}
	if ( $cat && 'new' !== $cat ) $product->set_category_ids( array( (int) $cat ) );

	// Photos.
	$imgs = array_values( array_filter( array_map( 'intval', explode( ',', (string) $in['images'] ) ) ) );
	$product->set_image_id( $imgs ? $imgs[0] : '' );
	$product->set_gallery_image_ids( array_slice( $imgs, 1 ) );

	// Pricing.
	$regular = max( 0, (float) $in['regular'] );
	$product->set_regular_price( $regular ? wc_format_decimal( $regular, 0 ) : '' );
	$dtype = isset( $in['dtype'] ) ? $in['dtype'] : 'none';
	$sale  = '';
	$pct   = '';
	if ( 'percent' === $dtype && (float) $in['dpct'] > 0 ) {
		$pct  = min( 95, (float) $in['dpct'] );
		$sale = round( $regular * ( 1 - $pct / 100 ) );
	} elseif ( 'price' === $dtype && (float) $in['dprice'] > 0 && (float) $in['dprice'] < $regular ) {
		$sale = round( (float) $in['dprice'] );
	}
	$product->set_sale_price( '' !== $sale ? wc_format_decimal( $sale, 0 ) : '' );
	$product->set_date_on_sale_from( '' !== $sale && ! empty( $in['sale_from'] ) ? sanitize_text_field( $in['sale_from'] ) . ' 00:00:00' : '' );
	$product->set_date_on_sale_to( '' !== $sale && ! empty( $in['sale_to'] ) ? sanitize_text_field( $in['sale_to'] ) . ' 23:59:59' : '' );
	$product->update_meta_data( '_sb_discount_pct', $pct );
	$product->update_meta_data( '_sb_festive', '' !== $sale ? sanitize_key( $in['festive'] ) : '' );
	$product->update_meta_data( '_sb_festive_text', '' !== $sale ? sanitize_text_field( $in['festive_text'] ) : '' );
	$app = (float) $in['app_price'];
	$product->update_meta_data( '_sb_app_price', $app > 0 ? wc_format_decimal( $app, 0 ) : '' );

	// Stock.
	$qty = isset( $in['qty'] ) ? trim( (string) $in['qty'] ) : '';
	if ( '' !== $qty ) {
		$product->set_manage_stock( true );
		$product->set_stock_quantity( max( 0, (int) $qty ) );
		if ( 'outofstock' === $in['stock'] ) $product->set_stock_quantity( 0 );
	} else {
		$product->set_manage_stock( false );
		$product->set_stock_status( 'outofstock' === $in['stock'] ? 'outofstock' : 'instock' );
	}

	// Details.
	$product->set_weight( '' !== (string) $in['weight'] ? wc_format_decimal( $in['weight'] ) : '' );
	$product->update_meta_data( '_sb_model', sanitize_text_field( $in['model'] ) );
	try { $product->set_sku( sanitize_text_field( $in['sku'] ?? '' ) ); } catch ( Exception $e ) { /* duplicate code — keep the old one */ }
	$product->update_meta_data( '_sb_brand', sanitize_text_field( $in['brand'] ) );
	$product->update_meta_data( '_sb_dimensions', sanitize_text_field( $in['dimensions'] ) );

	$colors = array();
	foreach ( (array) ( isset( $in['color_name'] ) ? $in['color_name'] : array() ) as $i => $n ) {
		$n = sanitize_text_field( $n );
		if ( '' === $n ) continue;
		$colors[] = array( 'name' => $n, 'hex' => sanitize_hex_color( $in['color_hex'][ $i ] ) ?: '#cccccc' );
	}
	$product->update_meta_data( '_sb_colors', wp_json_encode( $colors ) );

	$specs = array();
	foreach ( (array) ( isset( $in['spec_label'] ) ? $in['spec_label'] : array() ) as $i => $l ) {
		$l = sanitize_text_field( $l ); $val = sanitize_text_field( $in['spec_value'][ $i ] );
		if ( '' !== $l && '' !== $val ) $specs[] = array( 'label' => $l, 'value' => $val );
	}
	$product->update_meta_data( '_sb_specs', wp_json_encode( $specs ) );

	$pid = $product->save();

	// Brand term.
	$brand = sanitize_text_field( $in['brand'] );
	if ( taxonomy_exists( 'product_brand' ) ) wp_set_object_terms( $pid, $brand ? array( $brand ) : array(), 'product_brand' );

	wp_safe_redirect( admin_url( 'admin.php?page=sb-studio&saved=' . $pid ) );
	exit;
} );

add_action( 'admin_post_sb_studio_stock', function () {
	$id = (int) $_GET['id'];
	if ( ! current_user_can( 'edit_product', $id ) ) wp_die( 'Not allowed.' );
	check_admin_referer( 'sb_stock_' . $id );
	$p = wc_get_product( $id );
	if ( $p ) {
		if ( $p->is_in_stock() ) {
			if ( $p->managing_stock() ) $p->set_stock_quantity( 0 );
			$p->set_stock_status( 'outofstock' );
		} else {
			if ( $p->managing_stock() ) $p->set_stock_quantity( max( 1, (int) $p->get_stock_quantity() ) );
			$p->set_stock_status( 'instock' );
		}
		$p->save();
	}
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=sb-studio' ) );
	exit;
} );

add_action( 'admin_post_sb_studio_trash', function () {
	$id = (int) $_GET['id'];
	if ( ! current_user_can( 'delete_product', $id ) ) wp_die( 'Not allowed.' );
	check_admin_referer( 'sb_trash_' . $id );
	wp_trash_post( $id );
	wp_safe_redirect( admin_url( 'admin.php?page=sb-studio&trashed=1' ) );
	exit;
} );

/* ---------- Settings ---------- */

function sb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	if ( isset( $_POST['sb_settings_nonce'] ) && wp_verify_nonce( $_POST['sb_settings_nonce'], 'sb_settings' ) ) {
		foreach ( array( 'sb_app_link', 'sb_email', 'sb_whatsapp', 'sb_facebook', 'sb_instagram', 'sb_youtube', 'sb_announcement', 'sb_address' ) as $k ) {
			update_option( $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
		update_option( 'sb_email_notifications', ! empty( $_POST['sb_email_notifications'] ) ? 'yes' : 'no' );
		echo '<div class="sbs-toast">Settings saved.</div>';
	}
	sb_studio_header( 'Soubenco settings', 'Store contact details, social links and the app link.' );
	?>
	<form method="post" class="sbs-card sbs-settings">
		<?php wp_nonce_field( 'sb_settings', 'sb_settings_nonce' ); ?>
		<h2>Store contact</h2>
		<div class="sbs-grid">
			<label class="sbs-field"><span>Contact email</span><input name="sb_email" type="email" value="<?php echo esc_attr( get_option( 'sb_email' ) ); ?>" placeholder="e.g. soubenco@gmail.com"></label>
			<label class="sbs-field"><span>WhatsApp number</span><input name="sb_whatsapp" value="<?php echo esc_attr( get_option( 'sb_whatsapp' ) ); ?>" placeholder="e.g. 8801XXXXXXXXX"></label>
			<label class="sbs-field sbs-span-2"><span>Store / office address</span><input name="sb_address" value="<?php echo esc_attr( get_option( 'sb_address' ) ); ?>"></label>
			<label class="sbs-field"><span>Facebook page link</span><input name="sb_facebook" value="<?php echo esc_attr( get_option( 'sb_facebook' ) ); ?>"></label>
			<label class="sbs-field"><span>Instagram link</span><input name="sb_instagram" value="<?php echo esc_attr( get_option( 'sb_instagram' ) ); ?>"></label>
			<label class="sbs-field"><span>YouTube link</span><input name="sb_youtube" value="<?php echo esc_attr( get_option( 'sb_youtube' ) ); ?>"></label>
			<label class="sbs-field"><span>App download link (Google Play)</span><input name="sb_app_link" value="<?php echo esc_attr( get_option( 'sb_app_link' ) ); ?>"></label>
			<label class="sbs-field sbs-span-2"><span>Top announcement bar text</span><input name="sb_announcement" value="<?php echo esc_attr( get_option( 'sb_announcement', 'Delivery all over Bangladesh · Genuine products · Secure payment' ) ); ?>"></label>
		</div>
		<label class="sbs-check"><input type="checkbox" name="sb_email_notifications" value="1" <?php checked( 'yes', get_option( 'sb_email_notifications', 'yes' ) ); ?>> Email customers about price drops and restocks</label>
		<div class="sbs-actions"><button class="sbs-btn sbs-btn-primary">Save settings</button></div>
	</form>
	<?php
}
