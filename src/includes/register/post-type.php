<?php 

  $labels = array(
        'name' => 'Contacts',
        'singular_name' => 'Contact',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Contact',
        'edit_item' => 'Edit Contact',
        'new_item' => 'New Contact',
        'all_items' => 'All Contacts',
        'view_item' => 'View Contact',
        'search_items' => 'Search Contacts',
        'not_found' =>  'No Contacts found',
        'not_found_in_trash' => 'No Contacts found in Trash', 
        'parent_item_colon' => '',
        'menu_name' => 'Contacts'
  );
  $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true, 
        'show_in_menu' => "iletisim2",
        'query_var' => true,
        'rewrite' => array( 'slug' => 'contact' ),
        'capability_type' => 'post',
        'has_archive' => false, 
        'hierarchical' => false,
        'menu_position' => 5,
        'supports' => array( 'title', 'editor', 'thumbnail' )
  ); 
  register_post_type( 'contact', $args );

  add_action('admin_menu', 'add_contact_sub_menu');

  function add_contact_sub_menu(){
	  add_submenu_page( 'anasayfa', 'İletişim Bilgileri', 'İletişim Bilgileri', 'edit_theme_options', 'edit.php?post_type=contact', 'options-contact', 10 );
	  add_submenu_page( 'anasayfa', 'İletişim Kategorileri', 'İletişim Kategorileri', 'edit_theme_options', 'edit-tags.php?taxonomy=contact-type&post_type=contact', 'options-contact', 11 );  	
  }

  $labels = array(
        'name' => 'Templates',
        'singular_name' => 'Template',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Template',
        'edit_item' => 'Edit Template',
        'new_item' => 'New Template',
        'all_items' => 'All Templates',
        'view_item' => 'View Template',
        'search_items' => 'Search Templates',
        'not_found' =>  'No Templates found',
        'not_found_in_trash' => 'No Templates found in Trash', 
        'parent_item_colon' => '',
        'menu_name' => 'Templates'
  );
  $args = array(
        'labels' => $labels,
        'public' => false,
        'publicly_queryable' => true,
        'show_ui' => true, 
        'show_in_menu' => "template",
        'query_var' => true,
        'rewrite' => array( 'slug' => 'template' ),
        'capability_type' => 'post',
        'has_archive' => false, 
        'hierarchical' => false,
        'menu_position' => 5,
        'supports' => array( 'title')
  ); 
  register_post_type( 'template', $args );

  add_action('admin_menu', 'add_template_sub_menu');

  function add_template_sub_menu(){
    add_submenu_page( 'anasayfa', 'Templates', 'Templates', 'edit_theme_options', 'edit.php?post_type=template&lang=all', 'options-template', 12 );   
  }


function save_template_as_twig( $post_id ) {
    // Post türü kontrolü
    if ( get_post_type( $post_id ) !== 'template' ) {
        return;
    }

    // Autosave veya revizyon kontrolü
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Post durumu kontrolü (publish veya draft olmalı)
    $post_status = get_post_status( $post_id );
    if ( $post_status !== 'publish' && $post_status !== 'draft' ) {
        return;
    }

    // Post slug'ını ve içeriği al
    $post_slug = get_post_field( 'post_name', $post_id );
    $content = get_field( 'content', $post_id );

    // İçerik boşsa işlem yapma
    if ( empty( $content ) ) {
        return;
    }

    $theme_dir = get_stylesheet_directory();
    $templates_dir = $theme_dir . '/theme/templates/_custom/'; // "themes" yerine "theme"

    // Klasör yoksa oluştur
    if ( ! file_exists( $templates_dir ) ) {
        mkdir( $templates_dir, 0755, true );
    }

    // Meta değerini kontrol et
    $existing_file_name = get_post_meta( $post_id, 'template', true );

    // Eğer mevcut bir dosya varsa, dosyayı sil
    if ( !empty( $existing_file_name ) ) {
        $existing_file_path = $templates_dir . $existing_file_name;
        if ( file_exists( $existing_file_path ) ) {
            unlink( $existing_file_path );
        }
    }

    // Yeni dosya adını oluştur
    $new_file_name = $post_slug . '.twig';
    $file_path = $templates_dir . $new_file_name;

    // Dosya içeriğini oluştur veya güncelle
    file_put_contents( $file_path, $content );

    // Post meta değerini güncelle
    update_post_meta( $post_id, 'template', $new_file_name );
}
add_action( 'save_post', 'save_template_as_twig', 10, 3 );

function delete_template_twig_file( $post_id ) {
    if ( get_post_type( $post_id ) !== 'template' ) {
        return;
    }
    $file_name = get_post_meta( $post_id, 'template', true );
    $theme_dir = get_stylesheet_directory();
    $templates_dir = $theme_dir . '/theme/templates/_custom/';
    $file_path = $templates_dir . $file_name;
    if ( file_exists( $file_path ) ) {
        unlink( $file_path );
    }
}
add_action( 'before_delete_post', 'delete_template_twig_file' );

