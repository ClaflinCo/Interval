<?php
// api/notify_n8n.php

function notify_n8n(array $payload): void {
    try {
        $url = getenv('N8N_WEBHOOK_URL');
        $secret = getenv('N8N_SHARED_SECRET');
        
        if (empty($url) || empty($secret)) {
            return;
        }
        
        if (!function_exists('curl_init')) {
            return;
        }
        
        $ch = curl_init($url);
        if (!$ch) {
            return;
        }
        
        $jsonData = json_encode($payload);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        
        $headers = [
            'Content-Type: application/json',
            'X-Auth-Token: ' . $secret
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Execute fire-and-forget request
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $t) {
        // Fail silently
        return;
    }
}
?>
