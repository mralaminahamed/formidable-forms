<?php
/**
 * Add-Ons addon view.
 *
 * @package Formidable
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

if ( ! is_array( $addon ) || $addon['slug'] === 'views' ) {
	return;
}

$plan_required     = FrmFormsHelper::get_plan_required( $addon );
$is_formidable_pro = $addon['slug'] === 'formidable-pro';
?>
<li class="frm-card-item frm-flex-col plugin-card-<?php echo esc_attr( $addon['slug'] ); ?> frm-no-thumb frm-addon-<?php echo esc_attr( $addon['status']['type'] ); ?>">
	<div class="frm-flex frm-gap-xs frm-items-center frm-mb-2xs">
		<span class="frm-border-icon">
			<?php FrmAddonsHelper::get_addon_icon( $addon['slug'] ); ?>
		</span>

		<h3>
			<span class="frm-font-medium frm-truncate"><?php echo esc_html( ! empty( $addon['display_name'] ) ? $addon['display_name'] : $addon['title'] ); ?></span>
			<?php
			if ( ! empty( $addon['is_new'] ) ) {
				FrmAppHelper::show_pill_text();
			}
			?>
		</h3>

		<?php if ( ! $plan_required && FrmAppHelper::pro_is_installed() ) { ?>
			<div class="frm-ml-auto">
				<?php
				FrmHtmlHelper::toggle(
					'frm-' . $addon['slug'],
					'frm-' . $addon['slug'],
					array(
						'checked'  => $addon['status']['type'] === 'active',
						'disabled' => $is_formidable_pro,
						'echo'     => true,
					)
				);
				FrmAddonsController::show_conditional_action_button(
					array(
						'addon'         => $addon,
						'license_type'  => ! empty( $license_type ) ? $license_type : false,
						'plan_required' => 'plan_required',
						'upgrade_link'  => $pricing,
					)
				);
				?>
			</div>
			<?php
		}//end if
		?>
	</div>

	<p class="frm-line-clamp-2">
		<?php echo FrmAppHelper::kses( $addon['excerpt'], array( 'a' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</p>

	<span class="frm-page-skeleton-divider frm-mt-auto"></span>

	<div class="frm-flex frm-items-center frm-justify-between">
		<?php
		if ( ! empty( $addon['docs'] ) && ! $plan_required ) {
			?>
			<a class="frm-link-with-external-icon" href="<?php echo esc_url( $addon['docs'] ); ?>" target="_blank" aria-label="<?php esc_attr_e( 'View Docs', 'formidable' ); ?>">
				<?php esc_html_e( 'View Docs', 'formidable' ); ?>
				<?php FrmAppHelper::icon_by_class( 'frmfont frm_arrowup8_icon' ); ?>
			</a>
			<?php
		} else {
			FrmFormsHelper::show_plan_required( $plan_required, $pricing . ' & utm_content = ' . $addon['slug'] );
			?>
			<div>
				<?php FrmAddonsController::addon_upgrade_link( $addon, $pricing ); ?>
			</div>
			<?php
		}
		?>
	</div>
</li>
