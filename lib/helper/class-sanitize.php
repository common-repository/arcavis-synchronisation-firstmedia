<?php
defined( 'ABSPATH' ) or die( 'No guetsli!' );


class FmArcavisSanitizeHelper {
    public static function sanitizePost($data) {
        $data = self::sanitizeArray($data);
        return $data;
    }
    
    public static function sanitizeArray($data){
        if(! is_array($data) ) return sanitize_text_field($data);
        foreach($data as $key => $value) {
            if( is_array($value) )
                $data[$key] = self::sanitizeArray($data);
            else
                $data[$key] = sanitize_text_field($data);
        }
        return $data;
    }
}