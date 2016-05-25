<?php
/**
* @wordpress-plugin
* Plugin Name:       Google Analytics Article Audit
* Plugin URI:        http://indibits.com/products/wordpress-plugins/google-analytics-article-audit/
* Description:       A WordPress plugin to help you audit your articles posted on your WordPress blog with the help of Google Analytics data of the website.
* Version:           1.0
* Author:            Indibits
* Author URI:        http://indibits.com
* License:           GNU GPL 3.0
*/
require_once realpath(dirname(__FILE__) . '/google-api-php-client/src/Google/autoload.php');

session_start();
global $ga_article_audit_db_version;
$ga_article_audit_db_version = '1.0';
class GA_Article_Audit
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
    
    /**
     * Start up
     */
    public function __construct()
    {
		register_activation_hook( __FILE__, array( $this, 'ga_article_audit_install' ) );
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
		
		add_action( 'wp_ajax_post_status_update', array( $this, 'post_status_update' ) );
        add_action( 'wp_ajax_post_status_update', array( $this, 'post_status_update' ) );
		
		add_action( 'wp_ajax_get_next_page', array( $this, 'get_next_page' ) );
        add_action( 'wp_ajax_get_next_page', array( $this, 'get_next_page' ) );
		
		add_action( 'admin_enqueue_scripts', array($this, 'popup_script' ));
		
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        $hook_suffix = add_utility_page(
            'GA Article Audit', 
            'GA Article Audit', 
            'manage_options', 
            'ga-article-audit-admin-settings', 
            array( $this, 'create_admin_page' ), 'dashicons-chart-area'
        );
		/* This hook invokes the function only on our plugin administration screen */
		add_action( 'admin_print_scripts-' . $hook_suffix, array( $this, 'ga_article_audit_admin_script') );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {		
        // Set class property
		$this->option = get_option( 'ga_article_audit_authentication_setting' );
		$this->options = get_option( 'ga_article_audit_option_name' );
		
        ?>
        <div class="wrap">
            <h2>Google Analytics Article Audit Reports</h2>  
        <?php  if(!isset($this->option['authentication_code'])): ?> 			
 <a href='#' onClick='login();' id="loginText"'>Get Authentication </a>
    <a href="#" style="display:none" id="logoutText" target='myIFrame' onclick="myIFrame.location='https://www.google.com/accounts/Logout'; startLogoutPolling();return false;">logout </a>
    <iframe name='myIFrame' id="myIFrame" style='display:none'></iframe>
    <div id='uName'></div>
	<?php //endif; ?>
		<form method="POST" action="options.php">
			<?php
			   settings_fields( 'ga-article-audit-authentication-setting' );   
			   do_settings_sections( 'ga-article-audit-authentication-setting' );
			   submit_button();
			?>		  
		</form>
	<?php endif; 
		   // Check if there is a logout request in the URL.
			if (isset($_REQUEST['logout'])) {
				// Clear the access token from the session storage.
				unset($_SESSION['access_token']);
			}
			/*************************/
			$client = new Google_Client();
			$client->setAuthConfigFile(plugin_dir_url( __FILE__ ) . '/client_secrets.json');
			$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
			$client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);
			$client->setIncludeGrantedScopes(true);
			$client->setAccessType('offline');
				
		    if( isset( $this->option['authentication_code'] ) ){	
			    
				global $wpdb;
				$resultset = json_decode( get_option('ga_article_audit_tokens') );
				
				if ($client->isAccessTokenExpired()) {
					
					if( isset( $resultset )){
						
						$refreshToken = $resultset->refreshToken;
						
					    $client->refreshToken( $refreshToken );
					    $accessToken = $client->getAccessToken();
						
						$ga_article_audit_tokens = json_encode( array( 'time' => current_time( 'mysql' ), 'accessToken' =>  $accessToken, 'refreshToken' => $refreshToken ) );
						
					    update_option( 'ga_article_audit_tokens', $ga_article_audit_tokens );
					} else {
						$client->authenticate( $this->option['authentication_code'] );
						
						$accessToken = $client->getAccessToken();
						$refreshToken = $client->getRefreshToken();
					
						$ga_article_audit_tokens = json_encode( array( 'time' => current_time( 'mysql' ),'accessToken' =>  $accessToken, 'refreshToken' => $refreshToken ) );
						update_option( 'ga_article_audit_tokens', $ga_article_audit_tokens );
					}
				}
			} else {
				$resultset = json_decode(get_option('ga_article_audit_tokens'));
			
				if ($client->isAccessTokenExpired()) {
					if( isset( $resultset ) ){
						$refreshToken = $resultset->refreshToken;
						$client->refreshToken( $refreshToken );
						$accessToken = $client->getAccessToken();			
						$ga_article_audit_tokens = json_encode( array( 'time' => current_time( 'mysql' ), 'accessToken' =>  $accessToken, 'refreshToken' => $refreshToken ) );
						update_option( 'ga_article_audit_tokens', $ga_article_audit_tokens );
					} else {
						echo 'You need to reauthorize the application to get the analytics report.';
					}
				}
			}
			$auth_url = $client->createAuthUrl();
		?>
		<script>
		    var auth_url = '<?php echo $auth_url; ?>';
			//console.log(auth_url);
		</script>
		<?php
		if( isset($accessToken) ){
			
			$_SESSION['access_token'] = $accessToken ? $accessToken : $refreshToken;
			
			$client->setAccessToken($_SESSION['access_token']);
			
			// Create an authorized analytics service object.
			$analytics = new Google_Service_Analytics($client);
        
			// Get the view (profile) id for the authorized user.
		    $profile = $this->options['profile_id'] ? $this->options['profile_id'] : $this->getFirstProfileId($analytics);
			//$profile = $this->getFirstProfileId($analytics);
			
			// Get the results from the Core Reporting API and print the results.
			$results = $this->getResults($analytics, $profile);
	    }    
		?>
	 
        </div>
		<?php if( isset($profile) ): ?>
		<form method="POST" action="options.php">
		<?php 
			// This prints out all hidden setting fields
			settings_fields( 'ga-article-audit-option-group' );   
			do_settings_sections( 'ga-article-audit-admin-settings' );
			submit_button();
			?>		  
		</form>
		<?php endif; ?>
		<div class="ga-article-audit-report">
		    <?php
			if( isset( $this->option['authentication_code'] ) && $this->option['authentication_code'] != '' ){ 
				if( isset( $results ) ){
					// var_dump($results);
				   $this->printDataTable($results, $client, $profile);
				   $this->getPaginationInfo($results, $client, $profile);
				}
			}
			?>
		</div>
        <?php
    }
		
    /**
     * Register and add settings
     */
    public function page_init()
    {   
		/* Register Plugin Script */
		wp_register_script( 'ga-article-audit', plugin_dir_url( __FILE__ ) . 'js/ga-article-audit.js', array( 'jquery' ) );
		
		wp_localize_script( 'ga-article-audit', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1234) );	
		
		register_setting(
            'ga-article-audit-authentication-setting', // Option group
            'ga_article_audit_authentication_setting', // Option name
            array( $this, 'sanitization' ) // sanitization
        );
		
        register_setting(
            'ga-article-audit-option-group', // Option group
            'ga_article_audit_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
		
		add_settings_section(
		    'authentication_section',
			'Authentication Section',
			array( $this, 'print_authentication_section' ),
			'ga-article-audit-authentication-setting'
		);
		
        add_settings_section(
            'setting_section_id', // ID
            'GA Article Audit Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'ga-article-audit-admin-settings' // Page
        );
		
        add_settings_field(
            'authentication_code', 
            'Authentication Code', 
            array( $this, 'authentication_code_callback' ), 
            'ga-article-audit-authentication-setting', 
            'authentication_section'
        );
		
		add_settings_field(
            'profile_id', 
            'Chose Profile', 
            array( $this, 'profile_id_callback' ), 
            'ga-article-audit-admin-settings', 
            'setting_section_id'
        );

		add_settings_field(
		    'start_date',
			'Start Date',
			array( $this, 'start_date_callback' ),
			'ga-article-audit-admin-settings',
			'setting_section_id'
		);
		add_settings_field(
		    'end_date',
			'End Date',
			array( $this, 'end_date_callback' ),
			'ga-article-audit-admin-settings',
			'setting_section_id'
		);
		add_settings_field(
		    'page_views',
			'Page Views',
			array( $this, 'page_views_callback' ),
			'ga-article-audit-admin-settings',
			'setting_section_id'
		);
		add_settings_field(
		    'sorting_order',
			'Sorting Order( Ascending / Descending )',
			array( $this, 'page_view_order_callback' ),
			'ga-article-audit-admin-settings',
			'setting_section_id'
		);
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
	 
	public function sanitization( $input )
    {
        $new_input = array();

		if( isset( $input['authentication_code'] ) )
            $new_input['authentication_code'] = sanitize_text_field( $input['authentication_code'] );
		
        return $new_input;
    }
	
    public function sanitize( $input )
    {
        $new_input = array();
 
        if( isset( $input['profile_id'] ) )
            $new_input['profile_id'] = sanitize_text_field( $input['profile_id'] );
		
		if( isset( $input['start_date'] ) ){
			$input['start_date'] = date('Y-m-d',strtotime($input['start_date']));
		    $new_input['start_date'] = preg_replace("([^0-9/])", "", $input['start_date']);
		}
		if( isset( $input['end_date'] ) ){
			$input['end_date'] = date('Y-m-d',strtotime($input['end_date']));
		    $new_input['end_date'] = preg_replace("([^0-9/])", "", $input['end_date']); 
		}
		if( isset( $input['page_views'] ) )
		    $new_input['page_views'] = filter_var($input['page_views'], FILTER_SANITIZE_NUMBER_INT);
		
		if( isset( $input['sorting_order'] ) )
			$new_input['sorting_order'] = sanitize_text_field( $input['sorting_order'] );
		
        return $new_input;
    }

    /** 
     * Print the Section text
     */
	public function print_authentication_section()
	{
		print 'Enter Your Authentication Code Below:';
	}
    public function print_section_info()
    {
        print 'Choose Profile And Enter Other Details:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function profile_id_callback()
    { 
		$client = new Google_Client();
		if( isset( $_SESSION['access_token'] ) ){
		    $client->setAccessToken($_SESSION['access_token']);
		} 
		$analytics = new Google_Service_Analytics($client);
		
		$accounts = $analytics->management_accountSummaries->listManagementAccountSummaries();
		
		$profiles = $this->parse_opt_groups($this->format_profile_call($accounts->getItems()));
      
	    $html = '<select id="profile_id" name="ga_article_audit_option_name[profile_id]" value="' . $this->options['profile_id'] . '">' .  '<option value="">Choose a Profile</option>';
		
		foreach($profiles as $profile):
		   foreach($profile as $val) {
			    if(get_option( 'ga_article_audit_option_name' )['profile_id'] == $val[0]['id'] ){
				    $html .= '<option value="'. $val[0]['id'] . '" selected >' . $val[0]['name'] . '</option>';
} else{
				$html .= '<option value="'. $val[0]['id'] . '">' . $val[0]['name'] . '</option>';
}
			}
		endforeach;
		
		$html .= '</select>';

		printf(
			$html, 
		    isset( $this->options['profile_id'] ) ? esc_attr( $this->options['profile_id']) : ''
		);
    }

	public function format_profile_call( &$response ) {
        if ( isset( $response) ){
			$accounts = array();
			foreach ( $response as $item ) {
			
				// Check if webProperties is set
				if ( isset( $item['webProperties'] ) ) {
					$profiles = array();

					foreach ( $item['webProperties'] as $property_key => $property ) {
						$profiles[ $property_key ] = array(
							'id'    => $property['id'],
							'name'  => $property['name'],
							'items' => array(),
						);
			   
						// Check if profiles is set
						if ( isset( $property['profiles'] ) ) {
							foreach ( $property['profiles'] as $key => $profile ) {
								$profiles[ $property_key ]['items'][ $key ] = array_merge(
									get_object_vars($profile),
									array(
										'name'    => $profile['name'] . ' (' . $property['id'] . ')',
										'ua_code' => $property['id'],
									)
								);
							}
						}
					}

					$accounts[ $item['id'] ] = array(
						'id'          => $item['id'],
						'ua_code'     => $property['id'],
						'parent_name' => $item['name'],
						'items'       => $profiles,
					);

				}
			}
			return $accounts;
		} 
		return false;
	}
	
	public function parse_opt_groups(&$values){
		$opt_groups = array();
		foreach( $values as $key=>$value ){
			foreach( $value['items'] as $subitem ){
				$opt_groups[$subitem['name']]['items'] = $subitem['items'];
			}
		}
		return $opt_groups;
	}
	
    public function authentication_code_callback()
    {
        printf(
            '<input type="text" id="authentication_code" name="ga_article_audit_authentication_setting[authentication_code]" value="%s" />',
            isset( $this->option['authentication_code'] ) ? esc_attr( $this->option['authentication_code']) : ''
        );
    }
	
	public function start_date_callback(){
		$start_date = $this->options['start_date'] ? date('m/d/Y', strtotime($this->options['start_date'])) : date("m/d/Y", strtotime("-1 year"));
		
		printf('<input class="alignleft" name="ga_article_audit_option_name[start_date] type="text" id="startdate" value="' . $start_date . '">',
		    isset( $this->options['start_date'] ) ? esc_attr( date('mm/dd/yyyy',strtotime($this->options['start_date']))) : ''
		);
	}
	public function end_date_callback(){ 
	  
	    $end_date = $this->options['end_date'] ? date('m/d/Y', strtotime($this->options['end_date'])) : date("m/d/Y");
		
		printf('<input type="text" name="ga_article_audit_option_name[end_date]"  value="' . $end_date . '" id="enddate">',
		    isset( $this->options['end_date'] ) ? esc_attr( date('mm/dd/yyyy',strtotime($this->options['end_date']))) : ''
		);
	}
	public function page_views_callback(){
		if(get_option( 'ga_article_audit_option_name' )['page_views'] != ''){
			printf ('<input type="number" class="small-text" name="ga_article_audit_option_name[page_views]" id="page_views" min="1" max="100" value="' . get_option( 'ga_article_audit_option_name' )['page_views'] . '" >',
				isset( $this->options['page_views'] ) ? esc_attr( $this->options['page_views']) : ''
			);
		} else{
			printf ('<input type="number" class="small-text" name="ga_article_audit_option_name[page_views]" id="page_views" min="1" max="100" value="10" >',
				isset( $this->options['page_views'] ) ? esc_attr( $this->options['page_views']) : ''
			);
		}
	}
	public function page_view_order_callback(){
		$html = '';
		$html .= '<select id="sorting_order" name="ga_article_audit_option_name[sorting_order]" value="' . get_option( 'ga_article_audit_option_name' )['sorting_order'] . '">';
		if( get_option( 'ga_article_audit_option_name' )['sorting_order'] == 'ascending'  ){
	     	$html .= '<option id="ascending" value="ascending" selected>Ascending</option>';
     	} else {
			$html .= '<option id="ascending" value="ascending">Ascending</option>';
		}
		if( get_option( 'ga_article_audit_option_name' )['sorting_order'] == 'descending'  ){
	     	$html .= '<option id="descending" value="descending" selected>Descending</option>';
     	} else {
			$html .= '<option id="descending" value="descending">Descending</option>';
		}
		$html . '</select>';
		printf ( $html,
		    isset( $this->options['sorting_order'] ) ? esc_attr( $this->options['sorting_order']) : ''
		);
	}
	
	public function ga_article_audit_admin_script(){
		/* Link our already registered script to a page */
		wp_enqueue_script( 'ga-article-audit');
	}
	
	
	public function getFirstprofileId(&$analytics) {
	  // Get the user's first view (profile) ID.

	  // Get the list of accounts for the authorized user.
	  $accounts = $analytics->management_accounts->listManagementAccounts();

	   if (count($accounts->getItems()) > 0) {
		$items = $accounts->getItems();
		$firstAccountId = $items[0]->getId();

		// Get the list of properties for the authorized user.
		$properties = $analytics->management_webproperties
			->listManagementWebproperties($firstAccountId);

		if (count($properties->getItems()) > 0) {
		  $items = $properties->getItems();
		  $firstPropertyId = $items[0]->getId();

		  // Get the list of views (profiles) for the authorized user.
		  $profiles = $analytics->management_profiles
			  ->listManagementProfiles($firstAccountId, $firstPropertyId);

		  if (count($profiles->getItems()) > 0) {
			$items = $profiles->getItems();

			// Return the first view (profile) ID.
			return $items[0]->getId();

		  } else {
			throw new Exception('No views (profiles) found for this user.');
		  }
		} else {
		  throw new Exception('No properties found for this user.');
		}
	  } else {
		throw new Exception('No accounts found for this user.');
	  }
	}

	 public function getResults(&$analytics, &$profileId, &$page = 1) {
	    
		if( isset( get_option( 'ga_article_audit_option_name' )['start_date'] ) && preg_match('~[0-9]~', get_option( 'ga_article_audit_option_name' )['start_date'] ) ){
			$start_date = date('Y-m-d',strtotime(get_option( 'ga_article_audit_option_name' )['start_date']));
		} else {
			$start_date = '7daysAgo';
		}
		
		if( isset( get_option( 'ga_article_audit_option_name' )['end_date'] ) && preg_match('~[0-9]~', get_option( 'ga_article_audit_option_name' )['end_date'] ) ){
			$end_date = date('Y-m-d',strtotime(get_option( 'ga_article_audit_option_name' )['end_date']));
		} else{
			$end_date = 'today';
		}
	
		if( isset( get_option( 'ga_article_audit_option_name' )['sorting_order'] ) && get_option( 'ga_article_audit_option_name' )['sorting_order'] == 'ascending' ){
			$order = 'ga:pageViews';
		} else{
			$order = '-ga:pageViews';
		}
	
		if( isset( get_option( 'ga_article_audit_option_name' )['page_views'] ) && get_option( 'ga_article_audit_option_name' )['page_views'] ){
			$filter = 'ga:pageViews<' . get_option( 'ga_article_audit_option_name' )['page_views'];
		} else{
			$filter = 'ga:pageViews<=10';
		}
		$max_results =  10;
		
	    // Calls the Core Reporting API and queries for the number of sessions
	    return $analytics->data_ga->get(
		  'ga:' . $profileId,
		  $start_date,
		  $end_date,
		  'ga:pageviews,ga:uniquePageviews,ga:avgTimeOnPage,ga:bounceRate,ga:exitRate',
		  
		  //array('dimensions' => 'ga:pagePathLevel1', 'sort' => $order, 'filters' => $filter ) );
		  
		  array('dimensions' => 'ga:PagePath,ga:PagePathLevel1', 'sort' => $order, 'filters' => $filter,'start-index' => 1 + ($page-1)*$max_results,  'max-results' => $max_results ) );
	}


	public function printDataTable(&$results, &$analytics, &$profile) {
	 
	  if (count($results->getRows()) > 0) {
		$table = '';
		$table .= '<table class="wp-list-table widefat fixed striped report-table">';

		// Print headers.
		$table .= '<thead><tr class="header-row"><td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>';

		foreach ($results->getColumnHeaders() as $header) {
		  $table .= '<th scope="col" class="manage-column header ' . $header->name . '">' . $header->name . '</th>';
		}
		$table .= '</tr><tr><td scope="col"></td><td scope="col">Post Name</td><td scope="col">Page Path Level1</td><td scope="col">' . $results->totalsForAllResults['ga:pageViews'] . '</td><td scope="col">' . $results->totalsForAllResults['ga:uniquePageviews'] . '</td><td scope="col">' . sprintf('%02d:%02d:%02d', (round($results->totalsForAllResults['ga:avgTimeOnPage'])/3600),(round($results->totalsForAllResults['ga:avgTimeOnPage'])/60%60), round($results->totalsForAllResults['ga:avgTimeOnPage'])%60) . '</td><td scope="col">' .  round($results->totalsForAllResults['ga:bounceRate'], 2)  . '%</td><td scope="col">' . round($results->totalsForAllResults['ga:exitRate'], 2) . '%</td></tr></thead><tbody>';

		$i = 0;
		// Print table rows.
		foreach ($results->getRows() as $row) {
         
		  $table .= '<tr class="content-row"><th scope="row" class="check-column"><label class="screen-reader-text" for="cb-select-">Select Nunc eget ultricies libero</label>
			<input id="cb-select-'. trim($results->getRows()[$i][1], '/') .'" class="select-post" type="checkbox" name="post[]" value="'. trim($results->getRows()[$i][1], '/') .'">
			<div class="locked-indicator"></div>
		</th>';
			foreach ($row as $key=>$cell) {
				switch( $key ){
					case 0:
					    $postid = url_to_postid( site_url() . htmlspecialchars($cell, ENT_NOQUOTES));
						if(get_post_status($postid) == 'publish' ){
							$status = 'Published';	
						} elseif(get_post_status($postid) == 'draft'){
							$status = 'Draft';	
						} elseif(get_post_status($postid) == 'pending'){
							$status = 'Pending Preview';	
						} elseif(get_post_status($postid) == 'private'){
							$status = 'Private';	
						}else{
							$status = get_post_status($postid);
						}
						
						if( $postid  == '' ){
							$post_title = get_headers(site_url() . htmlspecialchars($cell, ENT_NOQUOTES))[0];
							$table  .= '<td class="has-row-actions column-primary report" id="' . trim(htmlspecialchars($cell, ENT_NOQUOTES), '/') . '">'
									. '<strong><span class="post' . $postid . '"><a href="'. get_edit_post_link($postid) .'">' . $post_title . '</a></span></strong>'
									. '</td>';
						} else {
							$post_title = get_the_title($postid);
							$table  .= '<td class="has-row-actions column-primary report" id="' . trim(htmlspecialchars($cell, ENT_NOQUOTES), '/') . '">'
							        . '<strong><span class="post' . $postid . '"><a href="'. get_edit_post_link($postid) .'">' . $post_title . '</a></span> &mdash; <span class="status">' . $status . '</span></strong>'
							        . '<div class="row-actions"><span class="edit"><a href="'. get_edit_post_link($postid) .'">Edit</a>&#124;</span>'
									. '<span class="draft"><a class="todraft"  id="draft'. $postid .'" onClick="changeStatus(' . $postid . ')" title="draft" href="javascript:void(0)" rel="permalink">Draft</a>&#124;</span>'
									. '<span class="trash"><a class="submitdelete" title="Move this item to the Trash" href="'. get_delete_post_link( $postid, '', false ) .'">Trash</a>&#124;</span>'
									. '<span class="view"><a target="blank" href="'. get_permalink($postid) .'" title="View" rel="permalink">View</a></span></div>'
							        . '</td>';
						}
						break;
					case 1:
					    $table .= '<td class="has-row-actions column-primary report" id="' . trim(htmlspecialchars($cell, ENT_NOQUOTES), '/') . '">'
						. trim(htmlspecialchars($cell, ENT_NOQUOTES), '/')
						. '</td>';
						break;
					case 2:
					    $table .= '<td class="has-row-actions column-primary report">'
						. htmlspecialchars($cell, ENT_NOQUOTES)
						. '</td>';
						break;
					case 4:
					    $table .= '<td class="has-row-actions column-primary report z">'
					   . sprintf('%02d:%02d:%02d', (round((int)htmlspecialchars($cell, ENT_NOQUOTES))/3600),(round((int)htmlspecialchars($cell, ENT_NOQUOTES))/60%60), round((int)htmlspecialchars($cell, ENT_NOQUOTES))%60)
					    . '</td>';
						break;
					case 5:
					case 6:
					    $table .= '<td class="has-row-actions column-primary report y">'
					    . round(floatval(htmlspecialchars($cell, ENT_NOQUOTES)), 2)
					    . '%</td>';
					   break;
					default:
					    $table .= '<td class="has-row-actions column-primary report x">'
					    . htmlspecialchars($cell, ENT_NOQUOTES)
					    . '</td>';
				}
			}
		  $table .= '</tr>';
		  $i++;	
		}
		$table .= '</tbody></table>';
		
        submit_button( 'Send to Draft', 'delete', 'draft', true );
	    
	  } else {
		$table .= '<p>No Results Found.</p>';
	  }
	    print $table;
	}
	
	public  function getPaginationInfo(&$results, &$client, &$profile) {
		
		$table_id = $results->profileInfo->tableId;
		
		if( isset( $this->options['start_date'] ) && preg_match('~[0-9]~', $this->options['start_date'] ) ){
			$start_date = date('Y-m-d',strtotime($this->options['start_date']));
		} else {
			$start_date = '7daysAgo';
		}
		
		if( isset( $this->options['end_date'] ) && preg_match('~[0-9]~', $this->options['end_date'] ) ){
			$end_date = date('Y-m-d',strtotime($this->options['end_date']));
		} else{
			$end_date = 'today';
		}
	
		if( isset( $this->options['sorting_order'] ) && $this->options['sorting_order'] == 'ascending' ){
			$order = 'ga:pageViews';
		} else{
			$order = '-ga:pageViews';
		}
		if( isset( $this->options['page_views'] ) && $this->options['page_views'] ){
			$filter = 'ga:pageViews<' . $this->options['page_views'];
		} else{
			$filter = 'ga:pageViews<=10';
		}
		$max_results =  10;
		
		$accessToken = json_decode($_SESSION['access_token'])->access_token;
		$last_page_start_index = ((ceil($results->getTotalResults()/10)-1)*10)+1;
		$number_of_total_pages = ceil($results->getTotalResults()/10);

		if( $number_of_total_pages > 1 ){
print '<div class="tablenav-pages"><span class="displaying-num">' . $results->getTotalResults() . ' items</span>
<span class="first" aria-hidden="true">&laquo;</span><span class="prev tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>
<span class="screen-reader-text">Current Page</span><span id="table-paging" class="paging-input">' . 1 . ' </span> of <span id="total-pages" class="total-pages">' . $number_of_total_pages . '</span>
<a class="next-page" href="#"><span class="screen-reader-text">Next page</span><span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span></a><a class="last-page" href="#"><span class="screen-reader-text">Last page</span><span aria-hidden="true">&raquo;</span></a></span></div>';
		}
?>
<script>
accessToken = '<?php echo  $accessToken; ?>';
</script>
<?php
    }

	public function get_next_page(){
		if($_GET['whatever'] ){
			$client = new Google_Client();
			$client->setAccessToken($_SESSION['access_token']);
			$analytics = new Google_Service_Analytics($client);
			$profile = $this->options['profile_id'] ? $this->options['profile_id'] : $this->getFirstProfileId($analytics);
			$results = $this->getResults( $analytics, $profile, $_GET['page_number'] );
			$i = 0;
			
			if (count($results->getRows()) > 0) {
				foreach ($results->getRows() as $row) {
					foreach ($row as $key=>$cell) {
						switch( $key ){
							case 0:
								$postid = url_to_postid( site_url() . htmlspecialchars($cell, ENT_NOQUOTES));
								if(get_post_status($postid) == 'publish' ){
									$status = 'Published';	
								} elseif(get_post_status($postid) == 'draft'){
									$status = 'Draft';	
								} elseif(get_post_status($postid) == 'pending'){
									$status = 'Pending Preview';	
								} elseif(get_post_status($postid) == 'private'){
									$status = 'Private';	
								}else{
									$status = get_post_status($postid);
								}
					
								if( $postid  == '' ){
									$post_title = get_headers(site_url() . htmlspecialchars($cell, ENT_NOQUOTES))[0];
									$results->rows[$i][0] =  '<strong><span class="post' . $postid . '"><a href="'. get_edit_post_link($postid) .'">' . $post_title . '</a></span></strong>';
									
								} else {
									$post_title = get_the_title($postid);
									$results->rows[$i][0] =  '<strong><span class="post' . $postid . '"><a href="'. get_edit_post_link($postid) .'">' . get_the_title($postid) . '</a></span> &nbsp;&mdash;&nbsp;  <span class="status">' . $status . '</span></strong><div class="row-actions"><span class="edit"><a href="'. get_edit_post_link($postid) .'">Edit</a>&#124;</span><span class="draft"><a class="todraft"  id="draft'. $postid .'" onClick="changeStatus(' . $postid . ')" title="draft" href="javascript:void(0)" title="draft" rel="permalink">Draft</a>&#124;</span><span class="trash"><a class="submitdelete" title="Move this item to the Trash" href="'. get_delete_post_link( $postid, '', false ) .'">Trash</a>&#124;</span><span class="view"><a target="blank" href="'. get_permalink($postid) .'" title="View “test2”" rel="permalink">View</a></span></div>';
								}
								
								break;
						   case 1:
								$results->rows[$i][1] =  trim(htmlspecialchars($cell, ENT_NOQUOTES), '/');
								break;
							case 2:
								$results->rows[$i][2] = htmlspecialchars($cell, ENT_NOQUOTES);
								break;
							case 3:
								$results->rows[$i][3] = htmlspecialchars($cell, ENT_NOQUOTES);
								break;
							case 4:
								$results->rows[$i][4] = sprintf('%02d:%02d:%02d', (round((int)htmlspecialchars($cell, ENT_NOQUOTES))/3600),(round((int)htmlspecialchars($cell, ENT_NOQUOTES))/60%60), round((int)htmlspecialchars($cell, ENT_NOQUOTES))%60);
								break;
							case 5:
								$results->rows[$i][5] = round(floatval(htmlspecialchars($cell, ENT_NOQUOTES)), 2);
								break;
							case 6:
							   $results->rows[$i][6] = round(floatval(htmlspecialchars($cell, ENT_NOQUOTES)), 2);
							   break;
							default:
							   break;
						}
					}
					$i++;
				}
			}
			echo json_encode($results);
			exit;
		} else {
			return null;
		}
	}
	public function post_status_update(){
		
		global $wpdb;	
        if( isset($_POST['whatever'] )){
			if(isset( $_POST['selected_posts'] )){
				$selected_posts = $_POST['selected_posts'];
			}
		    //var_dump($_POST['selected_posts']);
		    $draft_posts = array();
			foreach( $selected_posts as $selected_post ){
				  $my_post = array(
					  'ID'           => trim($selected_post, 'post'),
					  'post_status' => 'draft',
				  );

				// Update the post into the database
				  $draft_posts[] = wp_update_post( $my_post );
				  
			}
			//echo count($draft_posts);
			if( isset( $draft_posts ) ){
				global $post;
				$slugs = array();
				foreach($draft_posts as $draft_post){
					$post = get_post( $draft_post );
					$slugs[$draft_post] = $post->post_name;
				}
				//echo count($slugs);
				//exit;
				echo json_encode($slugs);
			}
			exit;
	    } else {
		   echo 'Not Working !!';exit;
	    }
	}
	
	public function ga_article_audit_install(){

		global $wpdb;
		global $ga_article_audit_db_version;	
		
        delete_option( 'ga_article_audit_tokens' );
		delete_option( 'ga_article_audit_option_name' );
		delete_option( 'ga_article_audit_authentication_setting' );
		add_option( 'ga_article_audit_db_version', $ga_article_audit_db_version );
	}
	
	public static function ga_article_audit_uninstall()
    {
		delete_option( 'ga_article_audit_tokens' );
		delete_option( 'ga_article_audit_option_name' );
		delete_option( 'ga_article_audit_authentication_setting' );
		
    }

	public function popup_script() {
		wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ) );
		wp_enqueue_script( 'popup', plugin_dir_url( __FILE__ ) . 'js/popup.js', array( 'jquery' ));
		wp_register_style('jquery-ui', plugin_dir_url( __FILE__ ) . 'style/jquery-ui.css');
        wp_enqueue_style( 'jquery-ui' ); 
   }
}

if( is_admin() ){
    $ga_article_audit = new GA_Article_Audit();
	register_uninstall_hook(__FILE__, array( 'GA_Article_Audit', 'ga_article_audit_uninstall' ));
}
