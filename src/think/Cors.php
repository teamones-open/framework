<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2009 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

/**
 * 处理Cors跨域
 */
class Cors
{

    /**
     * @param Request $request
     * @return array|Response
     */
    public static function check(Request $request)
    {
        $origin = '*';
        if (!empty(C('web_url'))) {
            $allowUrl = explode(',', C('web_url'));
            $httpOrigin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
            if (in_array($httpOrigin, $allowUrl)) {
                $origin = $httpOrigin;
            }
        }

        $header = [
            //'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE',
            'Access-Control-Allow-Credentials' => true,
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Token, X-Userinfo, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept,Origin, Device-Unique-Code',
        ];

        if ($request->method() == 'OPTIONS') {
            return Response::create()->code(204)->header($header);
        }

        return $header;
    }

}
