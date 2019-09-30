<?php

/**
 * CssSweet wrapper class 
 * @package cssSweet
 *
 * @author @sepiariver <yj@modx.com> <info@sepiariver.com>
 * Copyright 2013 - 2015 by YJ Tso
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 **/

class CssSweet
{
    public $modx = null;
    public $namespace = 'csssweet';
    public $options = array();

    public function __construct(modX &$modx, array $options = array())
    {
        $this->modx = &$modx;
        $this->namespace = $this->getOption('namespace', $options, 'csssweet');

        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/csssweet/');
        $assetsPath = $this->getOption('assets_path', $options, $this->modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/csssweet/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/csssweet/');

        /* load config defaults */
        $this->options = array_merge(array(
            'namespace' => $this->namespace,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'vendorPath' => $corePath . 'model/vendor/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'connectorUrl' => $assetsUrl . 'connector.php',
        ), $options);

        require_once($this->options['vendorPath'] . 'autoload.php');
    }

    /**
     * Do special stuff to init scssphp classes
     *
     * @param array $path An array of import paths.
     * @param string $formatter The scssphp formatter class selector. Default 'Expanded'
     * @return object An instance of the $scssphp class.
     */
    public function scssphpInit($paths = array(), $formatter = 'Expanded')
    {
        $scssphp = null;

        // Instantiate Compiler
        $scssphp = new ScssPhp\ScssPhp\Compiler();
        if (!($scssphp instanceof \ScssPhp\ScssPhp\Compiler)) return null;

        // Set path
        $scssphp->setImportPaths($paths);

        // Set formatter
        $formatter = '\\ScssPhp\\ScssPhp\\Formatter\\' . $formatter;
        // Found this helpful
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Applying scssphp formatter class: ' . $formatter);

        $scssphp->setFormatter($formatter);

        // Ask and you shall receive
        return $scssphp;
    }

    public function jshrinkInit()
    {
        $jshrink = null;
        // Grab the JS minifier class
        $jshrink = new JShrink\Minifier();
        if (!($jshrink instanceof \JShrink\Minifier)) return null;
        return $jshrink;
    }

    public function getIris(string $value, $format = 'hex')
    {
        $iris = null;
        // Set format class
        $format = '\\OzdemirBurak\\Iris\\Color\\' . ucfirst(strtolower($format));
        // Grab the iris color format class
        $iris = new $format($value);
        if (!($iris instanceof $format)) return null;
        return $iris;
    }

    /**
     * Process and array of chunk (names) with provided $settings
     *
     * @param array $chunks An array of chunk names.
     * @param array $settings An array of settings/properties to pass to the chunks.
     * @return string A concatenated string of all processed chunk output.
     */
    public function processChunks(array $chunks, array $settings)
    {
        // Init var
        $contents = '';
        foreach ($chunks as $current) {

            $processed = '';
            if ($current) {
                try {
                    $this->modx->log(modX::LOG_LEVEL_INFO, 'Processing chunk: ' . $current);
                    $processed = $this->modx->getChunk($current, $settings);
                    if ($processed) {
                        $contents .= $processed;
                    } else {
                        $err = '$this->modx->getChunk() failed for chunk: ' . $current;
                        throw new Exception($err);
                    }
                } catch (Exception $err) {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, $err->getMessage());
                }
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Failed to get Chunk ' . $current . '. Chunk contents not saved.');
            }
        }
        // Even if contents is empty, return it
        return $contents;
    }

    /**
     * Instantiate ClientConfig and include settings
     *
     * @param array $settings An array of settings to merge into.
     * @return array Either the original array or a merged one.
     */
    public function getClientConfigSettings($settings)
    {
        // Init var
        $clientConfig = null;

        // Grab the ClientConfig class
        $ccPath = $this->modx->getOption('clientconfig.core_path', null, $this->modx->getOption('core_path') . 'components/clientconfig/');
        $ccPath .= 'model/clientconfig/';
        if (file_exists($ccPath . 'clientconfig.class.php')) $clientConfig = $this->modx->getService('clientconfig', 'ClientConfig', $ccPath);

        // If we got the class (which means it's installed properly), include the settings
        if ($clientConfig && ($clientConfig instanceof ClientConfig)) {
            $ccSettings = $clientConfig->getSettings();
            if (is_array($ccSettings)) $settings = array_merge($settings, $ccSettings);
        } else {
            $this->modx->log(modX::LOG_LEVEL_WARN, 'Failed to load ClientConfig class. ClientConfig settings not included.', '', 'saveCustomCssClientConfig');
        }

        // Settings may or may not be modified at this point
        return $settings;
    }

