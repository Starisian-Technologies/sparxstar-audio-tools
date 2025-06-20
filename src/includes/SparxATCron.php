<?php // In: src/includes/SparxATCron.php
namespace SPARXSTAR\src\includes;

if ( ! defined( 'ABSPATH' ) ) exit;

use function add_action;
use function get_attached_file; 
use function get_field;
use function get_post_thumbnail_id;
use function get_the_title;
use function has_post_thumbnail;
use function update_post_meta;
use function do_action;



class SparxATCron {
    public static function init() {
        // ... any existing cron events ...
        
        // NEW: Hook for our metadata job
        add_action('sparxat_update_id3_tags_hook', [self::class, 'update_id3_tags']);
    }

    /**
     * The main background job for writing ID3 tags to an audio file.
     */
    public static function update_id3_tags($post_id) {
        $audio_attachment_id = get_field('field_678ea0f178a96', $post_id);
        $file_path = get_attached_file($audio_attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            update_post_meta($post_id, '_SparxAT_status', 'failed_metadata_missing_file');
            return;
        }

        // --- 1. Gather all data from the "Single Source of Truth" (WordPress) ---
        $tag_data = [];
        $tag_data['title'][]  = get_the_title($post_id);
        $tag_data['year'][]   = get_field('copyright_year', $post_id); // field_678e9fa878a93
        $tag_data['comment'][] = 'Copyright ' . get_field('copyright_owner', $post_id); // field_678e9ed978a92
        $tag_data['TSRC'][]   = get_field('irsc_number', $post_id); // ISRC field_678e9c6e78a8b
        
        // Get related Artist post
        $artist_posts = get_field('byArtist', $post_id); // field_678e9b5978a89
        if (!empty($artist_posts)) {
            $tag_data['artist'][] = $artist_posts[0]->post_title;
        }
        
        // --- THIS IS THE KEY: Getting the Album and Album Art ---
        // First find which album this track belongs to. This assumes a relationship
        // field on the Track CPT pointing to the Album CPT.
        $albums = get_posts([
            'post_type' => 'album',
            'meta_query' => [
                [
                    'key' => 'track', // From your ACF Repeater: field_678ebf7faacd5
                    'value' => '"' . $post_id . '"',
                    'compare' => 'LIKE'
                ]
            ]
        ]);

        if (!empty($albums)) {
            $album_post = $albums[0];
            $tag_data['album'][] = $album_post->post_title;
            
            // Now get the album's featured image
            if (has_post_thumbnail($album_post->ID)) {
                $image_id = get_post_thumbnail_id($album_post->ID);
                $image_path = get_attached_file($image_id);
                if ($image_path && file_exists($image_path)) {
                    $tag_data['attached_picture'][0] = [
                        'data' => file_get_contents($image_path),
                        'picturetypeid' => 3, // 3 for "Cover (front)"
                        'description' => 'Cover',
                        'mime' => mime_content_type($image_path)
                    ];
                }
            }
        }

        // --- 2. Write the tags using the PHP ID3 Library ---
        require_once SPARXAT_PATH . 'libs/getid3/getid3.php';
        require_once SPARXAT_PATH . 'libs/getid3/write.php';
        
        $tagwriter = new \getid3_writetags;
        $tagwriter->filename       = $file_path;
        $tagwriter->tagformats     = ['id3v2.3', 'id3v1']; // Set desired formats
        $tagwriter->overwrite_tags = true;
        $tagwriter->tag_data       = $tag_data;

        if ($tagwriter->WriteTags()) {
            update_post_meta($post_id, '_SparxAT_status', 'metadata_complete');
            update_post_meta($post_id, '_SparxAT_report_message', 'ID3 tags updated successfully.');
            
            // --- 3. Hand off to the AI Mastering process ---
            // Now that the file is tagged, we can call your existing submission hook.
            do_action('SparxAT_submit_to_mastering_service', $post_id, $file_path);

        } else {
            update_post_meta($post_id, '_SparxAT_status', 'failed_metadata_write');
            update_post_meta($post_id, '_SparxAT_report_message', 'Error writing ID3 tags: ' . implode('; ', $tagwriter->errors));
        }
    }
}