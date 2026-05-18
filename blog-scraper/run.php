#!/usr/bin/env php
<?php
/**
 * Cybersecurity Blog Scraper - Main Entry Point
 * 
 * Usage:
 *   php run.php scan          - Scan all blog sources
 *   php run.php pending       - Show pending posts
 *   php run.php post [n]      - Post n pending posts (default: 1)
 *   php run.php stats         - Show statistics
 *   php run.php history       - Show scan history
 *   php run.php help          - Show help
 */

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabaseManager.php';
require_once __DIR__ . '/PostFormatter.php';
require_once __DIR__ . '/BlogPoster.php';

$config = require __DIR__ . '/config.php';

// Initialize components
$db = new DatabaseManager($config['database']['path']);
$formatter = new PostFormatter();
$poster = new BlogPoster($config, function($msg) {
    error_log($msg);
});

// CLI handler
$action = $argv[1] ?? 'help';

switch ($action) {
    case 'scan':
        echo "=== Cybersecurity Blog Scraper ===\n";
        echo "Starting scan of all sources...\n\n";
        
        $sources = $db->getSources('priority', 'ASC');
        $totalNew = 0;
        $totalSkipped = 0;
        
        foreach ($sources as $source) {
            echo "Scanning: {$source['name']}... ";
            
            // Fetch feed
            $cacheFile = __DIR__ . '/data/cache/' . md5($source['url']) . '.xml';
            $rssContent = null;
            
            // Check cache
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
                $rssContent = file_get_contents($cacheFile);
            } else {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $source['url'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => $config['scraper']['user_agent'],
                    CURLOPT_TIMEOUT => $config['scraper']['timeout'],
                    CURLOPT_FOLLOWLOCATION => true
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $rssContent = $response;
                    file_put_contents($cacheFile, $response);
                }
            }
            
            if (!$rssContent) {
                echo "FAILED\n";
                $db->recordScan($source['id'], 0, 0, 0, 'Failed to fetch feed');
                continue;
            }
            
            // Parse feed
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($rssContent);
            
            if ($xml === false) {
                echo "PARSE ERROR\n";
                $db->recordScan($source['id'], 0, 0, 0, 'Failed to parse feed');
                continue;
            }
            
            $channel = $xml->channel ?? $xml->feed ?? null;
            if (!$channel) {
                echo "NO CHANNEL\n";
                $db->recordScan($source['id'], 0, 0, 0, 'No channel found');
                continue;
            }
            
            $items = $channel->item ?? $channel->entry ?? [];
            $postsFound = 0;
            $postsNew = 0;
            $postsSkipped = 0;
            
            foreach ($items as $item) {
                $postUrl = (string)($item->link['href'] ?? $item->link ?? '');
                $title = (string)($item->title ?? '');
                $author = (string)($item->author ?? '');
                $published = (string)($item->pubDate ?? $item->published ?? $item->updated ?? '');
                $description = (string)($item->description ?? $item->summary ?? '');
                
                if (empty($title) || empty($postUrl)) {
                    continue;
                }
                
                $postsFound++;
                
                // Check if already scanned
                if ($db->postExists($postUrl)) {
                    $postsSkipped++;
                    continue;
                }
                
                // Format post
                $formattedPost = $formatter->formatPost([
                    'post_url' => $postUrl,
                    'title' => $title,
                    'author' => $author,
                    'published_date' => $published,
                    'description' => $description
                ]);
                
                // Insert into database
                $db->insertPost(
                    $source['id'],
                    $postUrl,
                    $title,
                    $author,
                    $published,
                    $formattedPost['framework']['summary'],
                    $formattedPost['framework']['whats_new'],
                    $formattedPost['framework']['question'],
                    $formattedPost['framework']['next_steps']
                );
                
                $postsNew++;
            }
            
            // Record scan
            $db->recordScan($source['id'], $postsFound, $postsNew, $postsSkipped);
            $db->updateLastScanned($source['id']);
            
            echo "✓ {$postsNew} new, {$postsSkipped} skipped\n";
            $totalNew += $postsNew;
            $totalSkipped += $postsSkipped;
            
            // Respect rate limiting
            sleep($config['scraper']['request_delay']);
        }
        
        echo "\nScan complete!\n";
        echo "Total new posts: {$totalNew}\n";
        echo "Total skipped: {$totalSkipped}\n";
        break;
        
    case 'pending':
        echo "=== Pending Posts ===\n\n";
        
        $posts = $db->getPendingPosts(10);
        
        if (empty($posts)) {
            echo "No pending posts.\n";
        } else {
            foreach ($posts as $i => $post) {
                $formatted = $formatter->formatPost($post);
                echo "[$i+1] {$formatted['title']}\n";
                echo "    Source: {$formatted['source']}\n";
                echo "    Summary: {$formatted['framework']['summary']}\n";
                echo "    ---\n";
            }
        }
        break;
        
    case 'post':
        echo "=== Posting to Blog ===\n\n";
        
        $count = intval($argv[2] ?? 1);
        $posts = $db->getPendingPosts($count);
        
        if (empty($posts)) {
            echo "No pending posts to post.\n";
            break;
        }
        
        foreach ($posts as $post) {
            $formatted = $formatter->formatPost($post);
            $contentHtml = $formatter->generateBlogPost($formatted);
            $contentMarkdown = $formatter->generateMarkdown($formatted);
            
            $postPayload = [
                'title' => $formatted['title'],
                'content_html' => $contentHtml,
                'content_markdown' => $contentMarkdown
            ];
            
            echo "Posting: {$formatted['title']}... ";
            
            $result = $poster->post($postPayload);
            
            if ($result['success']) {
                $db->markAsPosted($post['id']);
                echo "✓ Posted successfully\n";
            } else {
                echo "✗ Failed: {$result['error']}\n";
            }
        }
        break;
        
    case 'stats':
        echo "=== Blog Scraper Statistics ===\n\n";
        
        $stats = $db->getStatistics();
        
        echo "Total Sources: {$stats['total_sources']}\n";
        echo "Total Posts Scanned: {$stats['total_posts']}\n";
        echo "Pending Posts: {$stats['pending_posts']}\n";
        echo "Scans Today: {$stats['scans_today']}\n";
        break;
        
    case 'history':
        echo "=== Recent Scan History ===\n\n";
        
        $history = $db->getScanHistory(10);
        
        if (empty($history)) {
            echo "No scan history.\n";
        } else {
            foreach ($history as $entry) {
                echo "[$entry['scanned_at']] {$entry['source_name']}\n";
                echo "  Found: {$entry['posts_found']}, New: {$entry['posts_new']}, Skipped: {$entry['posts_skipped']}\n";
                if ($entry['error']) {
                    echo "  Error: {$entry['error']}\n";
                }
                echo "\n";
            }
        }
        break;
        
    case 'help':
    default:
        echo "Cybersecurity Blog Scraper\n";
        echo "==========================\n\n";
        echo "Usage: php run.php <action>\n\n";
        echo "Actions:\n";
        echo "  scan          - Scan all blog sources for new posts\n";
        echo "  pending       - Show pending posts ready for posting\n";
        echo "  post [n]      - Post n pending posts to your blog (default: 1)\n";
        echo "  stats         - Show scraper statistics\n";
        echo "  history       - Show recent scan history\n";
        echo "  help          - Show this help\n\n";
        echo "Examples:\n";
        echo "  php run.php scan              # Scan all sources\n";
        echo "  php run.php pending           # View pending posts\n";
        echo "  php run.php post 3            # Post 3 pending posts\n";
        echo "  php run.php stats             # View statistics\n";
        break;
}