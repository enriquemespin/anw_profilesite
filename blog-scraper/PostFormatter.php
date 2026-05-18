<?php
/**
 * Post Formatter
 * Formats blog posts using the framework:
 * - Summary: What's the post about?
 * - What's new: Key findings or updates
 * - Question: What should readers consider?
 * - Next steps: Actionable takeaways
 */

class PostFormatter {
    
    /**
     * Format a single post using the framework
     */
    public function formatPost($post) {
        return [
            'title' => $this->sanitize($post['title']),
            'url' => $post['post_url'],
            'source' => $post['source_name'],
            'published' => $post['published_date'],
            'framework' => [
                'summary' => $this->generateSummary($post),
                'whats_new' => $this->generateWhatsNew($post),
                'question' => $this->generateQuestion($post),
                'next_steps' => $this->generateNextSteps($post)
            ]
        ];
    }
    
    /**
     * Generate summary text
     */
    private function generateSummary($post) {
        // Use description if available, otherwise use title
        $description = isset($post['description']) ? $post['description'] : '';
        
        if (!empty($description)) {
            // Strip HTML tags
            $text = strip_tags($description);
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            // Remove extra whitespace
            $text = preg_replace('/\s+/', ' ', $text);
            // Truncate to 250 characters
            if (strlen($text) > 250) {
                $text = substr($text, 0, 250);
                // Don't cut in the middle of a word
                $text = substr($text, 0, strrpos($text, ' '));
                $text .= '...';
            }
            return $text;
        }
        
        // Fallback: use title as summary
        return $post['title'];
    }
    
    /**
     * Generate "What's new" section
     */
    private function generateWhatsNew($post) {
        // Extract key points from description
        $description = isset($post['description']) ? $post['description'] : '';
        
        if (!empty($description)) {
            $text = strip_tags($description);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            
            // Try to extract 2-3 key sentences
            $sentences = preg_split('/(?<=[.!?])\s+/', $text);
            $keyPoints = array_slice($sentences, 0, 3);
            
            if (!empty($keyPoints)) {
                return implode(' ', $keyPoints);
            }
        }
        
        return "Key findings and updates from this publication.";
    }
    
    /**
     * Generate thought-provoking question
     */
    private function generateQuestion($post) {
        $title = strtolower($post['title']);
        $description = isset($post['description']) ? strtolower($post['description']) : '';
        $content = $title . ' ' . $description;
        
        // Generate context-specific questions
        if (strpos($content, 'vulnerability') !== false || strpos($content, 'exploit') !== false) {
            return "How vulnerable is your infrastructure to this type of attack?";
        }
        
        if (strpos($content, 'ransomware') !== false || strpos($content, 'malware') !== false) {
            return "Are your backup and recovery procedures tested against modern ransomware tactics?";
        }
        
        if (strpos($content, 'data breach') !== false || strpos($content, 'leak') !== false) {
            return "What sensitive data could be exposed if a similar breach occurred in your organization?";
        }
        
        if (strpos($content, 'apt') !== false || strpos($content, 'nation-state') !== false || strpos($content, 'advanced persistent') !== false) {
            return "Does your threat detection strategy account for nation-state level adversaries?";
        }
        
        if (strpos($content, 'phishing') !== false || strpos($content, 'social engineering') !== false) {
            "When was your last phishing simulation test, and how did your team perform?";
        }
        
        if (strpos($content, 'zero-day') !== false || strpos($content, '0day') !== false) {
            "How quickly can your organization patch against zero-day vulnerabilities?";
        }
        
        // Default question
        return "What implications does this development have for your security posture?";
    }
    
    /**
     * Generate actionable next steps
     */
    private function generateNextSteps($post) {
        $title = strtolower($post['title']);
        $description = isset($post['description']) ? strtolower($post['description']) : '';
        $content = $title . ' ' . $description;
        
        $steps = [];
        
        // Generic first step
        $steps[] = "Review your current security controls against this threat";
        
        // Context-specific steps
        if (strpos($content, 'vulnerability') !== false || strpos($content, 'patch') !== false) {
            $steps[] = "Check if your systems are affected and apply patches immediately";
        }
        
        if (strpos($content, 'ransomware') !== false || strpos($content, 'backup') !== false) {
            $steps[] = "Verify your backup integrity and test restoration procedures";
        }
        
        if (strpos($content, 'phishing') !== false || strpos($content, 'email') !== false) {
            $steps[] = "Update email filtering rules and conduct user awareness training";
        }
        
        if (strpos($content, 'cloud') !== false || strpos($content, 'aws') !== false || strpos($content, 'azure') !== false) {
            $steps[] = "Audit cloud configurations and enforce least-privilege access";
        }
        
        if (strpos($content, 'supply chain') !== false || strpos($content, 'vendor') !== false) {
            $steps[] = "Review third-party vendor security assessments";
        }
        
        if (strpos($content, 'iot') !== false || strpos($content, 'device') !== false) {
            $steps[] = "Inventory IoT devices and segment them from critical networks";
        }
        
        // Always include monitoring step
        $steps[] = "Enhance monitoring and detection for related indicators of compromise";
        
        return implode(' → ', array_slice($steps, 0, 3));
    }
    
    /**
     * Sanitize text
     */
    private function sanitize($text) {
        return htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format multiple posts
     */
    public function formatPosts($posts) {
        $formatted = [];
        foreach ($posts as $post) {
            $formatted[] = $this->formatPost($post);
        }
        return $formatted;
    }
    
    /**
     * Generate blog post content for publishing
     */
    public function generateBlogPost($formattedPost) {
        $framework = $formattedPost['framework'];
        
        $content = <<<HTML
<h2>{$formattedPost['title']}</h2>
<p><em>Source: <a href="{$formattedPost['url']}" target="_blank">{$formattedPost['source']}</a></em></p>

<h3>Summary</h3>
<p>{$framework['summary']}</p>

<h3>What's New</h3>
<p>{$framework['whats_new']}</p>

<h3>Question to Consider</h3>
<p>{$framework['question']}</p>

<h3>Next Steps</h3>
<p>{$framework['next_steps']}</p>

<hr>
<p><small>Originally published at <a href="{$formattedPost['url']}" target="_blank">{$formattedPost['source']}</a>. This summary is for informational purposes.</small></p>
HTML;
        
        return $content;
    }
    
    /**
     * Generate markdown version
     */
    public function generateMarkdown($formattedPost) {
        $framework = $formattedPost['framework'];
        
        $markdown = <<<MARKDOWN
## {$formattedPost['title']}

*Source: [{$formattedPost['source']}]({$formattedPost['url']})*

### Summary
{$framework['summary']}

### What's New
{$framework['whats_new']}

### Question to Consider
{$framework['question']}

### Next Steps
{$framework['next_steps']}

---
*Originally published at [{$formattedPost['source']}]({$formattedPost['url']}). This summary is for informational purposes.*
MARKDOWN;
        
        return $markdown;
    }
}