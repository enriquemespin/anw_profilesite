<?php
/**
 * Blog Poster
 * Handles posting formatted content to various blog platforms
 */

class BlogPoster {
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Post to WordPress
     */
    public function postToWordPress($post) {
        $wpConfig = $this->config['blog_posting']['wordpress'];
        
        if (empty($wpConfig['url']) || empty($wpConfig['username']) || empty($wpConfig['application_password'])) {
            $this->log('WordPress credentials not configured');
            return ['success' => false, 'error' => 'WordPress credentials not configured'];
        }
        
        $url = rtrim($wpConfig['url'], '/') . '/posts';
        
        $data = [
            'title' => $post['title'],
            'content' => $post['content_html'],
            'status' => 'draft', // Always draft first for review
            'categories' => [1], // Cybersecurity category ID
            'tags' => ['cybersecurity', 'threat-intel', 'summary']
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($wpConfig['username'] . ':' . $wpConfig['application_password'])
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $result = json_decode($response, true);
            $this->log("Posted to WordPress: {$post['title']}");
            return ['success' => true, 'post_id' => $result['id'], 'url' => $result['link']];
        }
        
        $this->log("Failed to post to WordPress: HTTP $httpCode");
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }
    
    /**
     * Post to Medium
     */
    public function postToMedium($post) {
        $mediumConfig = $this->config['blog_posting']['medium'];
        
        if (empty($mediumConfig['api_key'])) {
            $this->log('Medium API key not configured');
            return ['success' => false, 'error' => 'Medium API key not configured'];
        }
        
        $url = 'https://api.medium.com/v1/posts';
        
        $data = [
            'title' => $post['title'],
            'contentFormat' => 'html',
            'content' => $post['content_html'],
            'content' => $post['content_markdown'],
            'publishStatus' => 'draft',
            'tags' => ['cybersecurity', 'threat-intel', 'security-news']
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $mediumConfig['api_key']
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $result = json_decode($response, true);
            $this->log("Posted to Medium: {$post['title']}");
            return ['success' => true, 'post_id' => $result['data']['id'], 'url' => $result['data']['url']];
        }
        
        $this->log("Failed to post to Medium: HTTP $httpCode");
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }
    
    /**
     * Post to custom platform
     */
    public function postToCustom($post, $apiUrl, $apiKey) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $data = [
            'title' => $post['title'],
            'content' => $post['content_html'],
            'status' => 'draft'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201 || $httpCode === 200) {
            $this->log("Posted to custom platform: {$post['title']}");
            return ['success' => true];
        }
        
        $this->log("Failed to post to custom platform: HTTP $httpCode");
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }
    
    /**
     * Main post method - routes to correct platform
     */
    public function post($post, $platform = null) {
        if ($platform === null) {
            $platform = $this->config['blog_posting']['platform'];
        }
        
        if (!$this->config['blog_posting']['enabled']) {
            return ['success' => false, 'error' => 'Blog posting is disabled'];
        }
        
        switch ($platform) {
            case 'wordpress':
                return $this->postToWordPress($post);
            case 'medium':
                return $this->postToMedium($post);
            case 'custom':
                return ['success' => false, 'error' => 'Custom platform requires API URL and key'];
            default:
                return ['success' => false, 'error' => "Unknown platform: $platform"];
        }
    }
    
    /**
     * Log a message
     */
    private function log($message) {
        if ($this->logger) {
            $this->logger($message);
        } else {
            error_log($message);
        }
    }
}