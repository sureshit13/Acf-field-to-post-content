<?php
/*
Plugin Name: ACF Fields Viewer and Updater
Description: Select a post type and get all post-wise ACF fields and values, with the ability to update them and append non-empty values to the post content, then clear the ACF fields.
Version: 1.4
Author: Suresh Dutt
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue custom stylesheet
function afvu_enqueue_styles() {
    wp_enqueue_style('custom-style-css', plugin_dir_url(__FILE__) . 'css/custom-style.css');
    wp_enqueue_script('custom-script', plugin_dir_url(__FILE__) . 'js/custom.js');
}
add_action('admin_enqueue_scripts', 'afvu_enqueue_styles');

// Add admin menu
add_action('admin_menu', 'acf_fields_viewer_menu');

function acf_fields_viewer_menu() {
    add_menu_page(
        'ACF Fields Viewer', 
        'ACF Fields Viewer', 
        'manage_options', 
        'acf-fields-viewer', 
        'acf_fields_viewer_page'
    );
}

// Display the plugin page
function acf_fields_viewer_page() {
    $success = false;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_type'])) {
        $selected_post_type = sanitize_text_field($_POST['post_type']);
        if (isset($_POST['update_acf_fields'])) {
            // Handle updating of ACF fields
            update_acf_fields($selected_post_type);
            $success = true;
        }
    }
    
    ?>
    <?php if ($success): ?>
        <div id="message" class="updated notice notice-success is-dismissible">
            <p><?php _e('ACF fields updated successfully.', 'acf-fields-viewer'); ?></p>
        </div>
    <?php endif; ?>
    <div class="wrap">
        <form id="submit_post_type" method="POST" action="">
            <div class="form-floating">    
                <select name="post_type" id="post_type" class="form-select" required>
                    <option value="">Select Post Type</option>
                    <?php
                    // Get all public post types, including custom and default ones
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type) {
                        $selected = (isset($_POST['post_type']) && $_POST['post_type'] == $post_type->name) ? 'selected' : '';
                        echo '<option value="' . esc_attr($post_type->name) . '" ' . $selected . '>' . esc_html($post_type->label) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <?php submit_button('Submit Post Type'); ?>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_type'])) {
            display_acf_fields($selected_post_type);
            ?>
            <script>
                document.getElementById('submit_post_type').style.display = 'none';
                document.getElementById('update_post_type').style.display = 'block';
            </script>
            <?php
        }else{?>
            <script>
                document.getElementById('update_post_type').style.display = 'none';
                document.getElementById('submit_post_type').style.display = 'block';
            </script>
        <?php }
        ?>
    </div>
    <?php
}

// Function to display ACF fields for the selected post type
function display_acf_fields($post_type) {
    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => -1 // Fetch all posts
    );

    $posts = new WP_Query($args);

    if ($posts->have_posts()) {
        ?>
        <form id="update_post_type" method="POST" action="" style="display:none;">
            <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
            <input type="hidden" name="update_acf_fields" value="1">
            <?php
            while ($posts->have_posts()) {
                $posts->the_post();
                $post_id = get_the_ID();
                $fields = get_fields($post_id);

                echo '<div class="post-content">';
                // echo '<h2>' . get_the_title($post_id) . '</h2>';
                // echo '<div>' . get_the_content(null, false, $post_id) . '</div>';
                //echo '<pre>';
                //print_r($fields);
                //echo '</pre>';
                // Handle ACF fields
                if ($fields) {
                    echo '<h3>Click on button to update and move acf field value into post content.</h3>';
                    //echo '<ul>';
                    foreach ($fields as $field_name => $field_value) {
                        if (!empty($field_value)) {
                            //echo '<li><strong>' . esc_html($field_name) . ':</strong> ';
                            if (is_array($field_value)) {
                                $field_value = json_encode($field_value);
                            }
                            echo '<input type="hidden" name="acf_fields[' . $post_id . '][' . esc_attr($field_name) . ']" value="' . esc_attr($field_value) . '">';
                            //echo '</li>';
                        }
                    }
                    //echo '</ul>';
                }

                echo '</div>';
            }
            ?>
            <?php submit_button('Update All ACF Fields'); ?>
        </form>
        <?php
    } else {
        echo '<p>No posts found for the selected post type.</p>';
    }

    wp_reset_postdata();
}

// Function to update ACF fields, append non-empty values to post content, and clear the fields
function update_acf_fields($post_type) {
    if (isset($_POST['acf_fields']) && is_array($_POST['acf_fields'])) {
        foreach ($_POST['acf_fields'] as $post_id => $fields) {
            $acf_content = '';

            foreach ($fields as $field_name => $field_value) {
                if (!empty($field_value)) {
                    if (is_array(json_decode($field_value, true))) {
                        $field_value = json_decode($field_value, true);
                    }
                    update_field($field_name, $field_value, $post_id);

                    // Append ACF field value to the post content
                    // $acf_content .= '<p><strong>' . esc_html($field_name) . ':</strong> ' . esc_html(is_array($field_value) ? json_encode($field_value) : $field_value) . '</p>';
                    $acf_content .= esc_html(is_array($field_value) ? json_encode($field_value) : $field_value) . '<br>';
                }
            }

            // Append ACF fields content to post content
            $existing_content = get_post_field('post_content', $post_id);
            $updated_content = $existing_content . $acf_content;

            // Update the post content
            $post_data = array(
                'ID'           => $post_id,
                'post_content' => $updated_content
            );
            wp_update_post($post_data);

            // Clear the ACF fields
            foreach ($fields as $field_name => $field_value) {
                if (!empty($field_value)) {
                    update_field($field_name, '', $post_id);
                }
            }
        }
    }
}
?>
