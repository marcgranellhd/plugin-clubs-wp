<?php
/**
 * Plugin Name: Club Listing Manager
 * Description: Permite a los usuarios listar clubs y a los administradores crear artículos automáticamente en el portafolio desde la lista de solicitudes.
 * Version: 3.0.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar Bootstrap
function clm_enqueue_scripts() {
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), null, true);
    
    // Estilos personalizados
    wp_add_inline_style('bootstrap-css', '
        .club-form {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .club-form .form-group {
            margin-bottom: 1.5rem;
        }
        .admin-club-details {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .club-logo img, .club-image img {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    ');
}
add_action('wp_enqueue_scripts', 'clm_enqueue_scripts');
add_action('admin_enqueue_scripts', 'clm_enqueue_scripts');

// Activar tabla personalizada al activar el plugin
register_activation_hook(__FILE__, 'clm_create_table');
function clm_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'club_listings';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        club_name VARCHAR(255) NOT NULL,
        short_description VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        logo_url VARCHAR(255),
        image_url VARCHAR(255),
        gallery_images TEXT,
        location VARCHAR(255) NOT NULL,
        opening_hours VARCHAR(255) NOT NULL,
        age_limit VARCHAR(10) NOT NULL,
        pet_friendly VARCHAR(10) NOT NULL,
        website_url VARCHAR(255),
        contact_phone VARCHAR(20),
        social_media VARCHAR(255),
        founded_date VARCHAR(20),
        contact_email VARCHAR(255) NOT NULL,
        portfolio_post_id BIGINT(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->last_error) {
        error_log('Error creating table: ' . $wpdb->last_error);
        return false;
    }

    // Verify if the column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'gallery_images'");
    if (empty($column_exists)) {
        // Add the column if it doesn't exist
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN gallery_images TEXT AFTER image_url");
    }

    return true;
}

// Asegurarse de que la tabla se crea al activar el plugin
register_activation_hook(__FILE__, 'clm_create_table');

// También crear la tabla si no existe cuando se accede a la página de administración
add_action('admin_init', 'clm_check_table');
function clm_check_table() {
    if (isset($_GET['page']) && $_GET['page'] === 'club-listings') {
        clm_create_table();
    }
}

// Mostrar formulario mediante shortcode
add_shortcode('club_listing_form', 'clm_display_form');
function clm_display_form() {
    ob_start();
    ?>
    <div class="club-form">
        <form method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
            <h2 class="mb-4 text-center">Formulario de Inscripción del Club</h2>

            <div class="form-group mb-3">
                <label class="form-label">Nombre del Club</label>
                <input type="text" name="club_name" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Descripción Corta</label>
                <input type="text" name="short_description" class="form-control" maxlength="255" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Descripción Completa</label>
                <textarea name="description" class="form-control" rows="4" required></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Logo del Club</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Imágenes del Club</label>
                        <input type="file" name="gallery_images[]" class="form-control" accept="image/*" multiple required>
                        <small class="form-text text-muted">Puedes seleccionar múltiples imágenes manteniendo presionada la tecla Ctrl</small>
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Localización Exacta</label>
                <input type="text" name="location" class="form-control" required>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label class="form-label">Horario de Apertura</label>
                        <input type="text" name="opening_hours" class="form-control" placeholder="Ej: Lunes a Domingo 10:00 - 23:00" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label class="form-label">Edad Mínima</label>
                        <select name="age_limit" class="form-select" required>
                            <option value="+18">+18</option>
                            <option value="+21">+21</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label class="form-label">¿Pet Friendly?</label>
                        <select name="pet_friendly" class="form-select" required>
                            <option value="Sí">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Página Web <small>(opcional)</small></label>
                        <input type="url" name="website_url" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Teléfono <small>(opcional)</small></label>
                        <input type="tel" name="contact_phone" class="form-control">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Redes Sociales <small>(opcional)</small></label>
                        <input type="url" name="social_media" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label class="form-label">Fecha de Fundación <small>(opcional)</small></label>
                        <input type="date" name="founded_date" class="form-control">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="form-label">Correo Electrónico de Contacto</label>
                <input type="email" name="contact_email" class="form-control" required>
            </div>

            <div class="text-center">
                <button type="submit" name="submit_club" class="btn btn-primary btn-lg px-5">Enviar Solicitud</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Guardar formulario en la base de datos
add_action('init', 'clm_handle_form_submission');
function clm_handle_form_submission() {
    if (isset($_POST['submit_club'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'club_listings';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Table does not exist: ' . $table_name);
            clm_create_table(); // Intentar crear la tabla si no existe
        }

        // Validar y procesar archivos
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Procesar logo
        $logo_url = '';
        if (!empty($_FILES['logo']['name'])) {
            $uploadedfile = $_FILES['logo'];
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $logo_url = $movefile['url'];
            } else {
                error_log('Logo upload error: ' . $movefile['error']);
                wp_die('Error al subir el logo: ' . $movefile['error']);
            }
        }

        // Procesar imágenes de galería
        $gallery_urls = array();
        if (!empty($_FILES['gallery_images']['name'][0])) {
            foreach ($_FILES['gallery_images']['name'] as $key => $value) {
                if ($_FILES['gallery_images']['error'][$key] === 0) {
                    $file = array(
                        'name'     => $_FILES['gallery_images']['name'][$key],
                        'type'     => $_FILES['gallery_images']['type'][$key],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                        'error'    => $_FILES['gallery_images']['error'][$key],
                        'size'     => $_FILES['gallery_images']['size'][$key]
                    );
                    
                    $upload = wp_handle_upload($file, $upload_overrides);
                    if (!isset($upload['error'])) {
                        $gallery_urls[] = $upload['url'];
                    } else {
                        error_log('Gallery image upload error: ' . $upload['error']);
                    }
                }
            }
        }

        $gallery_images_string = implode(',', $gallery_urls);

        // Preparar datos para inserción
        $data = array(
            'club_name' => sanitize_text_field($_POST['club_name']),
            'short_description' => sanitize_text_field($_POST['short_description']),
            'description' => wp_kses_post($_POST['description']),
            'logo_url' => esc_url($logo_url),
            'gallery_images' => $gallery_images_string,
            'location' => sanitize_text_field($_POST['location']),
            'opening_hours' => sanitize_text_field($_POST['opening_hours']),
            'age_limit' => sanitize_text_field($_POST['age_limit']),
            'pet_friendly' => sanitize_text_field($_POST['pet_friendly']),
            'website_url' => esc_url($_POST['website_url']),
            'contact_phone' => sanitize_text_field($_POST['contact_phone']),
            'social_media' => esc_url($_POST['social_media']),
            'founded_date' => sanitize_text_field($_POST['founded_date']),
            'contact_email' => sanitize_email($_POST['contact_email']),
        );

        // Insertar en la base de datos
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            error_log('Database error: ' . $wpdb->last_error);
            wp_die('Error al guardar en la base de datos: ' . $wpdb->last_error);
        }

        $insert_id = $wpdb->insert_id;
        error_log('Club inserted successfully. ID: ' . $insert_id);

        // Redirigir a la página de agradecimiento
        wp_redirect(add_query_arg('success', '1', home_url('/gracias')));
        exit;
    }
}

// Agregar página de administración
add_action('admin_menu', 'clm_admin_menu');
function clm_admin_menu() {
    add_menu_page(
        'Gestión de Clubs',
        'Clubs',
        'manage_options',
        'club-listings',
        'clm_admin_page',
        'dashicons-groups',
        20
    );
}

// Incluir la página de administración
require_once plugin_dir_path(__FILE__) . 'admin-page.php';