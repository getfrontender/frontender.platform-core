<?php
/*******************************************************
 * @copyright 2017-2019 Dipity B.V., The Netherlands
 * @package Frontender
 * @subpackage Frontender Platform Core
 *
 * Frontender is a web application development platform consisting of a
 * desktop application (Frontender Desktop) and a web application which
 * consists of a client component (Frontender Platform) and a core
 * component (Frontender Platform Core).
 *
 * Frontender Desktop, Frontender Platform and Frontender Platform Core
 * may not be copied and/or distributed without the express
 * permission of Dipity B.V.
 *******************************************************/

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
