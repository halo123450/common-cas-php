<?php
/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 8:17 PM
 */

namespace CommonCas\App;


interface ISession
{
    public function set($key, $value);

    public function get($key);

    public function flush();
}