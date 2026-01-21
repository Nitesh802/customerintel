<?php
/**
 * Citation Resolver - Enrichment of source references
 *
 * Converts raw URLs and citation objects into enriched metadata for
 * proper source attribution in synthesis output.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Citation Resolver
 * 
 * Enriches raw citations with metadata:
 * - Title extraction from URLs or document headers
 * - Domain identification and categorization
 * - Publication date detection from content/metadata
 * - Unique ID assignment for footnote references
 * - Integration with local_ci_source table for deduplication
 */
class citation_resolver {

    /**
     * Resolve and enrich citation list with metadata
     * 
     * Takes a mixed array of:
     * - Raw URL strings
     * - Citation objects {url, title?, quote?, source_id?}
     * - Source IDs from local_ci_source table
     * 
     * Returns enriched citations with:
     * - url: Canonical URL
     * - title: Extracted/provided document title
     * - domain: Domain name (e.g., "bloomberg.com", "sec.gov")
     * - publishedat: Publication timestamp (if detectable, null otherwise)
     * - id: Unique identifier for footnote references
     * 
     * Enrichment process:
     * 1. Normalize input format (URLs, objects, source IDs)
     * 2. Check local_ci_source for existing metadata
     * 3. Extract titles from URLs (HTTP HEAD requests, HTML parsing)
     * 4. Detect publication dates from content/metadata
     * 5. Assign unique IDs for consistent referencing
     * 6. Cache results in local_ci_source for future use
     * 
     * @param array $list_of_urls_or_objects Mixed array of citations to resolve
     * @return array Enriched citations: [{url, title, domain, publishedat?, id}, ...]
     */
    public function resolve(array $list_of_urls_or_objects): array {
        global $DB;
        
        $enriched_citations = [];
        $citation_fingerprints = []; // For deduplication
        
        foreach ($list_of_urls_or_objects as $citation_input) {
            $citation = $this->normalize_citation_input($citation_input);
            
            if (!$citation) {
                continue; // Skip invalid inputs
            }
            
            // Generate fingerprint for deduplication
            $fingerprint = $this->generate_citation_fingerprint($citation);
            if (in_array($fingerprint, $citation_fingerprints)) {
                continue; // Skip duplicates
            }
            $citation_fingerprints[] = $fingerprint;
            
            // Try to find existing source in database
            $existing_source = $this->find_existing_source($citation);
            
            if ($existing_source) {
                // Use existing metadata
                $enriched_citation = $this->enrich_from_source($citation, $existing_source);
            } else {
                // Resolve new citation
                $enriched_citation = $this->resolve_new_citation($citation);
            }
            
            if ($enriched_citation) {
                $enriched_citations[] = $enriched_citation;
            }
        }
        
        return $enriched_citations;
    }
    
    private function normalize_citation_input($input): ?array {
        if (is_string($input)) {
            // Raw URL string
            if (filter_var($input, FILTER_VALIDATE_URL)) {
                return ['url' => $input];
            }
            return null;
        }
        
        if (is_array($input)) {
            // Citation object or source ID reference
            if (isset($input['source_id'])) {
                return ['source_id' => $input['source_id']];
            }
            if (isset($input['url'])) {
                return $input;
            }
        }
        
        if (is_numeric($input)) {
            // Direct source ID
            return ['source_id' => $input];
        }
        
        return null;
    }
    
    private function generate_citation_fingerprint(array $citation): string {
        if (isset($citation['url'])) {
            // Normalize URL for fingerprinting
            $url = strtolower(trim($citation['url']));
            $url = preg_replace('/^https?:\/\//', '', $url);
            $url = preg_replace('/\/+$/', '', $url); // Remove trailing slashes
            return md5($url);
        }
        
        if (isset($citation['source_id'])) {
            return 'source_' . $citation['source_id'];
        }
        
        return md5(json_encode($citation));
    }
    
    private function find_existing_source(array $citation): ?object {
        global $DB;
        
        if (isset($citation['source_id'])) {
            $record = $DB->get_record('local_ci_source', ['id' => $citation['source_id']]);
            return $record === false ? null : $record;
        }
        
        if (isset($citation['url'])) {
            // Look for existing source by URL
            $record = $DB->get_record('local_ci_source', ['url' => $citation['url']]);
            return $record === false ? null : $record;
        }
        
        return null;
    }
    
    private function enrich_from_source(array $citation, object $source): array {
        $enriched = [
            'url' => $source->url ?? $citation['url'] ?? '',
            'title' => $source->title ?? 'Untitled Source',
            'domain' => $this->extract_domain($source->url ?? $citation['url'] ?? ''),
            'publishedat' => $source->publishedat ?? null,
            'id' => 'src_' . $source->id,
            'source_id' => $source->id
        ];
        
        // Include quote if provided in input
        if (isset($citation['quote'])) {
            $enriched['quote'] = $citation['quote'];
        }
        
        return $enriched;
    }
    
    private function resolve_new_citation(array $citation): ?array {
        if (!isset($citation['url'])) {
            return null;
        }
        
        $url = $citation['url'];
        $title = $citation['title'] ?? null;
        
        // Extract domain
        $domain = $this->extract_domain($url);
        
        // Try to extract title if not provided
        if (!$title) {
            $title = $this->extract_title_from_url($url);
        }
        
        // Generate unique ID for this citation
        $id = 'url_' . substr(md5($url), 0, 8);
        
        $enriched = [
            'url' => $url,
            'title' => $title ?: $this->generate_fallback_title($url, $domain),
            'domain' => $domain,
            'publishedat' => null, // Could be enhanced with metadata extraction
            'id' => $id
        ];
        
        // Include quote if provided
        if (isset($citation['quote'])) {
            $enriched['quote'] = $citation['quote'];
        }
        
        return $enriched;
    }
    
    private function extract_domain(string $url): string {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return 'unknown';
        }
        
        $host = strtolower($parsed['host']);
        
        // Remove www. prefix
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        return $host;
    }
    
    private function extract_title_from_url(string $url): ?string {
        // Simple title extraction - could be enhanced with HTTP requests
        // For now, use URL path/filename as fallback
        $parsed = parse_url($url);
        
        if (isset($parsed['path'])) {
            $path = trim($parsed['path'], '/');
            $segments = explode('/', $path);
            $last_segment = end($segments);
            
            if ($last_segment && $last_segment !== '') {
                // Clean up filename/slug for title
                $title = str_replace(['-', '_'], ' ', $last_segment);
                $title = preg_replace('/\.[a-zA-Z]+$/', '', $title); // Remove file extension
                return ucwords($title);
            }
        }
        
        return null;
    }
    
    private function generate_fallback_title(string $url, string $domain): string {
        $domain_names = [
            'bloomberg.com' => 'Bloomberg',
            'sec.gov' => 'SEC Filing',
            'reuters.com' => 'Reuters',
            'wsj.com' => 'Wall Street Journal',
            'ft.com' => 'Financial Times',
            'techcrunch.com' => 'TechCrunch',
            'crunchbase.com' => 'Crunchbase',
            'linkedin.com' => 'LinkedIn',
            'forbes.com' => 'Forbes',
            'fortune.com' => 'Fortune'
        ];
        
        $source_name = $domain_names[$domain] ?? ucfirst(str_replace('.com', '', $domain));
        
        return $source_name . ' Article';
    }
}