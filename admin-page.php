<?php
if (!defined('ABSPATH')) {
    exit;
}

function clm_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'club_listings';

    // Add delete handling code
    if (isset($_GET['delete_id']) && current_user_can('manage_options')) {
        $delete_id = intval($_GET['delete_id']);
        
        // Get portfolio post ID before deletion
        $club = $wpdb->get_row($wpdb->prepare("SELECT portfolio_post_id FROM $table_name WHERE id = %d", $delete_id));
        
        // Delete the portfolio post if it exists
        if (!empty($club->portfolio_post_id)) {
            wp_delete_post($club->portfolio_post_id, true);
        }
        
        // Delete from custom table
        $wpdb->delete(
            $table_name,
            ['id' => $delete_id],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>Club eliminado correctamente.</p></div>';
    }

    // Check if viewing details
    if (isset($_GET['view_id'])) {
        $club_id = intval($_GET['view_id']);
        $club = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $club_id));
        if ($club) {
            clm_display_club_details($club);
            return;
        }
    }

    // Crear publicación de Portfolio si se hace clic en Importar
    if (isset($_GET['import_id'])) {
        $club_id = intval($_GET['import_id']);
        $club = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $club_id));

        if ($club && empty($club->portfolio_post_id)) {
            $post_id = wp_insert_post([
                'post_title'   => sanitize_text_field($club->club_name),
                'post_content' => wp_kses_post(
                    "<strong>Descripción:</strong> {$club->description}<br>
                    <strong>Localización:</strong> {$club->location}<br>
                    <strong>Horario:</strong> {$club->opening_hours}<br>
                    <strong>Edad mínima:</strong> {$club->age_limit}<br>
                    <strong>Pet Friendly:</strong> {$club->pet_friendly}<br>
                    <strong>Web:</strong> <a href='{$club->website_url}'>{$club->website_url}</a><br>
                    <strong>Teléfono:</strong> {$club->contact_phone}<br>
                    <strong>Redes Sociales:</strong> <a href='{$club->social_media}'>Ver perfil</a><br>
                    <strong>Fundado en:</strong> {$club->founded_date}<br>
                    <strong>Contacto:</strong> {$club->contact_email}<br>"
                ),
                'post_status'  => 'publish',
                'post_type'    => 'portfolio',
            ]);

            // Guardar metadatos
            if ($post_id) {
                update_post_meta($post_id, '_club_logo', esc_url($club->logo_url));
                
                // Manejar múltiples imágenes
                $gallery_images = !empty($club->gallery_images) ? $club->gallery_images : $club->image_url;
                update_post_meta($post_id, '_club_gallery_images', $gallery_images);
                
                update_post_meta($post_id, '_club_location', sanitize_text_field($club->location));
                update_post_meta($post_id, '_club_opening_hours', sanitize_text_field($club->opening_hours));
                update_post_meta($post_id, '_club_age_limit', sanitize_text_field($club->age_limit));
                update_post_meta($post_id, '_club_pet_friendly', sanitize_text_field($club->pet_friendly));
                update_post_meta($post_id, '_club_website', esc_url($club->website_url));
                update_post_meta($post_id, '_club_phone', sanitize_text_field($club->contact_phone));
                update_post_meta($post_id, '_club_social', esc_url($club->social_media));
                update_post_meta($post_id, '_club_founded_date', sanitize_text_field($club->founded_date));
                update_post_meta($post_id, '_club_contact_email', sanitize_email($club->contact_email));
            }

            // Actualizar en la tabla que se creó el portfolio
            $wpdb->update(
                $table_name,
                ['portfolio_post_id' => $post_id],
                ['id' => $club_id]
            );

            echo '<div class="notice notice-success"><p>Publicación en Portfolio creada correctamente.</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>Esta solicitud ya tiene una publicación en el portfolio.</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>Solicitudes de Clubs</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Logo</th>
                    <th>Email</th>
                    <th>Fecha</th>
                    <th>Portfolio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $clubs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                if ($clubs) :
                    foreach ($clubs as $club) :
                ?>
                        <tr>
                            <td><?php echo esc_html($club->id); ?></td>
                            <td><?php echo esc_html($club->club_name); ?></td>
                            <td>
                                <?php if (!empty($club->logo_url)) : ?>
                                    <img src="<?php echo esc_url($club->logo_url); ?>" alt="Logo" width="50" height="50" style="object-fit:cover;">
                                <?php else : ?>
                                    No disponible
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($club->contact_email); ?></td>
                            <td><?php echo esc_html($club->created_at); ?></td>
                            <td>
                                <?php if ($club->portfolio_post_id) : ?>
                                    <a href="<?php echo get_permalink($club->portfolio_post_id); ?>" target="_blank">Ver Publicación</a>
                                <?php else : ?>
                                    No creada
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=club-listings&import_id=<?php echo esc_attr($club->id); ?>" class="button button-primary">
                                    <?php echo $club->portfolio_post_id ? 'Recrear Portfolio' : 'Importar a Portfolio'; ?>
                                </a>
                                <a href="?page=club-listings&view_id=<?php echo esc_attr($club->id); ?>" class="button button-secondary">Ver Detalles</a>
                                <a href="?page=club-listings&delete_id=<?php echo esc_attr($club->id); ?>" 
                                   class="button button-danger" 
                                   onclick="return confirm('¿Estás seguro de que deseas eliminar este club? Esta acción no se puede deshacer.');">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">No hay solicitudes registradas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
    .button-danger {
        background: #dc3545 !important;
        border-color: #dc3545 !important;
        color: #fff !important;
    }
    .button-danger:hover {
        background: #c82333 !important;
        border-color: #bd2130 !important;
    }
    </style>
    <?php
}

