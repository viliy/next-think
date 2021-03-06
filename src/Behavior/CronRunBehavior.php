<?php


    namespace Behavior;

    use Think\Behavior;

    /**
     * 自动执行任务
     */
    class CronRunBehavior extends Behavior
    {

        /**
         * 执行入口
         * @param mixed $params
         */
        public function run(&$params)
        {
            // 锁定自动执行
            $lockfile = RUNTIME_PATH . 'cron.lock';
            if (is_writable($lockfile) && filemtime($lockfile) > $_SERVER['REQUEST_TIME'] - C('CRON_MAX_TIME', null, 60)) {
                return;
            } else {
                touch($lockfile);
            }
            set_time_limit(1000);
            ignore_user_abort(true);

            // 载入cron配置文件
            // 格式 return array(
            // 'cronname'=>array('filename',intervals,nextruntime),...
            // );
            if (is_file(RUNTIME_PATH . '_crons.php')) {
                $crons = include RUNTIME_PATH . '_crons.php';
            } elseif (is_file(COMMON_PATH . 'Conf/crons.php')) {
                $crons = include COMMON_PATH . 'Conf/crons.php';
            }
            if (isset($crons) && is_array($crons)) {
                $update = false;
                $log = [];
                foreach ($crons as $key => $cron) {
                    if (empty($cron[2]) || $_SERVER['REQUEST_TIME'] >= $cron[2]) {
                        // 到达时间 执行cron文件
                        G('cronStart');
                        include COMMON_PATH . 'Cron/' . $cron[0] . EXT;
                        G('cronEnd');
                        $_useTime = G('cronStart', 'cronEnd', 6);
                        // 更新cron记录
                        $cron[2] = $_SERVER['REQUEST_TIME'] + $cron[1];
                        $crons[$key] = $cron;
                        $log[] = "Cron:$key Runat " . date('Y-m-d H:i:s') . " Use $_useTime s\n";
                        $update = true;
                    }
                }
                if ($update) {
                    // 记录Cron执行日志
                    \Think\Log::write(implode('', $log));
                    // 更新cron文件
                    $content = "<?php\nreturn " . var_export($crons, true) . ";\n?>";
                    file_put_contents(RUNTIME_PATH . '_crons.php', $content);
                }
            }
            // 解除锁定
            unlink($lockfile);

            return;
        }
    }