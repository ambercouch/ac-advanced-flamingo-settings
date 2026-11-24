<?php
/**
 * Portable Flamingo channel paths shared by export and import transports.
 *
 * @package AC_Advanced_Flamingo_Settings
 */

defined( 'ABSPATH' ) || exit;

/** Resolve channel identities by their ancestry, never source database IDs. */
class ACAFS_Channel_Hierarchy {

	/** Flamingo channel taxonomy. */
	const TAXONOMY = 'flamingo_inbound_channel';

	/**
	 * Export all assigned channels, including each complete ancestry path.
	 *
	 * @param int $message_id Message ID.
	 * @return array|WP_Error Portable channel fields or a safe failure.
	 */
	public function export_channels( $message_id ) {
		global $wpdb;
		$wpdb->last_error = '';
		$terms            = wp_get_object_terms( $message_id, self::TAXONOMY );
		// @phpstan-ignore notIdentical.alwaysFalse (WordPress taxonomy queries mutate the global database error after it is cleared; failure regressions verify this branch.)
		if ( is_wp_error( $terms ) || '' !== $wpdb->last_error ) {
			return $this->error( 'acafs_export_channel_failed' );
		}
		$fields = array(
			'channel_id'    => 0,
			'channel_slug'  => '',
			'channel_name'  => '',
			'channel_paths' => array(),
		);
		foreach ( $terms as $term ) {
			$path = array();
			$seen = array();
			$node = $term;
			while ( $node instanceof WP_Term ) {
				if ( isset( $seen[ $node->term_id ] ) ) {
					return $this->error( 'acafs_export_channel_cycle' );
				}
				$seen[ $node->term_id ] = true;
				array_unshift(
					$path,
					array(
						'name' => $node->name,
						'slug' => $node->slug,
					)
				);
				if ( ! $node->parent ) {
					break;
				}
				$node = get_term( $node->parent, self::TAXONOMY );
				if ( ! $node instanceof WP_Term ) {
					return $this->error( 'acafs_export_channel_parent_missing' );
				}
			}
			$fields['channel_paths'][] = $path;
		}
		if ( ! empty( $terms ) ) {
			$fields['channel_id']   = (int) $terms[0]->term_id;
			$fields['channel_slug'] = $terms[0]->slug;
			$fields['channel_name'] = $terms[0]->name;
		}
		return $fields;
	}

	/**
	 * Validate an additive channel_paths field without silently losing ancestry.
	 *
	 * @param mixed $paths Untrusted decoded paths.
	 * @return array|WP_Error Sanitized paths or a validation failure.
	 */
	public function normalize_paths( $paths ) {
		if ( ! is_array( $paths ) || ! $this->is_list( $paths ) ) {
			return $this->error( 'acafs_import_channel_paths_invalid' );
		}
		$normalized = array();
		foreach ( $paths as $path ) {
			if ( ! is_array( $path ) || empty( $path ) || ! $this->is_list( $path ) ) {
				return $this->error( 'acafs_import_channel_paths_invalid' );
			}
			$nodes = array();
			foreach ( $path as $node ) {
				if ( ! is_array( $node ) || ! isset( $node['name'], $node['slug'] ) || ! is_string( $node['name'] ) || ! is_string( $node['slug'] ) ) {
					return $this->error( 'acafs_import_channel_paths_invalid' );
				}
				$name = sanitize_text_field( $node['name'] );
				$slug = sanitize_title( $node['slug'] );
				if ( '' === $name || '' === $slug ) {
					return $this->error( 'acafs_import_channel_paths_invalid' );
				}
				$nodes[] = array(
					'name' => $name,
					'slug' => $slug,
				);
			}
			$normalized[] = $nodes;
		}
		return $normalized;
	}

	/**
	 * Resolve paths parent-first, or use the legacy flat slug when paths are absent.
	 *
	 * Legacy numeric-only exports remain unassigned: ancestry cannot be inferred.
	 *
	 * @param array $message Normalized message.
	 * @return array|WP_Error Destination IDs or a safe failure.
	 */
	public function resolve_channels( array $message ) {
		if ( ! array_key_exists( 'channel_paths', $message ) ) {
			return $this->resolve_legacy_channel( $message );
		}
		if ( empty( $message['channel_paths'] ) ) {
			return array();
		}
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return $this->error( 'acafs_import_channel_taxonomy_missing' );
		}
		$ids = array();
		foreach ( $message['channel_paths'] as $path ) {
			$parent_id = 0;
			foreach ( $path as $node ) {
				$parent_id = $this->resolve_node( $node, $parent_id );
				if ( is_wp_error( $parent_id ) ) {
					return $parent_id;
				}
			}
			$ids[] = $parent_id;
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Assign only explicitly exported channels and verify actual persistence.
	 *
	 * @param int   $post_id Message ID.
	 * @param array $ids     Destination term IDs.
	 * @return true|WP_Error Assignment result.
	 */
	public function assign_channels( $post_id, array $ids ) {
		if ( empty( $ids ) ) {
			return true;
		}
		$result = wp_set_object_terms( $post_id, $ids, self::TAXONOMY, false );
		$stored = wp_get_object_terms( $post_id, self::TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $result ) || empty( $result ) || is_wp_error( $stored ) ) {
			return $this->error( 'acafs_import_channel_assignment_failed' );
		}
		$stored = array_map( 'intval', $stored );
		sort( $ids );
		sort( $stored );
		return $ids === $stored ? true : $this->error( 'acafs_import_channel_assignment_failed' );
	}

