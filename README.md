# 在线书签导航

轻量级的个人在线书签管理工具，简单易用和高效部署，项目源代码不到 100KB，仅依赖 PHP 和 MySQL，无需复杂环境即可在虚拟主机上运行，将零散的浏览器书签网址集中式管理，做到一处部署，任意一台联网设备上都能访问自己的书签。

## 在线书签导航能做什么

浏览器自带的书签收藏功能虽然够用，但在实际使用中经常会遇到这些问题：

1. 多台设备、各家浏览器之间的书签难以同步，书签迁移很麻烦
2. 随着收藏的网址越来越多，后续查找效率会越来越低，想快速搜索书签不方便
3. 想查看经常访问的网址或者最近访问的网址不方便，只能翻看浏览器的历史记录
4. 想在任意一台联网设备上都能访问自己的书签

这个项目的目标，就是把这些零散的网址进行统一管理，让你在任何能联网的设备都能打开自己的书签导航。

## 主要功能

- 支持账号注册、登录和鉴权
- 支持书签添加、编辑、删除、搜索和分类管理
- 支持书签按“点击次数”、“添加日期”、“最后点击”排序
- 支持分类导航栏拖拽排序，实时保存顺序
- 支持卡片视图和表格视图
- 支持导入浏览器导出的书签 HTML 文件
- 支持导出书签 HTML 文件
- 增加Chrome插件，可在任意界面快速添加书签至系统
- 响应式设计，适配桌面和移动端，随时随地管理书签

## 部署要求

- **PHP**: 7.2 或更高版本。
- **MySQL**: 5.6 或更高版本。
- **Web 服务器**: 任意支持 PHP 的服务器（如 Apache、Nginx）。
- **空间**: 至少 1MB（包括源代码和数据库）。

## 部署教程

### 1. 下载源代码

1. 网站配置：

   修改配置文件**`config.php`**

   ```
   // 数据库连接参数，请按你的虚拟主机信息修改
   define('DB_HOST', '数据库地址');
   define('DB_NAME', '数据库名称');
   define('DB_USER', '数据库用户名');
   define('DB_PASS', '数据库密码');
   define('DB_CHARSET', 'utf8mb4');
   
   // 网站基础URL（用于重定向等，末尾不带斜杠）
   define('BASE_URL', 'https://你的域名.com');
   ```

2. Chrome 浏览器插件（可选）：

   修改插件文件夹内 3 个文件

   | 文件            | 修改项                                             |
   | :-------------- | :------------------------------------------------- |
   | `popup.js`      | 顶部 `const BASE_URL = 'https://你的域名.com/';`   |
   | `manifest.json` | `host_permissions` 中的 `"https://你的域名.com/*"` |
   | `popup.html`    | 底部链接 `<a href="https://你的域名.com/">`        |

### 2. 上传到虚拟主机

```
bookmarks/
  ├── add.php                  # 添加书签页面（含自动获取标题）
  ├── api_add_bookmark.php     # 接收书签添加请求
  ├── api_categories.php       # 获取分类列表
  ├── categories.php           # 分类管理页面
  ├── config.php               # 数据库连接配置
  ├── db.php                   # 数据库连接与初始化
  ├── delete.php               # 删除书签处理
  ├── edit.php                 # 编辑书签页面
  ├── export.php               # 导出书签为HTML
  ├── functions.php            # 公共函数
  ├── get_title.php            # 获取网页标题接口
  ├── index.php                # 主页
  ├── init.php                 # 自动加载会话和数据库连接
  ├── import.php               # 处理导入书签文件
  ├── install.php              # 创建表的脚本
  ├── login.php                # 用户登录页面
  ├── logout.php               # 退出登录
  ├── register.php             # 用户注册页面
  ├── script.js                # 前端脚本
  ├── style.css                # 样式文件
  ├── settings.php             # 设置页面
  ├── tracking.php             # 点击统计与重定向
  ├── update_sort.php          # 拖拽排序AJAX端点
  ├── favicon.ico              # 默认图标和 favicon
```



1. 上传：

   - 使用 FTP 工具（如 FileZilla）将整个文件夹上传到虚拟主机的根目录或指定目录。
   - 例如，上传到 /public_html/

2. 目录说明：

   - 如果你上传的网站文件不在虚拟主机根目录（例如根目录已绑定其他站点，文件上传至子目录 `/bookmarks` ）

   - 可以使用 **URL 重写** 将根域名请求转发到 `/bookmarks` 子目录。在根目录创建 `.htaccess` 文件，写入：

   - ```
     RewriteEngine On
     RewriteCond %{REQUEST_URI} !^/bookmarks/
     RewriteRule ^(.*)$ /bookmarks/$1 [L]
     ```

### 3. 访问站点

- 在浏览器中访问： https://yourdomain.com/install.php 
- 系统会自动创建数据库表
- 然后访问： https://yourdomain.com/ 注册登录后即可开始添加和管理书签！

## 使用说明

1. 添加书签：
   - 点击“添加书签”按钮，输入网址后，点击“获取标题”按钮即可自动获取网页标题，然后选择分类。
2. 编辑/删除：
   - 切换至表格视图，点击“编辑”或“删除”书签。
3. 分类管理：
   - 在顶部导航栏点击“分类管理”图标，进入分类管理页可“编辑”或“删除”分类。拖动分类可调整顺序，松手后自动保存。
4. 设置：
   - 在顶部导航栏点击“设置”图标，进入设置页可选择默认书签视图、修改密码、导出导入书签、关闭或开启注册、生成API密钥（用于浏览器插件）。
5. 搜索：
   - 在搜索框输入标题或者链接，按 Enter 或点击搜索。
6. 书签排序：
   - 在卡片视图（任意非“全部”分类时）或者表格视图下点击“添加日期”、“点击次数”、“最后点击”按钮，即可对相应分类的书签进行排序。
  
## Star History

<a href="https://www.star-history.com/?repos=funtoes%2Fbookmarks&type=date&legend=top-left">
 <picture>
   <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/chart?repos=funtoes/bookmarks&type=date&theme=dark&legend=top-left" />
   <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/chart?repos=funtoes/bookmarks&type=date&legend=top-left" />
   <img alt="Star History Chart" src="https://api.star-history.com/chart?repos=funtoes/bookmarks&type=date&legend=top-left" />
 </picture>
</a>
