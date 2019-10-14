<?php
/**
 * Plugin Name: TomTom Store Locator
 * Description: A store locator plugin based on TomTom maps.
 */

/**
 * Runs on activation to create database table if it doesn't exist.
 */
function ttlocator_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . "tomtom_locator_locations";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name text,
      address text,
      city text,
      state tinytext,
      country text,
      postcode tinytext,
      latitude decimal(10,6),
      longitude decimal(10,6),
      PRIMARY KEY(id)
    ) $charset_collate;";

    require_once(ABSPATH . "wp-admin/includes/upgrade.php");
    dbDelta( $sql );
}

register_activation_hook(__FILE__, 'ttlocator_install');

/**
 * Gets a list of all registered locations from the database
 */
function ttlocator_get_locations() {
    global $wpdb;
	$table_name = $wpdb->prefix . "tomtom_locator_locations";

	$results = $wpdb->get_results("select * from $table_name");

	return $results;
}

/**
 * Receives AJAX call to add a new location to the database
 */
function ttlocator_add_location() {
    if (!is_admin()) wp_die();
    global $wpdb;
	$table_name = $wpdb->prefix . "tomtom_locator_locations";

    $name = wp_strip_all_tags($_POST["name"]);
    $address = wp_strip_all_tags($_POST["address"]);
    $city = wp_strip_all_tags($_POST["city"]);
    $state = wp_strip_all_tags($_POST["state"]);
    $country = wp_strip_all_tags($_POST["country"]);
    $postcode = wp_strip_all_tags($_POST["postcode"]);
    $latitude = wp_strip_all_tags($_POST["latitude"]);
    $longitude = wp_strip_all_tags($_POST["longitude"]);

    $success = $wpdb->query($wpdb->prepare("
        INSERT INTO $table_name (
          name, 
          address, 
          city, 
          state,
          country,
          postcode,
          latitude, 
          longitude
        )
        VALUES (%s, %s, %s, %s, %s, %s, %f, %f);
    ", array($name, $address, $city, $state, $country, $postcode, $latitude, $longitude)));

    if(!$success) {
        status_header(500);
    }

    wp_die();
}

function ttlocator_delete_locations() {
    if (!is_admin()) {
        status_header(401);
        wp_die();
    }

	global $wpdb;
	$table_name = $wpdb->prefix . "tomtom_locator_locations";
    $locations_string = wp_strip_all_tags($_POST["ids"]);
    $location_ids = array_map('intval', explode(',', $locations_string));

    $success = true;
    foreach($location_ids as $id) {
        $query_succeeded = $wpdb->query(
            $wpdb->prepare( "DELETE FROM $table_name where id = %d;", $id ));
        if(!$query_succeeded)
            $success = false;
    }

    if(!$success) {
        status_header(500);
    }
    wp_die();
}

/**
 * Generates the 'Add Store' portion of the map page.
 */
function ttlocator_add_store_html() {
    ?>
    <div class="ttlocator-add-store-page">
        <h2>Add Store</h2>
        <p>
            Start by adding a store name and address, then click 'Lookup' to see the new store on the map.
            A street address plus the city and state/province is usually enough.
        </p>
        <p>
            If you're happy with the address marker that pops up on the map, click 'Save'.
            If not, add more detail to the address and then click 'Lookup' again to refine your search.
        </p>
        <div class="ttlocator-row">
            <div class="ttlocator-field-label">
                <label for="store-name">Store Name</label>
            </div>
            <div class="ttlocator-text-field">
                <input type="text" name="store-name" style="width: 100%"/>
            </div>
        </div>
        <div class="ttlocator-row">
            <div class="ttlocator-field-label">
                <label for="store-address">Store Address</label>
            </div>
            <div class="ttlocator-text-field">
                <input type="text" name="store-address" style="width: 100%"/>
            </div>
            <div class="ttlocator-field-button">
                <button class="button button-primary ttlocator-lookup-button">
                    Lookup
                </button>
            </div>
        </div>
        <div class="ttlocator-row ttlocator-lookup-message-area">
            <p id="ttlocator-store-lookup-messages"></p>
        </div>
        <div class="ttlocator-row">
            <button class="button ttlocator-add-store-cancel">Cancel</button>
            <div class="ttlocator-add-store-save"><button class="button button-primary">Save</button></div>
        </div>
    </div>
    <?php
}

/**
 * Generates the table of store locations.
 * @param $locations array An array of store locations.
 */
function ttlocator_store_table_html($locations) {
    ?>
    <table id="ttlocator-stores-table" class="wp-list-table striped widefat fixed">
        <thead>
            <tr>
                <th class="ttlocator-table-check">
                    <input type="checkbox" id="ttlocator-select-all"/>
                </th>
                <th>Name</th>
                <th>Address</th>
                <th>City</th>
                <th>State/Province</th>
                <th>Post/Zip Code</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($locations as $location): ?>
            <tr>
                <td class="ttlocator-table-check">
                    <input type="checkbox"
                           class="ttlocator-select-check"
                           data-id="<?php echo esc_attr($location->id); ?>" />
                </td>
                <td><?php echo esc_attr($location->name); ?></td>
                <td><?php echo esc_attr($location->address); ?></td>
                <td><?php echo esc_attr($location->city); ?></td>
                <td><?php echo esc_attr($location->state); ?></td>
                <td><?php echo esc_attr($location->postcode); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Generates the HTML for the popup deletion confirmation dialog
 */
function ttlocator_delete_location_confirm_dialog_html() {
    ?>
    <div id="delete-confirm" class="hidden" style="max-width:800px">
        <h3>Are you sure?</h3>
        <div class="ttlocator-delete-text">
            <p>You're about to delete <span id="store-deletion-count"></span> store(s).</p>
            <p>Are you <b>sure</b> you want to do this?</p>
        </div>
        <div class="ttlocator-row ttlocator-perm-delete-buttons">
            <button id="ttlocator-perm-delete" class="button button-primary">
                Permanently Delete
            </button>
            <button id="ttlocator-perm-delete-cancel" class="button">
                Cancel
            </button>
        </div>
    </div>
    <?php
}

/**
 * Define the HTML that will be displayed on the plugin's admin page.
 */
function ttlocator_config_page_html() {
    $plugin_directory = plugin_dir_url(__FILE__);
    $stylesheet = $plugin_directory . "styles/styles.css";
    $locator_js = $plugin_directory . "scripts/locator.js";
    $tomtom_sdk_dir = $plugin_directory . "tomtom-sdk/";
    $map_stylesheet = $tomtom_sdk_dir . "map.css";
    $tomtom_js = $tomtom_sdk_dir . "tomtom.min.js";
    $locations = ttlocator_get_locations();

	wp_enqueue_script("jquery");
    wp_enqueue_style("ttlocator-styles", $stylesheet);
    wp_enqueue_style("ttlocator-tomtom-map-styles", $map_stylesheet);
    wp_enqueue_script("ttlocator-tomtom-sdk", $tomtom_js);
	wp_enqueue_script("ttlocator-locator-page-script",
        $locator_js, array(), false, true);

	// add jQuery UI dialogs for deletion confirmation
	wp_enqueue_script( 'jquery-ui-dialog' ); //
	wp_enqueue_style( 'wp-jquery-ui-dialog' );

	?>
    <div class="ttlocator_locator_page">
    <h1>TomTom Store Locator</h1>
    <div id='map' style='height:500px;width:95%'></div>
    <script>
        var tomtomSdkKey = '<?php echo esc_attr(get_option("tomtom_locator_api_key")); ?>';
        var tomtomSdkPath = '<?php echo $tomtom_sdk_dir; ?>';
        var storeLocations = <?php echo json_encode($locations); ?>;
    </script>
    <div class="ttlocator-stores-table">
    <h2>Stores</h2>
	<?php if (sizeof($locations) === 0): ?>
        <p>
            You haven't added any stores. Would you like to add one now?
        </p>
	<?php
    else:
        echo ttlocator_store_table_html($locations);
	endif; ?>
        <div class="ttlocator-add-store-button">
            <button class="button button-primary ttlocator-add-store">
                Add Store
            </button>
            <button id="ttlocator-delete-selected"
                    class="button bu">
                Delete Selected
            </button>
        </div>
	</div>
    </div>
    <?php
    echo ttlocator_add_store_html();
    echo ttlocator_delete_location_confirm_dialog_html();
}

/**
 * Generate HTML for the SDK setup page
 */
function ttlocator_sdk_setup_page_html() {
	$plugin_directory = plugin_dir_url(__FILE__);
	$stylesheet = $plugin_directory . "styles/styles.css";
	?>
    <link href="<?php echo $stylesheet ?>" rel="stylesheet">
    <h1>TomTom SDK Setup</h1>
    <h2>SDK Key</h2>
    <p>Please enter your TomTom API key:</p>
    <form method="post" action="options.php">
	    <?php settings_fields( 'tomtom-map-plugin-sdk-setup' ); ?>
	    <?php do_settings_sections( 'tomtom-map-plugin-sdk-setup' ); ?>
        <input type="text"
               class="ttlocator-sdk-key-input"
               name="tomtom_locator_api_key"
               value="<?php echo esc_attr(get_option("tomtom_locator_api_key")) ?>" />
        <?php submit_button() ?>
    </form>
	<?php
}

/**
 * Call add_submenu_page to add our plugin's admin page to the WordPress Settings menu.
 */
function ttlocator_pages_init() {
	add_menu_page( "TomTom Map Configuration",
		"TomTom Store Locator",
		"manage_options",
		"tomtom-map-plugin");

	add_submenu_page("tomtom-map-plugin",
        "TomTom Store Locations",
        "Store Locations",
        "manage_options",
        "tomtom-map-plugin",
        "ttlocator_config_page_html");

	add_submenu_page("tomtom-map-plugin",
        "TomTom SDK Setup",
        "SDK Setup",
        "manage_options",
        "tomtom-map-plugin-sdk-setup",
        "ttlocator_sdk_setup_page_html");

	if (is_admin()) {
	    add_action('admin_init', 'ttlocator_register_settings');
    }
}

function ttlocator_register_settings() {
    register_setting('tomtom-map-plugin-sdk-setup', 'tomtom_locator_api_keytomtom_locator_api_key');
}

add_action('admin_menu', 'ttlocator_pages_init' );
add_action('wp_ajax_ttlocator_add_location', 'ttlocator_add_location');
add_action('wp_ajax_ttlocator_delete_locations', 'ttlocator_delete_locations');