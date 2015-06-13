<?php

//$file="http://www.google.com/reader/public/subscriptions/user%2F10862070116690190079%2Fbundle%2FWriting%2BTech%20Bundle";

class OPML_reader {

	function __construct($file = ''){
		if (!empty($file)){
			$this->opml_file = $this->open_OPML($file);
			$this->file_url = $file;
//			$this->get_OPML_obj();
		}
	}

	function open_OPML($file) {
        pf_log('open_OPML invoked.');
		if(1 == ini_get('allow_url_fopen')){
			pf_log('Using simplexml_load_file to load OPML.');
            $file = simplexml_load_file($file);
		} else {
            pf_log('Using cURL to load OPML file.');
			$ch = curl_init();
			$timeout = 5;
			curl_setopt($ch, CURLOPT_URL, $file);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			$data = curl_exec($ch);
			curl_close($ch);
			$file = simplexml_load_string($data);
		}

    #echo '<pre>'; var_dump($data); #die();
		if (empty($file)) {
            pf_log('Received an empty file.');
			return false;
		} else {
            pf_log('Received file.');
            //pf_log($file);
			$opml_data = $file;
			return $opml_data;
		}
	}

	function get_OPML_obj($url = false){
		pf_log('get_OPML_obj invoked.');
		if (false == $url){
			$opml_data = $this->opml_file;
		} else {
			$opml_data = $this->open_OPML($url);
		}
		$obj = new OPML_Object($url);
		$this->opml = $obj;
		$this->opml->set_title($opml_data->head->title);
		pf_log('Reading out from OPML file named '.$opml_data->head->title);
		foreach ( $opml_data->body->outline as $folder ){
			//return $folder;
			$this->make_OPML_obj($folder);
		}
		return $this->opml;
	}

	function make_OPML_obj($entry, $parent = false) {
		//$entry = (array) $entry;
		#return $entry; #die();
		$entry_a = $this->get_opml_properties($entry);
		pf_log('Making an OPML obj using properties of:');
		pf_log($entry_a);
		if ( isset($entry_a['xmlUrl']) ){
			//pf_log('Making a feed.');
			$feed_obj = $this->opml->make_a_feed_obj($entry_a);
			$this->opml->set_feed($feed_obj, $parent);
		} else {
			//pf_log('Making a folder.');
			$folder_obj = $this->opml->make_a_folder_obj($entry_a);
			$this->opml->set_folder($folder_obj);
			foreach ($entry as $feed){
				$this->make_OPML_obj($feed, $folder_obj);
			}
		}
	}

	function get_opml_properties($simple_xml_obj){
		$obj = $simple_xml_obj->attributes();
		$array = array();
		foreach ($obj as $key=>$value){
			$array[$key] = (string) $value;
		}
		return $array;
	}

	function add_to_opml_data($feed_obj, $param) {
		$array = $obj->$param;
		$array[] = $string;
		$obj->$param =  $array;
		return $obj;
	}

	# Pass the URL and if you want to return an array of objects or of urls.
	function get_OPML_data($url, $is_array = true){
		//pf_log('OPML Reader process invoked: get_OPML_data');
		$opml_data = $this->open_OPML($url);

        #var_dump($opml_data); die();
		if (!$opml_data || empty($opml_data)){
			//pf_log('Could not open the OPML file.');
            //pf_log('Resulted in:');
            //pf_log($opml_data);
			return false;
		}

		//Site data
		$a = array();
		//Feed URI
		$b = array();
		$c = 0;

		/** Get XML data:
		  * supplies:
		  * [text] - Text version of title
		  * [text] - Text version of title
		  * [type] - Feed type (should be rss)
		  * [xmlUrl] - location of the RSS feed on the site.
		  * [htmlUrl] - The site home URI.
		**/
		foreach ($opml_data->body->outline as $folder){
            //pf_log($c++);
            #var_dump($folder); die();
			# Check if there are no folders.
            if (isset($folder['xmlUrl'])){
                //pf_log('Not a folder.');
                $b[] = $folder['xmlUrl']->__toString();
            }

            foreach ($folder->outline as $data){
                //pf_log('A folder.');
				$a[] = reset($data);
			}
			// Pulls out the feed location.
			foreach ($a as $outline) {
               // pf_log('Feed found:');
                //pf_log($outline['xmlUrl']);
				$b[] = $outline['xmlUrl'];
			}

		}
		#var_dump($a);
   #var_dump($b);
   #die();
			if ($is_array){
                pf_log('Is array:');
                pf_log($b);
				return $b;
			} else {
                pf_log('Is not array:');
                pf_log($a);
				return $a;
			}

	}

}

class OPML_Object {

	function __construct($url){
		$this->url = $url;
		$this->folders = array();
		$this->feeds = array();
	}

