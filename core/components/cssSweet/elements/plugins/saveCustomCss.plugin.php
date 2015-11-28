<?php
/**
 * saveCustomCss
 * @author @sepiariver
 * Copyright 2013 - 2015 by YJ Tso <yj@modx.com> <info@sepiariver.com>
 *
 * saveCustomCss and cssSweet is free software; 
 * you can redistribute it and/or modify it under the terms of the GNU General 
 * Public License as published by the Free Software Foundation; 
 * either version 2 of the License, or (at your option) any later version.
 *
 * saveCustomCss and cssSweet is distributed in the hope that it will be useful, 
 * but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * saveCustomCss and cssSweet; if not, write to the Free Software Foundation, Inc., 
 * 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package cssSweet
 *
 */

// Never fire on the front end
if ($modx->context->get('key') !== 'mgr') return;
 
// In case the wrong event is enabled in plugin properties
if ($modx->event->name !== 'OnSiteRefresh' && $modx->event->name !== 'OnChunkFormSave') return;

// Dev mode option
$mode = ($modx->getOption('dev_mode', $scriptProperties, 0)) ? 'dev' : 'custom';
// Letting folks know what's going on
$modx->log(modX::LOG_LEVEL_INFO, 'saveCustomCss plugin is running in mode: ' . $mode);

// Specify a comma-separated list of chunk names in plugin properties
$chunks = array_filter(array_map('trim', explode(',', $modx->getOption($mode . '_scss_chunks', $scriptProperties, ''))));
// If no chunk names specified, there's nothing to do.
if (empty($chunks)) {
    $modx->log(modX::LOG_LEVEL_WARN, 'No chunks were set in the saveCustomCss plugin property: ' . $mode . '_scss_chunks. No action performed.');
    return;
}

// Don't run this for every ChunkSave event
if ($modx->event->name === 'OnChunkFormSave' && !in_array($chunk->get('name'), $chunks)) return;

// Specify an output file name in plugin properties
$filename = $modx->getOption($mode . '_css_filename', $scriptProperties, '');
if (empty($filename)) return;

// Optionally choose an output format if not minified
$css_output_format = $modx->getOption('css_output_format', $scriptProperties, 'Expanded');
$css_output_format_options = array('Expanded','Nested','Compact');
if (!in_array($css_output_format, $css_output_format_options)) $css_output_format = 'Expanded';

// Optionally minify the output, defaults to 'true' 
$minify_custom_css = (bool) $modx->getOption('minify_custom_css', $scriptProperties, true);
$css_output_format = ($minify_custom_css) ? 'Compressed' : $css_output_format;

// Strip CSS comment blocks; defaults to 'false'
$strip_comments = (bool) $modx->getOption('strip_css_comment_blocks', $scriptProperties, false);
$css_output_format = ($minify_custom_css && $strip_comments) ? 'Crunched' : $css_output_format;

// Optionally set base_path for scss imports
$scss_import_paths = $modx->getOption('scss_import_paths', $scriptProperties, '');
$scss_import_paths = (empty($scss_import_paths)) ? array() : array_filter(array_map('trim', explode(',', $scss_import_paths)));

// Get the output path; construct fallback; log for debugging
$csssCustomCssPath = $modx->getOption('custom_css_path', $scriptProperties, '');
if (empty($csssCustomCssPath)) $csssCustomCssPath = $modx->getOption('assets_path') . 'components/csssweet/';
$modx->log(modX::LOG_LEVEL_INFO, '$csssCustomCssPath is: ' . $csssCustomCssPath . ' on line: ' . __LINE__);
$csssCustomCssPath = rtrim($csssCustomCssPath, '/') . '/';

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

// Initialize settings array
$settings = array();

// Get context settings
$settings_ctx = $modx->getOption($mode . '_context_settings_context', $scriptProperties, '');

if ($settings_ctx) {
    $settings_ctx = $modx->getContext($settings_ctx);
    $settings = array_merge($settings, $settings_ctx->config);
}

