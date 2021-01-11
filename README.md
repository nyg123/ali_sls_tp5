#### 说明
扩展tp5.0的日志驱动，只需简单配置，即可把日志上传至阿里云日志服务。上传失败时会自动保存到本地，不影响系统运行

#### 安装

> composer require nyg/ali_sls_tp5

#### 配置config.php

```php
[
'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        #'type'  => 'File',
        #日志驱动改为Sls 
        'type' => 'Sls',
        # 阿里云sls公网域名，阿里云的主机可以用内网
        'endpoint' => 'cn-qingdao.log.aliyuncs.com',
        # 阿里云账号和密钥
        'access_key_id' => '',
        'access_key_secret' => '',
        # 项目（Project）是日志服务中的资源管理单元
        'project' => 'php-log-test',
        # 日志库（Logstore）是日志服务中日志数据的采集、存储和查询单元
        'logstore' => '',
        
        # 可选配置
        # 默认记录详细的日志信息，如果不想记录细节可以设置为false
        'more_info'=>true,
        
        'source'=>'',
        
        // 日志记录级别
        'level' => [],
        // 日志单独记录 
        'apart_level' => [],
    ]
 ]
```
到这里就可以正常使用了，和使用框架自带的log驱动用法是一样的

#### 附录
1,统计各接口响应时间和错误次数的查询语句，可以生成仪表盘
>  `* | SELECT uri as "接口地址", COUNT(*) as "请求数",avg(runtime) as "平均响应时间",max(runtime) as "最大响应时间",count_if(msg like '%[ error ]%') as "错误数",date_format(max(__time__),'%Y-%m-%d %H:%i:%S') as "最近请求" GROUP BY uri ORDER BY "最近请求" DESC  LIMIT 20`