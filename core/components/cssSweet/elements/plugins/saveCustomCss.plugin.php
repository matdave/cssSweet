<?php
/**
 * saveCustomCss
 * @author @sepiariver
 * Copyright 2013 by YJ Tso <yj@modx.com>
 *
 * saveCustomCss and cssSweet is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * saveCustomCss and cssSweet is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * saveCustomCss and cssSweet; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package cssSweet
 *
 * TO DOs: change file_exists check on line 66 to is_dir? Better check on last line OR more accurate return notice.
 */
// Dev mode option
$mode = ($modx->getOption('dev_mode', $scriptProperties, 0)) ? 'dev' : 'custom';

// In case the wrong event is enabled in plugin properties
if ($modx->event->name !== 'OnSiteRefresh' && $modx->event->name !== 'OnChunkFormSave') return;

// Optionally a comma-separated list of chunk names can be specified in plugin properties
$chunks = array_map('trim', explode(',', $modx->getOption($mode . '_css_chunk', $scriptProperties, 'csss.custom.css')));

// Don't run this for every ChunkSave event
if ($modx->event->name === 'OnChunkFormSave' && !in_array($chunk->get('name'), $chunks)) return;

// Optionally a file name can be specified in plugin properties
$filename = $modx->getOption($mode . '_css_filename', $scriptProperties, 'csss-custom.css');

// Optionally minify the output, defaults to 'true'
$minify_custom_css = $modx->getOption('minify_custom_css', $scriptProperties, true);
// strips CSS comment blocks, defaults to 'false'
$strip_comments = $modx->getOption('strip_css_comment_blocks', $scriptProperties, false);

// Construct path from system settings - can be set in properties as of v.1.1
$csssCustomCssPath = $modx->getOption('custom_css_path', $scriptProperties, $modx->getOption('assets_path') . 'components/csssweet/');
$csssCustomCssPath = rtrim($csssCustomCssPath, '/') . '/';

// Grab the ClientConfig class
$ccPath = $modx->getOption('clientconfig.core_path', null, $modx->getOption('core_path') . 'components/clientconfig/');
$ccPath .= 'model/clientconfig/';
if (file_exists($ccPath . 'clientconfig.class.php')) $clientConfig = $modx->getService('clientconfig','ClientConfig', $ccPath);
$settings = array();

// If we got the class (which means it's installed properly), include the settings
if ($clientConfig instanceof ClientConfig) {
    $settings = $clientConfig->getSettings();
    /* Make settings available as [[++tags]] */
    $modx->setPlaceholders($settings, '+');
} else { 
    $modx->log(modX::LOG_LEVEL_WARN, 'Failed to load ClientConfig class. ClientConfig settings not included.','','saveCustomCssClientConfig'); 
}

// If directory exists but isn't writable we have a problem, Houston
if (file_exists($csssCustomCssPath) && !is_writable($csssCustomCssPath)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'The directory at ' . $csssCustomCssPath . 'is not writable!','','saveCustomCss');
    return;
}
    
// Check if directory exists, if not, create it
if (!file_exists($csssCustomCssPath)) {
    if (mkdir($csssCustomCssPath, 0755, true)) {
        $modx->log(modX::LOG_LEVEL_INFO, 'Directory created at ' . $csssCustomCssPath, '', 'saveCustomCss');
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Directory could not be created at ' . $csssCustomCssPath, '', 'saveCustomCss');
        return;
    }
}

// Parse chunk with $settings array
$contents = '';
foreach ($chunks as $current) {
    $processed = '';
    if ($current) $processed = $modx->getChunk($current, $settings);
    if ($processed) { 
        $contents .= $processed;    
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to get Chunk ' . $current . '. Custom CSS not saved.', '', 'saveCustomCss');
    }
}
if (empty($contents)) return;
$contents = '/* Contents generated by MODX - this file will be overwritten. */' . PHP_EOL . $contents;

// Define target file
$file = $csssCustomCssPath . $filename;

// Output
if ($minify_custom_css) {
    $contents = preg_replace("/\s+/", " ", $contents);
    $expanded = array(' {', '{ ', ' }', '} ', ' :', ': ', ' ;', '; ', ', ', ' ,');
    $contracted = array('{', '{', '}', '}', ':', ':', ';', ';', ',', ',');
    $contents = str_replace($expanded, $contracted, $contents);
    if ($strip_comments) {
        $contents = preg_replace("/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//", " ", $contents);
    }
} 
file_put_contents($file, $contents);
if (file_exists($file) && is_readable($file)) $modx->log(modX::LOG_LEVEL_INFO, 'Success! Custom CSS saved to file "' . $file . '"', '', 'saveCustomCss');