<?php
defined( 'ABSPATH' ) || exit;

class ACAFS_Single_Message {

    public function __construct() {
        add_action( 'edit_form_after_title', array( $this, 'override_flamingo_fields' ) );
    }

    /**
     * Override Flamingo's fields box and display file links instead of hashes.
     */
    public function override_flamingo_fields( $post ) {
        if ( $post->post_type !== 'flamingo_inbound' ) {
            return;
        }

        $data = get_post_meta( $post->ID, '_meta', true );

        if ( ! is_array( $data ) ) {
            echo '<div class="postbox"><h2>' . esc_html__( 'Fields', 'acafs' ) . '</h2><div class="inside"><p>No data found.</p></div></div>';
            return;
        }

        echo '<div class="postbox"><h2>' . esc_html__( 'Fields', 'acafs' ) . '</h2><div class="inside"><table class="form-table striped">';
        foreach ( $data as $key => $value ) {
            echo '<tr><th style="width:180px;">' . esc_html( $key ) . '</th><td>' . $this->maybe_convert_hash_to_link( $value ) . '</td></tr>';
        }
        echo '</table></div></div>';
    }

    /**
     * Convert Flamingo file hash to download link if possible.
     */
    private function maybe_convert_hash_to_link( $value ) {
        if ( is_string( $value ) && preg_match( '/^[a-f0-9]{32,}$/', $value ) ) {
            $uploads_dir = wp_upload_dir();
            $dir         = trailingslashit( $uploads_dir['basedir'] ) . 'wpcf7_uploads/';
            $url         = trailingslashit( $uploads_dir['baseurl'] ) . 'wpcf7_uploads/';
            $matched     = glob( $dir . $value . '*.*' );

            if ( ! empty( $matched ) && file_exists( $matched[0] ) ) {
                $filename = basename( $matched[0] );
                $file_url = esc_url( $url . $filename );

                return sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    $file_url,
                    esc_html( $filename )
                );
            }
        }

        return esc_html( $value );
    }

}

class ACAFS_Flamingo_Debug {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'log_screen' ) );
        add_action( 'admin_notices', array( $this, 'notice_on_single_message' ) );
    }

    public function log_screen( $hook_suffix ) {
        if ( ! is_admin() ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        error_log( 'ACAFS admin_enqueue_scripts: hook=' . $hook_suffix );
        if ( $screen ) {
            error_log( 'ACAFS screen id=' . $screen->id );
        }
    }

    public function notice_on_single_message() {
        if ( isset( $_GET['page'], $_GET['action'], $_GET['post'] )
            && 'flamingo_inbound' === $_GET['page']
            && 'edit' === $_GET['action'] ) {
            echo '<div class="notice notice-info"><p>'
                . esc_html__( 'ACAFS: Detected Flamingo single message screen.', 'acafs' )
                . '</p></div>';
            error_log( 'ACAFS: admin_notices fired on Flamingo single message.' );
        }
    }
}
new ACAFS_Flamingo_Debug();



class ACAFS_CF7_Persist_Uploads
{

    /**
     * Store URLs we persisted this request.
     * Format: [ 'your-file' => [ 'https://…/file1.ext', 'https://…/file2.ext' ] ]
     */
    private $latest_urls = array();

    public function __construct()
    {
        // Copy CF7 temp uploads to a permanent folder and replace posted data before mail is sent.
        add_action( 'wpcf7_before_send_mail', array( $this, 'persist_and_replace' ), 1, 1 );

        // Ensure Flamingo stores URLs (not hashes) if any hashes slip through.
        add_filter('flamingo_add_inbound', array(
            $this,
            'replace_hash_with_url_for_flamingo'
        ));

        add_filter( 'flamingo_add_inbound', array( $this, 'acafs_force_file_urls_into_flamingo' ), 9999 );

        add_action( 'flamingo_add_inbound_post', function( $post_id, $args ) {
            $meta = get_post_meta( $post_id, '_meta', true );
            error_log( 'ACAFS saved meta(_meta) for post ' . $post_id . ' = ' . print_r( $meta, true ) );
        }, 10, 2 );


    }