	function set_folder($folder_obj){
		$folder_obj->slug = $this->slugify($folder_obj->title);
		$this->folders[$folder_obj->slug] = $folder_obj;
	}
	function set_title($string){
		if (empty($string)){
			$this->title = $this->url;
		} else {
			$this->title = (string) $string;
		}
	}
	function get_title(){
		if (empty($this->title)){
			return $this->url;
		} else {
			return $this->title;
		}
	}
	function get_folder( $key ){
		$folders = $this->folders;
		$key = $this->slugify($key);
		return $folders[$key];

	}
	function set_feed($feed_obj, $folder = false){
		if (!$folder){
			//Do not set an unsorted feed if it has already been set
			//as a sorted feed.
			if (!isset($this->feeds[$feed_obj->id])){
				$feed_obj->folder = false;
				return array_push($this->feeds, $feed_obj);
			}
		} else {
			if (isset($this->feeds[$feed_obj->id])){
				$feed_obj = $this->feeds[$feed_obj->id];
				//$feed_obj->folder[] = $folder;
			} elseif (empty($feed_obj->folder) || !is_array($feed_obj->folder)) {
				$feed_obj->folder = array();
				//$feed_obj->folder[] = $folder;
			} else {
				//$feed_obj->folder[] = $folder;
			}
			if (is_array($folder)){
				foreach ($folder as $folder_type){
					$feed_obj->folder[] = $folder_type;
				}
			} else {
				$feed_obj->folder[] = $folder;
			}
			//var_dump($feed_obj);
			$this->feeds[$feed_obj->id] = $feed_obj;
		}
	}
	public function check_keys($array, $keys, $strict = false){
		$array['missing'] = array();
		foreach($keys as $key){
			if ( !array_key_exists($key, $array) ){
				if ($strict) {
					return false;
				} else {
					$array[$key] = '';
					$array['missing'][] = $key;
				}
			}
		}
		return $array;
	}
	function assure_title_and_text($entry){
		if (!empty($entry['title']) && !empty($entry['text']) ){
			$entry['text'] = $entry['title'];
		} elseif (!empty($entry['text']) && !empty($entry['title'])) {
			$entry['title'] = $entry['text'];
		} elseif ( empty($entry['title']) && empty($entry['text']) && !empty($entry['feedUrl']) ) {
			$entry['text'] = $entry['feedUrl'];
			$entry['title'] = $entry['feedUrl'];
		}
		return $entry;
	}

	function make_a_folder_obj($entry){
		$folder = new stdClass();
		$entry = (array) $entry;
		$entry = $this->check_keys($entry, array('title', 'text') );
		$entry['title'] = (!empty($entry['title']) ? $entry['title'] : false);
		$entry['text'] = (!empty($entry['text']) ? $entry['text'] : false);
		$entry = $this->assure_title_and_text($entry);
		#var_dump($entry); die();
		$folder->title = $entry['title'];
		$folder->text = $entry['text'];
		//pf_log('Making folder with title of '.$folder->title);
		return $folder;
	}
	function make_a_feed_obj($entry){
		$feed = new stdClass();
		$entry = (array) $entry;
		if ( empty( $entry['xmlUrl'] ) ){
			$entry['xmlUrl'] = $entry['htmlUrl'];
		}
		if ( empty($entry['feedUrl']) ){
			$entry['feedUrl'] = $entry['xmlUrl'];
		}
		$entry = $this->assure_title_and_text($entry);
		$entry = $this->check_keys($entry, array( 'title', 'text', 'type', 'xmlUrl', 'htmlUrl', 'feedUrl' ) );
		$feed->title = $entry['title'];
		$feed->text = $entry['text'];
		$feed->type = $entry['type'];
		$feed->xmlUrl = str_replace('&amp;', '&', $entry['xmlUrl']);
		$feed->feedUrl = str_replace('&amp;', '&', $entry['feedUrl']);
		$feed->htmlUrl = str_replace('&amp;', '&', $entry['htmlUrl']);
		$feed->id = md5($feed->feedUrl);
		//pf_log('Making feed with URL of '.$feed->feedUrl);
		return $feed;
	}
	function order_opml_entries($a, $b){
		if (empty($a->folder)){
			return 1;
		}
		if (empty($b->folder)){
			return -1;
		}
		$a = $a->folder[0];
		$b = $b->folder[0];
		if (!$a){
			return -1;
		}
		if (strcasecmp($a, $b) == 0){
			return 0;
		}
		if (strcasecmp($a, $b) < 0){
			return -1;
		} else {
			return 1;
		}
	}
	function order_feeds_by_folder(){
		usort($this->feeds, array($this, 'order_opml_entries'));
	}
	function get_feeds_by_folder($folder){
		$folder_a = array();
		if (is_array($folder) && !empty($folder[0]) ){
			$folder = $folder[0];
		} elseif ( is_array($folder) && !empty($folder['slug']) ){
			$folder = $folder['slug'];
		}
		foreach ( $this->feeds as $feed ){
			//var_dump($feed);
			if ( !empty($feed->folder) ){
				foreach($feed->folder as $feed_folder){
					//var_dump('folder: '.$folder);
					//var_dump($feed_folder);
					if ( !is_object($feed_folder) ){
						var_dump('Not an object');
						var_dump($feed_folder);
					}
					if ($feed_folder->slug == $this->slugify($folder)){
						$folder_a[] = $feed;
					}
				}
			}
		}
		if ( empty($folder_a) ){
			return false;
		}
		return $folder_a;
	}
	public function get_feeds_without_folder(){
		$folder_a = array();
		foreach ( $this->feeds as $feed ){
			//var_dump($feed);
			if ( empty($feed->folder) ){
				$folder_a[] = $feed;
			}
		}
		if ( empty($folder_a) ){
			return false;
		}
		return $folder_a;
	}
	function get_feed_by_id($unique_id){
		return $this->feeds[$unique_id];
	}
	function sanitize($string, $force_lowercase = true, $anal = false) {
		$strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
					   "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
					   "", "", ",", "<", ".", ">", "/", "?");
		if (is_array($string)){
			$string = implode(' ', $string);
		}
		$clean = trim(str_replace($strip, "", strip_tags($string)));
		$clean = preg_replace('/\s+/', "-", $clean);
		$clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;

