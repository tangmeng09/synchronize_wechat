[![Documentation Status](https://readthedocs.org/projects/synchronize-wechat/badge/?version=latest)](https://synchronize-wechat.readthedocs.io/en/latest/?badge=latest)
[![Build Status](https://travis-ci.com/zhaofeng-shu33/synchronize_wechat.svg?branch=master)](https://travis-ci.com/zhaofeng-shu33/synchronize_wechat)

# synchronize wechat
![](icon-256x256.png)
synchronize wechat post articles to wordpress website,
see [plugins](https://wordpress.org/plugins/synchronize-wechat/) for release.

## Introduction
WeChat is the most popular instant message tools in China. Nowadays, wechat posts are very common and people read traditional webpages in less frequency. 
However, for better search engine index and Corporation image maintenance,
Official website needs a technique to synchronize what they post on wechat public platform to their own website. 
By our search we do not find any open source solution. 
As a result, we try to implement a one. 
The official website we choose is based on `WordPress`, 
but we believe similar solutions can be provided and customized if the general pipeline is worked out. 

## Showcase
![](demo.gif)

## Third party service
This wordpress plugin relies on the third party service. The techinical document is [here](https://mp.weixin.qq.com/wiki).
This plugin also uses third party PHP library, called [simple html dom](https://github.com/demonkoryu/php-simple-html-dom)
and [wechat php sdk](https://github.com/gaoming13/wechat-php-sdk).

The policies of wechat service can be found at [here](https://mp.weixin.qq.com/cgi-bin/announce?action=getannouncement&key=1503979103&version=1&lang=zh_CN&platform=2).

## Features we have achieved

1. given urls of articles, fetch them and republish them on wordpress website
1. synchronize historical articles to wordpress website by just one click

Note: Only the second feature uses the Wechat service.

## How to use
See [wiki](https://github.com/zhaofeng-shu33/synchronize_wechat/wiki/User-Guide)

## How to build Developping Environment
See [wiki](https://github.com/zhaofeng-shu33/synchronize_wechat/wiki/Developping-Environment)

### Prerequisite

1. You need an independent public IP address binded to your server and your wechat public domain(must be **authorized**).
2. The server can deploy WordPress as developping environment.

Note: The public IP address is used for the whitelist of the wechat public domain. Otherwise, you can not use the wechat api.

### ToDo List

* Improve user interface.
* Handle original author.

### Playing with the API

### Documentation
Use `doxygen` at the project root to generate the document.