    /**
     * Force Flamingo to store our persisted file URLs instead of CF7 hashes.
     * Adds detailed logging so we can confirm it runs and see what was changed.
     */
    public function acafs_force_file_urls_into_flamingo( $args ) {
        error_log( 'ACAFS inbound(9999): ENTER' );


        // Log brief snapshot of incoming meta
        if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
            error_log( 'ACAFS inbound: meta(before)=' . print_r( array_slice( $args['meta'], 0, 10, true ), true ) );
        } else {
            error_log( 'ACAFS inbound: meta(before)=<none/invalid>' );
        }

        // Log our persisted URLs for this request
        error_log( 'ACAFS inbound: latest_urls=' . print_r( $this->latest_urls, true ) );

        if ( empty( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
            error_log( 'ACAFS inbound: no meta, return' );
            return $args;
        }

        // If we persisted file URLs, inject them regardless of what CF7 put in meta.
        if ( ! empty( $this->latest_urls ) && is_array( $this->latest_urls ) ) {
            foreach ( $this->latest_urls as $field => $urls ) {
                if ( empty( $urls ) || ! is_array( $urls ) ) {
                    continue;
                }
                $joined = implode( "\n", array_map( 'esc_url_raw', $urls ) );

                if ( array_key_exists( $field, $args['meta'] ) ) {
                    if ( is_array( $args['meta'][ $field ] ) ) {
                        $args['meta'][ $field ] = $urls;
                        error_log( 'ACAFS inbound: set ARRAY URLs for field=' . $field );
                    } else {
                        $args['meta'][ $field ] = $joined;
                        error_log( 'ACAFS inbound: set STRING URLs for field=' . $field . ' value=' . $joined );
                    }
                } else {
                    // If field missing in meta, add it so it shows in Flamingo.
                    $args['meta'][ $field ] = $joined;
                    error_log( 'ACAFS inbound: ADDED field=' . $field . ' value=' . $joined );
                }
            }
        } else {
            // Safety: if we didn’t capture URLs (shouldn’t happen now that copy works), try converting any hash-shaped values to our acafs-cf7 location
            foreach ( $args['meta'] as $k => $v ) {
                if ( is_string( $v ) && preg_match( '/^[a-f0-9]{32,}$/', $v ) ) {
                    error_log( 'ACAFS inbound: WARNING had hash for field=' . $k . ' with no latest_urls' );
                }
            }
        }

        error_log( 'ACAFS inbound: meta(after)=' . print_r( array_slice( $args['meta'], 0, 10, true ), true ) );
        return $args;
    }

