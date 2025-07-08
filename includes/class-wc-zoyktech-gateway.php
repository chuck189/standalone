@@ .. @@
     /**
      * Handle payment callback
      */
     public function handle_callback() {
        if (ob_get_level()) {
            ob_end_clean();
        }
-            // Get callback data
-            $callback_data = $this->get_callback_data();
-
-            if (empty($callback_data)) {
-                throw new Exception('No callback data received');
-            }
-
-            $this->log('Callback data: ' . print_r($callback_data, true));
-
-            // Verify callback signature if available
-            if (isset($callback_data['signature'])) {
-                if (!$this->api->verify_callback_signature($callback_data)) {
-                    throw new Exception('Invalid callback signature');
-                }
-            }
-
-            // Get order ID from callback
-            $zoyktech_order_id = isset($callback_data['order_id']) ? $callback_data['order_id'] : '';
-            
-            if (empty($zoyktech_order_id)) {
-                throw new Exception('Order ID missing in callback');
-            }
-
-            // Find WooCommerce order
-            $orders = wc_get_orders(array(
-                'meta_key' => '_zoyktech_order_id',
-                'meta_value' => $zoyktech_order_id,
-                'limit' => 1
-            ));
-
-            if (empty($orders)) {
-                throw new Exception('Order not found: ' . $zoyktech_order_id);
-            }
-
-            $order = $orders[0];
-
-            // Determine payment status
-            $status = $this->determine_payment_status($callback_data);
-            
-            $this->log('Payment status for order ' . $order->get_id() . ': ' . $status);
-
-            // Update payment log
-            $this->update_payment_log($zoyktech_order_id, $status, $callback_data);
-
-            // Handle payment result
-            switch ($status) {
-                case 'completed':
-                    $this->handle_successful_payment($order, $callback_data);
-                    break;
-                    
-                case 'failed':
-                case 'cancelled':
-                case 'expired':
-                    $this->handle_failed_payment($order, $callback_data, $status);
-                    break;
-                    
-                default:
-                    $this->log('Unknown payment status: ' . $status);
-                    break;
-            }
-
-            // Send success response
-            wp_send_json_success(array(
-                'message' => 'Callback processed successfully',
-                'order_id' => $order->get_id(),
-                'status' => $status
-            ));
-
-        } catch (Exception $e) {
-            $this->log('Callback error: ' . $e->getMessage());
-            wp_send_json_error(array('message' => $e->getMessage()));
-        }
+        // Callback handling is now managed by the Payment Completion Handler
+        // This ensures proper order completion and immediate enrollment
+        $this->log('Callback received - delegating to completion handler');
+        
+        // The completion handler will process this callback
+        // We just need to acknowledge receipt
+        wp_send_json_success(array('message' => 'Callback acknowledged'));
     }