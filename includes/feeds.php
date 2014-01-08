<?php 

/**
 * Feeds data schema
 *
 * The RSS Import module stores several different types of data. The schemas
 * are described below.
 *
 * (1) FEEDS
 *     Feeds are stored in a custom post type ('pf_feed'). Data
 *     is mapped as follows:
 *
 *     [data type]       -> [WP location]
 *     feed title   	 -> wp_posts.post_title
 *     feed notes 		 -> wp_posts.post_content
 *     xmlUrl        	 -> wp_postmeta ('pf_feed_xmlUrl')
 *     htmlUrl           -> wp_postmeta ('pf_feed_htmlUrl') 
 *     type           	 -> wp_postmeta ('pf_feed_type') 
 *     created           -> wp_posts.post_date_gmt
 */
 
class PF_Feeds_Schema {
	#var $post_type;
	#var $tag_taxonomy;

	public function init() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self;
		}

		return $instance;
	}	
	
	public function __construct() {
		$this->post_type = 'pf_feed';
		$this->tag_taxonomy = 'pf_feed_category';

		// Post types and taxonomies must be registered after 'init'
		add_action( 'init', array( $this, 'register_feed_post_type' ) );
		#add_action('admin_init', array($this, 'deal_with_old_feedlists') );
		add_action( 'pf_feed_post_type_registered', array( $this, 'register_feed_tag_taxonomy' ) );
		if (is_admin()){
			add_action('wp_ajax_deal_with_old_feedlists', array($this, 'deal_with_old_feedlists'));
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'feeder_menu', array( $this, 'add_to_feeder' ) );
		}
	
	}
	
	/**
	 * Feed items are stored in a CPT, which is registered here
	 */
	public function register_feed_post_type() {
		$labels = array(
			'name'               => __( 'Feeds', 'pf' ),
			'singular_name'      => __( 'Feed', 'pf' ),
			'add_new'            => _x( 'Add New', 'pf', 'add new feed' ),
			'all_items'          => __( 'All Feeds', 'pf' ),
			'add_new_item'       => __( 'Add New Feed', 'pf' ),
			'edit_item'          => __( 'Edit Feed', 'pf' ),
			'new_item'           => __( 'New Feed', 'pf' ),
			'view_item'          => __( 'View Feed', 'pf' ),
			'search_items'       => __( 'Search Feeds', 'pf' ),
			'not_found'          => __( 'No feeds found', 'pf' ),
			'not_found_in_trash' => __( 'No feeds found in trash', 'pf' ),
		);

		register_post_type( $this->post_type, apply_filters( 'pf_register_feed_post_type_args', array(
			'label'       => $labels['name'],
			'labels'      => $labels,
			'description' => __( 'Feeds imported by PressForward&#8217;s Feed Importer', 'pf' ),
			'public'      => false,
			'hierarchical' => true,
			'supports' 	=> array('title','editor','author','thumbnail','excerpt','custom-fields','page-attributes'),
			'taxonomies' => array('post_tag'),
			'show_ui'     => true, // for testing only
		) ) );

		do_action( 'pf_feed_post_type_registered' );
	}

	public function register_feed_tag_taxonomy() {
		$labels = array(
			'name'          => __( 'Feed Tags', 'pf' ),
			'singular_name' => __( 'Feed Tag', 'pf' ),
			'all_items'     => __( 'All Feed Tags', 'pf' ),
			'edit_item'     => __( 'Edit Feed Tag', 'pf' ),
			'update_item'   => __( 'Update Feed Tag', 'pf' ),
			'add_new_item'  => __( 'Add New Feed Tag', 'pf' ),
			'new_item_name' => __( 'New Feed Tag', 'pf' ),
			'search_items'  => __( 'Search Feed Tags', 'pf' ),
		);

		register_taxonomy( $this->tag_taxonomy, $this->post_type, apply_filters( 'pf_register_feed_tag_taxonomy_args', array(
			'labels' => $labels,
			'public' => true,
			'show_admin_columns' => true,
			'rewrite' => false
		) ) );
	}
	
	public function deal_with_old_feedlists() {
		
		$feedlist = get_option( PF_SLUG . '_feedlist' );
		if ( (false == $feedlist) || (empty($feedlist)) ){
			return true;
		} else {
			$feedlist = $feedlist;
		}
		$all_feeds_array = apply_filters( 'imported_rss_feeds', $feedlist );
		pf_log('Preparing to transform feedlist into a list of feed posts.');
		$ordered_all_feeds_array = array_values($all_feeds_array);
		$tidy_all_feeds_array = array_filter( $ordered_all_feeds_array, 'strlen' );
		#print_r('<pre>'); var_dump($tidy_all_feeds_array); print_r('</pre>'); die();
		foreach ($tidy_all_feeds_array as $key => $feed){
			$feedlist = $this->progressive_feedlist_transformer($tidy_all_feeds_array, $feed, $key);
		}
		$check_up = update_option( PF_SLUG . '_feedlist', $feedlist );
		if (!$check_up){
			wp_die('Unable to update feedlist option with new smaller feedlist.');
		}		
		
	}
	
	# Not only is this moving feeds over into feed CPT posts, but this methodology will insure a time-out won't force the process to restart.
	# There should probably be a AJAX interface for this, same as the AB subscribe method. 
	public function progressive_feedlist_transformer($feedlist = array(), $xmlUrl, $key) {
		
		$check = $this->create($xmlUrl, array('type' => 'rss-quick'));
		if ($check){
			unset($feedlist[$key]);
		}
		return $feedlist;
	}
	
	public function kill_all_feeds(){

		$mycustomposts = get_posts( array( 'post_type' => 'pf_feed', 'posts_per_page'=>-1) );
		   foreach( $mycustomposts as $mypost ) {
			 // Delete each post.
			 wp_delete_post( $mypost->ID, true);
			// Set to False if you want to send them to Trash.
		   }
	}
	
	# A function to take an argument array and turn it into a Feed CPT entry.
	public function feed_post_setup($r, $insert_type = 'insert'){
		
		foreach ($r as $k=>$e){
			if (!$e)
				$r[$k] = '';
		}
		
		$wp_args_d = array(
			'post_type' 	=> $this->post_type,
			'post_status' 	=> 'publish',
			'post_title'	=> $r['title'],
			'post_content'	=> $r['description'],
			'guid'			=> $r['url'],
			'tax_input' 	=> array($this->tag_taxonomy => $r['tags'])
		);
		# Duplicate the function of WordPress where creating a pre-existing 
		# post results in an update to that post. 
		
		if (!self::has_feed($r['url'])){
			$insert_type = 'insert';
		} else {
			$insert_type = 'update';
		}
		
		$wp_args = wp_parse_args( $r, $wp_args_d );
		
		
		if ($insert_type == 'update') {

			if  (!isset($r['ID'])){
				$post_obj = self::get_feed($r['url']);
				$r['ID'] = $post_obj->ID;
			}
			$wp_args['ID'] = $r['ID'];
			wp_update_post( $wp_args );
			$post_id = $r['ID'];
		}
		
		if ($insert_type == 'insert'){
			$posts = self::has_feed($r['url']);
			if (!$posts){
				$wp_args['guid'] = $r['url'];
				#$wp_args['post_date'] = date( 'Y-m-d H:i:s', time());
				$post_id = wp_insert_post($wp_args);
			} else {
				self::feed_post_setup($r, 'update');
				# @todo Better error needed.
				return false;
			}
		}
#echo '<pre>';
		#var_dump($post_id);
		#echo '</pre>';
		if ( is_numeric($post_id) ){
			self::set_pf_feed_type($post_id, $r['type']);
			$r['feedUrl'] = $r['url'];
			$unsetables = array('title', 'description', 'tags', 'type', 'url');
			foreach ($unsetables as $k=>$a){
				unset($r[$a]);
			}
			self::set_feed_meta($post_id, $r);
#echo '</pre>';
			return true;
		} else {
			return false;
		}	
	}
	
	
	# A function to pull feed meta out of the SimplePie object and into the argument array. 
	public function setup_rss_meta($r, $theFeed){
	
		if(empty($r)){
			
		} else {
			if (!$r['htmlUrl']){
				$r['htmlUrl'] = $theFeed->get_permalink();
			}
			if (!$r['title']){
				$r['title'] = $theFeed->get_title();
			}
			if (!$r['description']){
				$r['description'] = $theFeed->get_description();
			}
			if (!$r['feed_icon']){
				$r['feed_icon'] = $theFeed->get_favicon();
			}	
			if (!$r['feed_author']){
				$r['feed_author'] = $theFeed->get_author();
			}	
			if (!$r['thumbnail']){
				$r['thumbnail'] = $theFeed->get_image_url();
			}				
			if (empty($r['tags'])){
				$r['tags'] = $theFeed->get_feed_tags();
			}
		}
		return $r;
	
	}
	
	/*
	 * A function to create a new feed entry.
	 * Arguments:
	 * 		$htmlUrl = false, $type = false, $title = false, $tags = false, $thumbnail = false, $description = false, $added_by_user = false, $added_by_module = false 
	 */
	
	public function create($feedUrl, $args = array()){
		#print_r('<pre>'); var_dump($feedUrl); print_r('</pre>'); die();
		if (!isset($args['url'])){
			$args['url'] = $feedUrl;
		}
		$r = wp_parse_args( $args, array(
			'title'   		=> false,
			'url'     		=> 'http://pressforward.org/feed/',
			'htmlUrl' 		=> false,
			'type'	  		=> 'rss',
			'feedUrl'		=> $feedUrl,
			'description' 	=> false,
			'feed_author' 	=> false,
			'feed_icon'  	=> false,
			'copyright'		=> false,			
			'thumbnail'  	=> false,
			'user_added'    => false,
			'module_added' 	=> 'rss-import',
			'tags'    => array(),
		) );
		
		if ($r['type'] == 'rss'){
		
			if (is_wp_error($theFeed = fetch_feed($feedUrl))){
				return new WP_Error('badfeed', __('The feed fails verification.'));
			} else {
				$r = self::setup_rss_meta($r, $theFeed);
			}		
		}
		if (!$r['user_added']){
			$current_user = wp_get_current_user();
			$r['user_added'] = $current_user->user_login;
		}
		if ($r['type'] == 'rss-quick'){
			$r['title'] = $r['url'];
		}
		if (self::has_feed($feedUrl)){
			self::feed_post_setup($r, 'update');
		} else {
			self::feed_post_setup($r);
		}
		return true;

	}
	
	public function get_feed($url){
			
			$posts = self::has_feed($url);
			return $posts[0];
		
	}
	
	# A function to pull feeds from the database. 
	public function get( $args = array() ) {
		if ( ! post_type_exists( 'pf_feed' ) ) { $this->register_feed_post_type(); }
		$wp_args = array(
			'post_type'        => $this->post_type,
			'post_status'      => 'publish',
			'suppress_filters' => false,
		);

		$query_filters = array();

		// WP_Query does not accept a 'guid' param, so we filter hackishly
		if ( isset( $args['url'] ) ) {
		
			$parts = substr_count($args['url'], '&');
				
			if($parts > 0){
				#Apparently WP query can't deal with more than one part in a URL query. So we need another way.
				$args['meta_key'] = 'feedUrl';
				$args['meta_value'] = $args['url'];
			} else {	
				$this->filter_data['guid'] = $args['url'];
				unset( $args['url'] );
				$query_filters['posts_where'][] = '_filter_where_guid';
			}
		}

		foreach ( $query_filters as $hook => $filters ) {
			foreach ( $filters as $f ) {
				add_filter( $hook, array( $this, $f ) );
			}
		}

		// Other WP_Query args pass through
		$wp_args = wp_parse_args( $args, $wp_args );

		$posts = get_posts( $wp_args );

		foreach ( $query_filters as $hook => $filters ) {
			foreach ( $filters as $f ) {
				remove_filter( $hook, array( $this, $f ) );
			}
		}

		// Fetch some handy pf-specific data


		return $posts;
	}

	# Check if a post or posts exists with get, if it does not
	# return false. If it does, return the array of posts. 
	public function has_feed($url){
		$parsed = parse_url($url);
		if(!isset($parsed['scheme'])){
			$url = 'http://' . $url;
		}
		$posts = self::get(array('url' => $url));
		if (count($posts) > 0){
			return $posts;
		} else {
			return false;
		}
	}
	
	# @to-do - This will be important for plugging in non-RSS modules.
	# A function to retrieve a list of feeds for walking, display.
	# Specifically by type.
	public function get_feeds_by_type($type = 'all', $args = array()){
		
		# Eventually we'll want to use the 'retrieve_by' 
		# to allow devs to pass different types of queries
		# using the data passed in the argument. 
		$r = wp_parse_args( $args, array(
			'title'   		=> false,
			'url'     		=> false,
			'htmlUrl' 		=> false,
			'type'	  		=> $type,
			'description' 	=> false,
			'feed_author' 	=> false,
			'feed_icon'  	=> false,
			'copyright'		=> false,			
			'thumbnail'  	=> false,
			'user_added'    => false,
			'module_added' 	=> false,
			'tags'    		=> array(),
			'page'			=> false,
			'retrieve_by'	=> false
		) );
		
	
	}
	
	# When walking through the feedlist, if it is an old entry,
	# call this function to renew the feed post with better data.
	public function update_url($url){
		$posts = self::has_feed($url);
		if (!$posts){
			$check = self::create($url);
			return $check;
		}
		$c = 0;
		foreach ($posts as $post){
			setup_postdata($post);
			$post_id = get_the_ID();
			if (is_numeric($post_id)){
				if (($c == 0)){
					self::update($post_id, array('url' => $url));
				} else {
					if ($url == get_post_meta($post_id, 'feedUrl', true)){
						wp_delete_post( $post_id, true );
					}
				}
				$c++;
			} else {
				# Let's duplicate WordPress's mechanic of 'update' creating a new post if it doesn't exist.
				self::create($url);
			}
		}
		wp_reset_postdata();
	}
	
	# A function to update an existing feed CPT entry.
	public function update($post_id, $args){
		$r = wp_parse_args( $args, array(
			'ID'			=> $post_id,
			'title'   		=> false,
			'url'     		=> false,
			'htmlUrl' 		=> false,
			'type'	  		=> 'rss',
			'description' 	=> false,
			'feed_author' 	=> false,
			'feed_icon'  	=> false,
			'copyright'		=> false,			
			'thumbnail'  	=> false,
			'module_added' 	=> 'rss-import',
			'tags'    		=> array(),
		) );
		if (!$r['url']){
			return false;
		}
		if ($r['type'] == 'rss'){
		
			if (is_wp_error($theFeed = fetch_feed($feedURL))){
				return new WP_Error('badfeed', __('The feed fails verification.'));
			} else {
				$r = self::setup_rss_meta($r, $theFeed);
			}		
		}
		
		self::feed_post_setup($r, 'update');

		
	}
	
	# This function makes it easy to set the type of 'feed', which is important when we move to using something other than RSS.
	
	public function set_pf_feed_type($id, $type = "rss") {
	
		$updateResult = update_post_meta($id, 'feed_type', $type);
		
		if (is_wp_error($updateResult)){
			return $updateResult->get_error_message();
		} else {
			return true;
		}
	
	}
	
	public function get_pf_feed_type($id) {
		pf_log( 'Invoked: PF_Feed_Schema::get_pf_feed_type($id)' );
		$updateResult = get_post_meta($id, 'feed_type', true);
		if (is_wp_error($updateResult)){
			return $updateResult->get_error_message();
		} elseif ( !$updateResult ) {
			return false;
		} else {
			return $updateResult;
		}	
	}
	
	# This function processes the meta data passed to the create or
	# update feed object and turns it into post_meta. 
	#
	# Note that the goal with all feed meta is to describe it
	# in a way as similar to OPML as possible for accurate
	# output later. 
	public function set_feed_meta($post_id, $args){
		$c = 1;
		#echo '<pre>';
		#var_dump($args);
		#echo '</pre>';
		foreach ($args as $k=>$a){
		
			if(!$a){
	
			} else {
				update_post_meta($post_id, $k, $a);
			}
			$c++;
		
		}
		
		if ($c+1 == count($args)){
			update_post_meta($post_id, 'meta_data', 'complete');

		}
	}
	
	public function _filter_where_guid( $where ) {
		global $wpdb;
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.guid = %s ", $this->filter_data['guid'] );
		return $where;
	}
	
	function admin_enqueue_scripts() {
		global $pf;

		global $pagenow;

		$hook = 0 != func_num_args() ? func_get_arg( 0 ) : '';

		if ( !in_array( $pagenow, array( 'admin.php' ) ) )
			return;

		if(!in_array($hook, array('pressforward_page_pf-feeder')) )
			return;		
			
	
		wp_enqueue_script( 'feed_control_script', PF_URL . '/assets/js/feeds_control.js', array('jquery', PF_SLUG . '-twitter-bootstrap') );
	}
	
	function add_to_feeder(){
		?>	
			<br />
			<br />
		<button type="button" class="resetFeedOps btn btn-warning" id="resetFeedOps" value="Reset all Feed Retrieval Options"><?php _e('Reset all Feed Retrieval Options', 'pf'); ?></button>    <br />
		<?php
			$feed_go = get_option( PF_SLUG . '_feeds_go_switch', 0);
			$feed_iteration = get_option( PF_SLUG . '_feeds_iteration', 0);
			$retrieval_state = get_option( PF_SLUG . '_iterate_going_switch', 0);
			$chunk_state = get_option( PF_SLUG . '_ready_to_chunk', 1 );
			$retrieval_state = sprintf(__('Feeds Go? %1$d  Feeds iteration? %2$d  Going switch? %3$d  Ready to chunk? %4$d', 'pf'), $feed_go, $feed_iteration, $retrieval_state, $chunk_state);
			echo $retrieval_state;
		
		?>
		<br />
		<button type="button" class="redoFeeds btn btn-warning" id="resetFeedOps" value="Switch feeds to new retrieval setup"><?php _e('Switch feeds to new retrieval setup', 'pf'); ?></button>    <br />		
		<?php
	}
	
}