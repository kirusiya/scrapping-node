<?php
/*
Plugin Name: Custom Post Creator Scrapping
Description: Plugin para crear publicaciones personalizadas mediante una API REST.
Version: 1.0
Author: Ing. Edward Avalos
*/

//endpoint REST
add_action('rest_api_init', function () {
    register_rest_route('custom-post-creator/v1', '/create-post', array(
        'methods' => 'POST',
        'callback' => 'crear_publicaciones',        
    ));
});

function crear_publicaciones($request) {
    $data = $request->get_json_params();

    if (empty($data) || empty($data['posts'])) {
        return new WP_Error('empty_data', 'No se proporcionaron datos válidos', array('status' => 400));
    }

    foreach ($data['posts'] as $post) {

        if (empty($post['title']) || empty($post['description']) || empty($post['link']) || empty($post['meta_link']) ) {
            return new WP_Error('invalid_data', 'Faltan campos necesarios', array('status' => 400));
        }
 
        $existing_post_id = post_id_by_meta_key_and_value('meta_link', $post['meta_link']);

        $pagina = $post['pagina'];

        $post_content = wp_kses_post($post['description']);

        if($pagina=='pagepersonnel'){
            $post_content = wp_kses_post($post['short_desc']) . wp_kses_post($post['description']);
        }

        if($pagina=='weworkremotely'){
            $post_content = wp_kses_post($post['description']);

            if($post_content=='Time zones:'){
                $post_content = "<h3>Job: ".$post['title']."</h3>";
                $post_content .= "<p>The job offer does not contain a description, just click on the Red Button to apply for the job, and the company will contact you</p>";
            }else{
                $post_content = "<h3>Job: ".$post['title']."</h3>". wp_kses_post($post['description']);
                $post_content .= "<p>The job offer does not contain a description, just click on the Red Button to apply for the job, and the company will contact you</p>";
            }
        }

        if($pagina=='virtualvocations'){
            $post_content = "<div>".wp_kses_post($post['excerpt'])."</div>"  . wp_kses_post($post['description']);
        }

        

        if (!$existing_post_id) {
            $new_post = array(
                'post_title' => $post['title'],
                'post_content' => $post_content,
                'post_status' => 'publish',
                'post_type' => 'job_listing',
                'post_author' => 1  
            );

            $post_id = wp_insert_post($new_post);

            //imagen principal
            set_post_thumbnail($post_id, 1331);
            update_post_meta($post_id, '_thumbnail_id', 1331);

            if (!empty($post['meta_link'])) {
                update_post_meta($post_id, 'meta_link', $post['meta_link'], true);
            }

            //PAGINA
            update_post_meta($post_id, '_pagina', $post['pagina']);

            //ubicacion del trabajo
            if ( isset($post['job_location']) && !empty($post['job_location']) && $post['job_location']!=='') {
                update_post_meta($post_id, '_job_location', $post['job_location']);
            }
            
            //industria del trabajo
            wp_set_object_terms($post_id, array(124), 'job_listing_industry');

            //categoria del trabajo
            wp_set_object_terms($post_id, array(133), 'job_listing_category');

            //aplicar con link
            update_post_meta($post_id, '_application', $post['link']);

            //Compañia anonima
            if ( isset($post['job_company']) && !empty($post['job_company']) && $post['job_company']!=='') {
                update_post_meta($post_id, '_company_name', $post['job_company']);
            }else{
                update_post_meta($post_id, '_company_name', 'Anonymous Company');
            }
            

            //meta para saber si escrapping
            update_post_meta($post_id, '_scrapping', 1);

            //postear en Linkedin
            update_post_meta($post_id, '_profile_selection_linkedin', 'urn:li:organization:20827521');

            /******LISTING TYPE********/

            /*pagepersonnel*/
            if ($pagina == 'pagepersonnel' && isset($post['listing_type']) && $post['listing_type'] !== '') {
                $tipo_oferta = $post['listing_type'];
            
                // Convertir texto a slug (reemplazar espacios por guiones y convertir a minúsculas)
                $tipo_oferta_slug = sanitize_title($tipo_oferta);
            
                // Verificar si el término ya existe en la taxonomía job_listing_type
                $term = get_term_by('slug', $tipo_oferta_slug, 'job_listing_type');
            
                // Si el término no existe, crearlo y obtener su ID
                if (!$term) {
                    $term = wp_insert_term($tipo_oferta, 'job_listing_type', array('slug' => $tipo_oferta_slug));
                    $term_id = $term['term_id'];
                } else {
                    $term_id = $term->term_id;
                }
            
                // Asignar la taxonomía al post
                wp_set_object_terms($post_id, array($term_id), 'job_listing_type');
            }
            /*pagepersonnel*/
            
            /*flexjobscom*/
            if ($pagina == 'flexjobscom' && isset($post['listing_type']) && $post['listing_type'] !== '') {
                $tipo_oferta = $post['listing_type'];

                $ofertas = explode(' | ', $tipo_oferta);
                // Array para almacenar los slugs de las ofertas
                $ofertas_slugs = array();

                foreach ($ofertas as $oferta) {
                    // Convertir la oferta en un slug
                    $slug = sanitize_title($oferta);
                    // Verificar si la taxonomía ya existe
                    $term = term_exists($slug, 'job_listing_type');
                    if (!$term || $term === 0 || $term === null) {
                        // Si la taxonomía no existe, crearla
                        $term = wp_insert_term($oferta, 'job_listing_type', array('slug' => $slug));
                        if (is_wp_error($term)) {
                            // Manejo de error si no se puede crear la taxonomía
                            continue;
                        }
                    }
                    // Almacenar el slug de la oferta en el array
                    $ofertas_slugs[] = $slug;
                }

                // Asignar las taxonomías al post
                wp_set_object_terms($post_id, $ofertas_slugs, 'job_listing_type');
            
                
            }
            /*flexjobscom*/

            /*los tiempos*/
            if ($pagina == 'lostiempos' && isset($post['listing_type']) && $post['listing_type'] !== '') {
                $tipo_oferta = $post['listing_type'];

                // Verificar si $tipo_oferta contiene '/'
                if (strpos($tipo_oferta, '/') !== false) {
                    // Si contiene '/', dividir en múltiples ofertas
                    $ofertas = explode('/', $tipo_oferta);
                } else {
                    // Si no contiene '/', tratar como una sola oferta
                    $ofertas = array($tipo_oferta);
                }
                // Array para almacenar los slugs de las ofertas
                $ofertas_slugs = array();

                foreach ($ofertas as $oferta) {
                    // Convertir la oferta en un slug
                    $slug = sanitize_title($oferta);
                    // Verificar si la taxonomía ya existe
                    $term = term_exists($slug, 'job_listing_type');
                    if (!$term || $term === 0 || $term === null) {
                        // Si la taxonomía no existe, crearla
                        $term = wp_insert_term($oferta, 'job_listing_type', array('slug' => $slug));
                        if (is_wp_error($term)) {
                            // Manejo de error si no se puede crear la taxonomía
                            continue;
                        }
                    }
                    // Almacenar el slug de la oferta en el array
                    $ofertas_slugs[] = $slug;
                }

                // Asignar las taxonomías al post
                wp_set_object_terms($post_id, $ofertas_slugs, 'job_listing_type');
            
                
            }
            /*los tiempos*/


            /******LISTING TYPE********/

            /*********JOB CATEGORY*************/
            if (isset($post['listing_category']) && $post['listing_category'] !== '') {
                $categoria_oferta = $post['listing_category'];
            
                // Convertir texto a slug (reemplazar espacios por guiones y convertir a minúsculas)
                $categoria_oferta_slug = sanitize_title($categoria_oferta);
            
                // Verificar si el término ya existe en la taxonomía job_listing_type
                $term = get_term_by('slug', $categoria_oferta_slug, 'job_listing_category');
            
                // Si el término no existe, crearlo y obtener su ID
                if (!$term) {
                    $term = wp_insert_term($categoria_oferta, 'job_listing_category', array('slug' => $categoria_oferta_slug));
                    $term_id = $term['term_id'];
                } else {
                    $term_id = $term->term_id;
                }
            
                // Asignar la taxonomía al post
                wp_set_object_terms($post_id, array($term_id), 'job_listing_category');
            }
            /*********JOB CATEGORY*************/

            
             
        }else{
            continue;
        }
    }

    return array('message' => 'Publicaciones creadas correctamente');
}



