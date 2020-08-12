<?php

namespace App\Command;

use App\Services\Config;
use App\Services\DefaultConfig;

class Update
{
    public static function update($xcat)
    {
        global $_ENV;
        $copy_result = copy(BASE_PATH . '/config/.config.php', BASE_PATH . '/config/.config.php.bak');
        if ($copy_result == true) {
            echo ('备份成功' . PHP_EOL);
        } else {
            echo ('备份失败，迁移终止' . PHP_EOL);
            return false;
        }

        echo(PHP_EOL);
        // 检查并创建新增的配置项
        echo DefaultConfig::detectConfigs();

        echo(PHP_EOL);

        echo('开始升级客户端...' . PHP_EOL);
        Job::updatedownload();
        echo('客户端升级结束' . PHP_EOL);

        echo('开始升级 QQWry...' . PHP_EOL);
        $xcat->initQQWry();
        echo('升级 QQWry结束' . PHP_EOL);

        echo (PHP_EOL);

        $config_old = file_get_contents(BASE_PATH . '/config/.config.php');
        $config_new = file_get_contents(BASE_PATH . '/config/.config.example.php');

        //执行版本升级
        $version_old = $_ENV['version'] ?? 0;
        self::old_to_new($version_old);

        //将旧config迁移到新config上
        $migrated = array();
        foreach ($_ENV as $key => $value_reserve) {
            if ($key == 'config_migrate_notice' || $key == 'version') {
                continue;
            }

            $regex = '/_ENV\[\'' . $key . '\'\].*?;/s';
            $matches_new = array();
            preg_match($regex, $config_new, $matches_new);
            if (isset($matches_new[0]) == false) {
                echo ('未找到配置项：' . $key . ' 未能在新config文件中找到，可能已被更名或废弃' . PHP_EOL);
                continue;
            }

            $matches_old = array();
            preg_match($regex, $config_old, $matches_old);

            $config_new = str_replace($matches_new[0], $matches_old[0], $config_new);
            $migrated[] = '_ENV[\'' . $key . '\']';
        }
        echo (PHP_EOL);

        //检查新增了哪些config
        $regex_new = '/_ENV\[\'.*?\'\]/s';
        $matches_new_all = array();
        preg_match_all($regex_new, $config_new, $matches_new_all);
        $differences = array_diff($matches_new_all[0], $migrated);
        foreach ($differences as $difference) {
            if ($difference == '_ENV[\'config_migrate_notice\']' ||
                $difference == '_ENV[\'version\']') {
                continue;
            }
            //匹配注释
            $regex_comment = '/' . $difference . '.*?;.*?(?=\n)/s';
            $regex_comment = str_replace(array('[', ']'), array('\[', '\]'), $regex_comment);
            $matches_comment = array();
            preg_match($regex_comment, $config_new, $matches_comment);
            $comment = '';
            if (isset($matches_comment[0])) {
                $comment = $matches_comment[0];
                $comment = substr(
                    $comment,
                    strpos(
                        $comment,
                        '//',
                        strpos($comment, ';') //查找';'之后的第一个'//'，然后substr其后面的comment
                    ) + 2
                );
            }
            //裁去首尾
            $difference = substr($difference, 15);
            $difference = substr($difference, 0, -2);

            echo ('新增配置项：' . $difference . ':' . $comment . PHP_EOL);
        }
        echo ('新增配置项通常带有默认值，因此通常即使不作任何改动网站也可以正常运行' . PHP_EOL);

        //输出notice
        $regex_notice = '/_ENV\[\'config_migrate_notice\'\].*?(?=\';)/s';
        $matches_notice = array();
        preg_match($regex_notice, $config_new, $matches_notice);
        $notice_new = $matches_notice[0];
        $notice_new = substr(
            $notice_new,
            strpos(
                $notice_new,
                '\'',
                strpos($notice_new, '=') //查找'='之后的第一个'\''，然后substr其后面的notice
            ) + 1
        );
        echo('以下是迁移附注：');
        if (isset($_ENV['config_migrate_notice'])) {
            if ($_ENV['config_migrate_notice'] != $notice_new) {
                echo($notice_new);
            }
        } else {
            echo ($notice_new);
        }
        echo (PHP_EOL);

        file_put_contents(BASE_PATH . '/config/.config.php', $config_new);
        echo (PHP_EOL . '迁移完成' . PHP_EOL);

        echo (PHP_EOL);

        self::update_malio_config($xcat);

        echo ('开始升级composer依赖...' . PHP_EOL);
        system('php ' . BASE_PATH . '/composer.phar selfupdate');
        system('php ' . BASE_PATH . '/composer.phar install -d ' . BASE_PATH);
        echo ('升级composer依赖结束，请自行根据上方输出确认是否升级成功' . PHP_EOL);
        system('rm -rf ' . BASE_PATH . '/storage/framework/smarty/compile/*');
        system('chown -R www:www ' . BASE_PATH . '/storage');
    }