    /**
     * Copy uploaded files to /uploads/acafs-cf7/Y/m/ and replace posted data with URLs.
     */
    public function persist_and_replace($contact_form)
    {
        error_log('ACAFS persist: running at priority 1 before Flamingo');
        if (!class_exists('WPCF7_Submission'))
        {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (!$submission)
        {
            return;
        }

        $uploaded = $submission->uploaded_files(); // ['field-name' => '/tmp/path' OR ['path1','path2']]
        if (!is_array($uploaded) || empty($uploaded))
        {
            return;
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error']))
        {
            return; // uploads not available
        }

        $subdir = 'acafs-cf7/' . gmdate('Y/m') . '/';
        $target_dir = trailingslashit($uploads['basedir']) . $subdir;
        $base_url = trailingslashit($uploads['baseurl']) . $subdir;

        wp_mkdir_p($target_dir);

        foreach ($uploaded as $field => $paths)
        {
            // Normalize to array of strings.
            $paths = is_array($paths) ? array_filter($paths) : ($paths ? array($paths) : array());
            if (empty($paths))
            {
                continue;
            }

            $urls_for_field = array();

            foreach ($paths as $path)
            {
                if (!is_string($path) || $path === '')
                {
                    continue;
                }
                if (!file_exists($path))
                {
                    continue;
                }

                $unique = wp_unique_filename($target_dir, basename($path));
                $dest = $target_dir . $unique;

                // Copy (not move) so CF7 can clean up its temp file as usual.
                if (copy($path, $dest))
                {
                    $urls_for_field[] = esc_url_raw($base_url . $unique);
                }
            }

            if (!empty($urls_for_field))
            {
                $this->latest_urls[$field] = $urls_for_field;
            }
        }

        // Replace posted data values so Flamingo stores URLs, not hashes.
        if (!empty($this->latest_urls))
        {
            $posted_data = $submission->get_posted_data();

            foreach ($this->latest_urls as $field => $urls)
            {
                if (isset($posted_data[$field]))
                {
                    // Preserve structure: if CF7 gave an array, keep array; else use newline-joined string.
                    if (is_array($posted_data[$field]))
                    {
                        $posted_data[$field] = $urls;
                    } else
                    {
                        $posted_data[$field] = implode("\n", $urls);
                    }
                }
            }

            // CF7 5.2+ supports this setter.
            if (method_exists($submission, 'set_posted_data'))
            {
                $submission->set_posted_data($posted_data);
            }
        }
    }

    /**
     * As a safety net, swap any remaining hashes with URLs we just saved before Flamingo writes the message.
     */
    public function replace_hash_with_url_for_flamingo( $args ) {
        // $args typically contains 'meta' => [ field => value, ... ] right before Flamingo saves the message.
        if ( empty( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
            error_log('ACAFS flamingo_add_inbound: no meta to adjust');
            return $args;
        }

        if ( empty( $this->latest_urls ) || ! is_array( $this->latest_urls ) ) {
            error_log('ACAFS flamingo_add_inbound: no latest_urls to apply');
            return $args;
        }

        // Log before state (brief)
        error_log( 'ACAFS flamingo_add_inbound: will apply URLs for fields: ' . implode( ',', array_keys( $this->latest_urls ) ) );

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
                    error_log( 'ACAFS flamingo_add_inbound: set array URLs for field=' . $field );
                } else {
                    $args['meta'][ $field ] = $joined;
                    error_log( 'ACAFS flamingo_add_inbound: set string URLs for field=' . $field . ' value=' . $joined );
                }
            } else {
                // If Flamingo didn’t include the key (edge case), add it as string to ensure visibility.
                $args['meta'][ $field ] = $joined;
                error_log( 'ACAFS flamingo_add_inbound: added field=' . $field . ' value=' . $joined );
            }
        }

        return $args;
    }

}

new ACAFS_CF7_Persist_Uploads();

/**
 * Show extracted file links on Flamingo single message screens.
 * Non-invasive: doesn't touch Flamingo internals; just adds a small panel.
 */
class ACAFS_Flamingo_File_Panel
{

    public function __construct()
    {
        add_action('edit_form_after_title', array(
            $this,
            'render_panel'
        ));
    }

    /**
     * Render a compact panel listing any file URLs found in post_content.
     *
     * @param WP_Post $post
     * @return void
     */
    public function render_panel($post)
    {
        if (!is_admin() || !$post instanceof WP_Post || 'flamingo_inbound' !== $post->post_type)
        {
            return;
        }

        $content = (string)$post->post_content;

        // Find http/https URLs (covers our persisted URLs).
        $urls = $this->extract_urls($content);

        // If none found, try to resolve any CF7 hash-looking tokens in the content (32+ hex),
        // but *only* to see if a persisted URL sits on the same line.
        // (We do not touch the file system here; this is just a display helper.)
        if (empty($urls))
        {
            $urls = $this->extract_urls_from_lines_with_hashes($content);
        }

        // Nothing to show? Exit quietly.
        if (empty($urls))
        {
            return;
        }

        // Output a small, accessible admin panel.
        echo '<div class="postbox" id="acafs-file-links" aria-labelledby="acafs-file-links-title" style="margin-top:12px">';
        echo '<h2 id="acafs-file-links-title" class="hndle"><span>' . esc_html__('File Uploads (ACAFS)', 'acafs') . '</span></h2>';
        echo '<div class="inside"><ul>';

        foreach ($urls as $url)
        {
            $sanitized = esc_url($url);
            $label = esc_html(wp_basename(wp_parse_url($url, PHP_URL_PATH) ?: $url));
            printf('<li><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></li>', $sanitized, $label);
        }

        echo '</ul></div></div>';
    }

    /**
     * Extract http/https URLs from a block of text.
     *
     * @param string $text
     * @return string[]
     */
    private function extract_urls($text)
    {
        $urls = array();

        if (preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $text, $m))
        {
            foreach ($m[0] as $u)
            {
                $urls[] = $u;
            }
        }

