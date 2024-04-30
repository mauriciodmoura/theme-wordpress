/*
Plugin Name: Magic Table
Plugin URI: https://example.com/magic-table
Description: A plugin that creates a custom table, provides shortcodes for form submission and data display, and extends the WordPress REST API.
Version: 1.0
Requires at least: 5.8
Requires PHP: 7.2
Author: MAURICIO DE MOURA
Author URI: https://example.com
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Update URI: https://example.com/magic-table
Text Domain: magic-table
Domain Path: /languages
*/

add_action( 'init', 'my_init' );

function my_init() {
    maybe_create_my_table();
    add_shortcode( 'my_form', 'my_shortcode_form' );
    add_shortcode( 'my_list', 'my_shortcode_list' );
    add_action( 'rest_api_init', 'my_table_rest_api_init' );
}

function maybe_create_my_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_table';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    )";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

function my_shortcode_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['thing_name'])) {
        $thing_name = sanitize_text_field($_POST['thing_name']);
        insert_data_to_my_table($thing_name);
    }
    ?>
    <form method="POST">
        <label for="thing_name">Thing's Name:</label>
        <input type="text" id="thing_name" name="thing_name">
        <button type="submit">Submit</button>
    </form>
    <?php
}

function get_my_table_data($page = 1, $per_page = 10, $orderby = 'id', $order = 'ASC', $search = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_table';
    $search = sanitize_text_field($search);
    $orderby = sanitize_sql_orderby($orderby);
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    $page = absint($page);
    $per_page = absint($per_page);
    $query = "SELECT * FROM $table_name";
    if (!empty($search)) {
        $query .= $wpdb->prepare(" WHERE name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    }
    $query .= " ORDER BY $orderby $order LIMIT %d OFFSET %d";
    return $wpdb->get_results($wpdb->prepare($query, $per_page, ($page - 1) * $per_page));
}

function my_shortcode_list() {
    $data = get_my_table_data();
    ?>
    <table>
        <thead>
            <tr>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $item) : ?>
                <tr>
                    <td><?php echo esc_html($item->name); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $search_form = '<form method="GET">
    <label for="search">Search:</label>
    <input type="search" id="search" name="search">
    <button type="submit">Search</button>
    </form>';
    echo $search_form;
}

function insert_data_to_my_table( $name ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_table';
    $wpdb->insert( $table_name, array( 'name' => $name ), array( '%s' ) );
}

function my_table_rest_api_init() {
    register_rest_route( 'my-table/v1', '/insert', array(
        'methods' => 'POST',
        'callback' => 'my_table_rest_insert',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
    ) );
    register_rest_route( 'my-table/v1', '/select', array(
        'methods' => 'GET',
        'callback' => 'my_table_rest_select',
        'permission_callback' => function () {
            return current_user_can( 'read_posts' );
        },
    ) );
}

function my_table_rest_insert( WP_REST_Request $request ) {
    $name = $request->get_param( 'name' );
    insert_data_to_my_table( $name );
    return rest_ensure_response( array( 'message' => 'Data inserted successfully' ) );
}

function my_table_rest_select( WP_REST_Request $request ) {
    $page = absint($request->get_param( 'page' ));
    $per_page = absint($request->get_param( 'per_page' ));
    $orderby = $request->get_param( 'orderby' );
    $order = $request->get_param( 'order' );
    $search = $request->get_param( 'search' );
    $data = get_my_table_data( $page, $per_page, $orderby, $order, $search );
    return rest_ensure_response( $data );
}