// Función para obtener el ID del post por un meta key y valor
function post_id_by_meta_key_and_value($key, $value) {
    global $wpdb;
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID 
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'publish'",
        $key, $value
    ));
    return $post_id;
}



/***************codigo para postear en linkeding*****************/
/**
 * Verificar y actualizar el meta '_profile_selection_linkedin' para posts tipo 'job_listing'.
 */
function actualizar_metadato_linkedin_para_job_listings() {
    // Obtener todos los posts tipo 'job_listing' que no tienen '_profile_selection_linkedin' o está vacío.
    $args = array(
        'post_type' => 'job_listing',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_profile_selection_linkedin',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_profile_selection_linkedin',
                'value' => '',
                'compare' => '=',
            ),
        ),
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            // Asignar el valor 'urn:li:organization:20827521' al meta '_profile_selection_linkedin'.
            update_post_meta(get_the_ID(), '_profile_selection_linkedin', 'urn:li:organization:104952429');
        }
        wp_reset_postdata();
    }
}

// Ejecutar la función en cada carga de página usando 'init'.
//add_action('init', 'actualizar_metadato_linkedin_para_job_listings');// actualizar los post con datos necesarios


/**
 * Función para verificar posts tipo 'job_listing' sin meta '_custom_linkedin_share_message'.
 */
function verificar_custom_linkedin_share_message() {
    $args = array(
        'post_type' => 'job_listing',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_sent_to_linkedin',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_sent_to_linkedin',
                'value' => '',
                'compare' => '=',
            ),
        ),
        'orderby' => 'ID', // Ordenar por ID
        'order' => 'ASC',  // Orden ascendente (de menor a mayor)
        'fields' => 'ids', // Solo obtener los IDs de los posts encontrados.
    );

    $query = new WP_Query($args);

    $post_ids = $query->posts;

    wp_send_json_success($post_ids); // Enviar los IDs como respuesta JSON.
    wp_die();
}

// Registrar la función AJAX para usuarios autenticados y no autenticados.
add_action('wp_ajax_verificar_custom_linkedin_share_message', 'verificar_custom_linkedin_share_message');
add_action('wp_ajax_nopriv_verificar_custom_linkedin_share_message', 'verificar_custom_linkedin_share_message');

