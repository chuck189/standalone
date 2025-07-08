<?php
/**
 * Template for plan list
 *
 * @package TutorPro\Subscription
 * @subpackage Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.3.0
 */

use Tutor\Ecommerce\Tax;
use TutorPro\Subscription\Models\PlanModel;

extract( $data );//phpcs:ignore
?>

<div class="tutor-subscription-choose-plan"><?php echo esc_html__( 'Choose plan', 'tutor-pro' ); ?></div>
<?php
foreach ( $course_plans as $plan ) :
	$in_sale_price = $plan_model->in_sale_price( $plan );
	$display_price = $in_sale_price ? $plan->sale_price : $plan->regular_price;

	if ( $show_price_with_tax && $tax_rate > 0 && ! Tax::is_tax_included_in_price() ) {
		$tax_amount     = Tax::calculate_tax( $display_price, $tax_rate );
		$display_price += $tax_amount;
	}

	$features      = $plan_model->prepare_plan_features( $plan );
	$plan_buy_link = add_query_arg( array( 'plan' => $plan->id ), $checkout_link );
	?>

		<label class="tutor-course-subscription-plan <?php echo esc_attr( $plan->is_featured ? 'featured' : '' ); ?>"
			data-features="<?php echo esc_attr( wp_json_encode( $features ) ); ?>"
			data-plan-id="<?php echo esc_attr( $plan->id ); ?>"
			data-checkout-link="<?php echo esc_url( $plan_buy_link ); ?>"
		>
			<div class="tutor-subscription-header">
				<div class="tutor-d-flex tutor-align-center tutor-gap-1">
					<input type="radio" name="plan_id" value="<?php echo esc_attr( $plan->id ); ?>" class="tutor-form-check-input" autocomplete="off">
					<span class="tutor-fs-6 tutor-fw-medium tutor-color-black">
					<?php echo esc_html( $plan->plan_name ); ?>

					<?php
					if ( $plan->is_featured ) :
						?>
							<span class="tutor-subscription-featured-badge">
								<i class="tutor-icon-star-bold"></i>
							</span>
						<?php
						endif;
					?>
					</span>
				</div>

				<div class="tutor-ml-32 tutor-mt-4">
					<div>
						<strong class="tutor-subscription-price"><?php echo esc_html( tutor_get_formatted_price( $display_price ) ); ?></strong>
						<?php if ( $in_sale_price ) : ?>
							<span class="tutor-subscription-discount-price"><?php echo esc_html( tutor_get_formatted_price( $plan->regular_price ) ); ?></span>
						<?php endif; ?>
						<?php if ( PlanModel::PAYMENT_RECURRING === $plan->payment_type ) { ?>
						<span class="tutor-fs-6 tutor-color-subdued">
							<?php
							echo esc_html(
								$plan->recurring_value > 1
								? sprintf(
									/* translators: %s: value, %s: name */
									__( '/ %1$s %2$s', 'tutor-pro' ),
									$plan->recurring_value,
									$plan->recurring_interval . ( $plan->recurring_value > 1 ? 's' : '' )
								)
								:
								sprintf(
									/* translators: %s: recurring interval */
									__( '/ %1$s', 'tutor-pro' ),
									$plan->recurring_interval . ( $plan->recurring_value > 1 ? 's' : '' )
								)
							);
							?>
						</span>
						<?php } else { ?>
							<span class="tutor-fs-6 tutor-color-subdued">/ <?php esc_html_e( 'lifetime', 'tutor-pro' ); ?></span>
							<?php } ?>
					</div>
					<?php if ( $show_price_with_tax && $tax_rate > 0 ) : ?>
					<div class="tutor-fs-7 tutor-color-subdued"><?php esc_html_e( 'Incl. tax', 'tutor-pro' ); ?></div>
					<?php endif; ?>
				</div>
			</div>
		</label>
<?php endforeach; ?>
