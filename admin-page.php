<?php 

add_action('admin_menu', 'adv_importer_admin_page');

function adv_importer_admin_page(){
    add_options_page( 
        "Advance importer", 
        "Advance Importer",
        'manage_options', 
        'advance-importer', 
        'adv_importer_admin_page_render'
    );
}


function adv_importer_admin_page_render(){
    require __DIR__ . '/templates/meta-input-form.php';
}