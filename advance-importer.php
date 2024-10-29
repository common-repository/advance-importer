<?php
/*
 * Plugin Name: Advance Importer
 * Plugin URI: https://wordpress.org/plugins/advance-importer
 * Description: A powerful plugin for Import and export (Page, Post, Custom post type) data with Attachments.
 * Author: Coder618
 * Version: 1.0.0
 * Author URI: https://coder618.github.io/
 * Requires at least: 4.5
 * Requires PHP: 7.0
 * Text Domain: advance-importer
 */

class ADV_IMPORTER {
	protected static $export_query_run = false;
	protected static $args = array();

	public function __construct() {
		$this->includes();
		$this->reg_hooks();
	}

	public function includes() {
		require __DIR__ . '/admin-page.php';
		require __DIR__ . '/class-ajax.php';
	}

	public function write_log($log) {
		if ( true === WP_DEBUG ) {
            $backtrace = debug_backtrace();
            $caller = $backtrace[0]['file'] . ":" . $backtrace[0]['line']  ;
            error_log('Caller: ' . $caller);
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
	}

    public function reg_hooks(){
		add_filter( 'export_args', [$this, 'export_args'], 10, 1 );
		add_action( 'export_filters', [$this, 'wp_export_filters'], 10000 );
		add_action( 'export_wp', [$this, 'export_wp'], 10, 1 );
		add_filter( 'export_query', [$this, 'add_attachments_to_export_query'], 10, 1 );
		
		// styling
        add_action( 'admin_enqueue_scripts'	, [ $this, 'enqueue_admin_assets' ] );

		// ajax function
		$ajax_class = new  ADV_IMPORTER_AJAX();
		add_action( 'wp_ajax_adv_importer_update_settings',[ $ajax_class , 'update_plugin_settings'] );

	}
	public function enqueue_admin_assets() {
		wp_enqueue_script( 'adv_importer_script', plugin_dir_url( __FILE__ ). 'dist/adv-importer-js.js'  , ['jquery'], 1,true );
		wp_enqueue_style( 'adv_importer_style', plugin_dir_url( __FILE__ ). 'dist/adv-importer-style.css', [], 1, 'all' );
	}


	public function export_args( $args ) {
		if ( isset($_GET['export-with-attachment']) ) {
			$args['export-with-attachment'] = (int) $_GET['export-with-attachment'];
		}else{
			self::write_log("export-with-attachment Not found in GET");
		}
		return $args;
	}

	public function wp_export_filters() { 
		include __DIR__ . '/templates/export-confirmation.php';
	}
	
	public function export_wp( $args ) {
		self::$args = $args;
		add_filter( 'query', array($this, 'export_query_filter'), 10, 1 );
	}

	public function export_query_filter( $query ) {
		global $wpdb;
		if (
			false === self::$export_query_run
			&& is_string($query)
			&& 0 === strpos( $query, "SELECT ID FROM {$wpdb->posts} " )
		) {
			remove_filter( 'query', array($this, 'export_query_filter'), 10 );
			self::$export_query_run = true;

			$query = apply_filters( 'export_query', $query );
		}
		return $query;
	}

	public function add_attachments_to_export_query( $query ) {
		global $wpdb;

		

		if ( isset( self::$args['content'], self::$args['export-with-attachment'] ) && 'all' !== self::$args['content'] && 'attachment' !== self::$args['content'] && self::$args['export-with-attachment'] ) {
			self::write_log( "Condition met");

			$attachments = array_filter( $wpdb->get_results( "SELECT ID, guid, post_parent FROM {$wpdb->posts} WHERE post_type = 'attachment'", OBJECT_K ) );
			if ( empty( $attachments ) ) {
				return $query;
			}

			$ids = array();
			$cache = array();

			$posts = array_filter( $wpdb->get_col( $query ) );
			if ( $posts ) {
				$ids = $wpdb->get_col( sprintf( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND post_id IN(%s)", implode(',', $posts) ) );

				foreach ( $attachments as $id => $att ) {
					if ( in_array( $att->post_parent, $posts ) ) {
						$ids[] = (int) $id;
					}
				}
			}


			$adv_importer_string = trim( get_option("adv-importer-meta-string"));
			$adv_importer_arr = explode(",", trim( get_option("adv-importer-meta-string")) );

			$meta_key_list = [];

			if( $adv_importer_string && is_array($adv_importer_arr) && count($adv_importer_arr) > 0 ){
				foreach( $adv_importer_arr as $meta_key) {
					if( trim($meta_key) ){
						$meta_key_list[] = sanitize_key($meta_key);
					}
				}
			}

			$id_from_meta = [];
			if ( $posts ) {
				$meta_obj = array_filter($wpdb->get_results( sprintf( "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN(%s)", implode(',', $posts) ) ));
				foreach ( $meta_obj as $meta_id => $meta_row ) {
					$meta_key =  $meta_row->meta_key;
					$meta_value =  $meta_row->meta_value;
					$in_post_id = intval($meta_row->post_id);

					// If user select any meta key 
					if( count($meta_key_list) > 0 ){

						self::write_log("User specific metakey will check");
						self::write_log($meta_key_list);


						if( in_array( $meta_key , $meta_key_list  ) ){
							if ( in_array( $in_post_id, $posts ) && get_post_type( intval($meta_value)) == 'attachment' ) {
								$ids[] = (int) $meta_value;
								$id_from_meta[] = (int) $meta_value;
							}
						}
					
					}else{
						
						// check if meta value is a attachment POST TYPE AND matched with  the post id
						if ( in_array( $in_post_id, $posts ) && get_post_type( intval($meta_value)) == 'attachment' ) {
							$ids[] = (int) $meta_value;
							$id_from_meta[] = (int) $meta_value;
						}
					}

				}
			}



			$attachments = $this->getPostAttachmentsMeta( $attachments );
			$attachment_map = $this->getUrlToAttachmentMap( $attachments );

			$q = str_replace( 'SELECT ID FROM ', 'SELECT post_content FROM ', $query ) . ' AND post_content REGEXP "((wp-image-|wp-att-)[0-9][0-9]*)|\\\[(gallery|playlist) |<!-- wp:(gallery|audio|image|video) |href=|src="' ;
			foreach ( $wpdb->get_col( $q ) as $text ) {
				preg_match_all('#(wp-image-|wp-att-)(\d+)#', $text, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					$ids[] = (int) $match[2];
				}

				preg_match_all('#\[(gallery|playlist)\s+.*ids=["\']([\d\s,]*)["\'].*\]#', $text, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					foreach( explode( ',', $match[2] ) as $id ) {
						$ids[] = (int) $id;
					}
				}

				preg_match_all('#<!-- wp:gallery ({.+}) -->#', $text, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					$match = json_decode( $match[1] );
					if ( isset( $match, $match->ids ) ) {
						foreach( $match->ids as $id ) {
							$ids[] = (int) $id;
						}
					}
				}
				
				preg_match_all('#<!-- wp:(audio|image|video) ({.*}) -->#', $text, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					$match = json_decode( $match[2] );
					if ( isset( $match, $match->id ) ) {
						$ids[] = (int) $match->id;
					}
				}

				preg_match_all('#(href|src)\s*=\s*["\']([^"\']+)["\']#', $text, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					if ( isset( $cache[ $match[2] ] ) ) {
						continue;
					}

					$needle = trim($match[2]);
					if ( 0 === strpos( $needle, '#' ) || 0 === strpos( $needle, 'mailto:') ) {
						continue;
					}

					if ( ! preg_match( '|^([a-zA-Z]+:)?//|', $needle ) ) {
						// relative url
						$needle = $this->getFullURL( $needle );
					}

					if (!empty($attachment_map[$needle])) {
						$cache[ $match[2] ] = $ids[] = (int) $attachment_map[$needle];
					}
				}
			}
			$ids = array_filter( array_unique( $ids ) );
			$ids = apply_filters( 'export_query_media_ids', $ids );
			$ids = array_filter( array_unique( $ids ) );

			if ( count($ids) > 0 ) {
				if ( 0 === strpos($query, "SELECT ID FROM {$wpdb->posts} INNER JOIN {$wpdb->term_relationships} ") ) {
					$query = str_replace( "SELECT ID FROM {$wpdb->posts} INNER JOIN {$wpdb->term_relationships} ", "SELECT ID FROM {$wpdb->posts} LEFT JOIN {$wpdb->term_relationships} ", $query );
				}
				$query .= sprintf( " OR {$wpdb->posts}.ID IN (%s) ", implode(',', $ids) );
			}
		}else{
			self::write_log("export-with-attachment not select");
		}
		return $query;
	}

	protected function getPostAttachmentsMeta( array $attachments ) {
		global $wpdb;

		$attachment_ids = array_keys( $attachments );
		$i = 0;
		do {
			$chunk = array_slice( $attachment_ids, $i * 1000, 1000 );
			$chunk[] = 0; 
			$q = sprintf( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN('_wp_attached_file', '_wp_attachment_metadata') AND post_id IN(%s)", implode( ',', $chunk ) );
			foreach( $wpdb->get_results( $q, ARRAY_A ) as $meta ) {
				if ( isset( $attachments[ $meta['post_id'] ] ) ) {
					$attachments[ $meta['post_id'] ]->{$meta['meta_key']} = maybe_unserialize( $meta['meta_value'] );
				}
			}
			$i++;
		} while( sizeof( $chunk ) > 1 );

		return $attachments;
	}

	protected function getUrlToAttachmentMap( $attachments ) {
		$attachment_map = array();

		foreach ( $attachments as $id => $att ) {
			if ( isset( $att->_wp_attached_file ) ) {
				$hay = $this->getFullURL( $att->_wp_attached_file );
				$attachment_map[ $hay ] = (int) $att->ID;
			}

			if ( isset( $att->_wp_attachment_metadata['file'] ) ) {
				$hay = $this->getFullURL( $att->_wp_attachment_metadata['file'] );
				$attachment_map[ $hay ] = (int) $att->ID;
			}

			if ( isset( $att->_wp_attachment_metadata['file'], $att->_wp_attachment_metadata['sizes'] ) ) {
				$base = trailingslashit( dirname( $att->_wp_attachment_metadata['file'] ) );
				foreach( $att->_wp_attachment_metadata['sizes'] as $size ) {
					$hay = $this->getFullURL( $base . $size['file'] );
					$attachment_map[ $hay ] = (int) $att->ID;
				}
			}

			if ( isset( $att->guid ) ) {
				$attachment_map[ $att->guid ] = (int) $att->ID;
			}
		}

		return $attachment_map;
	}

	protected function getFullURL( $file ) {
		if ( ( $uploads = wp_get_upload_dir() ) && false === $uploads['error'] ) {
			if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
				$url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
			} elseif ( false !== strpos($file, 'wp-content/uploads') ) {
				$url = trailingslashit( $uploads['baseurl'] . '/' . _wp_get_attachment_relative_path( $file ) ) . basename( $file );
			} else {
				$url = $uploads['baseurl'] . "/$file";
			}
			return $url;
		}
		return false;
	}
}

new ADV_IMPORTER();