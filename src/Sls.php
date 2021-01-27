<?php

namespace think\log\driver;

use think\App;
use GetClientIp;

class Sls
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'endpoint' => '',
        'access_key_id' => '',
        'access_key_secret' => '',
        'project' => '',
        'logstore' => '',
        'source' => '',
        'json' => false,
        'more_info' => true,
        'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'time_format' => ' c ',
        'apart_level' => [],
    ];

    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    public function save(array $log = [])
    {
        $info = '';
        foreach ($log as $type => $val) {
            $level = '';
            foreach ($val as $msg) {
                if (!is_string($msg)) {
                    $msg = var_export($msg, true);
                }
                $level .= '[ ' . $type . ' ] ' . $msg . "\r\n";
            }
            if (in_array($type, $this->config['apart_level'])) {
                $this->write($level, $type, true);
            } else {
                $info .= $level;
            }
            if (strlen($info) > 1024 * 1024) { //如果大于1M进行拆分
                $this->write($info);
                $info = '';
            }
        }
        if ($info) {
            return $this->write($info);
        }
        return true;
    }

    protected function write($message, $destination = 'default', $apart = false)
    {
        $data = [
            'msg' => $message,
        ];
        if (!IS_CLI) {
            if ($this->config['more_info'] && !$apart) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    $data['current_uri'] = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                } else {
                    $data['current_uri'] = "cmd:" . implode(' ', $_SERVER['argv']);
                }
                $data['runtime'] = round(microtime(true) - THINK_START_TIME, 10);
                $data['reqs'] = $data['runtime'] > 0 ? number_format(1 / $data['runtime'], 2) : '∞';
                $data['memory_use'] = number_format((memory_get_usage() - THINK_START_MEM) / 1024, 2);
                $data['file_load'] = count(get_included_files());
            }
            $data['request_post'] = json_encode($_POST);
        }
        $data['server'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '0.0.0.0';
        $data['remote'] = (new GetClientIp())->getClientIp();
        if (!$data['remote']) {
            $data['remote'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        }
        $data['method'] = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
        $data['uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $data['now'] = date($this->config['time_format']);
        $logItem = new \Aliyun_Log_Models_LogItem();
        $logItem->setTime(time());

        $contents = $this->config['json'] ? ['content' => json_encode($data, $this->config['json_options'])] : $data;
        $logItem->setContents($contents);
        $tmp = 0;
        $path = LOG_PATH . date('Ym') . DS . date('d') . '.log';
        $dir = dirname($path);
        !is_dir($dir) && mkdir($dir, 0755, true);
        while (true) {
            try {
                $client = new \Aliyun_Log_Client($this->config['endpoint'], $this->config['access_key_id'],
                    $this->config['access_key_secret']);
                $req = new \Aliyun_Log_Models_PutLogsRequest($this->config['project'], $this->config['logstore'],
                    $destination, $this->config['source'], [$logItem]);
                $client->putLogs($req);
                return true;
            } catch (\Exception $e) {
                $tmp++;
                error_log("sls_error:" . $e->getMessage(), 3, $path);
            }
            if ($tmp > 3) {
                error_log(json_encode($data), 3, $path);
                return false;
            }
        }
    }
}