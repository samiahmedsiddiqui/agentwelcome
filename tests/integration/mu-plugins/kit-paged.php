<?php
// A list that a page prints itself, the way a custom page template does: two posts a view, linked as /page/N/.
add_shortcode( 'kit_list', function () {
	$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	$list  = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 2, 'paged' => $paged ) );
	$html  = '';
	foreach ( $list->posts as $item ) {
		$html .= '<h2>' . esc_html( $item->post_title ) . '</h2>';
	}
	return $html . '<div class="pagination">' . paginate_links( array( 'base' => trailingslashit( get_permalink() ) . 'page/%#%/', 'format' => '', 'current' => $paged, 'total' => $list->max_num_pages ) ) . '</div>';
} );
