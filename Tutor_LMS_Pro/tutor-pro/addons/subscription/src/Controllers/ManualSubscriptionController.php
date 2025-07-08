<?php
/**
 * Handler of manual subscription enrollment.
 *
 * @package TutorPro\Subscription
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.5.0
 */

namespace TutorPro\Subscription\Controllers;

use Tutor\Ecommerce\OrderController;
use Tutor\Models\OrderModel;
use Tutor\Traits\JsonResponse;
use TUTOR_ENROLLMENTS\Enrollments;
use TutorPro\Subscription\Models\PlanModel;
use TutorPro\Subscription\Models\SubscriptionModel;
use TutorPro\Subscription\Settings;
use TutorPro\Subscription\Subscription;

/**
 * ManualSubscriptionController Class.
 *
 * @since 3.5.0
 */
class ManualSubscriptionController {
	use JsonResponse;

	/**
	 * Subscription model.
	 *
	 * @var SubscriptionModel
	 */
	private $subscription_model;

	/**
	 * Order model.
	 *
	 * @var OrderModel
	 */
	private $order_model;

	/**
	 * Order controller instance.
	 *
	 * @var OrderController
	 */
	private $order_ctrl;

	/**
	 * Plan model
	 *
	 * @var PlanModel
	 */
	private $plan_model;


	/**
	 * Register hooks and dependencies
	 *
	 * @since 3.5.0
	 *
	 * @param bool $register_hooks whether to register hooks or not.
	 */
	public function __construct( $register_hooks = true ) {
		$this->subscription_model = new SubscriptionModel();
		$this->order_model        = new OrderModel();
		$this->order_ctrl         = new OrderController( false );
		$this->plan_model         = new PlanModel();

		if ( ! $register_hooks ) {
			return;
		}

		add_filter( 'tutor_manual_enrollment', array( $this, 'manual_subscription_enrollment' ), 10, 4 );
		add_filter( 'tutor_unenrolled_users_response', array( $this, 'unenrolled_users_response_for_subscription' ), 10, 2 );
	}

	/**
	 * Manual subscription enrollment
	 *
	 * @since 3.5.0
	 *
	 * @param array  $enrollment_data enrollment data.
	 * @param array  $object_ids object ids.
	 * @param array  $student_ids student ids.
	 * @param string $payment_status payment status.
	 *
	 * @return array
	 */
	public function manual_subscription_enrollment( $enrollment_data, $object_ids, $student_ids, $payment_status ) {
		if ( tutor_utils()->is_monetize_by_tutor() && Subscription::is_enabled() && Settings::membership_only_mode_enabled() ) {
			$plan_id = $object_ids[0] ?? 0;
			$plan    = apply_filters( 'tutor_get_plan_info', null, $plan_id );
			if ( ! $plan ) {
				return $enrollment_data;
			}

			$enrollment_class   = new Enrollments( false );
			$subscription_model = new SubscriptionModel();
			$plan_model         = new PlanModel();
			$total_enrollments  = 0;
			$failed_enrollments = array();

			foreach ( $student_ids as $student_id ) {

				if ( $subscription_model->is_subscribed( $plan_id, $student_id ) ) {
					$failed_enrollments[] = $enrollment_class->get_failed_user_data( $student_id );
					continue;
				}

				$item = array(
					'item_id'        => $plan_id,
					'regular_price'  => $plan->regular_price,
					'sale_price'     => $plan_model->in_sale_price( $plan ) ? $plan->sale_price : null,
					'discount_price' => null,
				);

				try {
					add_filter( 'tutor_apply_plan_trial', fn( $bool ) => false );

					$args = array(
						'payment_method' => OrderModel::PAYMENT_METHOD_MANUAL,
						'note'           => __( 'Order created for manual subscription', 'tutor-pro' ),
					);

					$sale_discount_amount = isset( $item['sale_price'] ) ? $item['regular_price'] - $item['sale_price'] : 0;
					if ( $sale_discount_amount ) {
						$args['discount_amount'] = $sale_discount_amount;
					}

					$order_id = $this->order_ctrl->create_order(
						$student_id,
						$item,
						$payment_status,
						OrderModel::TYPE_SUBSCRIPTION,
						null,
						$args
					);

					if ( ! $order_id ) {
						$failed_enrollments[] = $enrollment_class->get_failed_user_data( $student_id );
						continue;
					}

					if ( OrderModel::PAYMENT_PAID === $payment_status ) {
						do_action( 'tutor_order_payment_status_changed', $order_id, OrderModel::PAYMENT_UNPAID, $payment_status );
					}

					$total_enrollments++;

				} catch ( \Throwable $th ) {
					$this->response_bad_request( $th->getMessage() );
				}
			}

			$enrollment_data = array(
				'message'                    => __( 'Subscription added for selected students', 'tutor-pro' ),
				'is_subscription_enrollment' => true,
				'failed_enrollment_list'     => $failed_enrollments,
				'total_enrolled_students'    => $total_enrollments,

			);
		}

		return $enrollment_data;
	}

	/**
	 * Filter unenrolled users response for subscription
	 *
	 * @since 3.5.0
	 *
	 * @param array $response response.
	 * @param int   $object_id object id.
	 *
	 * @return array
	 */
	public function unenrolled_users_response_for_subscription( $response, $object_id ) {
		if ( Settings::membership_only_mode_enabled() ) {
			$subscription_model  = new SubscriptionModel();
			$response['results'] = array_map(
				function ( $val ) use ( $subscription_model, $object_id ) {
					if ( $subscription_model->is_subscribed( $object_id, $val->ID ) ) {
						$val->is_enrolled       = 1;
						$val->enrollment_status = __( 'Already Subscribed', 'tutor-pro' );
					}
					return $val;
				},
				$response['results']
			);
		}

		return $response;
	}
}
