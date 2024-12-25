# PostToTelegram

文章支持提交到Tg频道。  
在提交文章时同步提交到Tg频道。  
 
github：[https://github.com/awinds/PostToTelegram](https://github.com/awinds/PostToTelegram) 

## 使用方法  

1.下载本插件，放在 `usr/plugins/` 目录中

2.文件夹名改为 `PostToTelegram`

3.登录管理后台，激活插件

4.插件管理，设置，必填项为空则不会推送

## 设置

+ `推送模式选择` 分为文章模式（预览模式）和图片模式，推送模式不一样，显示效果不一样，图片模式会在内容和附件中选取md的一张图片来推送，图片没有的情况则改文章模式推送。
+ `推送图片是否统计图片数` 是否在推送图片时标题中显示图片数
+ `推送图片是否推送图片组` 是否推送图片组，就是一次推送多张图片(最多3张)
+ `推送标题emoji` 推送的标准前面显示emoji，使用emoji更醒目
+ `Telegram Bot Token` 从 `@BotFather` 获取你的 `Bot Token`
+ `Telegram Chat ID` 建立你的分享频道，公有则为你的频道名称 `@频道名称`，私有则邀请如 `@get_id_bot` 机器人进入对应群组, 自动发送 Channel ID
+ `Telegram API转发地址` 通过自己建立转发api来发送到tg，为空则默认为`https://api.telegram.org`，如何代理自行寻找教程
+ `推送分类ID` 填写的分类则推送，不填写则推送所有分类
+ `是否启用日志` 启用后会生成log提交日志

## 说明
- 本项目为推广我的图片网站到tg频道而开发。
- 未发布或发布时间未到的不推送，同一文章一天内修改不推送。
- 文章模式推送内容包括标题、标签、网址，网址如果有og标签，会在频道显示预览，og标签说明请看：
> https://xiaoa.me/archives/mate_og_tc.html
- 图片模式推送为内容或附件的第一张图片，标题可选择是否显示图片数，和emoji开头。

## 版本
### v1.2.0
- 增加推送图片组模式

### v1.1.0
- 增加配置项，分类过滤、emoji开头、是否统计图片数

### v1.0.0
- 新建插件，post内容推送到tg频道。