@@ .. @@
     /**
      * Log messages
      */
     private function log($message) {
        // Prevent output during construction
        if (!ob_get_level()) {
            ob_start();
        }
        
         if ($this->debug) {
             error_log('ZOYKTECH_API: ' . $message);
         }
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
     }
+
+    /**
+     * Check payment status
+     */
+    public function check_payment_status($order_id) {
+        $this->log('Checking payment status for order: ' . $order_id);
+        
+        try {
+            $response = $this->make_request('/payment_status', array(
+                'order_id' => $order_id,
+                'merchant_id' => $this->merchant_id
+            ));
+            
+            if (isset($response['status'])) {
+                $status = intval($response['status']);
+                
+                switch ($status) {
+                    case 1:
+                    case 2:
+                        return 'completed';
+                    case 3:
+                        return 'failed';
+                    case 4:
+                        return 'cancelled';
+                    case 5:
+                        return 'expired';
+                    default:
+                        return 'processing';
+                }
+            }
+            
+            return 'unknown';
+            
+        } catch (Exception $e) {
+            $this->log('Status check failed: ' . $e->getMessage());
+            return 'error';
+        }
+    }
 }