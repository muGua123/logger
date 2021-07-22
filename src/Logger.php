<?php

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Core;
use EasySwoole\HttpClient\HttpClient;

class Logger
{
    // 定义各个日志级别的保存天数
    const LOG_LEVEL_INFO = "info";
    const LOG_LEVEL_WARNING = "warning";
    const LOG_LEVEL_ERROR = "error";

    /**
     * @param mixed $content
     * @param string $status
     * 写入日志
     */
    public static function write($content = '', string $status = 'error')
    {
        $date = date('Y-m-d H:i:s');

        $filePath = EASYSWOOLE_ROOT . '/Log/';

        $i = 1;
        // 占用磁盘大于1M，就生成新文件
        while (1) {
            $filePathNew = $filePath . date('Y_m_d') . "~$i.log";
            if (!file_exists($filePathNew) || filesize($filePathNew) <= 1000000) {
                break;
            }
            $i++;
        }

        $str = "[$date]: [====$status====] $content \n";
        file_put_contents($filePathNew, $str, FILE_APPEND | LOCK_EX);
    }

    /**
     * 输出内容到控制台
     */
    public static function consolePrint($msg, string $status)
    {
        $date = date('Y-m-d H:i:s');
        switch ($status) {
            case self::LOG_LEVEL_ERROR:
                $color = "\e[31m[$date] 失败！";
                break;
            case self::LOG_LEVEL_WARNING:
                $color = "\e[33m[$date] 警告！";
                break;
            default:
                $color = "\e[0m[$date] 正常！";
                break;
        }
        echo "$color\n";
        print_r($msg);
        echo "\e[0m" . PHP_EOL;
    }

    /**
     * 异步写日志
     */
    public static function asyncWrite($type, $message, $logLever)
    {
        go(function () use ($type, $message, $logLever) {
            if ($message instanceof \Throwable) {
                $message = [
                    "错误信息" => $message->getMessage(),
                    "错误栈" => $message->getTraceAsString(),
                ];
            }
            if ('dev' == Core::getInstance()->runMode()) {
                self::consolePrint($message, $logLever);
            }
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            try {
                $HttpClient = new HttpClient();
                $HttpClient->setUrl(Config::getInstance()->getConf("LOGSTASH.url"));
                $param = [
                    "secret" => "nfvui23hrf98vreh9d2",
                    "source" => Config::getInstance()->getConf("SERVER_NAME"),
                    "mode" => Core::getInstance()->runMode(),
                    "log_level" => $logLever,
                    "type" => $type,
                    "time" => date("Y-m-d H:i:s"),
                    "message" => $message,
                ];
                $res = $HttpClient->postJson(json_encode($param));
                if ("ok" != $res->getBody()) {
                    throw new \Exception("写日志到logstash失败！！");
                }
            } catch (\Throwable $e) {
                self::write($e);
            }
        });
    }
}