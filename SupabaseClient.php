<?php
// SupabaseClient.php
class SupabaseClient {
    private $url;
    private $key;
    
    public function __construct($url, $key) {
        $this->url = $url;
        $this->key = $key;
    }
    
    public function request($method, $endpoint, $data = null) {
        $ch = curl_init($this->url . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->key,
                'Authorization: Bearer ' . $this->key,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ],
            CURLOPT_POSTFIELDS => $data ? json_encode($data) : null
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Error cURL: ' . $error);
        }
        
        return [
            'data' => json_decode($response, true),
            'status' => $httpCode
        ];
    }
    
    public function uploadFile($bucket, $filename, $fileContent) {
        $ch = curl_init($this->url . '/storage/v1/object/' . $bucket . '/' . $filename);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->key,
                'Authorization: Bearer ' . $this->key,
                'Content-Type: image/*',
                'x-upsert'. 'true'
            ],
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    public function deleteFile($bucket, $filename) {
        $ch = curl_init($this->url . '/storage/v1/object/' . $bucket . '/' . $filename);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->key,
                'Authorization: Bearer ' . $this->key
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
}
?>