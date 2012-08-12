<?php
/*
Plugin Name: RSS to PressForward
Plugin URI: http://aramzs.me
Description: This plugin is am RSS parser for CHNM's Press Forward project. 
Version: 0.001
Author: Aram Zucker-Scharff
Author URI: http://aramzs.me
License: GPL2
*/

/*  Developed for the Center for History and New Media

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//Set some variables for likely later re-use. 
$rsspf_slug = 'rsspf';
$rsspf_title = 'RSS to Press Forward';
$rsspf_menu_slug = $rsspf_slug . '-menu';
$rsspf_nominations_edit_posttype = 'edit.php?post_type=nomination';
$rsspf_nominations_add_posttype = 'post-new.php?post_type=nomination';


//Activate the admin menu creation function.
add_action('admin_menu', 'register_rsspf_custom_menu_pages', 9);
function register_rsspf_custom_menu_pages() {
	global $rsspf_slug, $rsspf_title, $rsspf_menu_slug;

	/*
		First create the plugin menu, with the following variables
		The page title (in title tags)
		The menu title (on screen title)
		The permissions level required for access (edit_posts makes it accessable to Contributor level users and above). 
		The menu's slug.
		The function to build the menu.
		The icon URL.
		The menu position (25 is Comments' menu position, so 24 should put it right above the Comments menu entry).
	*/
	add_menu_page ($rsspf_title, $rsspf_title, 'edit_posts', $rsspf_menu_slug, array('rsspf', 'rsspf_reader_builder'), plugins_url('rss-to-pressforward/rss-forward-16.png'), 24);

	
	//Now create an options page for the plugin. This page is only accessable to Administrative level users. 
	add_submenu_page($rsspf_menu_slug, $rsspf_title . ' Options', $rsspf_title . ' Options', 'manage_options', $rsspf_slug . '-options', array('rsspf', 'rsspf_options_builder'));
	
	//Now create an feed-listing page for the plugin, where the user can add feeds. This page is Editor level users and above. 
	add_submenu_page($rsspf_menu_slug, $rsspf_title . ' Feeder', $rsspf_title . ' Feeder', 'edit_others_posts', $rsspf_slug . '-feeder', array('rsspf', 'rsspf_feeder_builder'));
	
//	add_submenu_page($rsspf_menu_slug, 'Nominated Posts', 'Nominated Posts', 'edit_posts', $rsspf_slug . '-nominated' ,$rsspf_nominations_edit_posttype);
	
}

//Let's create the nominations post-type
add_action('init', 'create_rsspf_nomination_post_type');
function create_rsspf_nomination_post_type() {
	global $rsspf_slug, $rsspf_title, $rsspf_menu_slug;
	$args = array(
				'labels' => array(
									'name' => __( 'Nominations' ),
									'singular_name' => __( 'Nomination' ),
									'add_new' => __('Nominate'),
									'add_new_item' => __('Add New Nomination'),
									'edit_item' => __('Edit Nomination'),
									'new_item' => __('New Nomination'),
									'view_item' => __('View Nomination'),
									'search_items' => __('Search Nominations'),
									'not_found' => __('No nominations found'),
									'not_found_in_trash' => __('No nominations found in Trash')
								),
				'description' => 'Posts from around the internet nominated for consideration to public posting',
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => $rsspf_menu_slug,
				'has_archive' => false
			);
	
	register_post_type('nomination', $args);

}
//A quick note for later, nomination count - perhaps stored as post_meta for each nomination? 

class rsspf {

	function rsspf_feedlist() {
	
		$feedlist = 'http://feeds.feedburner.com/DHNowEditorsChoiceAndNews';
		return $feedlist;
	
	}
	
	function rsspf_reader_builder() {
		global $rsspf_slug, $rsspf_title, $rsspf_menu_slug;
		//Calling the feedlist within the rsspf class. 
		$feedlist = rsspf::rsspf_feedlist();
		echo '<h1>' . $rsspf_title . '</h1>';
		$theFeed = fetch_feed($feedlist);
		//A testing method, to insure the feed is being received and processed. 
		//print_r($theFeed);
		
		//Use this foreach loop to go through the overall feedlist, select each individual feed item (post) and do stuff with it.
		//Based off SimplePie's tutorial at http://simplepie.org/wiki/tutorial/how_to_display_previous_feed_items_like_google_reader.
		$c = 1;
		foreach($theFeed->get_items() as $item) {
		
			echo $c++ . '. ';
			//The following is a fix as described in http://simplepie.org/wiki/faq/typical_multifeed_gotchas
			$iFeed = $item->get_feed();
			echo '<strong>' . $iFeed->get_title() . '</strong>';
			echo ' : ';
			echo $item->get_title();
			echo '<br />';
			echo '<small>Published on ' . $item->get_date('r') . ' by ' . $item->get_author() . '</small>';
			echo '<br />';
			echo '<div>' . $item->get_content() . '</div>';
			echo '<br />';
			echo '<a target="_blank" href="' . $item->get_permalink() . '">Read More</a>';
			echo '<br />';
			echo '<hr />';
			echo '<br />';
		
		}
		

	}
	
	function rsspf_options_builder() {
	
		echo 'Options';
	
	}
	
	function rsspf_feeder_builder() {
	
		echo 'Feeder';
	
	}	



}

?>