<?php

class CountComments extends Object {
	
	var $path = '';
	var $url = '';
	var $prefix = 'wp_';
	
	function wp_admin_init()
	{
		$this->plugin_page = basename(__FILE__);
		add_management_page(
			__('CommentCount'), 
			__('CommentCount'), 
			7, 
			$this->plugin_page, 
			array(&$this, 'wp_admin_options')
		);
		add_action( 'admin_head', array($this, 'wp_admin_css') );
	}
	
	function wp_admin_css()
	{
		if(isset($_GET['page']) AND $_GET['page'] == $this->plugin_page)
			echo ( '<link rel="stylesheet" type="text/css" href="'. $this->url . 'css/CountComments.css" />' );
	}
	
	function wp_admin_options()
	{
		global $wpdb;
		
		// Shorten the get variable.
		$q = $_GET;
		
		// Get URL
		$url = $this->build_url(array(), array('page'));
		
		// Limit the permitted actions to the following:
		$permitted_actions = array(
			'monthly'		=>true,
			'weekly'		=>true,
			'daily'			=>true,
			'popular'		=>true,
			'read'			=>true,
		);
		if(!$permitted_actions[$q['action']]) 
			$action = "monthly";
		else 
			$action = $q['action'];
		
		
		// Determine the number of results to show.
		if(!is_numeric($q['limit']) OR $q['limit'] > 50 OR $q['limit'] < 1)
			$limit = 20;
		else
			$limit = $q['limit'];
			
		// Determine the starting number.
		if(!is_numeric($q['pageNumber']) OR $q['pageNumber'] > 50 OR $q['pageNumber'] < 1)
			$pageNumber = 1;
		else
			$pageNumber = $q['pageNumber'];
		
		// Load a set of stats to display depending on the units selected.
		switch($action)
		{
			case 'popular':
				$date = '';
				if(preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/isu', $q['date'])
				OR preg_match('/^([0-9]{4})-([0-9]{2})$/isu', $q['date']))
					$datestamp = $q['date'];
				else
					$datestamp = date('Y-m');
				
				$sql = "SELECT comment_post_ID FROM `" . $this->prefix . "comments` WHERE comment_date LIKE '" . $datestamp . "%' AND comment_approved = '1';";
				$result = $wpdb->get_results($sql);
				
				$posts = array();
				foreach($result as $comment)
					$posts[$comment->comment_post_ID]++;
				
				arsort($posts);
				
				// Create the list of post ID's to inject into the SQL query.
				$start = (($pageNumber-1) * $limit);
				$sqla = array();
				$counter = 0;
				foreach($posts as $post_ID=>$count)
				{
					if($counter >= $start AND $counter < ($start+$limit)) 
					{
						$sqla[] = "ID='$post_ID'";
					}
					$counter++;
				}
				$sql = "SELECT * FROM `" . $this->prefix . "posts` WHERE " . implode(' OR ', $sqla);
				$result = $wpdb->get_results($sql);
				
				// Sort the posts back into the appropriate order.
				$sorted_posts = array();
				foreach($posts as $postID=>$numComments)
				{
					foreach($result as $key=>$post)
					{
						if($post->ID == $postID)
						{
							$post->numComments = $numComments;
							$sorted_posts[] = $post;
							unset($result[$key]);
							break;
						}
					}
				}
										
				$template = 'popular_posts.phtml';
				$navigation = $this->get_paged_nav(count($posts), $limit, false);
			break;
			
			case 'read':
				$date = '';
				if(preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/isu', $q['date'])
				OR preg_match('/^([0-9]{4})-([0-9]{2})$/isu', $q['date']))
					$datestamp = $q['date'];
				else
					$datestamp = date('Y-m');
					
				// Find total number of results.
				$sql = "SELECT count(*) AS `result` FROM `" . $this->prefix . "comments` WHERE comment_date LIKE '" . $datestamp . "%' AND comment_approved = '1';";
				$result = $wpdb->get_results($sql);
				$total = $result[0]->result;
				
				// Find the results for this page.
				$start = (($pageNumber-1) * $limit);
				$sql = "SELECT * FROM `" . $this->prefix . "comments` WHERE comment_date LIKE '" . $datestamp . "%' AND comment_approved = '1' LIMIT $start, $limit;";
				$result = $wpdb->get_results($sql);
				
				$template = 'read_comments.phtml';
				$navigation = $this->get_paged_nav($total, $limit, false);
			break;
			
			case 'daily':
				if(preg_match('/^([0-9]{4})-([0-9]{2})$/isu', $q['date'], $regs))
				{
					$year = $regs[1];
					$month = $regs[2];
					$datestamp = $q['date'];
				}
				else
				{
					$year = date('Y');
					$month = date('m');
					$datestamp = date('Y-m');
				}
					
				// Get the number of days in this month.
				$timestamp = mktime(1, 1, 1, $month, 1, $year);
				$limit = date('t', $timestamp);
				
				// Get the current page set of months.
				$start = 1;
				for($i=1; $i<=$limit; $i++)
				{
					$day = sprintf('%02d', $i);
					$datestamp = "$year-$month-$day";
					$query = "SELECT count(*) AS `result` FROM `" . $this->prefix . "comments` WHERE comment_date LIKE '" . $datestamp . "%' AND comment_approved = '1';";
					$numbers[$datestamp]['sql'] = $query;
					$result = $wpdb->get_results($query);
					$numbers[$datestamp]['count'] = $result[0]->result;
					$numbers[$datestamp]['query'] = $query;
				}
					
				ksort($numbers);
				$template = 'daily_totals.phtml';
			break;
			
			case 'monthly':
			default:
				// Find the total number of months that we have records for.
				$sql = "SELECT comment_date FROM `" . $this->prefix . "comments`  ORDER BY `wp_comments`.`comment_date` ASC LIMIT 0,1;";
				$result = $wpdb->get_results($sql);
				$initial_date = $result[0]->comment_date;
				if(preg_match('/^([0-9]{4})-([0-9]{2})/isu', $initial_date, $regs))
				{
					$initial_year = $regs[1];
					$initial_month = $regs[2];
					
					$year = date("Y");
					$month = date("m");
					
					$years_diff = $year - $initial_year;
					$months_diff = $month - $initial_month;
					
					$total_months = ($years_diff * 12) + $months_diff + 1;
				}
				
				// Get the current page set of months.
				$timestamp = time();
				$start = (($pageNumber-1) * $limit);
				for($i=$start; $i<($limit+$start); $i++)
				{
					$timestamp = strtotime("-$i month");
					$datestamp = date("Y-m", $timestamp);
					$query = "SELECT count(*) AS `result` FROM `" . $this->prefix . "comments` WHERE comment_date LIKE '" . $datestamp . "%' AND comment_approved = '1';";
					$numbers[$datestamp]['sql'] = $query;
					$result = $wpdb->get_results($query);
					$numbers[$datestamp]['count'] = $result[0]->result;
					$numbers[$datestamp]['query'] = $query;
				}
				
				krsort($numbers);
				$template = 'monthly_totals.phtml';
				$navigation = $this->get_paged_nav($total_months, $limit, false);
			break;
		}
		
		// Include the appropriate template based on what is set above.
		include($this->path . "/html/" . $template);
		
	}
	
