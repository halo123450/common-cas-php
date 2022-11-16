<?php
/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 9:00 PM
 */

namespace CommonCas\App;


class CasException extends \Exception
{
    const state = "state";
    const curl_empty = "curl_empty";
    const curl_date_error = "curl_date_error";

    private $type;
    private $data;

    public function __construct($type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * 获取错误类型
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * CasException 转字符串
     * @return string
     */
    public function __toString()
    {
        return "错误类型:" . $this->type . ";错误内容:" . json_encode($this->data);
    }
}