	/**
	 * Find a node only under its already resolved destination parent.
	 *
	 * Prefer matching name plus parent; slugs may have acquired destination suffixes.
	 * When siblings share a name, require an unambiguous matching slug as well.
	 *
	 * @param array $node   Name and slug.
	 * @param int   $parent_id Destination parent ID.
	 * @return int|WP_Error Existing ID, zero when absent, or lookup failure.
	 */
	private function find_node( array $node, $parent_id ) {
		global $wpdb;
		$wpdb->last_error = '';
		$terms            = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'parent'     => $parent_id,
				'name'       => $node['name'],
				'hide_empty' => false,
			)
		);
		// @phpstan-ignore notIdentical.alwaysFalse (WordPress taxonomy queries mutate the global database error after it is cleared; failure regressions verify this branch.)
		if ( is_wp_error( $terms ) || '' !== $wpdb->last_error ) {
			return $this->error( 'acafs_import_channel_lookup_failed' );
		}
		if ( 1 === count( $terms ) ) {
			return (int) $terms[0]->term_id;
		}
		if ( count( $terms ) > 1 ) {
			$matches = array_filter(
				$terms,
				static function ( $term ) use ( $node ) {
					return $term->slug === $node['slug'];
				}
			);
			if ( 1 === count( $matches ) ) {
				return (int) reset( $matches )->term_id;
			}
			return $this->error( 'acafs_import_channel_ambiguous' );
		}
		return 0;
	}

	/**
	 * Reuse or create one parent-qualified node, including a concurrent creation.
	 *
	 * @param array $node   Name and requested slug.
	 * @param int   $parent_id Destination parent ID.
	 * @return int|WP_Error Destination ID or a creation failure.
	 */
	private function resolve_node( array $node, $parent_id ) {
		$found = $this->find_node( $node, $parent_id );
		if ( is_wp_error( $found ) || $found ) {
			return $found;
		}
		$inserted = wp_insert_term(
			wp_slash( $node['name'] ),
			self::TAXONOMY,
			array(
				'slug'   => $node['slug'],
				'parent' => $parent_id,
			)
		);
		if ( is_wp_error( $inserted ) ) {
			if ( 'term_exists' === $inserted->get_error_code() ) {
				$found = $this->find_node( $node, $parent_id );
				if ( is_wp_error( $found ) || $found ) {
					return $found;
				}
			}
			return $this->error( 'acafs_import_channel_creation_failed' );
		}
		if ( empty( $inserted['term_id'] ) ) {
			return $this->error( 'acafs_import_channel_creation_failed' );
		}
		$term = get_term( (int) $inserted['term_id'], self::TAXONOMY );
		if ( ! $term instanceof WP_Term || $parent_id !== (int) $term->parent ) {
			return $this->error( 'acafs_import_channel_creation_failed' );
		}
		return (int) $term->term_id;
	}

	/**
	 * Preserve legacy flat-slug imports without guessing missing parents or IDs.
	 *
	 * @param array $message Legacy message.
	 * @return array|WP_Error Destination IDs or failure.
	 */
	private function resolve_legacy_channel( array $message ) {
		global $wpdb;
		if ( '' === $message['channel_slug'] ) {
			return array();
		}
		$wpdb->last_error = '';
		$terms            = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'slug'       => $message['channel_slug'],
				'hide_empty' => false,
			)
		);
		// @phpstan-ignore notIdentical.alwaysFalse (WordPress taxonomy queries mutate the global database error after it is cleared; failure regressions verify this branch.)
		if ( is_wp_error( $terms ) || '' !== $wpdb->last_error ) {
			return $this->error( 'acafs_import_channel_lookup_failed' );
		}
		if ( count( $terms ) > 1 ) {
			return $this->error( 'acafs_import_channel_ambiguous' );
		}
		if ( 1 === count( $terms ) ) {
			return array( (int) $terms[0]->term_id );
		}
		$node = array(
			'name' => '' !== $message['channel_name'] ? $message['channel_name'] : $message['channel_slug'],
			'slug' => $message['channel_slug'],
		);
		$term = $this->resolve_node( $node, 0 );
		return is_wp_error( $term ) ? $term : array( $term );
	}

	/**
	 * Check sequential arrays using the supported PHP 7.4 runtime.
	 *
	 * @param array $value Array to check.
	 * @return bool Whether it is a list.
	 */
	private function is_list( array $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Return safe channel diagnostics without exposing database errors or fields.
	 *
	 * @param string $code Specific failure code.
	 * @return WP_Error Channel failure.
	 */
	private function error( $code ) {
		return new WP_Error( $code, __( 'Flamingo channels could not be exported or restored safely. Check the channel paths and taxonomy configuration.', 'ac-advanced-flamingo-settings' ) );
	}
}
