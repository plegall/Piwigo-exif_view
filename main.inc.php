<?php /*
Plugin Name: Exif View
Version: 2.7.a
Description: Converts EXIF values to human readable localized values. Corresponds to EXIF specification 2.2, details in http://www.exif.org. Easily extensible.
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=155
Author: Martin Javorek
Author URI: mailto:maple@seznam.cz&subject=PWG%20EXIF%20View
*/

/*
-------------------------------------------------------------------------------
Change log:

0.2, 23th August 2007
- exposurue bias fix, date time original formatting

0.1, 1st August 2007
- initial version


-------------------------------------------------------------------------------

Extend your configuration in /include/config.local.inc.php file - example:

$conf['show_exif_fields'] = array(
  'Make',
  'Model',
  'ExifVersion',
  'Software',
  'DateTimeOriginal',
  'FNumber',
  'ExposureBiasValue',
  'FILE;FileSize',
  'ExposureTime',
  'Flash',
  'ISOSpeedRatings',
  'FocalLength',
  'FocalLengthIn35mmFilm',
  'WhiteBalance',
  'ExposureMode',
  'MeteringMode',
  'ExposureProgram',
  'LightSource',
  'Contrast',
  'Saturation',
  'Sharpness',
  );

*/

add_event_handler('format_exif_data', 'exif_translation' );

/**
 * Date and time format.
 * @see http://cz2.php.net/manual/en/function.date.php
 */
define('DATE_TIME_FORMAT', 'H:i:s j.n.Y');

/**
 * Truncates number.
 *
 * @param num number
 * @param digits number of digits, default 0
 */
function truncate($num, $digits = 0) {
    $shift = pow(10 , $digits);
    return ((floor($num * $shift)) / $shift);
}

/**
 * Format date.
 *
 * @param date given EXIF date
 */
function formatDate($date) {
	$dateTime = explode(' ', $date);
	$d = explode(':', $dateTime[0]);
	$t = explode(':', $dateTime[1]);
	// beware of american date format for mktime, it accepts date in M/D/Y ;-)
	return date(DATE_TIME_FORMAT, mktime($t[0], $t[1], $t[2], $d[1], $d[2], $d[0]));
}

/**
 * EXIF translation.
 *
 * @param $key EXIF key name
 * @param $value EXIF key value
 * @return translated value depending on key meaning and choosed language
 */