function clm_display_club_details($club) {
    ?>
    <div class="wrap">
        <div class="admin-club-details">
            <h1 class="h2 mb-4">Detalles del Club: <?php echo esc_html($club->club_name); ?></h1>
            
            <div class="row mb-4">
                <div class="col-md-6 club-logo">
                    <h3 class="h5 mb-3">Logo</h3>
                    <?php echo !empty($club->logo_url) ? '<img src="' . esc_url($club->logo_url) . '" alt="Logo" class="img-fluid mb-3" style="width: 259px;">' : '<div class="alert alert-info">No disponible</div>'; ?>
                </div>
                <div class="col-md-6 club-image">
                    <h3 class="h5 mb-3">Imágenes</h3>
                    <?php 
                    $gallery_images = get_post_meta($club->portfolio_post_id, '_club_gallery_images', true);
                    if (!empty($gallery_images)) {
                        $images = explode(',', $gallery_images);
                        echo '<div class="gallery-grid">';
                        foreach ($images as $image_url) {
                            echo '<img src="' . esc_url(trim($image_url)) . '" alt="Imagen" class="img-fluid mb-3 gallery-item">';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-info">No hay imágenes disponibles</div>';
                    }
                    ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="h5 mb-3">Información Básica</h3>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Nombre:</strong> <?php echo esc_html($club->club_name); ?></li>
                                <li class="list-group-item"><strong>Descripción Corta:</strong> <?php echo esc_html($club->short_description); ?></li>
                                <li class="list-group-item"><strong>Localización:</strong> <?php echo esc_html($club->location); ?></li>
                                <li class="list-group-item"><strong>Horario:</strong> <?php echo esc_html($club->opening_hours); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h5 mb-3">Características</h3>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Edad Mínima:</strong> <?php echo esc_html($club->age_limit); ?></li>
                                <li class="list-group-item"><strong>Pet Friendly:</strong> <?php echo esc_html($club->pet_friendly); ?></li>
                                <li class="list-group-item"><strong>Fecha de Fundación:</strong> <?php echo esc_html($club->founded_date); ?></li>
                                <li class="list-group-item"><strong>Fecha de Solicitud:</strong> <?php echo esc_html($club->created_at); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="h5 mb-3">Contacto y Enlaces</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Email:</strong> <?php echo esc_html($club->contact_email); ?></li>
                                <li class="list-group-item"><strong>Teléfono:</strong> <?php echo esc_html($club->contact_phone); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><strong>Web:</strong> <?php echo !empty($club->website_url) ? '<a href="' . esc_url($club->website_url) . '" target="_blank">' . esc_html($club->website_url) . '</a>' : 'No disponible'; ?></li>
                                <li class="list-group-item"><strong>Redes Sociales:</strong> <?php echo !empty($club->social_media) ? '<a href="' . esc_url($club->social_media) . '" target="_blank">' . esc_html($club->social_media) . '</a>' : 'No disponible'; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="description-section mb-4">
                <h3 class="h5 mb-3">Descripción Completa</h3>
                <div class="card">
                    <div class="card-body">
                        <?php echo wp_kses_post($club->description); ?>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="?page=club-listings" class="btn btn-secondary">← Volver al Listado</a>
                <?php if (empty($club->portfolio_post_id)) : ?>
                <a href="?page=club-listings&import_id=<?php echo esc_attr($club->id); ?>" class="btn btn-primary">Importar a Portfolio</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <style>
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .gallery-item {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    </style>
    <?php
}
