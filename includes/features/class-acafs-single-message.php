<?php
/**
 * AC Advanced Flamingo Settings – Single Message helpers
 *
 * - Persist CF7 uploads to a permanent location and store URLs in Flamingo.
 * - Enhance Flamingo single message meta table so upload URLs are clickable.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persist CF7 uploads and ensure Flamingo stores URLs instead of hashes.
 */
class ACAFS_CF7_Persist_Uploads {

    /**
     * Store URLs we persisted this request.
     *
     * Format: [ 'your-file' => [ 'https://…/file1.ext', 'https://…/file2.ext' ] ].
     *
     * @var array
     */
    private $latest_urls = array();

    /**
     * Hook into CF7 + Flamingo.
     */
    public function __construct() {
        // Copy CF7 temp uploads to a permanent folder and replace posted data before mail is sent.
        add_action( 'wpcf7_before_send_mail', array( $this, 'persist_and_replace' ), 1, 1 );

        // Safety net: adjust Flamingo meta right before the message is written.
        add_filter( 'flamingo_add_inbound', array( $this, 'replace_hash_with_url_for_flamingo' ) );

        // Final guard: force our URLs into Flamingo at a very late priority.
        add_filter( 'flamingo_add_inbound', array( $this, 'force_file_urls_into_flamingo' ), 9999 );
    }

    /**
     * Copy uploaded files to /uploads/acafs-cf7/Y/m/ and replace posted data with URLs.
     *
     * @param WPCF7_ContactForm $contact_form Contact form instance.
     * @return void
     */
    public function persist_and_replace( $contact_form ) {

        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        // ['field-name' => '/tmp/path' OR ['path1','path2']].
        $uploaded = $submission->uploaded_files();
        if ( ! is_array( $uploaded ) || empty( $uploaded ) ) {
            return;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            // Uploads not available.
            return;
        }

        $subdir      = 'acafs-cf7/' . gmdate( 'Y/m' ) . '/';
        $target_dir  = trailingslashit( $uploads['basedir'] ) . $subdir;
        $base_url    = trailingslashit( $uploads['baseurl'] ) . $subdir;

        wp_mkdir_p( $target_dir );

        foreach ( $uploaded as $field => $paths ) {

            // Normalize to array of strings.
            $paths = is_array( $paths ) ? array_filter( $paths ) : ( $paths ? array( $paths ) : array() );
            if ( empty( $paths ) ) {
                continue;
            }

            $urls_for_field = array();

            foreach ( $paths as $path ) {
                if ( ! is_string( $path ) || '' === $path ) {
                    continue;
                }
                if ( ! file_exists( $path ) ) {
                    continue;
                }

                $unique = wp_unique_filename( $target_dir, basename( $path ) );
                $dest   = $target_dir . $unique;

                // Copy (not move) so CF7 can clean up its temp file as usual.
                if ( copy( $path, $dest ) ) {
                    $urls_for_field[] = esc_url_raw( $base_url . $unique );
                }
            }

            if ( ! empty( $urls_for_field ) ) {
                $this->latest_urls[ $field ] = $urls_for_field;
            }
        }

        // Replace posted data values so Flamingo stores URLs, not hashes.
        if ( ! empty( $this->latest_urls ) ) {
            $posted_data = $submission->get_posted_data();

            foreach ( $this->latest_urls as $field => $urls ) {
                if ( isset( $posted_data[ $field ] ) ) {
                    // Preserve structure: if CF7 gave an array, keep array; else use newline-joined string.
                    if ( is_array( $posted_data[ $field ] ) ) {
                        $posted_data[ $field ] = $urls;
                    } else {
                        $posted_data[ $field ] = implode( "\n", $urls );
                    }
                }
            }

            // CF7 5.2+ supports this setter.
            if ( method_exists( $submission, 'set_posted_data' ) ) {
                $submission->set_posted_data( $posted_data );
            }
        }
    }

    /**
     * As a safety net, swap any remaining hashes with URLs we just saved before Flamingo writes the message.
     *
     * @param array $args Flamingo inbound args (includes 'meta').
     * @return array
     */
    public function replace_hash_with_url_for_flamingo( $args ) {
        if ( empty( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
            return $args;
        }

        if ( empty( $this->latest_urls ) || ! is_array( $this->latest_urls ) ) {
            return $args;
        }

        foreach ( $this->latest_urls as $field => $urls ) {
            if ( empty( $urls ) || ! is_array( $urls ) ) {
                continue;
            }

            // Normalize to newline-joined string for scalar fields.
            $joined = implode( "\n", array_map( 'esc_url_raw', $urls ) );

            if ( array_key_exists( $field, $args['meta'] ) ) {
                // Preserve structure: if Flamingo meta is array -> array of URLs; else -> newline string.
                if ( is_array( $args['meta'][ $field ] ) ) {
                    $args['meta'][ $field ] = $urls;
                } else {
                    $args['meta'][ $field ] = $joined;
                }
            } else {
                // If Flamingo didn’t include the key (edge case), add it as string to ensure visibility.
                $args['meta'][ $field ] = $joined;
            }
        }

        return $args;
    }

    /**
     * Final guard that runs very late to force our URLs into Flamingo meta.
     *
     * @param array $args Flamingo inbound args.
     * @return array
     */
    public function force_file_urls_into_flamingo( $args ) {
        // Right now this simply delegates to the same logic as above.
        // Keeping this as a separate method means we can extend it later if needed.
        return $this->replace_hash_with_url_for_flamingo( $args );
    }
}

// Boot persistence.
new ACAFS_CF7_Persist_Uploads();

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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    /**
     * Enqueue a small inline script on the Flamingo single inbound message screen.
     *
     * @param string $hook_suffix Current admin hook.
     * @return void
     */
    public function enqueue( $hook_suffix ) {
        if ( ! is_admin() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if (
            $screen
            && 'flamingo_page_flamingo_inbound' === $screen->id
            && isset( $_GET['action'], $_GET['post'] )
            && 'edit' === $_GET['action']
            && ctype_digit( (string) $_GET['post'] )
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
