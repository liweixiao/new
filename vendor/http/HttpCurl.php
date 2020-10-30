<?php

class HttpCurl{

    /**
     * 请求类
     * @param $url
     * @param array $data
     * @param string $method
     * @param array $options
     * @param bool $returnArray
     * @return mixed
     */
    protected function request($url, $data = [], $method = 'get', $options = [], $header = [], $returnArray = true){
        $curl = curl_init(); // 启动一个CURL会话
        // ee($data);
        is_array($data) && $data = http_build_query($data);
        $method = strtoupper($method);
        if ($method == 'GET') {
            $url .= stripos($url, '?') !== false ? '&' : '?';
            $url .= $data;
        } else {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        }
        
        unset($options[CURLOPT_URL], $options[CURLOPT_POST], $options[CURLOPT_POSTFIELDS]); //防止错误覆盖导致相关问题

        $_options = [
            CURLOPT_URL => $url,// 要访问的地址
            CURLOPT_SSL_VERIFYPEER => 0,// 对认证证书来源的检查
            CURLOPT_SSL_VERIFYHOST => 0,// 从证书中检查SSL加密算法是否存在
            CURLOPT_FOLLOWLOCATION => 1,// 使用自动跳转
            CURLOPT_AUTOREFERER => 1,// 自动设置Referer
            CURLOPT_TIMEOUT => 30,// 设置超时限制防止死循环
            // 模拟用户使用的浏览器
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36',
            CURLOPT_HEADER => 0, //不输出响应头
            CURLOPT_CUSTOMREQUEST => $method, //请求方式
            CURLOPT_RETURNTRANSFER => 1, //获取的信息以文件流的形式返回
        ];

        ////设置header信息
        if (!empty($header)) {
            $_options[CURLOPT_HTTPHEADER] = $header;
        }

        $options = ($_options + $options);

        curl_setopt_array($curl, $options);

        $return = curl_exec($curl); // 执行操作
        // ee($return);

        // $data = json_decode($data, true);
        // if (isset($data['taskid'])) {
        //     aa($return);
        // }

        //关闭URL请求
        curl_close($curl);
        if ($returnArray) {
            $return = json_decode($return, true);
        }

        return $return;
    }

    /**
     * get 请求
     * @param $url
     * @param array $data
     * @param array $options
     * @param bool $returnArray
     * @return mixed
     */
    public function getRequest($url, $data =[], $options = [], $header = [], $returnArray = true){
        return $this->request($url, $data, 'get', $options, $header, $returnArray);
    }

    /**
     * post 请求
     * @param $url
     * @param array $data
     * @param array $options
     * @param bool $returnArray
     * @return mixed
     */
    public function postRequest($url, $data = [], $options = [], $header = [], $returnArray = true){
        return $this->request($url, $data, 'post', $options, $header, $returnArray);
    }

}
