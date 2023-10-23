<?php
/**
 * Form Templates - Code from email modal.
 *
 * @package   Strategy11/FormidableForms
 * @copyright 2010 Formidable Forms
 * @license   GNU General Public License, version 2
 * @link      https://formidableforms.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
?>
<div id="frm-code-from-email-modal" class="frm-form-templates-modal-item frm_hidden">
	<div class="frm_modal_top">
		<div class="frm-modal-title">
			<h2><?php esc_html_e( 'Check Your Inbox', 'formidable' ); ?></h2>


		</div>
	</div>

	<div class="inside">
		<div>
			<p><?php esc_html_e( 'Enter the code that we sent to your email address.', 'formidable' ); ?></p>

			<div class="frm-form-templates-modal-fieldset">
				<input id="frm_code_from_email" type="text" placeholder="<?php esc_attr_e( 'Code from email', 'formidable' ); ?>" />

				<span id="frm_code_from_email_error" class="frm-form-templates-modal-error frm_hidden">
					<span frm-error="custom"></span>
					<span frm-error="wrong-code"><?php esc_html_e( 'Verification code is wrong', 'formidable' ); ?></span>
					<span frm-error="empty"><?php esc_html_e( 'Verification code is empty', 'formidable' ); ?></span>
				</span>
			</div>

			<div id="frm_code_from_email_options" class="frm_hidden">
				<a href="#" id="frm-change-email-address"><?php esc_html_e( 'Change email address', 'formidable' ); ?></a>
				<span>|</span>
				<a href="#" id="frm-resend-code"><?php esc_html_e( 'Resend code', 'formidable' ); ?></a>
			</div>
		</div>
	</div>

	<div class="frm_modal_footer">
		<a href="#" id="frm-code-modal-back-button" role="button" class="button button-secondary frm-button-secondary" role="button">
			<?php esc_html_e( 'Back', 'formidable' ); ?>
		</a>
		<a href="#" id="frm-confirm-email-address" class="button button-primary frm-button-primary" role="button">
			<?php esc_html_e( 'Save Code', 'formidable' ); ?>
		</a>
	</div>
</div>