	/**
	 * Get a paginated navigation bar
	 *
	 * This function will create and return the HTML for a paginated navigation bar
	 * based on the total number of results passed in $num_results, and the value
	 * found in $_GET['pageNumber'].  The programmer simply needs to call this function
	 * with the appropriate value in $num_results, and use the value in $_GET['pageNumber']
	 * to determine which results should be shown.
	 * Creates a list of pages in the form of:
	 * 1 .. 5 6 7 .. 50 51 .. 100
	 * (in this case, you would be viewing page 6)
	 * 
	 * Code taken from http://www.warkensoft.com/2009/12/paginated-navigation-bar/
	 *
	 * @global    int        $_GET['pageNumber'] is the current page of results being displayed.
	 * @param    int     $num_results is the total number of results to be paged through.
	 * @param    int     $num_per_page is the number of results to be shown per page.
	 * @param    bool    $show set to true to write output to browser.
	 *
	 * @return    string    Returns the HTML code to display the nav bar.
	 *
	 */
	function get_paged_nav($num_results, $num_per_page=10, $show=false)
	{
		// Set this value to true if you want all pages to be shown,
		// otherwise the page list will be shortened.
		$full_page_list = false; 
		
		// Initialize the output string.
		$output = '';
		
		// Shorten the get variable.
		$q = $_GET;
		
		// Determine which page we're on, or set to the first page.
		if(isset($q['pageNumber']) AND is_numeric($q['pageNumber'])) $page = $q['pageNumber'];
		else $page = 1;
		
		// Determine the total number of pages to be shown.
		$total_pages = ceil($num_results / $num_per_page);
		
		// Begin to loop through the pages creating the HTML code.
		for($i=1; $i<=$total_pages; $i++)
		{
			// Assign a new page number value to the pageNumber query variable.
			$q['pageNumber'] = $i;
			
			$new_url = $this->build_url($q);
			
			// Determine whether or not we're looking at this page.
			if($i != $page)
			{
				// Determine whether or not the page is worth showing a link for.
				// Allows us to shorten the list of pages.
				if($full_page_list == true
				OR $i == $page-1
				OR $i == $page+1
				OR $i == 1
				OR $i == $total_pages
				OR $i == floor($total_pages/2)
				OR $i == floor($total_pages/2)+1
				)
				{
					$output .= "<a href='$new_url'>$i</a> ";
				}
				else
				{
					$output .= '. ';
				}
			}
			else
			{
				// This is the page we're looking at.
				$output .= "<strong>$i</strong> ";
			}
		}
		
		// Remove extra dots from the list of pages, allowing it to be shortened.
		$output = ereg_replace('(\. ){2,}', ' .. ', $output);
		
		// Determine whether to show the HTML, or just return it.
		if($show) echo $output;
		
		return($output);
	}
	
	/**
	 * Build a url based on permitted query vars passed to the function.
	 * 
	 * @param $add array containing query vars to add to the query request.
	 * @param $qvars array containing query vars to keep from the old query request.
	 * 
	 * @return string containing the new URL
	 */
	function build_url($add = array(), $qvars = array())
	{
		// Get the original URL from the server.
		$url = $_SERVER['REQUEST_URI'];
		
		// Remove query vars from the original URL.
		if(preg_match('#^([^\?]+)(.*)$#isu', $url, $regs))
		$url = $regs[1];
		
		// Shorten the get variable.
		$q = $_GET;
		
		// Initialize a new array for storage of the query variables.
		$tmp = array();
		foreach($qvars as $key)
			$tmp[] = "$key=" . urlencode($q[$key]);
		
		foreach($add as $key=>$value)
			$tmp[] = "$key=" . urlencode($value);
		
		// Create a new query string for the URL of the page to look at.
		$qvars = implode("&amp;", $tmp);
		
		// Create the new URL for this page.
		$new_url = $url . '?' . $qvars;
		
		return($new_url);
	}
}

?>