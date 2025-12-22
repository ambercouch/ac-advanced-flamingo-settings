<?php
namespace ACAFS\Admin\Flamingo;
/**
 * AC Advanced Flamingo Settings – Single message file-link enhancer.
 *
 * Makes file URLs in Flamingo single message "Meta" box clickable.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Make file URLs in Flamingo single message "Meta" box clickable.
 *
 * This is intentionally light-touch: it does not modify Flamingo's PHP output,
 * only enhances the admin UI via JS.
 */
class ACAFS_Flamingo_File_Linker {

	/**
	 * Hook into the admin.
	 */
	public function __construct() {
        error_log('ACAFS_Flamingo_File_Linker loaded');
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

    public function register() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

	/**
	 * Enqueue a small inline script on the Flamingo single inbound message screen.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		unset( $hook_suffix ); // Silence unused parameter sniff; signature is fixed by WP.

		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if (
			$screen
			&& 'flamingo_page_flamingo_inbound' === $screen->id
			&& isset( $_GET['action'], $_GET['post'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'edit' === $_GET['action'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& ctype_digit( (string) $_GET['post'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			// Nothing external to enqueue; just add a small inline script.
			wp_register_script( 'acafs-flamingo-file-linker', false, array(), '1.0.0', true );

			$uploads = wp_get_upload_dir();

			$data = array(
				// Only convert links that live under these path fragments.
				'pathHints'   => array(
					'/acafs-cf7/',
					'/wpcf7_uploads/',
				),
				// Skip known non-file meta keys that are URLs but not uploads.
				'skipKeys'    => array(
					'url',
					'post_url',
					'post_id',
					'post_name',
					'post_title',
					'site_url',
				),
				// Require the uploads base URL to be present for extra safety.
				'uploadsBase' => ! empty( $uploads['error'] ) ? '' : trailingslashit( $uploads['baseurl'] ),
			);

			wp_add_inline_script(
				'acafs-flamingo-file-linker',
				'window.ACAFS_FILE_LINKER_DATA = ' . wp_json_encode( $data ) . ';',
				'before'
			);

			wp_add_inline_script(
				'acafs-flamingo-file-linker',
				"(function(){
					function onReady(fn){
						if(document.readyState === 'loading'){
							document.addEventListener('DOMContentLoaded', fn);
						} else {
							fn();
						}
					}

					onReady(function(){
						var cfg  = window.ACAFS_FILE_LINKER_DATA || {};
						var base = cfg.uploadsBase || '';

						// Exact table: <table class=\"widefat message-fields striped\"> in #inboundmetadiv.
						var table = document.querySelector('#inboundmetadiv table.widefat.message-fields');
						if(!table){ return; }

						function looksLikeFileUrl(url){
							if(!url || typeof url !== 'string'){ return false; }
							if(!/^https?:\\/\\//i.test(url)){ return false; }

							// Keep it to uploads and a plausible file extension.
							if(base && url.indexOf(base) !== 0){
								return false;
							}

							return /\\.(zip|pdf|png|jpe?g|gif|webp|svg|txt|csv|docx?|xlsx?|pptx?|rtf|mp3|mp4|mov|avi|heic|heif)$/i.test(url);
						}

						var rows    = table.querySelectorAll('tbody > tr');
						var skipMap = {};
						(cfg.skipKeys || []).forEach(function(k){ skipMap[k] = 1; });

						rows.forEach(function(tr){
							var keyCell = tr.querySelector('td.field-title');
							var valCell = tr.querySelector('td.field-value');
							if(!keyCell || !valCell){ return; }

							var key = (keyCell.textContent || '').trim();
							if(skipMap[key]){ return; }

							// Flamingo wraps the value in <p>…</p> (sometimes multiple).
							var paragraphs = valCell.querySelectorAll('p');
							if(!paragraphs.length){ return; }

							paragraphs.forEach(function(p){
								var raw = (p.textContent || '').trim();
								if(!raw){ return; }

								var parts = raw.split(/\\n+/).map(function(s){ return s.trim(); }).filter(Boolean);
								if(!parts.length){ return; }

								var frag       = document.createDocumentFragment();
								var madeChange = false;

								parts.forEach(function(part, idx){
									if(looksLikeFileUrl(part)){
										var a   = document.createElement('a');
										a.href  = part;
										a.textContent = part.split('/').pop();
										a.target = '_blank';
										a.rel    = 'noopener noreferrer';
										frag.appendChild(a);
										madeChange = true;
									} else {
										frag.appendChild(document.createTextNode(part));
									}
									if(idx < parts.length - 1){
										frag.appendChild(document.createElement('br'));
									}
								});

								if(madeChange){
									p.replaceWith(frag);
								}
							});
						});
					});
				})();",
				'after'
			);

			wp_enqueue_script( 'acafs-flamingo-file-linker' );
		}
	}
}

// Boot linker.
new ACAFS_Flamingo_File_Linker();
