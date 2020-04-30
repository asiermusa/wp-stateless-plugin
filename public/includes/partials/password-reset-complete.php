<?php
/**
 * Frontend Reset Password - Reset Complete
 *
 * @since    1.0.0
 *
 */  
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div id="password-lost-form-wrap">

		<div>
			<fieldset>
				<legend><?php echo $form_title; ?></legend>

				<p>
					<?php printf(__( 'Your password has been reset. You can now Sign in again.', 'frontend-reset-password' )); ?>
				</p>

			</fieldset>
		</div>

</div>