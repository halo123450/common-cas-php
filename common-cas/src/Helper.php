<?php
/**
 * Created by PhpStorm.
 * User: chentengfeng
 * Date: 2019/1/29
 * Time: 8:18 PM
 */

namespace CommonCas\App;


class Helper
{
    const rand_char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * 随机生成指定长度的随机字符串
     * @param $length
     * @return string
     */
    public function genRandStr($length)
    {
        // 若传入的不符合规范的字符串，则直接使用 10
        if (!is_int($length) || $length < 0) {
            $length = 10;
        }

        $string = '';
        for ($i = $length; $i > 0; $i--) {
            $string .= static::rand_char[mt_rand(0, strlen(static::rand_char) - 1)];
        }

        return $string;
    }

    /**
     * curl 请求
     * @param $url
     * @param null $post_data
     * @param array $use_cert
     * @param int $time_out
     * @return bool|mixed
     */
    public function curl($url, $post_data = null, $use_cert = array(), $time_out = 10)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, false);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

        if ($use_cert) {//设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $use_cert['sslcert_path']);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $use_cert['sslkey_path']);
        }

        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }

        $return_data = curl_exec($ch);

        if (curl_errno($ch)) {
//            dd(curl_error($ch));
            curl_close($ch);
            return false;
        }

        return $return_data;
    }

    /**
     * @param $params 参数
     * @param $key  string  加密的key
     * @param $except_arr  array 排除的字段
     * @return string
     */
    public function genSign($params, $key, array $except_arr = [])
    {
        //去掉一些为空的参数
        //$params = array_filter($params);
        ksort($params);

        $tmpstr = '';
        $trim_str = "'\t\n\r \v";
        foreach ($params as $k => $v) {
            //为null的字段不参与加密
            if (!in_array($k, $except_arr) && $v !== null) {
                //$tmpstr .= $k . '=' . $v . '&';
                //@TODO 微信android客户端会把'带到链接参数上面
                $tmpstr .= $this->trimAny($k, $trim_str) . '=' . $this->trimAny($v, $trim_str) . '&';
            }
        }
        $tmpstr .= '_key=' . $key;
        $sign = strtoupper(md5($tmpstr));
        return $sign;
    }

    /**
     * 过滤字符串 && 数字 && 数组 && 对象的空格
     * @author  jianwei
     * @param   需要过滤的数据
     * @param   $charlist = " \t\n\r\0\x0B",过滤的模式
     * @notic   支持多维
     */
    public function trimAny(&$data, $charlist = " \t\n\r\0\x0B")
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = $this->trimAny($value, $charlist);
                } else {
                    $data[$key] = $this->trimAny($value, $charlist);
                }
            }
        } else if (is_string($data)) {
            $data = trim($data, $charlist);
        }
        return $data;
    }

    /**
     * 获取当前时间
     * @author  jianwei
     * @param   $flag   double  当$flag 为 true 时,等同于 time()
     */
    public function getNow($flag = false)
    {
        static $now_time = null;
        if (null === $now_time) {
            $now_time = date('YmdHis', time());
        }

        if (true === $flag) {
            return date('YmdHis', time());
        }

        return $now_time;
    }

    /**
     * 请求参数生成秘钥
     *
     * @param $params
     * @param $sign_key
     * @return array
     * @author jiangxianli
     * @created_at 2019-02-19 14:53
     */
    public function paramsSignature(array $params, $sign_key)
    {
        $params['_ts'] = $this->getNow();
        $params['_rd'] = $this->genRandStr(8);
        $params['_sign'] = $this->genSign($params, $sign_key, ['_sign']);

        return $params;
    }
}