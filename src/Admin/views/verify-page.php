<?php
/**
 * Verify & Preview shell: title, notices, tabs and the active tab's content.
 *
 * @package AgentWelcome
 */

use AgentWelcome\Admin\View;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-local variables.
?>
<div class="wrap agentwelcome">
	<h1 class="agentwelcome-title">
		<?php esc_html_e( 'Verify & Preview', 'agentwelcome' ); ?>
		<span class="agentwelcome-version">v<?php echo esc_html( AGENTWELCOME_VERSION ); ?></span>
	</h1>
	<p class="agentwelcome-tagline"><?php esc_html_e( 'Check that the stored Markdown matches what a visitor sees, preview it, and refresh anything that is out of date.', 'agentwelcome' ); ?></p>
	<?php View::render( 'notices', $vars['notices'] ); ?>
	<?php if ( ! empty( $vars['blocked'] ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'robots.txt blocks AI crawlers.', 'agentwelcome' ); ?></strong>
				<?php
				/* translators: %s: crawler names. */
				printf( esc_html__( '%s are not allowed to read llms.txt or the .md pages, so they cannot use them. AgentWelcome does not change robots.txt.', 'agentwelcome' ), '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $vars['blocked'] ) ) . '</code>' );
				?>
			</p>
		</div>
	<?php endif; ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $vars['tabs'] as $slug => $entry ) : ?>
			<a class="nav-tab <?php echo $slug === $vars['tab'] ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $entry[1] ); ?>"><?php echo esc_html( $entry[0] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php $vars['content'](); ?>
</div>