        // De-duplicate while preserving order.
        return array_values(array_unique($urls));
    }

    /**
     * If no plain URLs were found, scan per-line for hash-like tokens and pick any URL on that line.
     *
     * @param string $text
     * @return string[]
     */
    private function extract_urls_from_lines_with_hashes($text)
    {
        $out = array();
        $lines = preg_split('/\R/u', $text);

        foreach ($lines as $line)
        {
            if (preg_match('/\b[a-f0-9]{32,}\b/i', $line) && preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $line, $m))
            {
                foreach ($m[0] as $u)
                {
                    $out[] = $u;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
//new ACAFS_Flamingo_File_Panel();


defined('ABSPATH') || exit;

class ACAFS_Flamingo_File_Notice
{

    public function __construct()
    {
        add_action('admin_notices', array(
            $this,
            'render_links_notice'
        ));
    }

    public function render_links_notice()
    {
        if (!is_admin() || !isset($_GET['page'], $_GET['action'], $_GET['post']) || 'flamingo_inbound' !== $_GET['page'] || 'edit' !== $_GET['action'])
        {
            return;
        }

        $post_id = absint($_GET['post']);
        if (!$post_id)
        {
            return;
        }

        $post = get_post($post_id);
        if (!$post || 'flamingo_inbound' !== $post->post_type)
        {
            return;
        }

        $urls = $this->collect_urls_from_post($post);

        if (empty($urls))
        {
            // Nothing to show; exit quietly.
            return;
        }

        echo '<div class="notice notice-info" aria-labelledby="acafs-file-links-title" style="padding:12px 15px">';
        echo '<p id="acafs-file-links-title" style="margin:0 0 6px;font-weight:600;">' . esc_html__('File Uploads (ACAFS)', 'acafs') . '</p>';
        echo '<ul style="margin:0; list-style:disc inside;">';
        foreach ($urls as $url)
        {
            $label = wp_basename(wp_parse_url($url, PHP_URL_PATH) ?: $url);
            printf('<li><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></li>', esc_url($url), esc_html($label));
        }
        echo '</ul></div>';
    }

    private function collect_urls_from_post(WP_Post $post)
    {
        $found = array();

        // 1) From post_content (your logs show Flamingo stores fields there).
        $found = array_merge($found, $this->extract_urls((string)$post->post_content));

        // 2) From all post meta (you mentioned you can see a meta field with the URL in Flamingo).
        $all_meta = get_post_meta($post->ID);
        if (is_array($all_meta))
        {
            foreach ($all_meta as $key => $vals)
            {
                foreach ((array)$vals as $val)
                {
                    // $val can be scalar or serialized array/string.
                    if (is_array($val))
                    {
                        $found = array_merge($found, $this->extract_urls(print_r($val, true)));
                    } else
                    {
                        $found = array_merge($found, $this->extract_urls((string)$val));
                    }
                }
            }
        }

        // De-dup while preserving order.
        $found = array_values(array_unique($found));

        // Keep only URLs inside uploads (optional hardening; remove if you want any URL):
        // $upload = wp_upload_dir();
        // if ( empty( $upload['error'] ) ) {
        //   $base = trailingslashit( $upload['baseurl'] );
        //   $found = array_values( array_filter( $found, function( $u ) use ( $base ) { return 0 === strpos( $u, $base ); } ) );
        // }

        return $found;
    }

    private function extract_urls($text)
    {
        $urls = array();
        if (preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $text, $m))
        {
            foreach ($m[0] as $u)
            {
                $urls[] = $u;
            }
        }
        return $urls;
    }
}

// instantiate (e.g., in your bootstrap/init)
//new ACAFS_Flamingo_File_Notice();


defined('ABSPATH') || exit;

class ACAFS_Flamingo_File_Linker
{

    /**
     * Screen guard: we only act on the Flamingo single inbound message page.
     * - screen id: flamingo_page_flamingo_inbound
     * - required query: ?page=flamingo_inbound&action=edit&post={id}
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array(
            $this,
            'enqueue'
        ));
    }

    public function enqueue($hook_suffix)
    {
        if (!is_admin())
        {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        // Confirm we're on the right screen and URL context.
        if ($screen && 'flamingo_page_flamingo_inbound' === $screen->id && isset($_GET['action'], $_GET['post']) && 'edit' === $_GET['action'] && ctype_digit((string)$_GET['post']))
        {
            // Nothing external to enqueue; just add a small inline script.
            wp_register_script('acafs-flamingo-file-linker', false, array(), '1.0.0', true);

            $allowed_hosts = array(wp_parse_url(home_url(), PHP_URL_HOST));
            $uploads = wp_get_upload_dir();
            // Pass a couple of guards down to JS.
            $data = array(
                'allowedHosts' => array_filter($allowed_hosts),
                // Only convert links that live under these path fragments.
                'pathHints' => array(
                    '/acafs-cf7/',
                    '/wpcf7_uploads/'
                ),
                // Skip known non-file meta keys that are URLs but not uploads.
                'skipKeys' => array(
                    'url',
                    'post_url',
                    'post_id',
                    'post_name',
                    'post_title'
                ),
                // For a stricter check, also require the uploads base URL to be present.
                'uploadsBase' => trailingslashit($uploads['baseurl']),
            );

            wp_add_inline_script('acafs-flamingo-file-linker', 'window.ACAFS_FILE_LINKER_DATA = ' . wp_json_encode($data) . ';', 'before');

            wp_add_inline_script(
                'acafs-flamingo-file-linker',
                "(function(){
		function onReady(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else { fn(); } }

		onReady(function(){
			var cfg = window.ACAFS_FILE_LINKER_DATA || {};
			console.log('[ACAFS] td-mode start', cfg);

			// The exact table you showed: <table class='widefat message-fields striped'>
			var table = document.querySelector('#inboundmetadiv table.widefat.message-fields');
			console.log('[ACAFS] table found?', !!table, table);

			if(!table){ console.warn('[ACAFS] message-fields table not found'); return; }

			function looksLikeFileUrl(url){
				if(!url || typeof url!=='string') return false;
				if(!/^https?:\\/\\//i.test(url)) return false;
				// keep it to uploads + file extension
				var inUploads = (url.indexOf('/acafs-cf7/') !== -1) || (url.indexOf('/wpcf7_uploads/') !== -1);
				if(!inUploads) return false;
				return /\\.(zip|pdf|png|jpe?g|gif|webp|svg|txt|csv|docx?|xlsx?|pptx?|rtf|mp3|mp4|mov|avi|heic|heif)$/i.test(url);
			}

			// Iterate rows: <tr><td class='field-title'>key</td><td class='field-value'><p>value</p></td></tr>
			var rows = table.querySelectorAll('tbody > tr');
			console.log('[ACAFS] rows:', rows.length);

			var skip = { url:1, post_url:1, post_id:1, post_name:1, post_title:1, site_url:1 };
			var changed = 0;

			rows.forEach(function(tr, idx){
				var keyCell = tr.querySelector('td.field-title');
				var valCell = tr.querySelector('td.field-value');
				if(!keyCell || !valCell) return;

				var key = (keyCell.textContent||'').trim();
				if (skip[key]) { return; }

				// Flamingo wraps the value in <p>…</p> (sometimes multiple)
				var paragraphs = valCell.querySelectorAll('p');
				if(!paragraphs.length) return;

				var madeChange = false;

				paragraphs.forEach(function(p){
					var raw = (p.textContent||'').trim();
					if(!raw) return;

					// Split by linebreaks – CF7 may join multiple file URLs with newlines
					var parts = raw.split(/\\n+/).map(function(s){return s.trim();}).filter(Boolean);

					var frag = document.createDocumentFragment();
					parts.forEach(function(part, i){
						if (looksLikeFileUrl(part)) {
							console.log('[ACAFS] linkify', key, '=>', part);
							var a = document.createElement('a');
							a.href = part;
							a.textContent = part.split('/').pop();
							a.target = '_blank';
							a.rel = 'noopener noreferrer';
							frag.appendChild(a);
							madeChange = true;
						} else {
							frag.appendChild(document.createTextNode(part));
						}
						if (i < parts.length - 1) frag.appendChild(document.createElement('br'));
					});

					if (madeChange) {
						// Replace the <p> with our fragment (preserves other markup in the cell)
						p.replaceWith(frag);
					}
				});

				if (madeChange) changed++;
			});

			console.log('[ACAFS] done, cells changed:', changed);
		});
	})();",
                'after'
            );




            wp_enqueue_script('acafs-flamingo-file-linker');
        }
    }
}

// Boot it.
new ACAFS_Flamingo_File_Linker();


