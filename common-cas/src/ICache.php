<?php
/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 8:12 PM
 */

namespace CommonCas\App;


interface ICache
{
    public function set($key, $value);

    public function get($key);

    public function del($key);
}