    public static function old_to_new($version_old)
    {
    }

    public static function update_malio_config($xcat)
    {
        echo ('----------开始.malio_config.php迁移工作-----------');
        global $Malio_Config;
        $copy_result = copy(BASE_PATH . '/config/.malio_config.php', BASE_PATH . '/config/.malio_config.php.bak');
        if ($copy_result == true) {
            echo ('备份成功' . PHP_EOL);
        } else {
            echo ('备份失败，迁移终止' . PHP_EOL);
            return false;
        }
        $config_old = file_get_contents(BASE_PATH . '/config/.malio_config.php');
        $config_new = file_get_contents(BASE_PATH . '/config/.malio_config.example.php');

        $migrated = array();
        foreach ($Malio_Config as $key => $value_reserve) {
            if ($key == 'config_migrate_notice' || $key == 'version') {
                continue;
            }

            $regex = '/Malio_Config\[\'' . $key . '\'\].*?;/s';
            $matches_new = array();
            preg_match($regex, $config_new, $matches_new);
            if (isset($matches_new[0]) == false) {
                echo ('未找到配置项：' . $key . ' 未能在新malio_config文件中找到，可能已被更名或废弃' . PHP_EOL);
                continue;
            }

            $matches_old = array();
            preg_match($regex, $config_old, $matches_old);

            $config_new = str_replace($matches_new[0], $matches_old[0], $config_new);
            $migrated[] = 'Malio_Config[\'' . $key . '\']';
        }
        echo (PHP_EOL);

        //检查新增了哪些config
        $regex_new = '/Malio_Config\[\'.*?\'\]/s';
        $matches_new_all = array();
        preg_match_all($regex_new, $config_new, $matches_new_all);
        $differences = array_diff($matches_new_all[0], $migrated);
        foreach ($differences as $difference) {
            if (
                $difference == 'Malio_Config[\'config_migrate_notice\']' ||
                $difference == 'Malio_Config[\'version\']'
            ) {
                continue;
            }
            //匹配注释
            $regex_comment = '/' . $difference . '.*?;.*?(?=\n)/s';
            $regex_comment = str_replace(array('[', ']'), array('\[', '\]'), $regex_comment);
            $matches_comment = array();
            preg_match($regex_comment, $config_new, $matches_comment);
            $comment = '';
            if (isset($matches_comment[0])) {
                $comment = $matches_comment[0];
                $comment = substr(
                    $comment,
                    strpos(
                        $comment,
                        '//',
                        strpos($comment, ';') //查找';'之后的第一个'//'，然后substr其后面的comment
                    ) + 2
                );
            }
            //裁去首尾
            $difference = substr($difference, 15);
            $difference = substr($difference, 0, -2);

            echo ('新增配置项：' . $difference . ':' . $comment . PHP_EOL);
        }
        echo ('新增配置项通常带有默认值，因此通常即使不作任何改动网站也可以正常运行' . PHP_EOL);

        //输出notice
        $regex_notice = '/System_Config\[\'config_migrate_notice\'\].*?(?=\';)/s';
        $matches_notice = array();
        preg_match($regex_notice, $config_new, $matches_notice);
        $notice_new = $matches_notice[0];
        $notice_new = substr(
            $notice_new,
            strpos(
                $notice_new,
                '\'',
                strpos($notice_new, '=') //查找'='之后的第一个'\''，然后substr其后面的notice
            ) + 1
        );
        echo ('以下是迁移附注：');
        if (isset($Malio_Config['config_migrate_notice'])) {
            if ($Malio_Config['config_migrate_notice'] != $notice_new) {
                echo ($notice_new);
            }
        } else {
            echo ($notice_new);
        }
        echo (PHP_EOL);

        file_put_contents(BASE_PATH . '/config/.malio_config.php', $config_new);
        echo (PHP_EOL . '.malio_config.php迁移完成' . PHP_EOL);

        echo (PHP_EOL);
    }
}
