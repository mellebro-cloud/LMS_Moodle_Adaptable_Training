<?php
// Compatibility shim for legacy Mustache class name used by older theme/settings code.
// Moodle 5.2 stores the StringLoader class in public/lib/mustache/src/Loader/StringLoader.php.

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../Loader/StringLoader.php');

if (!class_exists('Mustache_Loader_StringLoader', false)
        && class_exists('\\Mustache\\Loader\\StringLoader')) {
    class_alias('\\Mustache\\Loader\\StringLoader', 'Mustache_Loader_StringLoader');
}