WordPress-BitCoin4Read
================

A wordpress plugin, reader should pay bitcoin for reading.

一款要求读者支付比特币后才能阅读的WordPress付费阅读插件。

安装方法：
1.使用FTP连接到你的网站空间；
2.把插件解压到bitcoin4read目录，把该目录上传到你的WordPress插件目录/wp-content/plugins/下面；
3.进入WordPress后台，启用这个插件

在写文章的时候，使用[bitcoin4read price="0.001" title="" message="" day=""]付费阅读的内容[/bitcoin4read]。
price是价格，单位为B；title和message是交易附加信息，目前好像还无法正常使用；day是付费后能够阅读的有效期，为整数。

启动插件之前，先修改插件代码最开头的BITCOIN4READ_ADDRESS为你自己的收款地址。

插件中的download.php无法单独使用，需配合WP2PCS插件才能使用。