		return ($force_lowercase) ?
			(function_exists('mb_strtolower')) ?
				mb_strtolower($clean, 'UTF-8') :
				strtolower($clean) :
			$clean;
	}
	public function slugify($string, $case = true, $strict = false, $spaces = false) {
		//var_dump($string);
		if (is_array($string) ){
			$string = $string[0];
		}
		$string = strip_tags($string);
		// replace non letter or digits by -
		$string = preg_replace('~[^\\pL\d]+~u', '-', $string);
		if ($spaces == false){
			$stringSlug = str_replace(' ', '-', $string);
			$stringSlug = trim($stringSlug);
			$stringSlug = str_replace('&amp;','&', $stringSlug);
			//$charsToElim = array('?','/','\\');
			$stringSlug = $this->sanitize($stringSlug, $case, $strict);
		} else {
			//$string = strip_tags($string);
			//$stringArray = explode(' ', $string);
			//$stringSlug = '';
			//foreach ($stringArray as $stringPart){
			//	$stringSlug .= ucfirst($stringPart);
			//}
			$stringSlug = str_replace('&amp;','&', $string);
			//$charsToElim = array('?','/','\\');
			$stringSlug = $this->sanitize($stringSlug, $case, $strict);
		}

		$stringSlug = htmlspecialchars( $stringSlug, null, null, false );

		if (empty($stringSlug))
		{
			//var_dump('probs: ' .$string); die();
			return 'empty';
		}

		return $stringSlug;
	}
}

class OPML_Maker {

	function __construct($OPML_obj){
		if ( 'OPML_Object' != get_class( $OPML_obj ) ){
			return false;
		} else {
			$this->obj = $OPML_obj;
		}
		$this->force_safe = true;
	}

	function force_safe($force = true){
		if ($force){
			$this->force_safe = true;
			return true;
		}
	}

	function assemble_tag($tag, $obj, $self_closing = false, $filter = false){
		if (empty($obj)){
			return '';
		}
		$s = "<$tag";
		foreach ($obj as $property=>$value){
			if ( !empty($filter) && in_array( $property, $filter ) ){
				continue;
			}
			if ($this->force_safe){
				$s .= ' '.esc_attr($property).'="'.esc_attr($value).'"';
			} else {
				$s .= ' '.$property.'="'.$value.'"';
			}
		}
		if ($self_closing){
			$s .= '/>';
		} else {
			$s .= '>';
		}
		$s .= "\n";
		return $s;
	}

	public function template( $title = 'Blogroll' ){
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
		<opml version="2.0">
		    <head>
		        <title><?php echo $title; ?></title>
		        	<expansionState></expansionState>
					<linkPublicUrl><?php //@todo ?></linkPublicUrl>
					<lastCursor>1</lastCursor>
		    </head>

		    <body>
		    	<?php
		    		$c = 0;
		    		foreach ($this->obj->folders as $folder){
		    			if ($c > 0){
		    				echo "\n\t\t\t";
		    			} else {

		    			}
		    			echo $this->assemble_tag('outline', $folder);
		    				//var_dump($folder);
		    				$feeds = $this->obj->get_feeds_by_folder($folder->slug);
		    				//var_dump($feeds);
		    				if (!empty($feeds)){
			    				foreach ($feeds as $feed){
			    					//var_dump($feed);
			    					echo "\t\t\t\t".$this->assemble_tag('outline',$feed,true,array('folder','feedUrl'));
			    				}
			    			}
		    			echo "\t\t\t".'</outline>';
		    			$c++;
		    		}
		    		echo "\n";
		    		$folderless_count = 0;
		    		foreach ($this->obj->get_feeds_without_folder() as $feed){
		    			if ($c > 0){
		    				echo "\t\t\t";
		    			}
		    			echo $this->assemble_tag('outline',$feed,true,array('folder','feedUrl'));
		    			$c++;
		    		}
		    		echo "\n";
		    	?>
		    </body>
		</opml>
		<?php
		// get OPML from buffer and save to file
		$opml = ob_get_clean();
		$this->file_contents = $opml;
		return $opml;
	}

	public function make_as_file($filepath = false){
		if ( !$filepath ){
			file_put_contents(plugin_dir_path( __FILE__ ).'blogroll.opml', $this->file_contents);
		} else {
			file_put_contents($filepath, $this->file_contents);
		}
	}

}

?>