// Grab the ClientConfig class
$ccPath = $modx->getOption('clientconfig.core_path', null, $modx->getOption('core_path') . 'components/clientconfig/');
$ccPath .= 'model/clientconfig/';
if (file_exists($ccPath . 'clientconfig.class.php')) $clientConfig = $modx->getService('clientconfig','ClientConfig', $ccPath);

// If we got the class (which means it's installed properly), include the settings
if ($clientConfig instanceof ClientConfig) {
    $settings = $clientConfig->getSettings();
    if (is_array($settings)) $settings = array_merge($settings, $context->config);
} else { 
    $modx->log(modX::LOG_LEVEL_WARN, 'Failed to load ClientConfig class. ClientConfig settings not included.','','saveCustomCssClientConfig'); 
}

/* Make settings available as [[++tags]] */
$modx->setPlaceholders($settings, '+');

// Parse chunk with $settings array
$contents = '';
foreach ($chunks as $current) {
    $processed = '';
    if ($current) {
        try {
            $modx->log(modX::LOG_LEVEL_INFO, 'Processing chunk: ' . $current);
            $processed = $modx->getChunk($current, $settings);
            if ($processed) {
                $contents .= $processed;
            } else {
                $err = '$modx->getChunk() failed on line: ' . __LINE__ . ' for chunk: ' . $current;
                throw new Exception($err);
            }
        } catch (Exception $err) {
            $modx->log(modX::LOG_LEVEL_ERROR, $err->getMessage());
        }
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to get Chunk ' . $current . '. Chunk contents not saved.');
    }
}
// If there's no result, what's the point?
if (empty($contents)) return;

// CSS comments
$contents = '/* Contents generated by MODX - this file will be overwritten. */' . PHP_EOL . $contents;
if (!$strip_comments) $contents = str_replace('/*', '/*!', $contents);

// Define target file
$append_version = $modx->getOption($mode . '_append_version_to_filename', $scriptProperties, 0);
if ($append_version) {
    $version_setting_key = 'csssweet.' . $mode . '_css_version';
    $version = null;
    $version_setting = $modx->getObject('modSystemSetting', array('key' => $version_setting_key));
    if ($version_setting) $version = $version_setting->get('value');
    if (is_numeric($version)) {
        $version++;
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, $version_setting_key . ' system setting was not a valid integer. RESETTING version to 1');
        $version = '1';
    }

    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $filename = substr($filename, 0, strlen($filename) - strlen($ext));
    $filename .= $version . '.' . $ext;
    $version_setting->set('value', $version);
    if (!$version_setting->save()) $modx->log(modX::LOG_LEVEL_ERROR, $version_setting_key . ' system setting could NOT be updated!');

}
$file = $csssCustomCssPath . $filename;

// Grab the cssSweet class
$cssSweetPath = $modx->getOption('csssweet.core_path', null, $modx->getOption('core_path') . 'components/csssweet/');
$cssSweetPath .= 'model/csssweet/';
if (file_exists($cssSweetPath . 'csssweet.class.php')) $csssweet = $modx->getService('csssweet', 'CssSweet', $cssSweetPath);

if (!($csssweet instanceof CssSweet)) {

    $modx->log(modX::LOG_LEVEL_ERROR, '[SaveCustomCss] could not load the required csssweet class! No minification performed.');

} else {

    // Init scssphp
    $scssMin = $csssweet->scssphpInit($scss_import_paths, $css_output_format);
    if ($scssMin) {

        try {
            $contents = $scssMin->compile($contents);
        } 
        catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage() . ' scss not compiled. minification not performed.','','saveCustomCss'); 
        }
        
    } else { 
        $modx->log(modX::LOG_LEVEL_ERROR, 'Failed to load scss class. scss not compiled. minification not performed.','','saveCustomCss'); 
    }
    
}

// If we failed scss and minification at least output what we have
file_put_contents($file, $contents);
if (file_exists($file) && is_readable($file)) $modx->log(modX::LOG_LEVEL_INFO, 'Success! Custom CSS saved to file "' . $file . '"', '', 'saveCustomCss');