    public function getColorClass($input)
    {
        $input = trim($input);
        // Set color class
        $format = null;
        $unHash = false;
        $color = null;
        if (strpos($input, 'rgba') === 0) {
            $format = 'Rgba';
        } elseif (strpos($input, 'rgb') === 0) {
            $format = 'Rgb';
        } elseif (strpos($input, 'hsla') === 0) {
            $format = 'Hsla';
        } elseif (strpos($input, 'hsl') === 0) {
            $format = 'Hsl';
        } elseif (strpos($input, 'hsv') === 0) {
            $format = 'Hsv';
        } elseif (strpos($input, '#') === 0) {
            $format = 'Hex';
        } elseif (preg_match('/[a-fA-F0-9]{6}/', $input) || preg_match('/[a-fA-F0-9]{3}/', $input)) {
            $format = 'Hex';
            $input = '#' . $input;
            $unHash = true;
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[cssSweet.lighten] unsupported color format: ' . $input);
        }

        // Instantiate iris color class
        if ($format) {
            try {
                $color = $this->getIris($input, $format);
            } catch (\OzdemirBurak\Iris\Exceptions\InvalidColorException $e) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[cssSweet.lighten] InvalidColorException: ' . $e->getMessage());
            }
        }

        return [
            'format' => $format,
            'unHash' => $unHash,
            'color' => $color,
        ];
    }

    public function lightening($input, $options)
    {
        // Set color class
        $cc = $this->getColorClass($input);
        if (!$cc['color']) return '';
        $format = $cc['format'];
        $unHash = $cc['unHash'];
        $color = $cc['color'];

        // Set additional options
        preg_match('/(max)/', $options, $max);
        preg_match('/(rev)/', $options, $rev);
        $options = preg_replace('/[^0-9-]/', '', $options);

        // Light vs Dark
        $darkInput = $color->isDark();
        $lightInput = $color->isLight();

        // Shortcuts if using 'max' or 'rev' without percentage
        if (!$options) {
            // Default to 0 if no options percentage provided
            $perc = 0;
            $neg = false;
            // Set max/rev outputs 
            $wht = ($unHash) ? 'ffffff' : '#ffffff';
            $blk = ($unHash) ? '000000' : '#000000';
            // Return for max/rev
            if ($max) {
                if ($lightInput) return $wht;
                if ($darkInput) return $blk;
            }
            if ($rev) {
                if ($lightInput) return $blk;
                if ($darkInput) return $wht;
            }
        } else {
            // If ($options) process percentage
            $perc = (intval($options, 10) / 100);
            $neg = ($perc <= 0);
            $perc = min(abs($perc), 1) * 100;
            // Set max/rev outputs 
            $wht = $color->tint($perc);
            $blk = $color->shade($perc);
            // Return for max/rev
            if ($max) {
                if ($lightInput) return $wht;
                if ($darkInput) return $blk;
            }
            if ($rev) {
                if ($lightInput) return $blk;
                if ($darkInput) return $wht;
            }
        }

        // Lighten or darken the input
        $result = ($neg) ? $color->darken($perc) : $color->lighten($perc);

        // return processed hex color value
        if ($unHash && (strpos($result, '#') === 0)) {
            $result = substr($result, 1);
        }

        return $result;
    }

    public function modifying($input, $options)
    {
        // Get input: grab the first float in the string, then clean it for the unit
        $inputValue = floatval(trim($input));
        $unit = preg_replace('/[^a-zA-Z]/', '', trim($input, $inputValue));

        // Get options: operators go in an array, extract remaining float
        if (empty($options)) return $inputValue . $unit;
        preg_match('/[\+\-\*\/]/', $options, $op);
        $options = preg_replace('/[\+\-\*\/]/', '', $options);
        $optionValue = floatval(trim($options));

        // Only first operator
        $op = (empty($op[0])) ? '+' : $op[0];

        // Simple math only
        switch ($op) {
            case '-':
                $val = $inputValue - $optionValue;
                break;
            case '*':
                $val = $inputValue * $optionValue;
                break;
            case '/':
                $val = $inputValue / $optionValue;
                break;
            case '+':
            default:
                $val = $inputValue + $optionValue;
                break;
        }

        // Results
        return $val . $unit;
    }

    public function converting($input, $options = '')
    {
        // Set color class
        $cc = $this->getColorClass(trim($input));
        if (!$cc['color']) return '';
        $format = $cc['format'];
        $color = $cc['color'];

        // Clean options
        $options = ucfirst(strtolower(trim($options)));
        if (empty($options) || $options === $format) return $color;

        // Convert
        switch ($options) {
            case 'Rgb':
                return $color->toRgb();
                break;
            case 'Rgba':
                return $color->toRgba();
                break;
            case 'Hsl':
                return $color->toHsl();
                break;
            case 'Hsla':
                return $color->toHsla();
                break;
            case 'Hsv':
                return $color->toHsv();
                break;
            case 'Hex':
            default:
                return $color->toHex();
                break;
        }
    }

    public function saturating($input, $options)
    {
        // Set color class
        $cc = $this->getColorClass($input);
        if (!$cc['color']) return '';
        $format = $cc['format'];
        $unHash = $cc['unHash'];
        $color = $cc['color'];

        // Clean options
        if (empty($options)) return $color;

        // Saturate
        $perc = intval($options);
        $result = ($perc >= 0) ? $color->saturate($perc) : $color->desaturate(abs($perc));

        // return processed hex color value
        if ($unHash && (strpos($result, '#') === 0)) {
            $result = substr($result, 1);
        }

        return $result;
    }

    public function extracting($input, $options)
    {
        // Set color class
        $cc = $this->getColorClass($input);
        if (!$cc['color']) return '';
        $format = $cc['format'];
        $color = $cc['color'];

        // Channel map
        $channels = ['red' => 0, 'green' => 1, 'blue' => 2, 'alpha' => 3, 'r' => 0, 'g' => 1, 'b' => 2, 'a' => 3, '0' => 0, '1' => 1, '2' => 2, '3' => 3, 'hue' => 0, 'saturation' => 1, 'lightness' => 2, 'value' => 2, 'h' => 0, 's' => 1, 'l' => 2, 'v' => 2];

        // Clean options
        // Harder to troubleshoot if a color is returned here?
        if (empty($options)) return '';
        $o = (string) trim($options);
        if (!isset($channels[$o])) return '';

        $i = $channels[$o];
        $values =  $color->values();
        return $values[$i];
    }

    public function getProperties($properties, $mode)
    {
        if (!is_array($properties)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[cssSweet.getProperties] invalid $properties array.');
            return [];
        }
        $mode = (string) $mode;
        // Override properties with mode props
        foreach ($properties as $key => $val) {
            // skip any mode props
            if (strpos($key, $mode) === 0) continue;
            // these are standard scriptProperties
            $properties[$key] = (isset($properties[$mode . '_' . $key])) ? $properties[$mode . '_' . $key] : $val;
        }
        return $properties;
    }

    public function checkDir($path, $caller = 'csssweet.checkDir')
    {
        // If directory exists but isn't writable we have a problem, Houston
        if (file_exists($path) && !is_writable($path)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'The directory at ' . $path . 'is not writable!', '', $caller);
            return [
                'success' => false,
                'message' => 'The directory at ' . $path . 'is not writable!',
            ];
        }
        // Check if directory exists, if not, create it
        elseif (!file_exists($path)) {
            if (mkdir($path, 0755, true)) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Directory created at ' . $path, '', $caller);
                return [
                    'success' => true,
                    'message' => 'Directory created at ' . $path,
                ];
            } else {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Directory could not be created at ' . $path, '', $caller);
                return [
                    'success' => false,
                    'message' => 'Directory could not be created at ' . $path,
                ];
            }
        } else {
            return [
                'success' => true,
                'message' => 'Using output directory ' . $path,
            ];
        }
    }

    /* UTILITY METHODS (@theboxer) */

    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = array(), $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }
    /**
     * Despite the variable name, it takes a string and returns an array
     */
    public function explodeAndClean($array, $delimiter = ',')
    {
        $array = explode($delimiter, $array);     // Explode fields to array
        $array = array_map('trim', $array);       // Trim array's values
        $array = array_keys(array_flip($array));  // Remove duplicate fields
        $array = array_filter($array);            // Remove empty values from array

        return $array;
    }
    public function getChunk($tpl, $phs)
    {
        if (strpos($tpl, '@INLINE ') !== false) {
            $content = str_replace('@INLINE', '', $tpl);
            /** @var \modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk', array('name' => 'inline-' . uniqid()));
            $chunk->setCacheable(false);

            return $chunk->process($phs, $content);
        }

        return $this->modx->getChunk($tpl, $phs);
    }
}