function exif_key_translation($key, $value) {
   // EXIF
	if (!(strpos($key, 'ExifVersion') === FALSE)) {
      return $value[1].'.'.$value[2];
   }
   
   // Date Time Original
   if (!(strpos($key, 'DateTimeOriginal') === FALSE)) {
     // to fix bug:1862 the easiest way without releasing a new version of
     // Piwigo itself, it's better to bypass the date format function
     //
     // return formatDate($value);
     return $value;
   }

   // exposure time
	 if (!(strpos($key, 'ExposureTime') === FALSE)) {
      $tokens = explode('/', $value);

      if (isset($tokens[1]))
      {
        if ($tokens[1] > 0)
        {
          while ($tokens[0] % 10 == 0)
          {
            $tokens[0] = $tokens[0] / 10;
            $tokens[1] = $tokens[1] / 10;
          }
          
          if ($tokens[1] == 1)
          {
            return $tokens[0].' s';
          }
          else
          {
            return '1/'.floor(1/($tokens[0]/$tokens[1])).' s';
          }
        }
        else
        {
          return $tokens[0].' s';
        }
      }
      else
      {
        return $value.' s';
      }
   }

   // aperture
	 if (!(strpos($key, 'FNumber') === FALSE)) {
      $tokens = explode('/', $value);
      return $tokens[0]/$tokens[1];
   }

   // flash
   if (!(strpos($key, 'Flash') === FALSE)) {
      // 1st bit is fired/did not fired
      if (($value & 1) > 0) {
         $retValue = l10n('yes');
      } else {
         $retValue = l10n('no');
      }
      // 2nd+3rd bits are return light mode
      $returnLight = $value & (3 << 1);
      switch ($returnLight) {
        case 2 << 1: $retValue .= ', '.l10n('exif_value_flash_return_light_not_detected');break;
        case 3 << 1: $retValue .= ', '.l10n('exif_value_flash_return_light_detected');break;
      }
      // 4th+5th bits are mode
      $mode = $value & (3 << 3);
      switch ($mode) {
        case 0: $retValue .= ', '.l10n('exif_value_flash_mode').': '.l10n('exif_value_flash_mode_unknown');break;
        case 1 << 3: $retValue .= ', '.l10n('exif_value_flash_mode').': '.l10n('exif_value_flash_mode_compulsory');break;
        case 2 << 3: $retValue .= ', '.l10n('exif_value_flash_mode').': '.l10n('exif_value_flash_mode_supress');break;
        case 3 << 3: $retValue .= ', '.l10n('exif_value_flash_mode').': '.l10n('exif_value_flash_mode_auto');break;
      }
			// 6th bit is red eye function
      if (($value & (1 << 6)) > 0) {
         $retValue .= ', '.l10n('exif_value_red_eye');
      }
      return $retValue;
   }

   // exposure bias
   if (!(strpos($key, 'ExposureBiasValue') === FALSE)) {
      $tokens = explode('/', $value);
      $newValue = $tokens[0] / $tokens[1];
      // max EV range +-
      $maxEV = 5;
      // default value
      $retValue = $newValue;
      $absValue = truncate(abs($newValue), 2);
      $found = FALSE;
      // find through 1/3
      for ($i = 1; $i <= $maxEV * 3 ; $i++) {
         $ev = floor($i * 1/3.0 * 100) / 100;
         if ($ev == $absValue) {
            if ($i > 3) {
               $retValue = (truncate($i / 3)).' '.($i % 3).'/3';
            } else {
               $retValue = $i.'/3';
            }
            $found = TRUE;
            break;
         }
      }
      // find through 1/2
      if (!$found) {
         for ($i = 1; $i <= $maxEV * 2 ; $i++) {
            $ev = floor($i * 1/2.0 * 100) / 100;
            if ($ev == $absValue) {
               if ($i > 2) {
                  $retValue = ($i / 2).' '.($i % 2).'/2';
               } else {
                  $retValue = $i.'/2';
               }
               $found = TRUE;
               break;
            }
         }
      }
      // signs
      if (($newValue < 0) && $found) {
         $retValue = '- '.$retValue;
      }
      if ($newValue > 0) {
         $retValue = '+ '.$retValue;
      }
      return $retValue.' EV';
   }

   // focal length 35mm
   if (!(strpos($key, 'FocalLengthIn35mmFilm') === FALSE)) {
      return $value.' mm';
   }

   // focal length
   if (!(strpos($key, 'FocalLength') === FALSE)) {
      $tokens = explode('/', $value);
      return (round($tokens[0]/$tokens[1])).' mm';
   }

   // digital zoom
   if (!(strpos($key, 'DigitalZoomRatio') === FALSE)) {
      $tokens = explode('/', $value);
      if (isset($tokens[1]))
      {
        if ($tokens[1] > 0)
        {
          return ($tokens[0]/$tokens[1]);
        }
        else
        {
          return $tokens[0];
        }
      }
      else
      {
        return $value;
      }
   }

   // distance to subject
   if (!(strpos($key, 'SubjectDistance') === FALSE)) {
      $tokens = explode('/', $value);
      if (isset($tokens[1]))
      {  
        $distance = $tokens[0]/$tokens[1];
      }
      else
      {  
        $distance = $value;
      }
      return $distance.' m';
   }

   // white balance
   if (!(strpos($key, 'WhiteBalance') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_white_balance_auto');
         case 1: return l10n('exif_value_white_balance_manual');
         default: return '';
      }
   }

   // exposure mode
   if (!(strpos($key, 'ExposureMode') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_exposure_mode_auto');
         case 1: return l10n('exif_value_exposure_mode_manual');
         case 2: return l10n('exif_value_exposure_mode_auto_bracket');
         default: return '';
      }
   }

   // exposure metering mode
   if (!(strpos($key, 'MeteringMode') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_metering_mode_unknown');
         case 1: return l10n('exif_value_metering_mode_average');
         case 2: return l10n('exif_value_metering_mode_CenterWeightedAVG');
         case 3: return l10n('exif_value_metering_mode_spot');
         case 4: return l10n('exif_value_metering_mode_multispot');
         case 5: return l10n('exif_value_metering_mode_pattern');
         case 6: return l10n('exif_value_metering_mode_partial');
         default: return '';
      }
   }

   // exposure program
   if (!(strpos($key, 'ExposureProgram') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_exposure_program_not_defined');
         case 1: return l10n('exif_value_exposure_program_manual');
         case 2: return l10n('exif_value_exposure_program_normal');
         case 3: return l10n('exif_value_exposure_program_aperture');
         case 4: return l10n('exif_value_exposure_program_shutter');
         case 5: return l10n('exif_value_exposure_program_creative');
         case 6: return l10n('exif_value_exposure_program_action');
         case 7: return l10n('exif_value_exposure_program_portrait');
         case 8: return l10n('exif_value_exposure_program_landscape');
         default: return '';
      }
   }
   
   // light source
   if (!(strpos($key, 'LightSource') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_light_source_unknown');
         case 1: return l10n('exif_value_light_source_daylight');
         case 2: return l10n('exif_value_light_source_fluorescent');
         case 3: return l10n('exif_value_light_source_tungsten');
         case 4: return l10n('exif_value_light_source_flash');
         case 9: return l10n('exif_value_light_source_fine_weather');
         case 10: return l10n('exif_value_light_source_cloudy_weather');
         case 11: return l10n('exif_value_light_source_shade');
         case 12: return l10n('exif_value_light_source_daylight_fluorescent_d');
         case 13: return l10n('exif_value_light_source_daywhite_fluorescent_n');
         case 14: return l10n('exif_value_light_source_coolwhite_fluorescent_w');
         case 15: return l10n('exif_value_light_source_white_fluorescent');
         case 17: return l10n('exif_value_light_source_standard_light_a');
         case 18: return l10n('exif_value_light_source_standard_light_b');
         case 19: return l10n('exif_value_light_source_standard_light_c');
         case 20: return l10n('exif_value_light_source_D55');
         case 21: return l10n('exif_value_light_source_D65');
         case 22: return l10n('exif_value_light_source_D75');
         case 23: return l10n('exif_value_light_source_D50');
         case 24: return l10n('exif_value_light_source_iso_studio_tungsten');
         case 255: return l10n('exif_value_light_source_other');
         default: return '';
      }
   }

   // contrast
   if (!(strpos($key, 'Contrast') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_contrast_normal');
         case 1: return l10n('exif_value_contrast_soft');
         case 2: return l10n('exif_value_contrast_hard');
         default: return '';
      }
   }

   // sharpness
   if (!(strpos($key, 'Sharpness') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_sharpness_normal');
         case 1: return l10n('exif_value_sharpness_soft');
         case 2: return l10n('exif_value_sharpness_hard');
         default: return '';
      }
   }

   // saturation
   if (!(strpos($key, 'Saturation') === FALSE)) {
      switch ($value) {
         case 0: return l10n('exif_value_saturation_normal');
         case 1: return l10n('exif_value_saturation_low');
         case 2: return l10n('exif_value_saturation_hard');
         default: return '';
      }
   }

   // return value unchanged
   return $value;
}
define('exif_DIR' , basename(dirname(__FILE__)));
define('exif_PATH' , PHPWG_PLUGINS_PATH . exif_DIR . '/');
	/**
	 * Loads plugin language file.
	 */
  function loadLang() {
    global $lang;
    load_language('lang.exif', exif_PATH);
  }

/**
 * EXIF translation.
 *
 * @param $key EXIF key name
 * @param $value EXIF key value
 * @return translated value dependend on key meaning and choosed language
 */
function exif_translation($exif) {
	 // translate all exif fields
	 if (is_array($exif)) {
   	 loadLang();

	   foreach ($exif as $key => $value) {
	 		 $exif[$key] = exif_key_translation($key, $value);
	   }
	 }
   return $exif;
}

?>
