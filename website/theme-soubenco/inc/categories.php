<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Shop categories in display order (Soubenco's own list first). */
function sbt_shop_categories() {
	static $cache = null;
	if ( null !== $cache ) return $cache;
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0 ) );
	if ( is_wp_error( $terms ) ) $terms = array();
	$default = (int) get_option( 'default_product_cat' );
	$hidden  = array( 'n-a', 'na', 'uncategorized', 'others', 'other' );
	$cache = array_values( array_filter( $terms, function ( $t ) use ( $default, $hidden ) {
		if ( $t->term_id === $default || in_array( $t->slug, $hidden, true ) || get_term_meta( $t->term_id, 'sb_hide', true ) ) return false;
		$t->count = sbt_cat_count( $t );
		return $t->count > 0;
	} ) );
	usort( $cache, function ( $a, $b ) {
		$oa = (int) get_term_meta( $a->term_id, 'sb_order', true ); $ob = (int) get_term_meta( $b->term_id, 'sb_order', true );
		if ( $oa && $ob && $oa !== $ob ) return $oa - $ob;
		if ( $oa xor $ob ) return $oa ? -1 : 1;
		return $b->count - $a->count;
	} );
	return $cache;
}

/** Line icon for a category, matched by its slug. */
function sbt_category_icon( $slug, $size = 30 ) {
	$i = array(
		'phone'     => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
		'laptop'    => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 19h20"/>',
		'tablet'    => '<rect x="4.5" y="2.5" width="15" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
		'audio'     => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="7" rx="1.5"/><rect x="17" y="14" width="4" height="7" rx="1.5"/>',
		'watch'     => '<rect x="6" y="6" width="12" height="12" rx="3"/><path d="M9 6V2.5h6V6M9 18v3.5h6V18M12 9.5V12l1.5 1"/>',
		'camera'    => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
		'gaming'    => '<path d="M7 8h10a5 5 0 0 1 4.8 6.3l-.6 2.2a2.5 2.5 0 0 1-4.3 1L15 15H9l-1.9 2.5a2.5 2.5 0 0 1-4.3-1l-.6-2.2A5 5 0 0 1 7 8Z"/><path d="M8 10.5v3M6.5 12h3"/><circle cx="16" cy="11" r=".8"/><circle cx="17.5" cy="13" r=".8"/>',
		'tv'        => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
		'home'      => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
		'appliance' => '<rect x="5" y="2.5" width="14" height="19" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01"/>',
		'power'     => '<rect x="6" y="7" width="12" height="14" rx="2"/><path d="M10 3v4M14 3v4M11 12l-1.5 3h3L11 18"/>',
		'cable'     => '<path d="M7 3v4M11 3v4M5.5 7h7v4a3.5 3.5 0 0 1-7 0V7Z"/><path d="M9 14.5V17a4 4 0 0 0 8 0v-6a3 3 0 0 1 6 0"/>',
		'network'   => '<path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1"/>',
		'computer'  => '<rect x="3" y="3.5" width="18" height="12" rx="1.5"/><path d="M9 20.5h6M12 15.5v5"/>',
		'drone'     => '<circle cx="5.5" cy="5.5" r="2.5"/><circle cx="18.5" cy="5.5" r="2.5"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/><rect x="9" y="9" width="6" height="6" rx="1.5"/><path d="m7.5 7.5 1.5 1.5M16.5 7.5 15 9M7.5 16.5 9 15M16.5 16.5 15 15"/>',
		'care'      => '<path d="M9 3h6v4l2 3v10a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V10l2-3V3Z"/><path d="M9 13h6"/>',
		'accessory' => '<path d="M12 3a4 4 0 0 1 4 4v2h2a1 1 0 0 1 1 1l-1 10H6L5 10a1 1 0 0 1 1-1h2V7a4 4 0 0 1 4-4Z"/>',
		'default'   => '<path d="M3 7l9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
		'fridge'    => '<rect x="6" y="2.5" width="12" height="19" rx="2"/><path d="M6 10h12M9 6v2M9 13v3"/>',
		'fan'       => '<circle cx="12" cy="10" r="1.6"/><path d="M12 8.4C11 5 12.5 3 14.5 3.5c1.6.4 1.4 3-1 5.2M13.5 10.8c3.4.6 4.6 2.8 3.4 4.4-1 1.3-3.3.2-4.1-3M10.5 10.6c-2.8 2-5.2 1.6-5.4-.4-.2-1.6 2.3-2.3 4.9-.9"/><path d="M12 12v6M8.5 21h7"/>',
		'ac'        => '<rect x="2.5" y="5" width="19" height="8" rx="2"/><path d="M6 10h12M8 16.5c0 1.5-1 2-1 3.5M12 16.5c0 1.5-1 2-1 3.5M16 16.5c0 1.5-1 2-1 3.5"/>',
		'washer'    => '<rect x="4.5" y="2.5" width="15" height="19" rx="2"/><circle cx="12" cy="13.5" r="4.5"/><path d="M8 6h.01M11 6h3"/><path d="M9.5 14.5c1.5-1 3.5 1 5 0"/>',
		'microwave' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><rect x="5.5" y="8" width="10" height="8" rx="1"/><path d="M18.5 9v.01M18.5 12v.01M18.5 15v.01"/>',
		'cooker'    => '<path d="M4 11h16v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6Z"/><path d="M2.5 11h19M9 8a3 3 0 0 1 6 0M12 5V3.5"/>',
		'stove'     => '<rect x="3" y="7" width="18" height="11" rx="2"/><circle cx="8.5" cy="12.5" r="2.5"/><circle cx="15.5" cy="12.5" r="2.5"/><path d="M6 18v2.5M18 18v2.5"/>',
		'blender'   => '<path d="M7 3h10l-1.5 11h-7L7 3Z"/><path d="M8 14h8l1 7H7l1-7Z"/><path d="M12 17.5h.01"/>',
		'heater'    => '<path d="M12 21c-3.3 0-6-2.4-6-5.6 0-3.4 3-5 3.4-8.4C12 8.6 13 10.5 13 12c1-.6 1.6-1.6 1.8-3 1.8 1.6 3.2 3.8 3.2 6.4 0 3.2-2.7 5.6-6 5.6Z"/>',
		'bulb'      => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3Z"/>',
		'drop'      => '<path d="M12 3s6 6.4 6 11a6 6 0 0 1-12 0c0-4.6 6-11 6-11Z"/><path d="M9.5 15a2.5 2.5 0 0 0 2.5 2.5"/>',
		'bolt'      => '<path d="M13 2 5 13.5h6L10 22l8-11.5h-6L13 2Z"/>',
	);
	$map = array(
		'tv-entertainment' => 'tv', 'fans-cooling' => 'fan', 'kitchen' => 'cooker', 'home-appliances' => 'washer', 'lighting-accessories' => 'bulb',
		'refrigerator' => 'fridge', 'fridge' => 'fridge', 'freezer' => 'fridge',
		'air-condition' => 'ac', 'air-cooler' => 'ac', 'cooler' => 'ac',
		'washing' => 'washer', 'microwave' => 'microwave', 'oven' => 'microwave',
		'pressure-cooker' => 'cooker', 'rice-cooker' => 'cooker', 'cookware' => 'cooker', 'cooker' => 'cooker',
		'stove' => 'stove', 'induction' => 'stove', 'blender' => 'blender', 'mixer' => 'blender', 'juicer' => 'blender',
		'geyser' => 'heater', 'heater' => 'heater', 'lighting' => 'bulb', 'light' => 'bulb', 'lamp' => 'bulb',
		'water-filter' => 'drop', 'purifier' => 'drop', 'mosquito' => 'bolt',
		'ceiling-fan' => 'fan', 'fan' => 'fan', 'television' => 'tv', 'small-appliance' => 'appliance',
		'phone' => 'phone', 'mobile' => 'phone', 'smartphone' => 'phone',
		'laptop' => 'laptop', 'notebook' => 'laptop', 'macbook' => 'laptop',
		'tablet' => 'tablet', 'ipad' => 'tablet',
		'audio' => 'audio', 'headphone' => 'audio', 'earbud' => 'audio', 'speaker' => 'audio', 'sound' => 'audio',
		'watch' => 'watch', 'wearable' => 'watch', 'band' => 'watch',
		'camera' => 'camera', 'photo' => 'camera',
		'gaming' => 'gaming', 'game' => 'gaming', 'console' => 'gaming',
		'tv' => 'tv', 'television' => 'tv', 'monitor' => 'tv', 'display' => 'tv',
		'smart-home' => 'home', 'home' => 'home',
		'appliance' => 'appliance', 'kitchen' => 'appliance',
		'power' => 'power', 'charger' => 'power', 'battery' => 'power',
		'cable' => 'cable',
		'network' => 'network', 'router' => 'network', 'wifi' => 'network',
		'computer' => 'computer', 'pc' => 'computer', 'desktop' => 'computer',
		'drone' => 'drone',
		'care' => 'care', 'grooming' => 'care', 'personal' => 'care',
		'accessor' => 'accessory',
	);
	$key = 'default';
	foreach ( $map as $needle => $k ) {
		if ( false !== strpos( $slug, $needle ) ) { $key = $k; break; }
	}
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $i[ $key ] . '</svg>';
}

/** Brand entries that aren't real brands (e.g. "N/A"). */
function sbt_hidden_brand_ids() {
	if ( ! taxonomy_exists( 'product_brand' ) ) return array();
	$ids = array();
	foreach ( array( 'n-a', 'na', 'none' ) as $slug ) {
		$t = get_term_by( 'slug', $slug, 'product_brand' );
		if ( $t ) $ids[] = $t->term_id;
	}
	return $ids;
}

/** Products in a category including everything in its sub-categories. */
function sbt_cat_count( $term ) {
	$total = (int) $term->count;
	$kids  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'child_of' => $term->term_id, 'fields' => 'all' ) );
	if ( ! is_wp_error( $kids ) ) foreach ( $kids as $k ) $total += (int) $k->count;
	return $total;
}
