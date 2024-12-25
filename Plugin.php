<?php
/**
 * PostToTelegram，提交文章时同步提交到TG频道或群组，多种推送模式选择，<a href="https://xiaoa.me/archives/plugin_posttotelegram.html" target=_blank>详细了解</a>
 * 
 * @package PostToTelegram
 * @author 小A
 * @version 1.2.0
 * @link https://xiaoa.me/archives/plugin_posttotelegram.html
 */
class PostToTelegram_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('PostToTelegram_Plugin', 'sendToTelegram');
        return _t('插件已激活，文章发布时将自动推送到 Telegram 频道。可以到<a href="https://github.com/awinds/PostToTelegram">Github</a>查看说明');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        return '插件已禁用。';
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){

        $mode = new Typecho_Widget_Helper_Form_Element_Radio('mode',
            array ('0' => '推送文章模式(预览模式)', '1' => '推送图片模式(无图片则走文章模式)'),
            0,
            _t('推送模式选择：'),
            _t('文章模式(预览模式)和图片模式在TG中显示不一样，默认推送文章模式')
        );
        $form->addInput($mode);

        $photoNum = new Typecho_Widget_Helper_Form_Element_Radio('photoNum',
            array('1' => '是','0' => '否'),
            1,
            _t('推送图片是否统计图片数：'),
            _t('统计图片数在标题中显示为(数量p)，默认是')
        );
        $form->addInput($photoNum);

        $multiPhoto = new Typecho_Widget_Helper_Form_Element_Radio('multiPhoto',
            array('0' => '否','1' => '是'),
            0,
            _t('推送图片是否推送图片组：'),
            _t('推送图片的时候是否以图片组(最多3张)推送，默认否')
        );
        $form->addInput($multiPhoto);

        $titleEmoji = new Typecho_Widget_Helper_Form_Element_Text('titleEmoji',
            NULL,
            '❤',
            _t('推送标题emoji：'),
            _t('可以填写emoji为标题开头')
        );
        $form->addInput($titleEmoji);

        $token = new Typecho_Widget_Helper_Form_Element_Text('botToken',
            NULL,
            '',
            _t('Telegram Bot Token（必填项）：'),
            _t('请填写从 @BotFather 获取的 Bot Token')
        );
        $form->addInput($token);

        $chatId = new Typecho_Widget_Helper_Form_Element_Text('chatId',
            NULL,
            '',
            _t('Telegram Chat ID（必填项）：'),
            _t('请填写你的频道 ID（如 @channelusername）')
        );
        $form->addInput($chatId);

        $proxyUrl = new Typecho_Widget_Helper_Form_Element_Text('proxyUrl',
            null,
            '',
            _t('Telegram API转发地址：'),
            _t('结尾不带/，为空使用默认https://api.telegram.org，如何代理自行寻找教程')
        );
        $form->addInput($proxyUrl);

        $categoryIds = new Typecho_Widget_Helper_Form_Element_Text(
            'categoryIds',
            null,
            '',
            _t('推送分类ID：'),
            _t('填写的分类则推送，多个用半角,分隔，如:1,2，不填写则推送所有分类。<br>所有分类：'.self::myGetCategoryies())
        );
        $form->addInput($categoryIds);

        $log = new Typecho_Widget_Helper_Form_Element_Radio('log',
            array('0' => '否', '1' => '是'),
            0,
            _t('是否启用日志：'),
            _t('启用日志会在推送时生成日志，日志路径为./logs/PostToTelegram.log，默认不启用')
        );
        $form->addInput($log);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}


    private static function myGetCategoryies()
    {
        $db = Typecho_Db::get();
        $prow = $db->fetchAll(
            $db
                ->select()
                ->from("table.metas")
                ->where("type = ?", "category")
        );
        $text = "";
        foreach ($prow as $item) {
            $text .= $item["name"] . "(" . $item["mid"] . ")" . "&nbsp;&nbsp;&nbsp;&nbsp;";
        }
        return $text;
    }

    //记录日志
    private static function log_save($msg)
    {
        //日志记录是否启用
        if(Typecho_Widget::widget('Widget_Options')->plugin('PostToTelegram')->log)
        {
            $logSwitch = 1;

        }else{
            $logSwitch  = 0;
        }
        // 日志开关：1表示打开，0表示关闭
        $logFile = 'logs/PostToTelegram.log'; // 日志路径
        date_default_timezone_set('Asia/Shanghai');
        file_put_contents($logFile, date('[Y-m-d H:i:s]: ') . $msg . PHP_EOL, FILE_APPEND);
        return $msg;
    }

    //读取日志
    private static function log_get(){
        $file = "temp_log/post2tg.log";
        if(file_exists($file) && Typecho_Widget::widget('Widget_Options')->plugin('PostToTelegram')->log){
            $file = fopen($file, "r") or exit("Unable to open file!");
            while(!feof($file))
            {
                return fgets($file). "<br />";
            }
            fclose($file);
        }
    }


    private static function getImageFromPost($content,$cid)
    {
        $images = [];
        //先从内容提取
        // 使用正则表达式提取所有图片链接
        preg_match_all('/!\[.*?\]\((.*?)\)/', $content, $matches);
        // 获取所有图片链接
        foreach ($matches[1] as $imgUrl) {
            if($imgUrl != '') {
                array_push($images,$imgUrl);
            }
        }

        //从附件获取
        if(count($images) == 0) {
            Typecho_Widget::widget('Widget_Contents_Attachment_Related@'.$cid, 'parentId='.$cid)->to($attachment);
            if($attachment->have()){
                while ($attachment->next()){
                    if($attachment->attachment->isImage||strpos($attachment->attachment->url,'.webp')!==false) {
                        if($attachment->attachment->url != '') {
                            array_push($images,$attachment->attachment->url);
                        }
                    }
                }
            }
        }

        return $images;
    }

    /**
     * 文章模式生成消息
     */
    private static function getArticleMode($chatId, $title, $url,$tags,$titleEmoji)
    {
        $text = "{$titleEmoji}<b>{$title}</b>\n";
        foreach ($tags as $tag) {
            $text .= "#{$tag}  ";
        }
        $text = "\n{$url}";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false
        ];
        return $data;
    }

    /**
     * 图片组模式生成消息
     */
    private static function getMediaGroupMode($chatId, $title, $url,$tags,$titleEmoji,$photoNum,$images)
    {
        $media = [];
        $realCount = count($images);
        $count = $realCount >= 3 ? 3 : $realCount;
        $text = "{$titleEmoji}<b>{$title}";
        if ($photoNum == 1) {
            $text .= "({$realCount}p)";
        }
        $text .= "</b>";
        $text .= "  <a href='{$url}'>点击这里去浏览更多</a>\n";
        foreach ($tags as $tag) {
            $text .= "#{$tag}  ";
        }
        for ($i = 0;$i<$count;$i++) {
            if($i == 0) {
                array_push($media,[
                    'type' => 'photo',
                    'media' => $images[$i],
                    'caption' => $text,
                    'parse_mode' => 'HTML',
                ]);
            }
            else {
                array_push($media,[
                    'type' => 'photo',
                    'media' => $images[$i]
                ]);
            }
        }
        // 转换为 JSON 格式
        $data = [
            'chat_id' => $chatId,
            'media' => json_encode($media),
        ];
        return $data;
    }


    /**
     * 图片模式生成消息
     */
    private static function getImageMode($chatId, $title, $url,$tags,$titleEmoji,$photoNum,$images)
    {
        $data = [
            'chat_id' => ''
        ];
        $count = count($images);
        $text = "{$titleEmoji}<b>{$title}";
        if ($photoNum == 1) {
            $text .= "({$count}p)";
        }
        $text .= "</b>";
        $text .= "  <a href='{$url}'>点击这里去浏览更多</a>\n";
        foreach ($tags as $tag) {
            $text .= "#{$tag}  ";
        }
        $data = [
            'chat_id' => $chatId,
            'photo' => $images[0],
            'caption' => $text,
            'parse_mode' => 'HTML'
        ];
        return $data;
    }


    /**
     * 插件实现方法
     * 
     * @access public
     * @return void
     */
    public static function sendToTelegram($post, $class)
    {
        //确认是否发布
        $curtime = time();
        if ('publish' != $post['visibility'] || $post['created'] > $curtime) {
            self::log_save("文章状态：".$post['visibility'] ." 或发布时间：".$post['created'].">".$curtime);
            return;
        }
        //一天之内的文章不再次推送
        if((int)$class->modified > (int)$post['created'] && (int)$post['created']+86400 > (int)$class->modified){
            self::log_save("发布时间：".$post['created']."和更新时间".$class->modified."在一天内");
            return;
        }

        $options = Helper::options();
        $botToken = $options->plugin('PostToTelegram')->botToken;
        $chatId = $options->plugin('PostToTelegram')->chatId;

        if (empty($botToken) || empty($chatId)) {
            return;
        }

        $log = $options->plugin('PostToTelegram')->log;
        $mode = $options->plugin('PostToTelegram')->mode;
        $photoNum = $options->plugin('PostToTelegram')->photoNum;
        $multiPhoto = $options->plugin('PostToTelegram')->multiPhoto;
        $titleEmoji = $options->plugin('PostToTelegram')->titleEmoji;
        if(empty($titleEmoji)) {
            $titleEmoji = '❤';
        }
        $proxyUrl = $options->plugin('PostToTelegram')->proxyUrl;
        $categoryIds = $options->plugin('PostToTelegram')->categoryIds;
        $categoryIdArr = [];
        if(!empty($categoryIds)) {
            foreach ($categoryIds as $cateId) {
                array_push($categoryIdArr,intval($cateId));
            }
        }
        $tagsArr = [];
        foreach ($class->tags as $tag) {
            array_push($tagsArr,$tag['name']);
        }
        $mids = [];
        foreach ($class->categories as $cate) {
            array_push($mids,$cate['mid']);
        }

        if(count($categoryIdArr) > 0 && count($mids) > 0) {
            $ok = 0;
            foreach ($mids as $mid) {
                if(in_array($mid,$categoryIdArr)) {
                    $ok = 1;
                    break;
                }
            }
            if($ok == 0) {
                return;
            }
        }


        //发布文章
        $tgApi = 'https://api.telegram.org';
        $tgUrl = $tgApi.'/bot' . $botToken . '/';
        if(!empty($proxyUrl)) {
            $tgUrl = $proxyUrl.'/bot' . $botToken . '/';
        }

        // 获取文章内容
        $cid = $class->cid;
        $title = htmlspecialchars($post['title']);
        $url = $class->permalink;

        $data = [];

        if($mode == 1) {
            $images = self::getImageFromPost($post['text'],$cid);
            $countImg = count($images);
            //图片组
            if($multiPhoto == 1 && $countImg > 1) {
                $data = self::getMediaGroupMode($chatId, $title, $url, $tagsArr, $titleEmoji,$photoNum,$images);
                $tgUrl .= "sendMediaGroup";
            }
            elseif ($countImg > 0) {
                //图片模式
                $data = self::getImageMode($chatId,  $title, $url, $tagsArr, $titleEmoji,$photoNum,$images);
                $tgUrl .= "sendPhoto";
            }
            else {
                $data = self::getArticleMode($chatId, $title, $url, $tagsArr,$titleEmoji);
                $tgUrl .= "sendMessage";
            }
        }
        else {
            //文章模式
            $data = self::getArticleMode($chatId, $title, $url, $tagsArr,$titleEmoji);
            $tgUrl .= "sendMessage";
        }

        // 使用 cURL 发送 POST 请求
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tgUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // 忽略 SSL 证书问题
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            if($log == 1) {
                if (curl_errno($ch)) {
                    self::log_save("文章[{$title}]({$url})推送到tg失败：" . curl_error($ch));
                } else {
                    if($data['photo']) {
                        self::log_save("图片[{$title}]({$url}) - ({$data['photo']})推送到tg成功，请求：". json_encode($data) .",返回：". $response);
                    }
                    else {
                        self::log_save("文章[{$title}]({$url})推送到tg成功，请求：" . json_encode($data) . ",返回：" . $response);
                    }
                }
            }
            curl_close($ch);
        }
        catch (ExceptionType $e) {
            self::log_save("文章[{$title}]({$url})推送到tg失败：" . $e->getMessage());
        }
        finally {

        }
    }
}
