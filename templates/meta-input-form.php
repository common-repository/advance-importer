<?php 
$adv_imp_ajax_url = admin_url('admin-ajax.php');
$adv_importer_string = trim( get_option("adv-importer-meta-string"));


?>

<div class="adv-importer-wrapper">
    <h1>Advance importer</h1>


    <form action="<?php echo esc_url_raw( $adv_imp_ajax_url ) ?>" id="adv-importer-form">
        <div>
            <label for="adv-importer-meta-string"><?php  _e( "(Optional) Meta key list separate by comma(,)" , 'adv-importer')  ?> :</label>
        </div>

        <div>
            <textarea 
                name="adv-importer-meta-string" 
                id="adv-importer-meta-string" 
                cols="30" rows="10"
            ><?php  echo esc_textarea($adv_importer_string) ?></textarea>
        </div>

        <input type="hidden" name="nonce" id="adv-importer-nonce" value="<?php echo wp_create_nonce( 'adv-importer' ) ?>">

        <p class="small"><?php _e("Leave this field empty in you want to import all relevant attachment media.",'adv-importer')?></p>
        <p class="alert d-none"></p>
        
        <div class="btn-container">
            <button type="submit" class="button button-primary"><?php _e("Update/Save Information", "adv-importer") ?> </button>
        </div>
    </form>

</div>
