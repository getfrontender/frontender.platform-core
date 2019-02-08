<?php

namespace Frontender\Core\Routes\Helpers;

class Renderer {
    public static function json($data) {
        // Output JSON
        header('Content-type:application/json;charset=utf-8');
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Token');
        header('Access-Control-Expose-Headers:X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Token');
        header('Access-Control-Allow-Methods:GET, POST, PUT, DELETE, OPTIONS');
        return json_encode($data);
    }
}
