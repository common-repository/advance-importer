<?php 
class ADV_IMPORTER_AJAX{
    /**
	 * This function will update plugin settings option value
	 */
	public function update_plugin_settings(){

		if( isset( $_POST["nonce"] ) &&  wp_verify_nonce( $_POST['nonce'], 'adv-importer' ) ){
			if (   isset( $_POST["adv-importer-meta-string"] )    ){

				$meta_sting	= trim(sanitize_textarea_field($_POST["adv-importer-meta-string"]));
	
				$string_update_check= update_option( 'adv-importer-meta-string', $meta_sting,  false);
		
				if( $string_update_check  ){
					echo json_encode([
						'status' => true,
						'message' => "Settings Updated"
					]);
				}else{
					echo json_encode([
						'status' => false,
						'message' => "Settings Update Fail"
					]);
				}
			}else{
				echo json_encode([
					'status' => false,
					'message' => "Your Input not valid"
				]);
			}
		}else{
			echo json_encode([
                'status' => false,
                'message' => "Verification failed"
            ]);
		}

		
		die();
	}

}